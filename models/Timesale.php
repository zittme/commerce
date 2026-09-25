<?php

namespace Zittme\Modules\Commerce\Models;

class Timesale
{
	protected static $map = null;

	public static function windowEnd(object $sale, int $now = 0): int
	{
		$now = $now ?: time();
		if (($sale->status ?? 'Y') !== 'Y')
		{
			return 0;
		}
		$start = self::ts((string)$sale->start_date);
		$end = self::ts((string)$sale->end_date);
		if (($sale->mode ?? 'once') !== 'daily')
		{
			return ($start <= $now && $now < $end) ? $end : 0;
		}
		$today = date('Ymd', $now);
		foreach ([date('Ymd', $now - 86400), $today] as $day)
		{
			$ws = self::ts($day . str_pad((string)$sale->daily_start, 4, '0', STR_PAD_LEFT) . '00');
			$we = self::ts($day . str_pad((string)$sale->daily_end, 4, '0', STR_PAD_LEFT) . '00');
			if ($we <= $ws)
			{
				$we += 86400;
			}
			if ($ws <= $now && $now < $we && $ws >= $start && $ws < $end)
			{
				return min($we, $end);
			}
		}
		return 0;
	}

	public static function nextStart(object $sale, int $now = 0): int
	{
		$now = $now ?: time();
		if (($sale->status ?? 'Y') !== 'Y' || self::windowEnd($sale, $now))
		{
			return 0;
		}
		$start = self::ts((string)$sale->start_date);
		$end = self::ts((string)$sale->end_date);
		if (($sale->mode ?? 'once') !== 'daily')
		{
			return $start > $now ? $start : 0;
		}
		for ($i = 0; $i < 400; $i++)
		{
			$day = date('Ymd', $now + $i * 86400);
			$ws = self::ts($day . str_pad((string)$sale->daily_start, 4, '0', STR_PAD_LEFT) . '00');
			if ($ws > $now && $ws >= $start && $ws < $end)
			{
				return $ws;
			}
			if ($ws >= $end)
			{
				break;
			}
		}
		return 0;
	}

	protected static function ts(string $ymdhis): int
	{
		$ymdhis = str_pad(preg_replace('/\D/', '', $ymdhis), 14, '0');
		$t = strtotime(substr($ymdhis, 0, 4) . '-' . substr($ymdhis, 4, 2) . '-' . substr($ymdhis, 6, 2) . ' ' . substr($ymdhis, 8, 2) . ':' . substr($ymdhis, 10, 2) . ':' . substr($ymdhis, 12, 2));
		return $t ?: 0;
	}

	public static function activeMap(): array
	{
		if (self::$map !== null)
		{
			return self::$map;
		}
		self::$map = [];
		try
		{
			$db = \Zittme\Framework\DB::getInstance();
			$st = $db->query('SELECT commerce_timesale_item.*, commerce_timesale.title, commerce_timesale.mode, commerce_timesale.start_date, commerce_timesale.end_date, commerce_timesale.daily_start, commerce_timesale.daily_end, commerce_timesale.status'
				. ' FROM commerce_timesale_item JOIN commerce_timesale ON commerce_timesale.sale_srl = commerce_timesale_item.sale_srl'
				. ' WHERE commerce_timesale.status = ? AND commerce_timesale.end_date >= ?', ['Y', date('YmdHis')]);
			$rows = $st ? $st->fetchAll() : [];
		}
		catch (\Throwable $e)
		{
			$rows = [];
		}
		$now = time();
		foreach ($rows as $row)
		{
			$row->ends_at = self::windowEnd($row, $now);
			$row->starts_at = $row->ends_at ? 0 : self::nextStart($row, $now);
			$row->left = (int)$row->qty_limit > 0 ? max(0, (int)$row->qty_limit - (int)$row->sold_qty) : -1;
			$srl = (int)$row->item_srl;
			if (!isset(self::$map[$srl]) || ($row->ends_at && !self::$map[$srl]->ends_at))
			{
				self::$map[$srl] = $row;
			}
		}
		return self::$map;
	}

