<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 구매 등급 — 누적 결제완료 금액 기준으로 자동 산정 (cafe24 식).
 *
 * 코어 회원그룹·포인트와 무관한 커머스 자체 등급이다.
 * 결제완료/취소 시 recalc() 가 호출되어 등급이 자동으로 오르내리고,
 * 등급이 오르면 등급 쿠폰을 1회 자동 발급한다.
 * 등급별 적립률(credit_rate)이 있으면 기본 적립률 대신 적용된다.
 */
class Grade
{
	/**
	 * 등급 목록 (기준액 낮은 순).
	 *
	 * @return array
	 */
	public static function getList(): array
	{
		$output = executeQuery('commerce.getGradeList', new \stdClass);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) { return !empty($row->grade_srl); }));
	}

	/**
	 * 회원의 현재 등급 (없으면 null).
	 *
	 * @param int $member_srl
	 * @return ?object {grade_srl, title, credit_rate, total_spend}
	 */
	public static function getForMember(int $member_srl): ?object
	{
		if ($member_srl <= 0)
		{
			return null;
		}
		$stmt = \Rhymix\Framework\DB::getInstance()->query(
			'SELECT mg.grade_srl, mg.total_spend, g.title, g.credit_rate, g.min_spend, g.discount_type, g.discount_value'
			. ' FROM commerce_member_grade mg LEFT JOIN commerce_grade g ON g.grade_srl = mg.grade_srl'
			. ' WHERE mg.member_srl = ?',
			$member_srl
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		return ($row && !empty($row->grade_srl) && !empty($row->title)) ? $row : null;
	}

	/**
	 * 등급 재계산 — 결제완료 주문 합계로 가장 높은 구간을 배정.
	 *
	 * 등급 상승 시 등급 쿠폰을 1회 발급한다 (이미 받은 적 있으면 생략).
	 *
	 * @param int $member_srl
	 * @return void
	 */
	public static function recalc(int $member_srl): void
	{
		if ($member_srl <= 0)
		{
			return;
		}
		$grades = self::getList();
		if (!count($grades))
		{
			return;
		}

		$db = \Rhymix\Framework\DB::getInstance();
		$stmt = $db->query(
			'SELECT COALESCE(SUM(payment_price), 0) AS s FROM commerce_order WHERE member_srl = ? AND status = ?',
			$member_srl, Base::ORDER_PAID
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		$total = $row ? (int)$row->s : 0;

		// 가장 높은 구간 (목록은 min_spend 오름차순)
		$new_grade = null;
		foreach ($grades as $g)
		{
			if ($total >= (int)$g->min_spend)
			{
				$new_grade = $g;
			}
		}

		$current = self::getForMember($member_srl);
		$new_srl = $new_grade ? (int)$new_grade->grade_srl : 0;

		// upsert
		try
		{
			$db->query(
				'INSERT INTO commerce_member_grade (member_srl, grade_srl, total_spend, upddate) VALUES (?, ?, ?, ?)',
				$member_srl, $new_srl, $total, Base::now()
			);
		}
		catch (\Exception $e)
		{
			$db->query(
				'UPDATE commerce_member_grade SET grade_srl = ?, total_spend = ?, upddate = ? WHERE member_srl = ?',
				$new_srl, $total, Base::now(), $member_srl
			);
		}

		// 등급 상승 시 등급 쿠폰 자동 발급 (해당 쿠폰을 받은 적 없을 때만)
		$was_srl = $current ? (int)$current->grade_srl : 0;
		if ($new_grade && $new_srl !== $was_srl && (int)$new_grade->coupon_srl > 0)
		{
			$cnt = executeQuery('commerce.countCouponUses', (object)[
				'coupon_srl' => (int)$new_grade->coupon_srl,
				'member_srl' => $member_srl,
			]);
			if (!$cnt->toBool() || (int)($cnt->data->count ?? 0) === 0)
			{
				Coupon::issueTo((int)$new_grade->coupon_srl, $member_srl);
			}
		}
	}

	/**
	 * 등급별 상품 할인 사양. 없으면 null.
	 *
	 * @param int $member_srl
	 * @return ?object {type: 'amount'|'percent', value: float}
	 */
	public static function discountFor(int $member_srl): ?object
	{
		$grade = self::getForMember($member_srl);
		if (!$grade)
		{
			return null;
		}
		$type = (string)($grade->discount_type ?? '');
		$value = (float)($grade->discount_value ?? 0);
		if (!in_array($type, ['amount', 'percent'], true) || $value <= 0)
		{
			return null;
		}
		return (object)['type' => $type, 'value' => $value];
	}

	/**
	 * 단가에 등급 할인 적용. 정액은 원 단위 차감, 정률은 % 차감(원 미만 절사).
	 * 0원 아래로 내려가지 않는다.
	 *
	 * @param int $price
	 * @param ?object $discount discountFor() 결과 (null = 할인 없음)
	 * @return int
	 */
	public static function applyDiscount(int $price, ?object $discount): int
	{
		if (!$discount || $price <= 0)
		{
			return max(0, $price);
		}
		if ($discount->type === 'amount')
		{
			return max(0, $price - (int)$discount->value);
		}
		return max(0, $price - (int)floor($price * $discount->value / 100));
	}

	/**
	 * 회원에게 적용되는 적립률 % — 등급 적립률 > 기본 설정.
	 *
	 * @param int $member_srl
	 * @return int
	 */
	public static function creditRateFor(int $member_srl): float
	{
		$grade = self::getForMember($member_srl);
		if ($grade && (float)$grade->credit_rate > 0)
		{
			return round((float)$grade->credit_rate, 2);
		}
		return max(0, round((float)(Base::config()->credit_rate ?? 0), 2));
	}
}
