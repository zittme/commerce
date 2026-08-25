<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 구매 등급 — 누적 결제완료 금액 기준으로 자동 산정 (cafe24 식).
 *
 * 포인트와 무관한 커머스 자체 등급이다.
 * 결제완료/취소 시 recalc() 가 호출되어 등급이 자동으로 오르내리고,
 * 등급이 오르면 등급 쿠폰을 1회 자동 발급한다.
 * 등급별 적립률(credit_rate)이 있으면 기본 적립률 대신 적용된다.
 *
 * 등급에 코어 회원그룹(group_srl)을 걸어 두면 그 등급인 회원이 그 그룹에 들어간다.
 * 등급 전용 게시판이나 상품처럼 접근을 막는 일은 그 그룹으로 한다.
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
		$rows = array_values(array_filter($data, function($row) { return !empty($row->grade_srl); }));
		// 다국어 문구를 연결한 이름은 미리 바꿔 둔다 (원본은 title_raw)
		return Lang::textAll($rows, ['title']);
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
		// 별칭(mg, g)을 쓰는 조인은 프레임워크의 자동 프리픽스 재작성과 충돌하므로
		// PDO 핸들로 직접 실행한다 (Install::isGradeRateInt 와 같은 이유).
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare(
			'SELECT mg.grade_srl, mg.total_spend, g.title, g.credit_rate, g.min_spend, g.discount_type, g.discount_value'
			. ' FROM `' . $prefix . 'commerce_member_grade` AS mg'
			. ' LEFT JOIN `' . $prefix . 'commerce_grade` AS g ON g.grade_srl = mg.grade_srl'
			. ' WHERE mg.member_srl = ?'
		);
		$row = null;
		if ($stmt && $stmt->execute([$member_srl]))
		{
			$row = $stmt->fetchObject() ?: null;
			$stmt->closeCursor();
		}
		if ($row && !empty($row->grade_srl) && !empty($row->title))
		{
			$row->title_raw = (string)$row->title;
			$row->title = Lang::text((string)$row->title);
			return $row;
		}
		return self::getByGroups($member_srl);
	}

	/**
	 * 회원이 속한 코어 그룹에 연동된 등급. 여러 개면 기준 구매액이 가장 높은 등급.
	 *
	 * @param int $member_srl
	 * @return ?object
	 */
	public static function getByGroups(int $member_srl): ?object
	{
		$groups = \MemberModel::getMemberGroups($member_srl);
		if (!is_array($groups) || !count($groups))
		{
			return null;
		}
		$found = null;
		foreach (self::getList() as $grade)
		{
			$group_srl = (int)($grade->group_srl ?? 0);
			if ($group_srl > 0 && isset($groups[$group_srl]))
			{
				if (!$found || (int)$grade->min_spend >= (int)$found->min_spend)
				{
					$found = $grade;
				}
			}
		}
		if (!$found)
		{
			return null;
		}
		$row = clone $found;
		$row->total_spend = 0;
		return $row;
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

		// 누적 구매액은 기준 통화 기준이다. 외화 병행 판매 주문은 결제 시점에 박제한 환율로 환산해 더한다.
		$db = \Zittme\Framework\DB::getInstance();
		$stmt = $db->query(
			'SELECT payment_price, currency, exchange_rate FROM commerce_order WHERE member_srl = ? AND status = ?',
			$member_srl, Base::ORDER_PAID
		);
		// 코어는 버퍼링 없는 쿼리를 쓴다. 커서를 연 채 환산(설정 조회)을 호출하면 죽으므로 먼저 다 읽는다
		$rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_OBJ) ?: []) : [];
		$total = 0;
		foreach ($rows as $row)
		{
			$row_currency = strtoupper((string)($row->currency ?? '')) ?: Money::base();
			if ($row_currency === Money::base())
			{
				$total += (int)$row->payment_price;
			}
			else
			{
				$total += Money::minorToBase((int)$row->payment_price, $row_currency, (float)($row->exchange_rate ?? 0));
			}
		}

		// 가장 높은 구간 (목록은 min_spend 오름차순)
		$new_grade = null;
		foreach ($grades as $g)
		{
			if ($total >= (int)$g->min_spend)
			{
				$new_grade = $g;
			}
		}

		$by_group = self::getByGroups($member_srl);
		if ($by_group && (!$new_grade || (int)$by_group->min_spend > (int)$new_grade->min_spend))
		{
			$new_grade = $by_group;
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

		$was_srl = $current ? (int)$current->grade_srl : 0;

		if ($new_srl !== $was_srl)
		{
			self::syncGroup($member_srl, $was_srl, $new_srl);
		}

		// 등급 상승 시 등급 쿠폰 자동 발급 (해당 쿠폰을 받은 적 없을 때만)
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
	 * 등급에 걸린 회원그룹 번호. 연동이 없으면 0.
	 *
	 * @param int $grade_srl
	 * @return int
	 */
	public static function groupOf(int $grade_srl): int
	{
		if ($grade_srl <= 0)
		{
			return 0;
		}
		foreach (self::getList() as $grade)
		{
			if ((int)$grade->grade_srl === $grade_srl)
			{
				return (int)($grade->group_srl ?? 0);
			}
		}
		return 0;
	}

	/**
	 * 등급이 바뀐 회원을 예전 등급의 그룹에서 빼고 새 등급의 그룹에 넣는다.
	 *
	 * 커머스가 손대는 것은 등급에 걸린 그룹뿐이다. 관리자가 직접 넣은 회원까지
	 * 건드리지 않도록, 등급 전용 그룹을 따로 두라고 설정 화면에 적어 두었다.
	 *
	 * @param int $member_srl
	 * @param int $was_srl 예전 등급 번호 (0 = 없음)
	 * @param int $new_srl 새 등급 번호 (0 = 없음)
	 * @return void
	 */
	public static function syncGroup(int $member_srl, int $was_srl, int $new_srl): void
	{
		if ($member_srl <= 0)
		{
			return;
		}

		$was_group = self::groupOf($was_srl);
		$new_group = self::groupOf($new_srl);

		if ($was_group === $new_group)
		{
			return;
		}

		if ($was_group > 0)
		{
			\MemberController::removeMemberFromGroup($member_srl, $was_group);
		}
		if ($new_group > 0)
		{
			\MemberController::addMemberToGroup($member_srl, $new_group);
		}
	}

	/**
	 * 어느 등급에 속한 회원 번호 목록.
	 *
	 * @param int $grade_srl
	 * @return array
	 */
	public static function membersOf(int $grade_srl): array
	{
		if ($grade_srl <= 0)
		{
			return [];
		}
		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'SELECT member_srl FROM commerce_member_grade WHERE grade_srl = ?',
			$grade_srl
		);
		$rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
		return array_map('intval', $rows);
	}

	/**
	 * 연동을 새로 걸거나 바꿨을 때 그 등급 회원들을 한 번에 반영한다.
	 *
	 * @param int $grade_srl
	 * @param int $old_group 바뀌기 전 그룹 (0 = 없음)
	 * @param int $new_group 바뀐 뒤 그룹 (0 = 없음)
	 * @return int 손댄 회원 수
	 */
	public static function applyGroupToMembers(int $grade_srl, int $old_group, int $new_group): int
	{
		if ($old_group === $new_group)
		{
			return 0;
		}

		$members = self::membersOf($grade_srl);
		foreach ($members as $member_srl)
		{
			if ($old_group > 0)
			{
				\MemberController::removeMemberFromGroup($member_srl, $old_group);
			}
			if ($new_group > 0)
			{
				\MemberController::addMemberToGroup($member_srl, $new_group);
			}
		}
		return count($members);
	}

	/**
	 * 이미 다른 등급이 쓰고 있는 그룹인지. 빼고 넣는 것이 엇갈리므로 한 그룹은 한 등급에만 건다.
	 *
	 * @param int $group_srl
	 * @param int $except_grade_srl 지금 저장 중인 등급 (자기 자신은 제외)
	 * @return bool
	 */
	public static function groupTaken(int $group_srl, int $except_grade_srl = 0): bool
	{
		if ($group_srl <= 0)
		{
			return false;
		}
		foreach (self::getList() as $grade)
		{
			if ((int)$grade->grade_srl === $except_grade_srl)
			{
				continue;
			}
			if ((int)($grade->group_srl ?? 0) === $group_srl)
			{
				return true;
			}
		}
		return false;
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
	 * 다른 통화의 단가에 등급 할인 적용. 정액 할인은 기준 통화로 정해 두므로
	 * 그 통화로 환산해 뺀다. 정률은 통화와 무관하다.
	 *
	 * @param int $price 그 통화의 최소단위 정수
	 * @param ?object $discount
	 * @param string $currency
	 * @return int
	 */
	public static function applyDiscountIn(int $price, ?object $discount, string $currency): int
	{
		if (!$discount || $price <= 0)
		{
			return max(0, $price);
		}
		if ($discount->type !== 'amount')
		{
			return self::applyDiscount($price, $discount);
		}
		$off = Money::convertMinor((int)$discount->value, $currency);
		return $off < 0 ? $price : max(0, $price - $off);
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
