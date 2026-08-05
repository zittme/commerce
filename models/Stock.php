<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 재고 — 커머스 동시성의 심장.
 *
 * 차감·복구는 오직 조건부 UPDATE(affected rows 판정)로만 한다.
 *   PHP 에서 "조회 → 판단 → 저장"으로 나누면 동시 주문에서 재고가 음수로 뚫린다.
 *   (예약 모듈 Slot::occupy 와 같은 검증된 패턴)
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
	 * 재고 미사용 상품인가 (use_stock=N 이면 무제한 판매).
	 *
	 * @param object $item
	 * @return bool
	 */
	public static function isUnlimited(object $item): bool
	{
		return ($item->use_stock ?? 'Y') !== 'Y' && ($item->has_options ?? 'N') !== 'Y';
	}
}
