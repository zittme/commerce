<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 적립금 — 커머스 자체 원장.
 *
 * ★ 코어 point 모듈은 커뮤니티 포인트라 연동하지 않는다 (사용자 지시).
 * 잔액은 commerce_credit_balance, 이력은 commerce_credit_log.
 * 사용 차감은 `balance >= n` 조건부 UPDATE 원자 경로로만 일어난다 — 음수 잔액 불가.
 */
class Credit
{
	/**
	 * 잔액.
	 *
	 * @param int $member_srl
	 * @return int
	 */
	public static function balanceOf(int $member_srl): int
	{
		if ($member_srl <= 0)
		{
			return 0;
		}
		$stmt = \Rhymix\Framework\DB::getInstance()->query(
			'SELECT balance FROM commerce_credit_balance WHERE member_srl = ?', $member_srl
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		return $row ? (int)$row->balance : 0;
	}

	/**
	 * 적립·환불·관리자 조정 (+/- 모두, 단 잔액이 음수가 되는 차감은 사용 경로 spend 로만).
	 *
	 * @param int $member_srl
	 * @param int $amount +적립 / -회수
	 * @param string $type earn | refund | earn_cancel | admin
	 * @param int $order_srl
	 * @param string $memo
	 * @return bool
	 */
	public static function add(int $member_srl, int $amount, string $type, int $order_srl = 0, string $memo = ''): bool
	{
		if ($member_srl <= 0 || $amount === 0)
		{
			return false;
		}
		$db = \Rhymix\Framework\DB::getInstance();

		// 잔액 행 보장 (동시 생성은 PK 충돌로 한쪽만 성공 — 이후 UPDATE 는 공통)
		try
		{
			$db->query('INSERT INTO commerce_credit_balance (member_srl, balance, upddate) VALUES (?, 0, ?)', $member_srl, Base::now());
		}
		catch (\Exception $e)
		{
			// 이미 있음
		}

		if ($amount < 0)
		{
			// 회수는 잔액 한도 내에서만 (0 미만 방지)
			$stmt = $db->query(
				'UPDATE commerce_credit_balance SET balance = balance + ?, upddate = ? WHERE member_srl = ? AND balance >= ?',
				$amount, Base::now(), $member_srl, -$amount
			);
			if (!$stmt || $stmt->rowCount() !== 1)
			{
				// 잔액 부족 — 있는 만큼만 회수
				$current = self::balanceOf($member_srl);
				if ($current <= 0)
				{
					return false;
				}
				$amount = -$current;
				$db->query(
					'UPDATE commerce_credit_balance SET balance = 0, upddate = ? WHERE member_srl = ?',
					Base::now(), $member_srl
				);
			}
		}
		else
		{
			$db->query(
				'UPDATE commerce_credit_balance SET balance = balance + ?, upddate = ? WHERE member_srl = ?',
				$amount, Base::now(), $member_srl
			);
		}

		self::log($member_srl, $amount, $type, $order_srl, $memo);
		return true;
	}

	/**
	 * 사용 (원자 차감 — 잔액 부족이면 실패).
	 *
	 * @param int $member_srl
	 * @param int $amount 양수
	 * @param int $order_srl
	 * @return bool
	 */
	public static function spend(int $member_srl, int $amount, int $order_srl): bool
	{
		if ($member_srl <= 0 || $amount <= 0)
		{
			return false;
		}
		$stmt = \Rhymix\Framework\DB::getInstance()->query(
			'UPDATE commerce_credit_balance SET balance = balance - ?, upddate = ? WHERE member_srl = ? AND balance >= ?',
			$amount, Base::now(), $member_srl, $amount
		);
		if (!$stmt || $stmt->rowCount() !== 1)
		{
			return false;
		}
		self::log($member_srl, -$amount, 'spend', $order_srl);
		return true;
	}

	/**
	 * 결제 완료 적립 — 실결제 상품 금액(할인·적립금 사용 차감 후) 기준.
	 *
	 * @param object $order
	 * @return void
	 */
	public static function earnForOrder(object $order): void
	{
		$member_srl = (int)$order->member_srl;
		if ($member_srl <= 0)
		{
			return;
		}
		// 등급별 적립률이 있으면 우선 적용
		$rate = Grade::creditRateFor($member_srl);
		if ($rate <= 0)
		{
			return;
		}
		$base = max(0, (int)$order->item_total - (int)($order->discount_total ?? 0) - (int)($order->credit_used ?? 0));
		$earn = (int)floor($base * $rate / 100);
		if ($earn > 0)
		{
			self::add($member_srl, $earn, 'earn', (int)$order->order_srl);
		}
	}

	/**
	 * 주문 취소 정산 — 사용분 환불 + 적립분 회수.
	 *
	 * cancelAndRestock 전이 승자만 호출하므로 이중 정산이 없다.
	 *
	 * @param object $order
	 * @return void
	 */
	public static function settleCancel(object $order): void
	{
		$member_srl = (int)$order->member_srl;
		if ($member_srl <= 0)
		{
			return;
		}
		$order_srl = (int)$order->order_srl;

		// 사용분 환불
		$used = (int)($order->credit_used ?? 0);
		if ($used > 0)
		{
			self::add($member_srl, $used, 'refund', $order_srl);
		}

		// 적립분 회수 (이 주문의 earn 합계)
		$stmt = \Rhymix\Framework\DB::getInstance()->query(
			'SELECT COALESCE(SUM(amount), 0) AS s FROM commerce_credit_log WHERE order_srl = ? AND type = ?',
			$order_srl, 'earn'
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		$earned = $row ? (int)$row->s : 0;
		if ($earned > 0)
		{
			self::add($member_srl, -$earned, 'earn_cancel', $order_srl);
		}
	}

	/**
	 * 이력 목록.
	 *
	 * @param int $member_srl
	 * @param int $list_count
	 * @return array
	 */
	public static function getLogs(int $member_srl, int $list_count = 50): array
	{
		$output = executeQuery('commerce.getCreditLogs', (object)['member_srl' => $member_srl, 'list_count' => $list_count]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) { return !empty($row->log_srl); }));
	}

	/**
	 * 원장 기록.
	 *
	 * @param int $member_srl
	 * @param int $amount
	 * @param string $type
	 * @param int $order_srl
	 * @param string $memo
	 * @return void
	 */
	protected static function log(int $member_srl, int $amount, string $type, int $order_srl = 0, string $memo = ''): void
	{
		executeQuery('commerce.insertCreditLog', (object)[
			'log_srl' => getNextSequence(),
			'member_srl' => $member_srl,
			'order_srl' => $order_srl,
			'amount' => $amount,
			'balance_after' => self::balanceOf($member_srl),
			'type' => $type,
			'memo' => mb_substr($memo, 0, 250),
			'regdate' => Base::now(),
		]);
	}
}
