<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Grade
{
	public static function getList(): array
	{
		$output = executeQuery('commerce.getGradeList', new \stdClass);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		$rows = array_values(array_filter($data, function($row) { return !empty($row->grade_srl); }));
		return Lang::textAll($rows, ['title']);
	}

	public static function getForMember(int $member_srl): ?object
	{
		if ($member_srl <= 0)
		{
			return null;
		}
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
