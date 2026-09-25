<?php

namespace Zittme\Modules\Commerce\Models;

class Pin
{
	public const STOCK = 'stock';
	public const ASSIGNED = 'assigned';
	public const VOID = 'void';

	protected static function key(): string
	{
		$key = (string)(\Zittme\Framework\Config::get('crypto.encryption_key') ?? '');
		return $key !== '' ? $key : (string)\Zittme\Framework\Config::get('crypto.authentication_key');
	}

	public static function normalize(string $pin): string
	{
		return strtoupper(preg_replace('/[\s\-]+/', '', trim($pin)));
	}

	public static function hash(string $pin): string
	{
		return hash_hmac('sha256', self::normalize($pin), self::key());
	}

	protected static function encrypt(string $pin): string
	{
		return (string)\Zittme\Framework\Security::encrypt(trim($pin));
	}

	protected static function decrypt(string $enc): string
	{
		$out = \Zittme\Framework\Security::decrypt($enc);
		return $out === false || $out === null ? '' : (string)$out;
	}

	public static function mask(string $tail): string
	{
		return '****-****-' . $tail;
	}

	public static function isPinItem(?object $item): bool
	{
		return $item && ($item->is_pin ?? 'N') === 'Y';
	}

	public static function counts(): array
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT item_srl, status, COUNT(*) AS n, SUM(CASE WHEN revealed_date IS NOT NULL AND revealed_date <> \'\' THEN 1 ELSE 0 END) AS r FROM commerce_pin GROUP BY item_srl, status');
		$out = [];
		foreach (($st ? $st->fetchAll() : []) as $row)
		{
			$srl = (int)$row->item_srl;
			$out[$srl] = $out[$srl] ?? ['stock' => 0, 'assigned' => 0, 'revealed' => 0, 'void' => 0];
			$out[$srl][$row->status] = (int)$row->n;
			if ($row->status === self::ASSIGNED)
			{
				$out[$srl]['revealed'] = (int)$row->r;
			}
		}
		return $out;
	}

	public static function add(int $item_srl, string $text, string $memo = ''): array
	{
		$db = \Zittme\Framework\DB::getInstance();
		$added = $dup = $bad = 0;
		$seen = [];
		$now = date('YmdHis');
		foreach (preg_split('/\r\n|\r|\n/', $text) as $line)
		{
			$line = trim($line);
			if ($line === '' || preg_match('/^(pin|핀|번호)\b/i', $line))
			{
				continue;
			}
			$parts = array_map('trim', preg_split('/[,\t;]/', $line));
			$pin = $parts[0] ?? '';
			$norm = self::normalize($pin);
			if (strlen($norm) < 6 || strlen($norm) > 64 || !preg_match('/^[A-Z0-9]+$/', $norm))
			{
				$bad++;
				continue;
			}
			$hash = self::hash($pin);
			if (isset($seen[$hash]))
			{
				$dup++;
				continue;
			}
			$seen[$hash] = true;
			$st = $db->query('SELECT COUNT(*) FROM commerce_pin WHERE pin_hash = ?', [$hash]);
			if ($st && (int)($st->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0) > 0)
			{
				$dup++;
				continue;
			}
			$expire = preg_replace('/\D/', '', (string)($parts[1] ?? ''));
			$expire = strlen($expire) >= 8 ? substr($expire, 0, 8) : '';
			$db->query('INSERT INTO commerce_pin (pin_srl, item_srl, pin_enc, pin_hash, pin_tail, expire_date, status, order_srl, order_item_srl, memo, regdate) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)',
				[getNextSequence(), $item_srl, self::encrypt($pin), $hash, substr($norm, -4), $expire, self::STOCK, mb_substr($memo, 0, 120), $now]);
			$added++;
		}
		if ($added)
		{
			$db->query('UPDATE commerce_item SET stock = stock + ? WHERE item_srl = ?', [$added, $item_srl]);
			Item::syncSoldout($item_srl);
		}
		return ['added' => $added, 'dup' => $dup, 'bad' => $bad];
	}

	public static function voidStock(array $pin_srls): int
	{
		$db = \Zittme\Framework\DB::getInstance();
		$n = 0;
		foreach (array_unique(array_map('intval', $pin_srls)) as $srl)
		{
			$st = $db->query('SELECT item_srl FROM commerce_pin WHERE pin_srl = ? AND status = ?', [$srl, self::STOCK]);
			$rows = $st ? $st->fetchAll() : [];
			if (!count($rows))
			{
				continue;
			}
			$up = $db->query('UPDATE commerce_pin SET status = ? WHERE pin_srl = ? AND status = ?', [self::VOID, $srl, self::STOCK]);
			if ($up && $up->rowCount() > 0)
			{
				$db->query('UPDATE commerce_item SET stock = GREATEST(0, stock - 1) WHERE item_srl = ?', [(int)$rows[0]->item_srl]);
				Item::syncSoldout((int)$rows[0]->item_srl);
				$n++;
			}
		}
		return $n;
	}

	public static function getList(int $item_srl, string $status, string $q, int $page, int $per = 50): array
	{
		$where = ['1 = 1'];
		$params = [];
		if ($item_srl > 0) { $where[] = 'commerce_pin.item_srl = ?'; $params[] = $item_srl; }
		if (in_array($status, [self::STOCK, self::ASSIGNED, self::VOID], true)) { $where[] = 'commerce_pin.status = ?'; $params[] = $status; }
		if ($status === 'revealed') { $where[] = "commerce_pin.revealed_date <> ''"; }
		if ($q !== '')
		{
			$where[] = '(commerce_order.order_code LIKE ? OR commerce_pin.pin_tail = ?)';
			$params[] = '%' . $q . '%';
			$params[] = strtoupper(substr(self::normalize($q), -4));
		}
		$db = \Zittme\Framework\DB::getInstance();
		$from = ' FROM commerce_pin LEFT JOIN commerce_order ON commerce_order.order_srl = commerce_pin.order_srl WHERE ' . implode(' AND ', $where);
		$st = $db->query('SELECT COUNT(*)' . $from, $params);
		$total = $st ? (int)($st->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0) : 0;
		$st = $db->query('SELECT commerce_pin.pin_srl, commerce_pin.item_srl, commerce_pin.pin_tail, commerce_pin.expire_date, commerce_pin.status, commerce_pin.order_srl, commerce_pin.assigned_date, commerce_pin.revealed_date, commerce_pin.memo, commerce_pin.regdate, commerce_order.order_code, commerce_order.orderer_name' . $from . ' ORDER BY commerce_pin.pin_srl DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per), $params);
		return ['rows' => $st ? $st->fetchAll() : [], 'total' => $total, 'pages' => max(1, (int)ceil($total / $per))];
	}

	public static function assignForOrder(int $order_srl): array
	{
		$db = \Zittme\Framework\DB::getInstance();
		$assigned = $short = 0;
		$pin_lines = $all_lines = 0;
		foreach (Order::getItems($order_srl) as $oi)
		{
			$all_lines++;
			$item = Item::get((int)$oi->item_srl);
			if (!self::isPinItem($item))
			{
				continue;
			}
			$pin_lines++;
			$st = $db->query('SELECT COUNT(*) FROM commerce_pin WHERE order_item_srl = ?', [(int)$oi->order_item_srl]);
			$have = $st ? (int)($st->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0) : 0;
			$need = (int)$oi->qty - $have;
			for ($i = 0; $i < $need; $i++)
			{
				$ok = false;
				for ($try = 0; $try < 5 && !$ok; $try++)
				{
					$st = $db->query("SELECT pin_srl FROM commerce_pin WHERE item_srl = ? AND status = ? AND (expire_date = '' OR expire_date IS NULL OR expire_date >= ?) ORDER BY expire_date = '', expire_date ASC, pin_srl ASC LIMIT 1", [(int)$oi->item_srl, self::STOCK, date('Ymd')]);
					$rows = $st ? $st->fetchAll() : [];
					if (!count($rows))
					{
						break;
					}
					$up = $db->query('UPDATE commerce_pin SET status = ?, order_srl = ?, order_item_srl = ?, assigned_date = ? WHERE pin_srl = ? AND status = ?',
						[self::ASSIGNED, $order_srl, (int)$oi->order_item_srl, date('YmdHis'), (int)$rows[0]->pin_srl, self::STOCK]);
					$ok = $up && $up->rowCount() > 0;
				}
				$ok ? $assigned++ : $short++;
			}
		}
		if ($short > 0)
		{
			Order::log($order_srl, 0, 'memo', '', '', 0, sprintf(lang('commerce.pin_log_short'), $short));
			self::mailShort($order_srl, $short);
		}
		elseif ($assigned > 0)
		{
			Order::log($order_srl, 0, 'memo', '', '', 0, sprintf(lang('commerce.pin_log_assigned'), $assigned));
		}
		return ['assigned' => $assigned, 'short' => $short, 'pin_only' => $pin_lines > 0 && $pin_lines === $all_lines];
	}

	public static function releaseForOrder(int $order_srl): void
	{
		try
		{
			$db = \Zittme\Framework\DB::getInstance();
			$st = $db->query('SELECT pin_srl, item_srl, revealed_date FROM commerce_pin WHERE order_srl = ? AND status = ?', [$order_srl, self::ASSIGNED]);
			foreach (($st ? $st->fetchAll() : []) as $p)
			{
				if ((string)$p->revealed_date !== '')
				{
					$db->query('UPDATE commerce_pin SET status = ? WHERE pin_srl = ?', [self::VOID, (int)$p->pin_srl]);
					$db->query('UPDATE commerce_item SET stock = GREATEST(0, stock - 1) WHERE item_srl = ?', [(int)$p->item_srl]);
				}
				else
				{
					$db->query("UPDATE commerce_pin SET status = ?, order_srl = 0, order_item_srl = 0, assigned_date = '' WHERE pin_srl = ?", [self::STOCK, (int)$p->pin_srl]);
				}
			}
		}
		catch (\Throwable $e) {}
	}

	public static function revealedCount(int $order_srl): int
	{
		$st = \Zittme\Framework\DB::getInstance()->query("SELECT COUNT(*) FROM commerce_pin WHERE order_srl = ? AND revealed_date <> ''", [$order_srl]);
		return $st ? (int)($st->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0) : 0;
	}

	public static function countFor(int $order_item_srl): int
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT COUNT(*) FROM commerce_pin WHERE order_item_srl = ? AND status = ?', [$order_item_srl, self::ASSIGNED]);
		return $st ? (int)($st->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0) : 0;
	}

	public static function reveal(int $order_item_srl, int $order_srl): array
	{
		$db = \Zittme\Framework\DB::getInstance();
		$st = $db->query('SELECT pin_srl, pin_enc, expire_date, revealed_date FROM commerce_pin WHERE order_item_srl = ? AND order_srl = ? AND status = ? ORDER BY pin_srl ASC', [$order_item_srl, $order_srl, self::ASSIGNED]);
		$out = [];
		$first = false;
		foreach (($st ? $st->fetchAll() : []) as $p)
		{
			if ((string)$p->revealed_date === '')
			{
				$db->query('UPDATE commerce_pin SET revealed_date = ? WHERE pin_srl = ?', [date('YmdHis'), (int)$p->pin_srl]);
				$first = true;
			}
			$out[] = ['pin' => self::decrypt((string)$p->pin_enc), 'expire' => (string)$p->expire_date];
		}
		if ($first)
		{
			Order::log($order_srl, 0, 'memo', '', '', (int)(\Context::get('logged_info')->member_srl ?? 0), sprintf(lang('commerce.pin_log_revealed'), count($out), (string)\RX_CLIENT_IP));
		}
		return $out;
	}

	public static function boughtToday(int $item_srl, int $member_srl): int
	{
		$st = \Zittme\Framework\DB::getInstance()->query("SELECT SUM(commerce_order_item.qty) FROM commerce_order_item JOIN commerce_order ON commerce_order.order_srl = commerce_order_item.order_srl WHERE commerce_order_item.item_srl = ? AND commerce_order.member_srl = ? AND commerce_order.status IN ('pending', 'paid') AND commerce_order.regdate >= ?", [$item_srl, $member_srl, date('Ymd') . '000000']);
		return $st ? (int)($st->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0) : 0;
	}

	protected static function mailShort(int $order_srl, int $short): void
	{
		try
		{
			$order = Order::get($order_srl);
			$to = Order::adminRecipients();
			if (!$order || !count($to))
			{
				return;
			}
			$site = \Context::getSiteTitle() ?: 'Zittme';
			$subject = sprintf(lang('commerce.pin_mail_short_subject'), $site, $order->order_code);
			$body = '<p style="font-family:sans-serif;font-size:14px">' . escape(sprintf(lang('commerce.pin_mail_short_body'), $order->order_code, $short)) . '</p>';
			foreach ($to as $addr)
			{
				$mail = new \Zittme\Framework\Mail();
				$mail->addTo($addr);
				$mail->setSubject($subject);
				$mail->setBody($body, 'text/html');
				$mail->send();
			}
		}
		catch (\Throwable $e) {}
	}
}
