<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Settlement
{
	public const OPEN_CLAIMS = ['requested', 'approved'];
	public const REFUND_CLAIMS = ['cancel', 'return'];

	public static $error = '';

	public static function carryOf(int $seller_srl): int
	{
		$rows = \Zittme\Framework\DB::getInstance()->query('SELECT carry_balance FROM commerce_seller WHERE seller_srl = ?', [$seller_srl])->fetchAll();
		return max(0, (int)($rows[0]->carry_balance ?? 0));
	}

	public static function carryBalances(): array
	{
		$map = [];
		foreach (\Zittme\Framework\DB::getInstance()->query('SELECT seller_srl, carry_balance FROM commerce_seller WHERE carry_balance > 0')->fetchAll() as $row)
		{
			$map[(int)$row->seller_srl] = (int)$row->carry_balance;
		}
		return $map;
	}

	public static function applyCarry(int $gross, int $balance): array
	{
		$net = $gross - max(0, $balance);
		return [max(0, $net), max(0, $balance), max(0, -$net)];
	}

	public static function get(int $settlement_srl): ?object
	{
		if ($settlement_srl <= 0)
		{
			return null;
		}
		$output = executeQuery('commerce.getSettlement', (object)['settlement_srl' => $settlement_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->settlement_srl)) ? $output->data : null;
	}

	public static function getList(int $seller_srl, string $status, int $page): object
	{
		$args = (object)['page' => max(1, $page), 'list_count' => 30];
		if ($seller_srl > 0)
		{
			$args->seller_srl = $seller_srl;
		}
		if (in_array($status, ['ready', 'paid'], true))
		{
			$args->status = $status;
		}
		$output = executeQueryArray('commerce.getSettlementList', $args);
		$list = $output->toBool() ? array_values((array)$output->data) : [];
		return (object)['list' => $list, 'page_navigation' => $output->page_navigation ?? null];
	}

	protected static function deliveredCutoff(): string
	{
		$days = max(0, (int)(Base::config()->claim_days ?? 7));
		return date('YmdHis', time() - 86400 * ($days + 1));
	}

	protected static function openClaimMap(array $order_srls): array
	{
		$map = [];
		$order_srls = array_values(array_unique(array_filter(array_map('intval', $order_srls))));
		if (!count($order_srls))
		{
			return $map;
		}
		$db = \Zittme\Framework\DB::getInstance();
		$marks = implode(',', array_fill(0, count($order_srls), '?'));
		$claims = $db->query('SELECT order_srl, order_seller_srl, items FROM commerce_claim WHERE status IN (?, ?) AND order_srl IN (' . $marks . ')', array_merge(self::OPEN_CLAIMS, $order_srls))->fetchAll();
		foreach ($claims as $claim)
		{
			if ((int)$claim->order_seller_srl > 0)
			{
				$map[(int)$claim->order_seller_srl] = true;
				continue;
			}
			$map['order_' . (int)$claim->order_srl] = true;
		}
		return $map;
	}

	public static function candidates(string $from, string $to, int $seller_srl = 0): array
	{
		$sql = 'SELECT commerce_order_seller.order_seller_srl, commerce_order_seller.order_srl, commerce_order_seller.seller_srl, commerce_order_seller.status, commerce_order_seller.delivered_date'
			. ' FROM commerce_order_seller JOIN commerce_order ON commerce_order.order_srl = commerce_order_seller.order_srl'
			. ' WHERE commerce_order.status = ? AND commerce_order_seller.status IN (?, ?) AND commerce_order_seller.settlement_srl = 0'
			. " AND commerce_order_seller.delivered_date <> '' AND commerce_order_seller.delivered_date <= ?";
		$params = [Base::ORDER_PAID, Base::SELLER_DELIVERED, Base::SELLER_CONFIRMED, $to . '235959'];
		if ($seller_srl > 0)
		{
			$sql .= ' AND commerce_order_seller.seller_srl = ?';
			$params[] = $seller_srl;
		}
		$sql .= ' ORDER BY commerce_order_seller.order_seller_srl ASC';
		$rows = \Zittme\Framework\DB::getInstance()->query($sql, $params)->fetchAll();

		$cutoff = self::deliveredCutoff();
		$open = self::openClaimMap(array_map(function ($r) { return (int)$r->order_srl; }, $rows));
		return array_values(array_filter($rows, function ($row) use ($cutoff, $open) {
			if (Seller::isOperator((int)$row->seller_srl))
			{
				return false;
			}
			if (isset($open[(int)$row->order_seller_srl]) || isset($open['order_' . (int)$row->order_srl]))
			{
				return false;
			}
			return $row->status === Base::SELLER_CONFIRMED || (string)$row->delivered_date <= $cutoff;
		}));
	}

	public static function amounts(array $order_seller_srls): array
	{
		$result = [];
		$order_seller_srls = array_values(array_unique(array_filter(array_map('intval', $order_seller_srls))));
		if (!count($order_seller_srls))
		{
			return $result;
		}
		$db = \Zittme\Framework\DB::getInstance();
		$marks = implode(',', array_fill(0, count($order_seller_srls), '?'));
		$bundles = $db->query('SELECT order_seller_srl, order_srl, delivery_fee, operator_fee FROM commerce_order_seller WHERE order_seller_srl IN (' . $marks . ')', $order_seller_srls)->fetchAll();
		$items = $db->query('SELECT order_item_srl, order_seller_srl, order_srl, qty, subtotal, commission FROM commerce_order_item WHERE order_seller_srl IN (' . $marks . ')', $order_seller_srls)->fetchAll();

		$raw = [];
		$order_srls = [];
		foreach ($bundles as $b)
		{
			$raw[(int)$b->order_seller_srl] = (object)[
				'order_srl' => (int)$b->order_srl,
				'item_total' => 0,
				'delivery' => max(0, (int)$b->delivery_fee - (int)$b->operator_fee),
				'commission' => 0,
				'refund' => 0,
				'refund_commission' => 0,
			];
			$order_srls[(int)$b->order_srl] = true;
		}
		$by_item = [];
		foreach ($items as $it)
		{
			$os = (int)$it->order_seller_srl;
			if (!isset($raw[$os]))
			{
				continue;
			}
			$raw[$os]->item_total += (int)$it->subtotal;
			$raw[$os]->commission += (int)$it->commission;
			$by_item[(int)$it->order_item_srl] = $it;
		}

		$fx = [];
		if (count($order_srls))
		{
			$keys = array_keys($order_srls);
			$marks = implode(',', array_fill(0, count($keys), '?'));
			foreach ($db->query('SELECT order_srl, currency, exchange_rate FROM commerce_order WHERE order_srl IN (' . $marks . ')', $keys)->fetchAll() as $o)
			{
				$fx[(int)$o->order_srl] = [strtoupper((string)($o->currency ?: Money::base())), (float)$o->exchange_rate];
			}

			$claims = $db->query(
				'SELECT order_seller_srl, items FROM commerce_claim WHERE status = ? AND claim_type IN (?, ?) AND order_srl IN (' . $marks . ')',
				array_merge(['done'], self::REFUND_CLAIMS, $keys)
			)->fetchAll();
			foreach ($claims as $claim)
			{
				$targets = (array)json_decode((string)$claim->items, true);
				$os = (int)$claim->order_seller_srl;
				if ($os <= 0)
				{
					foreach ($targets as $target)
					{
						$oi = $by_item[(int)($target['order_item_srl'] ?? 0)] ?? null;
						if ($oi)
						{
							$os = (int)$oi->order_seller_srl;
							break;
						}
					}
				}
				if (!isset($raw[$os]))
				{
					continue;
				}
				foreach ($targets as $target)
				{
					$oi = $by_item[(int)($target['order_item_srl'] ?? 0)] ?? null;
					if (!$oi || (int)$oi->order_seller_srl !== $os)
					{
						continue;
					}
					$qty = min(max(0, (int)($target['qty'] ?? 0)), max(1, (int)$oi->qty));
					$raw[$os]->refund += (int)round((int)$oi->subtotal * $qty / max(1, (int)$oi->qty));
					$raw[$os]->refund_commission += (int)round((int)$oi->commission * $qty / max(1, (int)$oi->qty));
				}
			}
		}

		foreach ($raw as $os => $r)
		{
			$r->refund = min($r->refund, $r->item_total);
			$r->refund_commission = min($r->refund_commission, $r->commission);
			[$cur, $rate] = $fx[$r->order_srl] ?? [Money::base(), 0.0];
			$to_base = function (int $v) use ($cur, $rate) { return Money::minorToBase($v, $cur, $rate); };
			$result[$os] = (object)[
				'item_total' => $to_base($r->item_total),
				'delivery' => $to_base($r->delivery),
				'commission' => $to_base($r->commission),
				'refund' => $to_base($r->refund),
				'refund_commission' => $to_base($r->refund_commission),
			];
		}
		foreach ($order_seller_srls as $srl)
		{
			if (!isset($result[$srl]))
			{
				$result[$srl] = (object)['item_total' => 0, 'delivery' => 0, 'commission' => 0, 'refund' => 0, 'refund_commission' => 0];
			}
		}
		return $result;
	}

	public static function pendingAdjustments(int $seller_srl = 0): array
	{
		$db = \Zittme\Framework\DB::getInstance();
		$claimed = $db->query(
			'SELECT order_srl FROM commerce_claim WHERE status = ? AND claim_type IN (?, ?)',
			array_merge(['done'], self::REFUND_CLAIMS)
		)->fetchAll();
		$order_srls = array_values(array_unique(array_map(function ($r) { return (int)$r->order_srl; }, $claimed)));
		if (!count($order_srls))
		{
			return [];
		}
		$marks = implode(',', array_fill(0, count($order_srls), '?'));
		$sql = 'SELECT order_seller_srl, seller_srl, settle_refund, settle_refund_commission FROM commerce_order_seller WHERE settlement_srl > 0 AND order_srl IN (' . $marks . ')';
		$params = $order_srls;
		if ($seller_srl > 0)
		{
			$sql .= ' AND seller_srl = ?';
			$params[] = $seller_srl;
		}
		$rows = $db->query($sql, $params)->fetchAll();
		$amounts = self::amounts(array_map(function ($r) { return (int)$r->order_seller_srl; }, $rows));
		$out = [];
		foreach ($rows as $row)
		{
			$a = $amounts[(int)$row->order_seller_srl];
			$d_refund = $a->refund - (int)$row->settle_refund;
			$d_comm = $a->refund_commission - (int)$row->settle_refund_commission;
			if ($d_refund <= 0 && $d_comm <= 0)
			{
				continue;
			}
			$out[] = (object)[
				'order_seller_srl' => (int)$row->order_seller_srl,
				'seller_srl' => (int)$row->seller_srl,
				'refund' => max(0, $d_refund),
				'refund_commission' => max(0, $d_comm),
				'old_refund' => (int)$row->settle_refund,
				'old_refund_commission' => (int)$row->settle_refund_commission,
				'now_refund' => (int)$row->settle_refund + max(0, $d_refund),
				'now_refund_commission' => (int)$row->settle_refund_commission + max(0, $d_comm),
			];
		}
		return $out;
	}

	public static function preview(string $from, string $to, int $seller_srl = 0): array
	{
		$rows = self::candidates($from, $to, $seller_srl);
		$amounts = self::amounts(array_map(function ($r) { return (int)$r->order_seller_srl; }, $rows));
		$sum = [];
		$slot = function (int $key) use (&$sum) {
			if (!isset($sum[$key]))
			{
				$seller = Seller::get($key);
				$sum[$key] = (object)[
					'seller_srl' => $key,
					'shop_name' => (string)($seller->shop_name ?? ('#' . $key)),
					'order_count' => 0, 'item_total' => 0, 'delivery_total' => 0, 'refund_total' => 0, 'commission_total' => 0, 'settle_amount' => 0,
				];
			}
			return $sum[$key];
		};
		foreach ($rows as $row)
		{
			$s = $slot((int)$row->seller_srl);
			$a = $amounts[(int)$row->order_seller_srl];
			$s->order_count++;
			$s->item_total += $a->item_total;
			$s->delivery_total += $a->delivery;
			$s->refund_total += $a->refund;
			$s->commission_total += $a->commission - $a->refund_commission;
		}
		foreach (self::pendingAdjustments($seller_srl) as $adj)
		{
			$s = $slot($adj->seller_srl);
			$s->refund_total += $adj->refund;
			$s->commission_total -= $adj->refund_commission;
		}
		foreach ($sum as $s)
		{
			$s->gross_amount = $s->item_total + $s->delivery_total - $s->refund_total - $s->commission_total;
			[$s->settle_amount, $s->carry_in, $s->carry_out] = self::applyCarry($s->gross_amount, self::carryOf($s->seller_srl));
		}
		return $sum;
	}

	public static function create(string $from, string $to, int $seller_srl, int $actor_srl): int
	{
		$db = \Zittme\Framework\DB::getInstance();
		$groups = [];
		foreach (self::candidates($from, $to, $seller_srl) as $row)
		{
			$groups[(int)$row->seller_srl]['rows'][] = (int)$row->order_seller_srl;
		}
		foreach (self::pendingAdjustments($seller_srl) as $adj)
		{
			$groups[$adj->seller_srl]['adjust'][] = $adj;
		}
		$created = 0;
		$now = date('YmdHis');
		foreach ($groups as $group_seller => $group)
		{
			$settlement_srl = getNextSequence();
			$db->beginTransaction();
			try
			{
				if (self::createOne($db, $settlement_srl, (int)$group_seller, $group, $from, $to, $actor_srl, $now))
				{
					$db->commit();
					$created++;
				}
				else
				{
					$db->rollback();
				}
			}
			catch (\Throwable $e)
			{
				$db->rollback();
			}
		}
		return $created;
	}

	protected static function createOne($db, int $settlement_srl, int $group_seller, array $group, string $from, string $to, int $actor_srl, string $now): bool
	{
		{
			$t = (object)['order_count' => 0, 'item_total' => 0, 'delivery_total' => 0, 'refund_total' => 0, 'commission_total' => 0];

			$os_srls = $group['rows'] ?? [];
			if (count($os_srls))
			{
				$marks = implode(',', array_fill(0, count($os_srls), '?'));
				$db->query(
					"UPDATE commerce_order_seller SET settlement_srl = ?, settle_status = 'ready' WHERE settlement_srl = 0 AND order_seller_srl IN (" . $marks . ')',
					array_merge([$settlement_srl], $os_srls)
				);
				$claimed = $db->query('SELECT order_seller_srl, order_srl FROM commerce_order_seller WHERE settlement_srl = ?', [$settlement_srl])->fetchAll();
				$open = self::openClaimMap(array_map(function ($r) { return (int)$r->order_srl; }, $claimed));
				$kept = [];
				foreach ($claimed as $row)
				{
					if (isset($open[(int)$row->order_seller_srl]) || isset($open['order_' . (int)$row->order_srl]))
					{
						$db->query("UPDATE commerce_order_seller SET settlement_srl = 0, settle_status = 'none' WHERE order_seller_srl = ? AND settlement_srl = ?", [(int)$row->order_seller_srl, $settlement_srl]);
						continue;
					}
					$kept[] = $row;
				}
				$claimed = $kept;
				$amounts = self::amounts(array_map(function ($r) { return (int)$r->order_seller_srl; }, $claimed));
				foreach ($claimed as $row)
				{
					$a = $amounts[(int)$row->order_seller_srl];
					$t->order_count++;
					$t->item_total += $a->item_total;
					$t->delivery_total += $a->delivery;
					$t->refund_total += $a->refund;
					$t->commission_total += $a->commission - $a->refund_commission;
					$db->query(
						'UPDATE commerce_order_seller SET settle_refund = ?, settle_refund_commission = ? WHERE order_seller_srl = ?',
						[$a->refund, $a->refund_commission, (int)$row->order_seller_srl]
					);
				}
			}

			$adjusted = [];
			foreach ($group['adjust'] ?? [] as $adj)
			{
				$stmt = $db->query(
					'UPDATE commerce_order_seller SET settle_refund = ?, settle_refund_commission = ? WHERE order_seller_srl = ? AND settle_refund = ? AND settle_refund_commission = ?',
					[$adj->now_refund, $adj->now_refund_commission, $adj->order_seller_srl, $adj->old_refund, $adj->old_refund_commission]
				);
				if (!$stmt || $stmt->rowCount() < 1)
				{
					continue;
				}
				$db->query(
					'INSERT INTO commerce_settlement_adjust (adjust_srl, settlement_srl, order_seller_srl, seller_srl, refund, refund_commission, amount, regdate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
					[getNextSequence(), $settlement_srl, $adj->order_seller_srl, (int)$group_seller, $adj->refund, $adj->refund_commission, -($adj->refund - $adj->refund_commission), $now]
				);
				$t->refund_total += $adj->refund;
				$t->commission_total -= $adj->refund_commission;
				$adjusted[] = $adj;
			}

			if ($t->order_count === 0 && !count($adjusted))
			{
				return false;
			}
			$gross = $t->item_total + $t->delivery_total - $t->refund_total - $t->commission_total;
			$balance = self::carryOf($group_seller);
			[$pay, $carry_in, $carry_out] = self::applyCarry($gross, $balance);
			if ($carry_in !== $carry_out)
			{
				$stmt = $db->query('UPDATE commerce_seller SET carry_balance = ? WHERE seller_srl = ? AND carry_balance = ?', [$carry_out, $group_seller, $balance]);
				if (!$stmt || $stmt->rowCount() < 1)
				{
					return false;
				}
			}
			$seller = Seller::get((int)$group_seller);
			$output = executeQuery('commerce.insertSettlement', (object)[
				'settlement_srl' => $settlement_srl,
				'seller_srl' => (int)$group_seller,
				'period_start' => $from,
				'period_end' => $to,
				'order_count' => $t->order_count,
				'item_total' => $t->item_total,
				'delivery_total' => $t->delivery_total,
				'refund_total' => $t->refund_total,
				'commission_total' => $t->commission_total,
				'settle_amount' => $pay,
				'carry_in' => $carry_in,
				'carry_out' => $carry_out,
				'status' => 'ready',
				'bank_name' => (string)($seller->bank_name ?? ''),
				'bank_account' => (string)($seller->bank_account ?? ''),
				'bank_holder' => (string)($seller->bank_holder ?? ''),
				'created_by' => $actor_srl,
				'regdate' => $now,
			]);
			return $output->toBool();
		}
	}

	protected static function release(int $settlement_srl): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		foreach ($db->query('SELECT order_seller_srl, refund, refund_commission FROM commerce_settlement_adjust WHERE settlement_srl = ?', [$settlement_srl])->fetchAll() as $adj)
		{
			$db->query(
				'UPDATE commerce_order_seller SET settle_refund = settle_refund - ?, settle_refund_commission = settle_refund_commission - ? WHERE order_seller_srl = ?',
				[(int)$adj->refund, (int)$adj->refund_commission, (int)$adj->order_seller_srl]
			);
		}
		$db->query('DELETE FROM commerce_settlement_adjust WHERE settlement_srl = ?', [$settlement_srl]);
		$db->query("UPDATE commerce_order_seller SET settlement_srl = 0, settle_status = 'none', settle_refund = 0, settle_refund_commission = 0 WHERE settlement_srl = ?", [$settlement_srl]);
	}

	public static function markPaid(int $settlement_srl, string $memo): bool
	{
		self::$error = '';
		$before = self::get($settlement_srl);
		if (!$before)
		{
			self::$error = 'sc_err_settle_missing';
			return false;
		}
		if ($before->status !== 'ready')
		{
			self::$error = 'sc_err_settle_already_paid';
			return false;
		}
		$now = date('YmdHis');
		$args = (object)['settlement_srl' => $settlement_srl, 'status' => 'paid', 'from_status' => 'ready', 'paid_date' => $now];
		if ($memo !== '')
		{
			$args->memo = mb_substr($memo, 0, 250);
		}
		$output = executeQuery('commerce.updateSettlementPaid', $args);
		$st = self::get($settlement_srl);
		if (!$output->toBool() || !$st || $st->status !== 'paid')
		{
			self::$error = 'sc_err_settle_already_paid';
			return false;
		}
		\Zittme\Framework\DB::getInstance()->query(
			"UPDATE commerce_order_seller SET settle_status = 'done', settle_date = ? WHERE settlement_srl = ?",
			[$now, $settlement_srl]
		);
		return true;
	}

	public static function cancel(int $settlement_srl): bool
	{
		self::$error = '';
		$st = self::get($settlement_srl);
		if (!$st)
		{
			self::$error = 'sc_err_settle_missing';
			return false;
		}
		if ($st->status !== 'ready')
		{
			self::$error = 'sc_err_settle_paid_no_cancel';
			return false;
		}
		$db = \Zittme\Framework\DB::getInstance();
		$newer = $db->query('SELECT COUNT(*) AS cnt FROM commerce_settlement WHERE seller_srl = ? AND settlement_srl > ?', [(int)$st->seller_srl, $settlement_srl])->fetchAll();
		if ((int)($newer[0]->cnt ?? 0) > 0)
		{
			self::$error = 'sc_err_settle_not_latest';
			return false;
		}
		// 이 정산의 묶음에 뒤 정산의 조정 줄이 걸려 있으면 취소하지 않는다 (환불이 두 번 빠진다)
		$later = $db->query(
			'SELECT COUNT(*) AS cnt FROM commerce_settlement_adjust JOIN commerce_order_seller ON commerce_order_seller.order_seller_srl = commerce_settlement_adjust.order_seller_srl'
			. ' WHERE commerce_order_seller.settlement_srl = ? AND commerce_settlement_adjust.settlement_srl <> ?',
			[$settlement_srl, $settlement_srl]
		)->fetchAll();
		if ((int)($later[0]->cnt ?? 0) > 0)
		{
			self::$error = 'sc_err_settle_adjusted_later';
			return false;
		}

		$db->beginTransaction();
		try
		{
			$carry_in = (int)($st->carry_in ?? 0);
			$carry_out = (int)($st->carry_out ?? 0);
			if ($carry_in !== $carry_out)
			{
				$stmt = $db->query('UPDATE commerce_seller SET carry_balance = ? WHERE seller_srl = ? AND carry_balance = ?', [$carry_in, (int)$st->seller_srl, $carry_out]);
				if (!$stmt || $stmt->rowCount() < 1)
				{
					$db->rollback();
					self::$error = 'sc_err_settle_carry_changed';
					return false;
				}
			}
			$stmt = $db->query("DELETE FROM commerce_settlement WHERE settlement_srl = ? AND status = 'ready'", [$settlement_srl]);
			if (!$stmt || $stmt->rowCount() < 1)
			{
				$db->rollback();
				self::$error = 'sc_err_settle_paid_no_cancel';
				return false;
			}
			self::release($settlement_srl);
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollback();
			self::$error = 'sc_err_settle_failed';
			return false;
		}
		return true;
	}

	public static function markRefundedBundles(int $order_srl): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		$rows = $db->query('SELECT order_seller_srl FROM commerce_order_seller WHERE order_srl = ? AND status IN (?, ?, ?, ?, ?)', [$order_srl, 'paid', 'preparing', 'shipping', 'delivered', 'confirmed'])->fetchAll();
		if (!count($rows))
		{
			return;
		}
		$amounts = self::amounts(array_map(function ($r) { return (int)$r->order_seller_srl; }, $rows));
		foreach ($amounts as $os => $a)
		{
			if ($a->item_total > 0 && $a->refund >= $a->item_total)
			{
				$db->query(
					"UPDATE commerce_order_seller SET status = 'refunded' WHERE order_seller_srl = ? AND status IN ('paid', 'preparing', 'shipping', 'delivered', 'confirmed')",
					[(int)$os]
				);
			}
		}
	}


	public static function lines(int $settlement_srl): array
	{
		$db = \Zittme\Framework\DB::getInstance();
		$rows = $db->query(
			'SELECT commerce_order_seller.order_seller_srl, commerce_order_seller.order_srl, commerce_order_seller.delivered_date, commerce_order_seller.status, commerce_order_seller.settle_refund, commerce_order_seller.settle_refund_commission,'
			. ' commerce_order.order_code, commerce_order.paid_date'
			. ' FROM commerce_order_seller JOIN commerce_order ON commerce_order.order_srl = commerce_order_seller.order_srl'
			. ' WHERE commerce_order_seller.settlement_srl = ? ORDER BY commerce_order_seller.order_seller_srl ASC',
			[$settlement_srl]
		)->fetchAll();
		$amounts = self::amounts(array_map(function ($r) { return (int)$r->order_seller_srl; }, $rows));
		$later = [];
		if (count($rows))
		{
			$srls = array_map(function ($r) { return (int)$r->order_seller_srl; }, $rows);
			$marks = implode(',', array_fill(0, count($srls), '?'));
			foreach ($db->query(
				'SELECT order_seller_srl, SUM(refund) AS refund, SUM(refund_commission) AS refund_commission FROM commerce_settlement_adjust WHERE settlement_srl <> ? AND order_seller_srl IN (' . $marks . ') GROUP BY order_seller_srl',
				array_merge([$settlement_srl], $srls)
			)->fetchAll() as $lr)
			{
				$later[(int)$lr->order_seller_srl] = $lr;
			}
		}
		foreach ($rows as $row)
		{
			$a = $amounts[(int)$row->order_seller_srl];
			$l = $later[(int)$row->order_seller_srl] ?? null;
			$snap_refund = max(0, (int)$row->settle_refund - (int)($l->refund ?? 0));
			$snap_comm = max(0, (int)$row->settle_refund_commission - (int)($l->refund_commission ?? 0));
			$row->is_adjust = false;
			$row->item_total = $a->item_total;
			$row->delivery_fee = $a->delivery;
			$row->refund = $snap_refund;
			$row->commission = $a->commission - $snap_comm;
			$row->settle_amount = $a->item_total + $a->delivery - $row->refund - $row->commission;
		}

		$adjusts = $db->query(
			'SELECT commerce_settlement_adjust.order_seller_srl, commerce_settlement_adjust.refund, commerce_settlement_adjust.refund_commission, commerce_settlement_adjust.amount, commerce_order.order_code'
			. ' FROM commerce_settlement_adjust JOIN commerce_order_seller ON commerce_order_seller.order_seller_srl = commerce_settlement_adjust.order_seller_srl'
			. ' JOIN commerce_order ON commerce_order.order_srl = commerce_order_seller.order_srl'
			. ' WHERE commerce_settlement_adjust.settlement_srl = ? ORDER BY commerce_settlement_adjust.adjust_srl ASC',
			[$settlement_srl]
		)->fetchAll();
		foreach ($adjusts as $adj)
		{
			$rows[] = (object)[
				'order_seller_srl' => (int)$adj->order_seller_srl,
				'order_code' => (string)$adj->order_code . ' (' . lang('commerce.sc_adjust_line') . ')',
				'delivered_date' => '',
				'status' => 'adjust',
				'is_adjust' => true,
				'item_total' => 0,
				'delivery_fee' => 0,
				'refund' => (int)$adj->refund,
				'commission' => -(int)$adj->refund_commission,
				'settle_amount' => (int)$adj->amount,
			];
		}
		$st = self::get($settlement_srl);
		foreach ([['carry_in', -1, 'sc_carry_in_line'], ['carry_out', 1, 'sc_carry_out_line']] as [$key, $sign, $label])
		{
			$value = (int)($st->$key ?? 0);
			if ($value > 0)
			{
				$rows[] = (object)[
					'order_seller_srl' => 0,
					'order_code' => lang('commerce.' . $label),
					'delivered_date' => '',
					'status' => 'carry',
					'is_adjust' => true,
					'item_total' => 0,
					'delivery_fee' => 0,
					'refund' => 0,
					'commission' => 0,
					'settle_amount' => $sign * $value,
				];
			}
		}
		return $rows;
	}
}