	public static function forItem(object $item): ?object
	{
		$srl = (int)($item->item_srl ?? 0);
		if ($srl <= 0)
		{
			return null;
		}
		$row = self::activeMap()[$srl] ?? null;
		if (!$row)
		{
			return null;
		}
		if (!$row->ends_at || $row->left === 0)
		{
			$item->timesale_info = $row;
			return null;
		}
		$sale = (int)($item->sale_price ?? 0);
		$base = $sale > 0 ? $sale : (int)($item->price ?? 0);
		$price = self::priceFrom($row, $base);
		if ($price >= $base)
		{
			return null;
		}
		$row->price = $price;
		$row->base = $base;
		$item->timesale = $row;
		$item->timesale_info = $row;
		$item->grade_discount = 'N';
		return $row;
	}

	public static function priceFrom(object $row, int $base): int
	{
		if (($row->discount_type ?? 'percent') === 'price')
		{
			return max(0, (int)$row->value);
		}
		$rate = max(0, min(100, (float)$row->value));
		return max(0, (int)floor($base * (100 - $rate) / 100));
	}

	public static function priceIn(object $row, int $foreign_base, string $currency): int
	{
		if (($row->discount_type ?? 'percent') === 'price')
		{
			return max(0, Money::convertMinor((int)$row->value, $currency));
		}
		return self::priceFrom($row, $foreign_base);
	}

	public static function reserve(object $row, int $qty, int $member_srl): string
	{
		$db = \Zittme\Framework\DB::getInstance();
		if ((int)$row->per_member > 0)
		{
			if ($member_srl <= 0)
			{
				return lang('commerce.ts_msg_login');
			}
			$st = $db->query("SELECT SUM(commerce_order_item.qty) FROM commerce_order_item JOIN commerce_order ON commerce_order.order_srl = commerce_order_item.order_srl WHERE commerce_order_item.timesale_item_srl = ? AND commerce_order.member_srl = ? AND commerce_order.status IN ('pending', 'paid')", [(int)$row->ts_item_srl, $member_srl]);
			$bought = $st ? (int)($st->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0) : 0;
			if ($bought + $qty > (int)$row->per_member)
			{
				return sprintf(lang('commerce.ts_msg_per_member'), (int)$row->per_member);
			}
		}
		$st = $db->query('UPDATE commerce_timesale_item SET sold_qty = sold_qty + ? WHERE ts_item_srl = ? AND (qty_limit = 0 OR sold_qty + ? <= qty_limit)', [$qty, (int)$row->ts_item_srl, $qty]);
		if (!$st || $st->rowCount() < 1)
		{
			return lang('commerce.ts_msg_sold_out');
		}
		return '';
	}

