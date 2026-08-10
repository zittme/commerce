<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 재고 처리. 동시 주문에서도 수량이 어긋나지 않게 한다.
 *
 * 차감·복구는 오직 조건부 UPDATE(affected rows 판정)로만 한다.
 *   PHP 에서 "조회 → 판단 → 저장"으로 나누면 동시 주문에서 재고가 음수로 뚫린다.
 */
class Stock
{
	/**
	 * 재고 차감 (원자적).
	 *
	 * @param int $item_srl
	 * @param int $option_srl 0 이면 상품 재고, 아니면 옵션 재고
	 * @param int $qty
	 * @return bool 차감 성공 여부 (false = 재고 부족)
	 */
	public static function reserve(int $item_srl, int $option_srl, int $qty): bool
	{
		if ($qty <= 0)
		{
			return false;
		}

		$oDB = \Rhymix\Framework\DB::getInstance();
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
		return $stmt !== null && $stmt->rowCount() === 1;
	}

	/**
	 * 재고 복구 (원자적). 취소·만료·반품 승인 시.
	 *
	 * @param int $item_srl
	 * @param int $option_srl
	 * @param int $qty
	 * @return bool
	 */
	public static function release(int $item_srl, int $option_srl, int $qty): bool
	{
		if ($qty <= 0)
		{
			return false;
		}

		$oDB = \Rhymix\Framework\DB::getInstance();
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
		return $stmt !== null && $stmt->rowCount() === 1;
	}

	/**
	 * 재고 미사용 상품인가 (use_stock=N 이면 옵션 포함 무제한 판매).
	 *
	 * @param object $item
	 * @return bool
	 */
	public static function isUnlimited(object $item): bool
	{
		return ($item->use_stock ?? 'Y') !== 'Y';
	}

	/**
	 * 재고 조정 (재고 관리 화면 전용): 입고(in) / 출고(out) / 손실(loss).
	 *
	 * 출고·손실은 재고보다 많이 뺄 수 없다. 성공하면 이동 로그를 남긴다.
	 *
	 * @param int $item_srl
	 * @param int $option_srl 0 이면 본품
	 * @param string $type in|out|loss
	 * @param int $qty
	 * @param string $memo
	 * @param int $member_srl 처리자
	 * @return object {ok, message, stock_after}
	 */
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

		$oDB = \Rhymix\Framework\DB::getInstance();
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
			// 출고·손실 — 재고 밑으로 뚫리지 않게 조건부 UPDATE
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

		// 조정 후 재고 스냅샷
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
		return $result;
	}

	/**
	 * 재고 이동 로그.
	 *
	 * @param int $item_srl 0 이면 전체
	 * @param int $page
	 * @return object
	 */
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
