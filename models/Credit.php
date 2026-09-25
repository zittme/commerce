<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Credit
{
	public static function balanceOf(int $member_srl): int
	{
		if ($member_srl <= 0)
		{
			return 0;
		}
		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'SELECT balance FROM commerce_credit_balance WHERE member_srl = ?', $member_srl
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		return $row ? (int)$row->balance : 0;
	}

	public static function add(int $member_srl, int $amount, string $type, int $order_srl = 0, string $memo = ''): bool
	{
		if ($member_srl <= 0 || $amount === 0)
		{
			return false;
		}
		$db = \Zittme\Framework\DB::getInstance();

		try
		{
			$db->query('INSERT INTO commerce_credit_balance (member_srl, balance, upddate) VALUES (?, 0, ?)', $member_srl, Base::now());
		}
		catch (\Exception $e)
		{
		}

		if ($amount < 0)
		{
			$stmt = $db->query(
				'UPDATE commerce_credit_balance SET balance = balance + ?, upddate = ? WHERE member_srl = ? AND balance >= ?',
				$amount, Base::now(), $member_srl, -$amount
			);
			if (!$stmt || $stmt->rowCount() !== 1)
			{
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

	public static function spend(int $member_srl, int $amount, int $order_srl): bool
	{
		if ($member_srl <= 0 || $amount <= 0)
		{
			return false;
		}
		$stmt = \Zittme\Framework\DB::getInstance()->query(
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

	public static function earnForOrder(object $order): void
	{
		$member_srl = (int)$order->member_srl;
		if ($member_srl <= 0)
		{
			return;
		}
		if (strtoupper((string)($order->currency ?? Money::base())) !== Money::base())
		{
			return;
		}
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

	public static function settleCancel(object $order): void
	{
		$member_srl = (int)$order->member_srl;
		if ($member_srl <= 0)
		{
			return;
		}
		$order_srl = (int)$order->order_srl;

		$used = (int)($order->credit_used ?? 0);
		if ($used > 0)
		{
			self::add($member_srl, $used, 'refund', $order_srl);
		}

		$stmt = \Zittme\Framework\DB::getInstance()->query(
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
