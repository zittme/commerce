<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 쿠폰 — 정의(commerce_coupon)와 회원별 발급·사용(commerce_coupon_issue)을 분리한다.
 *
 * 사용은 issue 행의 order_srl = 0 → 주문번호 조건부 UPDATE 로만 일어난다(원자, 중복 사용 방지).
 * 코드 쿠폰의 전체 한도는 used_count 를 컬럼 간 비교 raw SQL 로 원자 차감한다.
 * 할인은 상품 금액에만 적용한다(배송비 제외).
 */
class Coupon
{
	/**
	 * 쿠폰 1건.
	 *
	 * @param int $coupon_srl
	 * @return ?object
	 */
	public static function get(int $coupon_srl): ?object
	{
		$output = executeQuery('commerce.getCoupon', (object)['coupon_srl' => $coupon_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->coupon_srl)) ? $output->data : null;
	}

	/**
	 * 코드로 1건.
	 *
	 * @param string $code
	 * @return ?object
	 */
	public static function getByCode(string $code): ?object
	{
		if ($code === '')
		{
			return null;
		}
		$output = executeQuery('commerce.getCouponByCode', (object)['code' => $code]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->coupon_srl)) ? $output->data : null;
	}

	/**
	 * 전체 목록 (관리자).
	 *
	 * @return array
	 */
	public static function getList(): array
	{
		$output = executeQuery('commerce.getCouponList', new \stdClass);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		$rows = array_values(array_filter($data, function($row) { return !empty($row->coupon_srl); }));
		// 다국어 문구를 연결한 이름은 미리 바꿔 둔다 (원본은 title_raw)
		return Lang::textAll($rows, ['title']);
	}

	/**
	 * 지금 사용 가능한 상태인가 (활성 + 기간).
	 *
	 * @param object $coupon
	 * @return bool
	 */
	public static function isUsableNow(object $coupon): bool
	{
		if (($coupon->status ?? 'Y') !== 'Y')
		{
			return false;
		}
		$now = Base::now();
		if (!empty($coupon->use_start) && $now < $coupon->use_start)
		{
			return false;
		}
		if (!empty($coupon->use_end) && $now > $coupon->use_end)
		{
			return false;
		}
		return true;
	}

	/**
	 * 할인액 계산. 조건 미달이면 null.
	 *
	 * @param object $coupon
	 * @param int $item_total 상품 금액 합계
	 * @return ?int
	 */
	public static function discountFor(object $coupon, int $item_total): ?int
	{
		if ($item_total <= 0 || $item_total < (int)($coupon->min_order ?? 0))
		{
			return null;
		}

		if (($coupon->discount_type ?? 'fixed') === 'percent')
		{
			$discount = (int)floor($item_total * (int)$coupon->discount_value / 100);
			$cap = (int)($coupon->max_discount ?? 0);
			if ($cap > 0)
			{
				$discount = min($discount, $cap);
			}
		}
		else
		{
			$discount = (int)$coupon->discount_value;
		}

		$discount = min($discount, $item_total);
		return $discount > 0 ? $discount : null;
	}

	/**
	 * 회원에게 발급 (관리자 발급 / 코드 등록).
	 *
	 * @param int $coupon_srl
	 * @param int $member_srl
	 * @param int $order_srl 즉시 사용이면 주문번호
	 * @return int issue_srl (실패 시 0)
	 */
	public static function issueTo(int $coupon_srl, int $member_srl, int $order_srl = 0): int
	{
		$issue_srl = getNextSequence();
		$output = executeQuery('commerce.insertCouponIssue', (object)[
			'issue_srl' => $issue_srl,
			'coupon_srl' => $coupon_srl,
			'member_srl' => $member_srl,
			'order_srl' => $order_srl,
			'regdate' => Base::now(),
			'used_date' => $order_srl > 0 ? Base::now() : '',
		]);
		return $output->toBool() ? $issue_srl : 0;
	}

	/**
	 * 회원의 미사용 쿠폰 목록 — 지금 주문에 적용 가능한 것만, 예상 할인액 포함.
	 *
	 * @param int $member_srl
	 * @param int $item_total
	 * @return array [{issue_srl, coupon, discount}]
	 */
	public static function listUsableForMember(int $member_srl, int $item_total): array
	{
		if ($member_srl <= 0)
		{
			return [];
		}
		$output = executeQuery('commerce.getMyCouponIssues', (object)['member_srl' => $member_srl, 'order_srl' => 0]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}

		$result = [];
		$coupons = [];
		foreach (is_array($output->data) ? $output->data : [$output->data] as $issue)
		{
			if (empty($issue->issue_srl))
			{
				continue;
			}
			$srl = (int)$issue->coupon_srl;
			if (!array_key_exists($srl, $coupons))
			{
				$coupons[$srl] = self::get($srl);
			}
			$coupon = $coupons[$srl];
			if (!$coupon || !self::isUsableNow($coupon))
			{
				continue;
			}
			$discount = self::discountFor($coupon, $item_total);
			if ($discount === null)
			{
				continue;
			}
			$result[] = (object)['issue_srl' => (int)$issue->issue_srl, 'coupon' => $coupon, 'discount' => $discount];
		}
		return $result;
	}

	/**
	 * 발급 쿠폰을 주문에 사용 (원자 점유).
	 *
	 * @param int $issue_srl
	 * @param int $member_srl
	 * @param int $order_srl
	 * @return bool 점유에 이겼는가
	 */
	public static function claimIssue(int $issue_srl, int $member_srl, int $order_srl): bool
	{
		$output = executeQuery('commerce.useCouponIssueIf', (object)[
			'issue_srl' => $issue_srl,
			'member_srl' => $member_srl,
			'from_order_srl' => 0,
			'order_srl' => $order_srl,
			'used_date' => Base::now(),
		]);
		if (!$output->toBool() || \DB::getInstance()->getAffectedRows() < 1)
		{
			return false;
		}
		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_coupon SET used_count = used_count + 1 WHERE coupon_srl = ?',
			(int)(self::getIssue($issue_srl)->coupon_srl ?? 0)
		);
		return true;
	}

	/**
	 * 코드 쿠폰 즉시 사용 — 한도·횟수 검사 후 사용 상태의 issue 를 만든다.
	 *
	 * @param string $code
	 * @param int $member_srl
	 * @param int $order_srl
	 * @param int $item_total
	 * @return object {success, message?, discount?, issue_srl?}
	 */
	public static function redeemCode(string $code, int $member_srl, int $order_srl, int $item_total): object
	{
		$coupon = self::getByCode($code);
		if (!$coupon || empty($coupon->code) || !self::isUsableNow($coupon))
		{
			return (object)['success' => false, 'message' => 'msg_shop_coupon_invalid'];
		}
		$discount = self::discountFor($coupon, $item_total);
		if ($discount === null)
		{
			return (object)['success' => false, 'message' => 'msg_shop_coupon_min_order'];
		}

		// 1인당 횟수
		$per = max(1, (int)($coupon->per_member ?? 1));
		$cnt = executeQuery('commerce.countCouponUses', (object)['coupon_srl' => (int)$coupon->coupon_srl, 'member_srl' => $member_srl]);
		if ($cnt->toBool() && (int)($cnt->data->count ?? 0) >= $per)
		{
			return (object)['success' => false, 'message' => 'msg_shop_coupon_used'];
		}

		// 전체 한도 — 컬럼 간 비교라 raw SQL 원자 차감
		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_coupon SET used_count = used_count + 1 WHERE coupon_srl = ? AND (total_limit = 0 OR used_count < total_limit)',
			(int)$coupon->coupon_srl
		);
		if (!$stmt || $stmt->rowCount() !== 1)
		{
			return (object)['success' => false, 'message' => 'msg_shop_coupon_soldout'];
		}

		$issue_srl = self::issueTo((int)$coupon->coupon_srl, $member_srl, $order_srl);
		if (!$issue_srl)
		{
			\Zittme\Framework\DB::getInstance()->query(
				'UPDATE commerce_coupon SET used_count = used_count - 1 WHERE coupon_srl = ? AND used_count > 0',
				(int)$coupon->coupon_srl
			);
			return (object)['success' => false, 'message' => 'msg_shop_coupon_invalid'];
		}
		return (object)['success' => true, 'discount' => $discount, 'issue_srl' => $issue_srl, 'coupon' => $coupon];
	}

	/**
	 * 발급 1건.
	 *
	 * @param int $issue_srl
	 * @return ?object
	 */
	public static function getIssue(int $issue_srl): ?object
	{
		$output = executeQuery('commerce.getCouponIssue', (object)['issue_srl' => $issue_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->issue_srl)) ? $output->data : null;
	}

	/**
	 * 주문 취소 시 쿠폰 반환 (재사용 가능 상태로).
	 *
	 * @param int $order_srl
	 * @return void
	 */
	public static function releaseByOrder(int $order_srl): void
	{
		// 사용 카운트 되돌리기: 해당 주문의 issue 를 먼저 찾는다
		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'SELECT issue_srl, coupon_srl FROM commerce_coupon_issue WHERE order_srl = ?', $order_srl
		);
		$rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_OBJ) : [];
		if (!count($rows))
		{
			return;
		}

		executeQuery('commerce.releaseCouponIssueByOrder', (object)[
			'order_srl' => $order_srl,
			'new_order_srl' => 0,
			'used_date' => '',
		]);
		foreach ($rows as $row)
		{
			\Zittme\Framework\DB::getInstance()->query(
				'UPDATE commerce_coupon SET used_count = used_count - 1 WHERE coupon_srl = ? AND used_count > 0',
				(int)$row->coupon_srl
			);
		}
	}
}
