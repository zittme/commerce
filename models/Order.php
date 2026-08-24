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
		$rows = array_values(array_filter($data, function($row) { return !empty($row->order_item_srl); }));
		// 다국어 코드가 스냅샷된 과거 주문도 화면에는 실값으로
		Lang::textAll($rows, ['item_name', 'option_name']);
		// 조합 옵션 이름은 지금 축 값에서 다시 만든다. 상품이나 옵션이 사라졌으면 스냅샷을 그대로 쓴다
		foreach ($rows as $row)
		{
			if ((int)($row->option_srl ?? 0) <= 0 || (int)($row->item_srl ?? 0) <= 0)
			{
				continue;
			}
			$order_item = Item::get((int)$row->item_srl);
			if (!$order_item)
			{
				continue;
			}
			$output = executeQuery('commerce.getOption', (object)['option_srl' => (int)$row->option_srl]);
			$order_option = ($output->toBool() && is_object($output->data) && !empty($output->data->option_srl)) ? $output->data : null;
			if (!$order_option || empty($order_option->combo))
			{
				continue;
			}
			$order_option->option_label = $row->option_name;
			$row->option_name = Combo::optionLabel($order_item, $order_option);
		}
		return $rows;
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

		// 배송완료 뒤 설정한 날이 지나면 확정으로 본다. 상태를 미리 바꿔 두지 않고 볼 때 계산한다
		if ($st === Base::SELLER_DELIVERED && self::isAutoConfirmed($sellers[0] ?? null))
		{
			return Base::SELLER_CONFIRMED;
		}

		return in_array($st, [Base::SELLER_PREPARING, Base::SELLER_SHIPPING, Base::SELLER_DELIVERED, Base::SELLER_CONFIRMED], true)
			? $st : Base::ORDER_PAID;
	}

	/**
	 * 배송완료 뒤 설정한 날이 지났는가. 설정이 0 이면 자동 확정을 쓰지 않는다.
	 *
	 * 구매확정은 하위주문 상태를 바꾸는 것뿐이라, 미리 돌려 둘 필요 없이 볼 때 계산하면 된다.
	 *
	 * @param ?object $seller 하위주문 (delivered_date 를 본다)
	 * @return bool
	 */
	public static function isAutoConfirmed(?object $seller): bool
	{
		$days = max(0, (int)(Base::config()->auto_confirm_days ?? 0));
		if ($days <= 0 || !$seller)
		{
			return false;
		}
		$delivered = preg_replace('/\D/', '', (string)($seller->delivered_date ?? ''));
		if (strlen($delivered) < 8)
		{
			return false;
		}
		$at = strtotime(substr($delivered, 0, 4) . '-' . substr($delivered, 4, 2) . '-' . substr($delivered, 6, 2)
			. ' ' . substr($delivered . '000000', 8, 2) . ':' . substr($delivered . '000000', 10, 2) . ':00');
		return $at > 0 && (time() - $at) >= $days * 86400;
	}

	/**
	 * 알림센터로 보낸다. 메일과 달리 설정과 무관하게 나간다.
	 *
	 * 운영자는 처리할 일(신규 주문·클레임)을, 구매자는 자기 주문의 진행을 받는다.
	 *
	 * @param string $kind
	 * @param object $order
	 * @param string $memo
	 * @return void
	 */
	protected static function notifyCenter(string $kind, object $order, string $memo = ''): void
	{
		$code = (string)$order->order_code;
		$buyer = (int)($order->member_srl ?? 0);

		switch ($kind)
		{
			case 'new_order':
				Notify::toAdmins(sprintf(lang('commerce.nc_new_order'), $code), Notify::consoleUrl('orders'));
				return;

			case 'claim':
				Notify::toAdmins(sprintf(lang('commerce.nc_claim'), $code), Notify::consoleUrl('claims'));
				return;

			case 'paid':
				Notify::send($buyer, sprintf(lang('commerce.nc_paid'), $code), Notify::orderUrl($code));
				return;

			case 'shipping':
				Notify::send($buyer, sprintf(lang('commerce.nc_shipping'), $code), Notify::orderUrl($code));
				return;

			case 'delivered':
				Notify::send($buyer, sprintf(lang('commerce.nc_delivered'), $code), Notify::orderUrl($code));
				return;

			case 'claim_done':
				Notify::send($buyer, sprintf(lang('commerce.nc_claim_done'), $code), Notify::orderUrl($code));
				return;
		}
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
	/**
	 * 이 사건에 메일을 보낼지. 설정에 값이 없으면 보내는 것으로 본다.
	 *
	 * @param string $kind
	 * @param bool $to_admin
	 * @return bool
	 */
	protected static function notifyEnabled(string $kind, bool $to_admin): bool
	{
		$config = Config::getConfig();
		if ($to_admin && ($config->notify_admin ?? 'N') !== 'Y')
		{
			return false;
		}
		$key = ($to_admin ? 'notify_admin_' : 'notify_buyer_') . $kind;
		return ($config->{$key} ?? 'Y') !== 'N';
	}

	/**
	 * 관리자 알림을 받을 메일 주소. 직접 적은 것 + 지정한 회원그룹의 회원.
	 *
	 * 담당자가 여럿이거나 자주 바뀌는 곳을 위해 그룹으로도 받을 수 있게 한다.
	 *
	 * @return array
	 */
	public static function adminRecipients(): array
	{
		$config = Config::getConfig();
		$list = preg_split('/[\s,;]+/', (string)($config->notify_admin_email ?? '')) ?: [];

		$group_srl = (int)($config->notify_admin_group ?? 0);
		if ($group_srl > 0)
		{
			// 별칭 조인은 프레임워크의 자동 프리픽스 재작성과 충돌한다 (Grade::getForMember 와 같은 이유)
			$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
			$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare(
				'SELECT m.email_address FROM `' . $prefix . 'member_group_member` AS mg'
				. ' JOIN `' . $prefix . 'member` AS m ON m.member_srl = mg.member_srl'
				. ' WHERE mg.group_srl = ?'
			);
			if ($stmt && $stmt->execute([$group_srl]))
			{
				$list = array_merge($list, $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
				$stmt->closeCursor();
			}
		}

		$list = array_map('trim', $list);
		$list = array_filter($list, function($mail) {
			return $mail !== '' && filter_var($mail, \FILTER_VALIDATE_EMAIL) !== false;
		});
		return array_values(array_unique($list));
	}

	public static function notifyMail(string $kind, ?object $order, string $memo = ''): void
	{
		if (!$order || (int)($order->order_srl ?? 0) <= 0)
		{
			return;
		}

		Deferred::call(self::class . '::notifyMailTask', [
			'kind' => $kind,
			'order_srl' => (int)$order->order_srl,
			'memo' => $memo,
		]);
	}

	/**
	 * 미뤄 둔 알림·메일 발송. Deferred 가 응답 뒤에 부른다.
	 *
	 * @param object $args {kind, order_srl, memo}
	 * @return void
	 */
	public static function notifyMailTask(object $args): void
	{
		$order = self::get((int)($args->order_srl ?? 0));
		if ($order)
		{
			self::notifyMailNow((string)($args->kind ?? ''), $order, (string)($args->memo ?? ''));
		}
	}

	/**
	 * 알림센터와 메일을 지금 보낸다.
	 *
	 * @param string $kind
	 * @param object $order
	 * @param string $memo
	 * @return void
	 */
	protected static function notifyMailNow(string $kind, object $order, string $memo = ''): void
	{
		// 알림센터는 메일 설정과 무관하게 보낸다. 메일을 끈 사이트도 알림은 받는다
		self::notifyCenter($kind, $order, $memo);
		try
		{
			$to_admin = in_array($kind, ['new_order', 'claim'], true);
			if (!self::notifyEnabled($kind, $to_admin))
			{
				return;
			}

			$recipients = $to_admin
				? self::adminRecipients()
				: array_filter([trim((string)($order->orderer_email ?? ''))], function($mail) {
					return filter_var($mail, \FILTER_VALIDATE_EMAIL) !== false;
				});
			if (!count($recipients))
			{
				return;
			}

			$site = \Context::getSiteTitle() ?: 'Zittme';
			$subject = sprintf(lang('commerce.mail_subject_' . $kind), $site, $order->order_code);
			if ($subject === '' || strpos($subject, 'mail_subject_') !== false)
			{
				return;
			}

			$rows = [];
			$rows[] = [lang('commerce.mail_order_code'), (string)$order->order_code];
			$rows[] = [lang('commerce.mail_orderer'), $order->orderer_name
				. ($order->orderer_phone ? ' (' . $order->orderer_phone . ')' : '')];

			$item_lines = [];
			foreach (self::getItems((int)$order->order_srl) as $item)
			{
				$item_lines[] = $item->item_name . ($item->option_name ? ' / ' . $item->option_name : '') . ' x ' . (int)$item->qty;
			}
			if (count($item_lines))
			{
				$rows[] = [lang('commerce.mail_items'), $item_lines];
			}

			$rows[] = [lang('commerce.mail_payment'), shop_money_in((int)$order->payment_price, $order->currency ?? 'KRW')];

			$notes = [];
			if ($kind === 'new_order' && $order->status === Base::ORDER_PENDING)
			{
				$notes[] = lang('commerce.mail_pending_note');
			}
			if ($memo !== '')
			{
				$notes[] = $memo;
			}

			$html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.7;color:#333">';
			$html .= '<p style="margin:0 0 14px;font-size:15px;font-weight:700">' . escape($subject) . '</p>';
			$html .= '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
			foreach ($rows as [$label, $value])
			{
				$value_html = is_array($value)
					? implode('<br />', array_map(function($line) { return escape($line); }, $value))
					: escape((string)$value);
				$html .= '<tr>'
					. '<th style="padding:6px 14px 6px 0;text-align:left;vertical-align:top;color:#6b7684;font-weight:600;white-space:nowrap">' . escape($label) . '</th>'
					. '<td style="padding:6px 0;vertical-align:top">' . $value_html . '</td>'
					. '</tr>';
			}
			$html .= '</table>';
			foreach ($notes as $note)
			{
				$html .= '<p style="margin:14px 0 0;color:#6b7684">' . escape($note) . '</p>';
			}
			$html .= '</div>';

			foreach ($recipients as $to)
			{
				$mail = new \Zittme\Framework\Mail();
				$mail->addTo($to);
				$mail->setSubject($subject);
				$mail->setBody($html, 'text/html');
				$mail->send();
			}
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
				\Zittme\Framework\DB::getInstance()->query(
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
			self::clearCartOf($order);
		}

		self::log($order_srl, 0, 'pay', $recovered ? Base::ORDER_EXPIRED : Base::ORDER_PENDING, Base::ORDER_PAID, 0, $recovered ? 'recovered from expired (deposit confirmed)' : '');
		self::notifyMail('paid', $order);
		return true;
	}

	/**
	 * 주문한 상품을 장바구니에서 뺀다.
	 *
	 * 주문을 만들 때가 아니라 결제가 끝난 뒤에 부른다. 만들 때 비우면 결제 화면에서
	 * 뒤로 갔을 때 담아 둔 것을 잃는다.
	 *
	 * PG 콜백 안에서도 불리므로 세션에 기대지 않는다. 비회원은 세션 키를 알 수 없어
	 * 결과 화면에서 한 번 더 부른다.
	 *
	 * @param object $order
	 * @return void
	 */
	public static function clearCartOf(object $order): void
	{
		$member_srl = (int)($order->member_srl ?? 0);
		if ($member_srl <= 0)
		{
			return;
		}
		$db = \Zittme\Framework\DB::getInstance();
		foreach (self::getItems((int)$order->order_srl) as $item)
		{
			$db->query(
				'DELETE FROM commerce_cart WHERE member_srl = ? AND item_srl = ? AND option_srl = ?',
				$member_srl, (int)$item->item_srl, (int)$item->option_srl
			);
		}
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
	 * 주문 한 건을 딸린 자료까지 지운다. 결제된 주문은 지우지 않는다 —
	 * 매출·재고 이력과 어긋나기 때문이다. 되돌릴 수 없으므로 부르는 쪽에서 권한을 확인한다.
	 *
	 * @param int $order_srl
	 * @return bool 지웠으면 true
	 */
	public static function purge(int $order_srl): bool
	{
		$order = self::get($order_srl);
		if (!$order || in_array((string)$order->status, [Base::ORDER_PAID], true))
		{
			return false;
		}

		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$handle = \Zittme\Framework\DB::getInstance()->getHandle();
		foreach (['commerce_order_item', 'commerce_order_address', 'commerce_order_log', 'commerce_order_seller', 'commerce_order'] as $table)
		{
			$stmt = $handle->prepare('DELETE FROM `' . $prefix . $table . '` WHERE order_srl = ?');
			if ($stmt)
			{
				$stmt->execute([$order_srl]);
				$stmt->closeCursor();
			}
		}
		return true;
	}

	/**
	 * 아직 결제가 끝나지 않은 이 회원의 주문 1건. 만료 시간이 지나지 않은 것만 본다.
	 *
	 * @param int $member_srl
	 * @return ?object
	 */
	public static function findOpenPending(int $member_srl): ?object
	{
		if ($member_srl <= 0)
		{
			return null;
		}
		$minutes = max(10, (int)(Base::config()->pending_minutes ?? 60));
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare(
			'SELECT * FROM `' . $prefix . 'commerce_order`'
			. ' WHERE member_srl = ? AND status = ? AND regdate >= ?'
			. ' ORDER BY order_srl DESC LIMIT 1'
		);
		if (!$stmt || !$stmt->execute([$member_srl, Base::ORDER_PENDING, date('YmdHis', time() - 60 * $minutes)]))
		{
			return null;
		}
		$row = $stmt->fetchObject() ?: null;
		$stmt->closeCursor();
		return $row;
	}

	/**
	 * 결제 대기 주문이 자동 취소되는 시각 (YmdHis). 대기 주문이 아니면 빈 문자열.
	 *
	 * @param object $order
	 * @return string
	 */
	public static function pendingDeadline(object $order): string
	{
		if (($order->status ?? '') !== Base::ORDER_PENDING || empty($order->regdate))
		{
			return '';
		}
		$minutes = max(10, (int)(Base::config()->pending_minutes ?? 60));
		return date('YmdHis', ztime((string)$order->regdate) + 60 * $minutes);
	}

	/**
	 * 결제를 이어서 할 주소. 처음 만든 결제 건이 아직 열려 있으면 그 화면으로 돌아간다.
	 * 결제 건이 없거나 닫혔으면 빈 문자열 (이어서 결제할 수 없다).
	 *
	 * @param object $order
	 * @return string
	 */
	public static function resumePayUrl(object $order): string
	{
		if (($order->status ?? '') !== Base::ORDER_PENDING || !class_exists('\Zittme\Modules\Zittme_pay\PayService'))
		{
			return '';
		}
		$pay = \Zittme\Modules\Zittme_pay\PayService::getOrderBySource('commerce', (int)$order->order_srl);
		if (!$pay || empty($pay->order_code) || !in_array((string)$pay->status, \Zittme\Modules\Zittme_pay\Models\Order::OPEN_STATUSES, true))
		{
			return '';
		}
		return \Zittme\Modules\Zittme_pay\PayService::getPayUrl((string)$pay->order_code);
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
