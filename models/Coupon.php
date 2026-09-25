<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Coupon
{
	public static function get(int $coupon_srl): ?object
	{
		$output = executeQuery('commerce.getCoupon', (object)['coupon_srl' => $coupon_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->coupon_srl)) ? $output->data : null;
	}

	public static function getByCode(string $code): ?object
	{
		if ($code === '')
		{
			return null;
		}
		$output = executeQuery('commerce.getCouponByCode', (object)['code' => $code]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->coupon_srl)) ? $output->data : null;
	}

	public static function getList(): array
	{
		$output = executeQuery('commerce.getCouponList', new \stdClass);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		$rows = array_values(array_filter($data, function($row) { return !empty($row->coupon_srl); }));
		return Lang::textAll($rows, ['title']);
	}

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
				$one = self::get($srl);
				if ($one)
				{
					$one->title_raw = (string)$one->title;
					$one->title = Lang::text((string)$one->title);
				}
				$coupons[$srl] = $one;
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

		$per = max(1, (int)($coupon->per_member ?? 1));
		$cnt = executeQuery('commerce.countCouponUses', (object)['coupon_srl' => (int)$coupon->coupon_srl, 'member_srl' => $member_srl]);
		if ($cnt->toBool() && (int)($cnt->data->count ?? 0) >= $per)
		{
			return (object)['success' => false, 'message' => 'msg_shop_coupon_used'];
		}

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

	public static function getIssue(int $issue_srl): ?object
	{
		$output = executeQuery('commerce.getCouponIssue', (object)['issue_srl' => $issue_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->issue_srl)) ? $output->data : null;
	}

	public static function releaseByOrder(int $order_srl): void
	{
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
