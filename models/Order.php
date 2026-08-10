<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 주문 — 상태 전이는 전부 조건부 UPDATE(멱등).
 *
 * 결제 트리거가 중복 도착해도 paid 처리는 한 번만 일어난다
 * (zittme_pay.updateOrderStatusIf 와 같은 패턴).
 */
class Order
{
	/**
	 * 주문 1건.
	 *
	 * @param int $order_srl
	 * @return ?object
	 */
	public static function get(int $order_srl): ?object
	{
		$output = executeQuery('commerce.getOrder', (object)['order_srl' => $order_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->order_srl)) ? $output->data : null;
	}

	/**
	 * 주문번호로 1건.
	 *
	 * @param string $code
	 * @return ?object
	 */
	public static function getByCode(string $code): ?object
	{
		$output = executeQuery('commerce.getOrderByCode', (object)['order_code' => $code]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->order_srl)) ? $output->data : null;
	}

	/**
	 * 주문 품목 목록.
	 *
	 * @param int $order_srl
	 * @return array
	 */
	public static function getItems(int $order_srl): array
	{
		$output = executeQuery('commerce.getOrderItems', (object)['order_srl' => $order_srl]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) { return !empty($row->order_item_srl); }));
	}

	/**
	 * 판매자 하위주문 목록.
	 *
	 * @param int $order_srl
	 * @return array
	 */
	public static function getSellerOrders(int $order_srl): array
	{
		$output = executeQuery('commerce.getOrderSellers', (object)['order_srl' => $order_srl]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) { return !empty($row->order_seller_srl); }));
	}

	/**
	 * 주문 상태 전이 (조건부, 멱등).
	 *
	 * @param int $order_srl
	 * @param array $from
	 * @param string $to
	 * @param array $extra
	 * @return bool 실제로 바뀌었는가
	 */
	public static function transition(int $order_srl, array $from, string $to, array $extra = []): bool
	{
		$args = (object)array_merge([
			'order_srl' => $order_srl,
			'status' => $to,
			'from_status_list' => implode(',', $from),
		], $extra);

		$output = executeQuery('commerce.updateOrderStatusIf', $args);
		if (!$output->toBool())
		{
			return false;
		}
		return \DB::getInstance()->getAffectedRows() > 0;
	}

	/**
	 * 구매자 노출용 상태 — paid 이후에는 배송 단계(하위주문 상태)를 우선 보여준다.
	 *
	 * @param object $order
	 * @param ?array $sellers 이미 조회했으면 전달 (없으면 조회)
	 * @return string pending|paid|preparing|shipping|delivered|confirmed|cancelled|failed|expired
	 */
	public static function displayStatus(object $order, ?array $sellers = null): string
	{
		if ($order->status !== Base::ORDER_PAID)
		{
			return (string)$order->status;
		}
		$sellers = $sellers ?? self::getSellerOrders((int)$order->order_srl);
		$st = count($sellers) ? (string)$sellers[0]->status : '';
		return in_array($st, [Base::SELLER_PREPARING, Base::SELLER_SHIPPING, Base::SELLER_DELIVERED, Base::SELLER_CONFIRMED], true)
			? $st : Base::ORDER_PAID;
	}

	/**
	 * 주문 알림 메일 (실패해도 주문 흐름을 막지 않는다).
	 *
	 * kind: new_order|claim → 관리자(notify_admin=Y 일 때), received|paid → 구매자.
	 * PG 콜백 안에서도 호출되므로 세션·mid 에 의존하지 않는다.
	 *
	 * @param string $kind
	 * @param ?object $order
	 * @param string $memo
	 */
	public static function notifyMail(string $kind, ?object $order, string $memo = ''): void
	{
		if (!$order)
		{
			return;
		}
		try
		{
			$config = Config::getConfig();
			$to_admin = in_array($kind, ['new_order', 'claim'], true);
			$to = $to_admin
				? (($config->notify_admin ?? 'N') === 'Y' ? trim((string)($config->notify_admin_email ?? '')) : '')
				: trim((string)($order->orderer_email ?? ''));
			if ($to === '' || !filter_var($to, \FILTER_VALIDATE_EMAIL))
			{
				return;
			}

			$site = \Context::getSiteTitle() ?: 'Zittme';
			$subjects = [
				'new_order' => '[' . $site . '] 신규 주문 접수 - ' . $order->order_code,
				'claim' => '[' . $site . '] 취소·반품 신청 - ' . $order->order_code,
				'received' => '[' . $site . '] 주문이 접수되었습니다 - ' . $order->order_code,
				'paid' => '[' . $site . '] 결제가 완료되었습니다 - ' . $order->order_code,
			];
			if (!isset($subjects[$kind]))
			{
				return;
			}

			$lines = [];
			$lines[] = '주문번호: ' . $order->order_code;
			$lines[] = '주문자: ' . $order->orderer_name . ($order->orderer_phone ? ' (' . $order->orderer_phone . ')' : '');
			foreach (self::getItems((int)$order->order_srl) as $item)
			{
				$lines[] = '- ' . $item->item_name . ($item->option_name ? ' / ' . $item->option_name : '') . ' x ' . (int)$item->qty;
			}
			$lines[] = '결제 금액: ' . number_format((int)$order->payment_price) . '원';
			if ($kind === 'new_order' && $order->status === Base::ORDER_PENDING)
			{
				$lines[] = '상태: 결제 대기 (무통장 주문이면 입금 확인이 필요합니다)';
			}
			if ($memo !== '')
			{
				$lines[] = $memo;
			}

			$mail = new \Rhymix\Framework\Mail();
			$mail->addTo($to);
			$mail->setSubject($subjects[$kind]);
			$mail->setBody(implode("\n", $lines), 'text/plain');
			$mail->send();
		}
		catch (\Throwable $e)
		{
			// 메일 실패는 무시 — 주문 처리가 우선이다
		}
	}

	/**
	 * 결제 완료 처리 (멱등).
	 *
	 * 전이에 이긴 요청만 하위주문 paid 전환·구매수 집계를 한다.
	 * 재고는 주문 생성 시 이미 선점했으므로 여기서 다시 차감하지 않는다.
	 *
	 * @param int $order_srl
	 * @return bool
	 */
	public static function markPaid(int $order_srl): bool
	{
		$won = self::transition($order_srl, [Base::ORDER_PENDING], Base::ORDER_PAID, ['paid_date' => Base::now()]);
		$recovered = false;
		if (!$won)
		{
			// 만료된 뒤에 입금이 확인된 경우: 돈은 받았으므로 주문을 되살린다.
			// 만료 때 반환했던 재고를 다시 차감한다 (부족해도 주문은 살린다 — 운영자가 조정).
			$won = self::transition($order_srl, [Base::ORDER_EXPIRED], Base::ORDER_PAID, ['paid_date' => Base::now()]);
			if (!$won)
			{
				return false;
			}
			$recovered = true;
			foreach (self::getItems($order_srl) as $item)
			{
				Stock::reserve((int)$item->item_srl, (int)$item->option_srl, (int)$item->qty);
			}
		}

		executeQuery('commerce.updateOrderSellersStatus', (object)[
			'order_srl' => $order_srl,
			'status' => Base::SELLER_PAID,
			'from_status_list' => $recovered
				? implode(',', [Base::SELLER_PENDING, Base::SELLER_CANCELLED])
				: Base::SELLER_PENDING,
		]);

		// 구매수 집계 (실패해도 치명적이지 않다)
		foreach (self::getItems($order_srl) as $item)
		{
			if ((int)$item->item_srl > 0)
			{
				\Rhymix\Framework\DB::getInstance()->query(
					'UPDATE commerce_item SET buy_count = buy_count + ? WHERE item_srl = ?',
					(int)$item->qty, (int)$item->item_srl
				);
			}
		}

		// 적립금 적립 + 구매 등급 재계산 (자체 원장 — 전이 승자만 도달하므로 이중 처리 없음)
		$order = self::get($order_srl);
		if ($order)
		{
			Credit::earnForOrder($order);
			Grade::recalc((int)$order->member_srl);
		}

		self::log($order_srl, 0, 'pay', $recovered ? Base::ORDER_EXPIRED : Base::ORDER_PENDING, Base::ORDER_PAID, 0, $recovered ? 'recovered from expired (deposit confirmed)' : '');
		self::notifyMail('paid', $order);
		return true;
	}

	/**
	 * 주문 전체 취소 + 재고 복구 (멱등).
	 *
	 * 전이에 이긴 요청만 재고를 되돌리므로 이중 복구가 없다.
	 *
	 * @param int $order_srl
	 * @param int $actor_srl
	 * @param string $memo
	 * @param string $to cancelled | expired
	 * @return bool
	 */
	public static function cancelAndRestock(int $order_srl, int $actor_srl = 0, string $memo = '', string $to = Base::ORDER_CANCELLED): bool
	{
		$won = self::transition(
			$order_srl,
			[Base::ORDER_PENDING, Base::ORDER_PAID],
			$to,
			['cancelled_date' => Base::now()]
		);
		if (!$won)
		{
			return false;
		}

		executeQuery('commerce.updateOrderSellersStatus', (object)[
			'order_srl' => $order_srl,
			'status' => Base::SELLER_CANCELLED,
			'from_status_list' => implode(',', [Base::SELLER_PENDING, Base::SELLER_PAID, Base::SELLER_PREPARING]),
		]);

		foreach (self::getItems($order_srl) as $item)
		{
			Stock::release((int)$item->item_srl, (int)$item->option_srl, (int)$item->qty);
		}

		// 쿠폰 반환 (재사용 가능 상태로) + 적립금 정산 (사용분 환불·적립분 회수)
		Coupon::releaseByOrder($order_srl);
		$order = self::get($order_srl);
		if ($order)
		{
			Credit::settleCancel($order);
			Grade::recalc((int)$order->member_srl);
		}

		self::log($order_srl, 0, $to === Base::ORDER_EXPIRED ? 'expire' : 'cancel', '', $to, $actor_srl, $memo);
		return true;
	}

	/**
	 * 만료된 결제 대기 주문 정리 (lazy — cron 불필요).
	 *
	 * @return int
	 */
	public static function expireStalePending(): int
	{
		$minutes = max(10, (int)(Base::config()->pending_minutes ?? 60));
		$output = executeQuery('commerce.getExpiredPending', (object)[
			'status' => Base::ORDER_PENDING,
			'before' => date('YmdHis', time() - 60 * $minutes),
			'list_count' => 20,
		]);
		if (!$output->toBool() || empty($output->data))
		{
			return 0;
		}

		$count = 0;
		foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
		{
			if (!empty($row->order_srl) && self::cancelAndRestock((int)$row->order_srl, 0, 'pending expired', Base::ORDER_EXPIRED))
			{
				$count++;
			}
		}
		return $count;
	}

	/**
	 * 이력 기록.
	 *
	 * @param int $order_srl
	 * @param int $order_seller_srl
	 * @param string $action
	 * @param string $before
	 * @param string $after
	 * @param int $actor_srl
	 * @param string $memo
	 * @return void
	 */
	public static function log(int $order_srl, int $order_seller_srl, string $action, string $before, string $after, int $actor_srl = 0, string $memo = ''): void
	{
		executeQuery('commerce.insertOrderLog', (object)[
			'log_srl' => getNextSequence(),
			'order_srl' => $order_srl,
			'order_seller_srl' => $order_seller_srl,
			'action' => $action,
			'before_status' => $before,
			'after_status' => $after,
			'actor_srl' => $actor_srl,
			'memo' => mb_substr($memo, 0, 250),
			'regdate' => Base::now(),
		]);
	}
}
