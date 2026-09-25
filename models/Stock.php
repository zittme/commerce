<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Stock
{
	public static function reserve(int $item_srl, int $option_srl, int $qty): bool
	{
		if ($qty <= 0)
		{
			return false;
		}

		$oDB = \Zittme\Framework\DB::getInstance();
		if ($option_srl > 0)
		{
			$stmt = $oDB->query(
				'UPDATE commerce_item_option SET stock = stock - ? ' .
				'WHERE option_srl = ? AND item_srl = ? AND status = ? AND stock >= ?',
				$qty, $option_srl, $item_srl, 'Y', $qty
			);
		}
		else
		{
			$stmt = $oDB->query(
				'UPDATE commerce_item SET stock = stock - ? ' .
				'WHERE item_srl = ? AND use_stock = ? AND stock >= ?',
				$qty, $item_srl, 'Y', $qty
			);
		}
		$won = $stmt !== null && $stmt->rowCount() === 1;
		if ($won)
		{
			self::checkLowStock($item_srl, $option_srl, self::currentStock($item_srl, $option_srl));
		}
		return $won;
	}

	public static function currentStock(int $item_srl, int $option_srl): int
	{
		$oDB = \Zittme\Framework\DB::getInstance();
		$stmt = $option_srl > 0
			? $oDB->query('SELECT stock FROM commerce_item_option WHERE option_srl = ?', $option_srl)
			: $oDB->query('SELECT stock FROM commerce_item WHERE item_srl = ?', $item_srl);
		$row = $stmt ? $stmt->fetchObject() : null;
		if ($stmt)
		{
			$stmt->closeCursor();
		}
		return $row ? (int)$row->stock : 0;
	}

	public static function release(int $item_srl, int $option_srl, int $qty): bool
	{
		if ($qty <= 0)
		{
			return false;
		}

		$oDB = \Zittme\Framework\DB::getInstance();
		if ($option_srl > 0)
		{
			$stmt = $oDB->query(
				'UPDATE commerce_item_option SET stock = stock + ? WHERE option_srl = ? AND item_srl = ?',
				$qty, $option_srl, $item_srl
			);
		}
		else
		{
			$stmt = $oDB->query(
				'UPDATE commerce_item SET stock = stock + ? WHERE item_srl = ? AND use_stock = ?',
				$qty, $item_srl, 'Y'
			);
		}
		$won = $stmt !== null && $stmt->rowCount() === 1;
		if ($won)
		{
			self::checkLowStock($item_srl, $option_srl, self::currentStock($item_srl, $option_srl));
		}
		return $won;
	}

	public static function isUnlimited(object $item): bool
	{
		return ($item->use_stock ?? 'Y') !== 'Y';
	}

	public static function adjust(int $item_srl, int $option_srl, string $type, int $qty, string $memo = '', int $member_srl = 0): object
	{
		$result = new \stdClass;
		$result->ok = false;
		$result->message = '';
		$result->stock_after = 0;

		if (!in_array($type, ['in', 'out', 'loss'], true) || $qty <= 0 || $item_srl <= 0)
		{
			$result->message = 'msg_invalid_request';
			return $result;
		}

		$oDB = \Zittme\Framework\DB::getInstance();
		if ($type === 'in')
		{
			if ($option_srl > 0)
			{
				$stmt = $oDB->query('UPDATE commerce_item_option SET stock = stock + ? WHERE option_srl = ? AND item_srl = ?', $qty, $option_srl, $item_srl);
			}
			else
			{
				$stmt = $oDB->query('UPDATE commerce_item SET stock = stock + ? WHERE item_srl = ?', $qty, $item_srl);
			}
		}
		else
		{
			if ($option_srl > 0)
			{
				$stmt = $oDB->query('UPDATE commerce_item_option SET stock = stock - ? WHERE option_srl = ? AND item_srl = ? AND stock >= ?', $qty, $option_srl, $item_srl, $qty);
			}
			else
			{
				$stmt = $oDB->query('UPDATE commerce_item SET stock = stock - ? WHERE item_srl = ? AND stock >= ?', $qty, $item_srl, $qty);
			}
		}

		if ($stmt === null || $stmt->rowCount() !== 1)
		{
			$result->message = 'msg_shop_stock_insufficient';
			return $result;
		}

		if ($option_srl > 0)
		{
			$row = $oDB->query('SELECT stock FROM commerce_item_option WHERE option_srl = ?', $option_srl);
		}
		else
		{
			$row = $oDB->query('SELECT stock FROM commerce_item WHERE item_srl = ?', $item_srl);
		}
		$fetched = $row ? $row->fetchObject() : null;
		// 커서를 닫아야 다음 쿼리(로그 INSERT)가 언버퍼드 충돌 없이 실행된다
		if ($row)
		{
			$row->closeCursor();
		}
		$result->stock_after = $fetched ? (int)$fetched->stock : 0;

		executeQuery('commerce.insertStockLog', (object)[
			'log_srl' => getNextSequence(),
			'item_srl' => $item_srl,
			'option_srl' => $option_srl,
			'type' => $type,
			'qty' => $qty,
			'stock_after' => $result->stock_after,
			'memo' => mb_substr($memo, 0, 250),
			'member_srl' => $member_srl,
			'regdate' => date('YmdHis'),
		]);

		$result->ok = true;
		self::checkLowStock($item_srl, $option_srl, $result->stock_after);
		return $result;
	}

	public static function checkLowStock(int $item_srl, int $option_srl, int $stock_after): void
	{
		$config = Base::config();
		if (($config->notify_low_stock ?? 'Y') !== 'Y')
		{
			return;
		}

		$item = Item::get($item_srl);
		if (!$item || ($item->use_stock ?? 'Y') !== 'Y')
		{
			return;
		}

		$row = $item;
		$label = Lang::text($item->item_name);
		if ($option_srl > 0)
		{
			$row = null;
			foreach (Item::getOptions($item_srl) as $opt)
			{
				if ((int)$opt->option_srl === $option_srl)
				{
					$row = $opt;
					$label .= ' - ' . Lang::text($opt->option_label);
					break;
				}
			}
			if (!$row)
			{
				return;
			}
		}

		$limit = (int)($row->low_stock ?? 0) ?: (int)($config->low_stock_default ?? 0);
		if ($limit <= 0)
		{
			return;
		}

		$alerted = (string)($row->low_stock_alerted ?? 'N') === 'Y';
		$low = $stock_after <= $limit;
		if ($low === $alerted)
		{
			return;
		}

		self::markAlerted($item_srl, $option_srl, $low);
		if (!$low)
		{
			return;
		}

		Deferred::call(self::class . '::lowStockTask', [
			'label' => $label,
			'stock' => $stock_after,
			'limit' => $limit,
			'url' => getNotEncodedFullUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock', 'f_low', 'Y'),
		]);
	}

	public static function lowStockTask(object $args): void
	{
		$label = (string)($args->label ?? '');
		$stock = (int)($args->stock ?? 0);
		$url = (string)($args->url ?? '');
		Notify::toAdmins(sprintf(lang('commerce.adm_low_stock_notify'), $label, number_format($stock)), $url);
		self::mailLowStock($label, $stock, (int)($args->limit ?? 0), $url);
	}

	protected static function markAlerted(int $item_srl, int $option_srl, bool $on): void
	{
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$table = $option_srl > 0 ? 'commerce_item_option' : 'commerce_item';
		$key = $option_srl > 0 ? 'option_srl' : 'item_srl';
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare(
			'UPDATE `' . $prefix . $table . '` SET low_stock_alerted = ? WHERE ' . $key . ' = ?'
		);
		if ($stmt)
		{
			$stmt->execute([$on ? 'Y' : 'N', $option_srl > 0 ? $option_srl : $item_srl]);
			$stmt->closeCursor();
		}
	}

	protected static function mailLowStock(string $label, int $stock, int $limit, string $url): void
	{
		$to = Order::adminRecipients();
		if (!count($to))
		{
			return;
		}

		$body = sprintf(lang('commerce.adm_low_stock_mail_body'), $label, number_format($stock), number_format($limit)) . "\n\n" . $url;
		foreach ($to as $address)
		{
			try
			{
				$mail = new \Zittme\Framework\Mail();
				$mail->addTo($address);
				$mail->setSubject(sprintf(lang('commerce.adm_low_stock_mail_subject'), $label));
				$mail->setBody(nl2br(escape($body)));
				$mail->send();
			}
			catch (\Throwable $e)
			{
			}
		}
	}

	public static function lowStockRows(int $limit = 100): array
	{
		$config = Base::config();
		$fallback = max(0, (int)($config->low_stock_default ?? 0));
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$handle = \Zittme\Framework\DB::getInstance()->getHandle();

		$sql = 'SELECT i.item_srl, 0 AS option_srl, i.item_name AS label, i.stock,'
			. ' CASE WHEN i.low_stock > 0 THEN i.low_stock ELSE ? END AS limit_qty'
			. ' FROM `' . $prefix . 'commerce_item` i'
			. " WHERE i.use_stock = 'Y' AND i.has_options = 'N' AND i.status IN ('sale', 'soldout')"
			. ' AND (CASE WHEN i.low_stock > 0 THEN i.low_stock ELSE ? END) > 0'
			. ' AND i.stock <= (CASE WHEN i.low_stock > 0 THEN i.low_stock ELSE ? END)'
			. ' UNION ALL '
			. ' SELECT i.item_srl, o.option_srl, CONCAT(i.item_name, \' - \', o.option_label) AS label, o.stock,'
			. ' CASE WHEN o.low_stock > 0 THEN o.low_stock ELSE ? END AS limit_qty'
			. ' FROM `' . $prefix . 'commerce_item_option` o'
			. ' JOIN `' . $prefix . 'commerce_item` i ON i.item_srl = o.item_srl'
			. " WHERE i.use_stock = 'Y' AND o.status = 'Y' AND i.status IN ('sale', 'soldout')"
			. ' AND (CASE WHEN o.low_stock > 0 THEN o.low_stock ELSE ? END) > 0'
			. ' AND o.stock <= (CASE WHEN o.low_stock > 0 THEN o.low_stock ELSE ? END)'
			. ' ORDER BY stock ASC, limit_qty DESC LIMIT ' . max(1, $limit);

		$stmt = $handle->prepare($sql);
		if (!$stmt || !$stmt->execute(array_fill(0, 6, $fallback)))
		{
			return [];
		}
		$rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
		$stmt->closeCursor();
		if (!$rows)
		{
			return [];
		}

		foreach ($rows as $row)
		{
			$row->label = Lang::text($row->label);
		}
		return $rows;
	}

	public static function lowStockCount(): int
	{
		return count(self::lowStockRows(500));
	}

	public static function getLogs(int $item_srl = 0, int $page = 1): object
	{
		$args = new \stdClass;
		if ($item_srl > 0)
		{
			$args->item_srl = $item_srl;
		}
		$args->page = max(1, $page);
		$args->list_count = 20;
		return executeQueryArray('commerce.getStockLogs', $args);
	}
}