	public static function release(array $list): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		foreach ($list as [$srl, $qty])
		{
			$db->query('UPDATE commerce_timesale_item SET sold_qty = GREATEST(0, sold_qty - ?) WHERE ts_item_srl = ?', [(int)$qty, (int)$srl]);
		}
	}

	public static function releaseForOrder(int $order_srl): void
	{
		try
		{
			$st = \Zittme\Framework\DB::getInstance()->query('SELECT timesale_item_srl, qty FROM commerce_order_item WHERE order_srl = ? AND timesale_item_srl > 0', [$order_srl]);
			$list = [];
			foreach (($st ? $st->fetchAll() : []) as $r)
			{
				$list[] = [(int)$r->timesale_item_srl, (int)$r->qty];
			}
			self::release($list);
		}
		catch (\Throwable $e) {}
	}

	public static function getList(): array
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_timesale ORDER BY sale_srl DESC');
		$rows = $st ? $st->fetchAll() : [];
		foreach ($rows as $row)
		{
			$row->ends_at = self::windowEnd($row);
			$row->starts_at = $row->ends_at ? 0 : self::nextStart($row);
			$st2 = \Zittme\Framework\DB::getInstance()->query('SELECT COUNT(*), SUM(sold_qty) FROM commerce_timesale_item WHERE sale_srl = ?', [(int)$row->sale_srl]);
			$c = $st2 ? $st2->fetchAll(\PDO::FETCH_NUM)[0] : [0, 0];
			$row->item_count = (int)$c[0];
			$row->sold = (int)$c[1];
		}
		return $rows;
	}

	public static function get(int $sale_srl): ?object
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_timesale WHERE sale_srl = ?', [$sale_srl]);
		$rows = $st ? $st->fetchAll() : [];
		return $rows[0] ?? null;
	}

	public static function itemsOf(int $sale_srl): array
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_timesale_item WHERE sale_srl = ? ORDER BY list_order ASC, ts_item_srl ASC', [$sale_srl]);
		return $st ? $st->fetchAll() : [];
	}

	public static function save(int $sale_srl, array $in, array $items): int
	{
		$db = \Zittme\Framework\DB::getInstance();
		$row = [
			'title' => mb_substr(trim((string)($in['title'] ?? '')), 0, 120),
			'mode' => ($in['mode'] ?? 'once') === 'daily' ? 'daily' : 'once',
			'start_date' => (string)$in['start_date'],
			'end_date' => (string)$in['end_date'],
			'daily_start' => (string)($in['daily_start'] ?? '1200'),
			'daily_end' => (string)($in['daily_end'] ?? '1300'),
			'status' => ($in['status'] ?? 'Y') === 'N' ? 'N' : 'Y',
			'last_update' => date('YmdHis'),
		];
		if ($sale_srl > 0 && self::get($sale_srl))
		{
			$sets = implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($row)));
			$db->query('UPDATE commerce_timesale SET ' . $sets . ' WHERE sale_srl = ?', array_merge(array_values($row), [$sale_srl]));
		}
		else
		{
			$sale_srl = getNextSequence();
			$row['sale_srl'] = $sale_srl;
			$row['regdate'] = date('YmdHis');
			$cols = array_keys($row);
			$db->query('INSERT INTO commerce_timesale (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($row));
		}
		$old = [];
		foreach (self::itemsOf($sale_srl) as $o)
		{
			$old[(int)$o->item_srl] = $o;
		}
		$keep = [];
		foreach (array_values($items) as $i => $it)
		{
			$item_srl = (int)($it['item_srl'] ?? 0);
			if ($item_srl <= 0 || isset($keep[$item_srl]))
			{
				continue;
			}
			$keep[$item_srl] = true;
			$type = ($it['discount_type'] ?? 'percent') === 'price' ? 'price' : 'percent';
			$value = $type === 'percent' ? max(1, min(99, (float)($it['value'] ?? 0))) : max(0, (int)($it['value'] ?? 0));
			$vals = [$type, $value, max(0, (int)($it['qty_limit'] ?? 0)), max(0, (int)($it['per_member'] ?? 0)), $i];
			if (isset($old[$item_srl]))
			{
				$db->query('UPDATE commerce_timesale_item SET discount_type = ?, value = ?, qty_limit = ?, per_member = ?, list_order = ? WHERE ts_item_srl = ?', array_merge($vals, [(int)$old[$item_srl]->ts_item_srl]));
			}
			else
			{
				$db->query('INSERT INTO commerce_timesale_item (ts_item_srl, sale_srl, item_srl, discount_type, value, qty_limit, per_member, list_order, sold_qty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)', array_merge([getNextSequence(), $sale_srl, $item_srl], $vals));
			}
		}
		foreach ($old as $item_srl => $o)
		{
			if (!isset($keep[$item_srl]))
			{
				$db->query('DELETE FROM commerce_timesale_item WHERE ts_item_srl = ?', [(int)$o->ts_item_srl]);
			}
		}
		self::$map = null;
		return $sale_srl;
	}

	public static function delete(int $sale_srl): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		$db->query('DELETE FROM commerce_timesale_item WHERE sale_srl = ?', [$sale_srl]);
		$db->query('DELETE FROM commerce_timesale WHERE sale_srl = ?', [$sale_srl]);
		self::$map = null;
	}

	public static function openItemSrls(): array
	{
		$out = [];
		foreach (self::activeMap() as $srl => $row)
		{
			if ($row->ends_at && $row->left !== 0)
			{
				$out[] = $srl;
			}
		}
		return $out;
	}
}
