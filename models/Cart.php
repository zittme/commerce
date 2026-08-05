<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 장바구니 — 회원은 member_srl, 비회원은 쿠키 세션키로 식별한다.
 */
class Cart
{
	/**
	 * 비회원 식별 쿠키 이름.
	 */
	public const COOKIE_NAME = 'zm_shop_cart';

	/**
	 * 현재 요청의 장바구니 소유자.
	 *
	 * @param bool $create_key 비회원 키가 없으면 발급할지
	 * @return object {member_srl, session_key}
	 */
	public static function owner(bool $create_key = false): object
	{
		$logged_info = \Context::get('logged_info');
		if ($logged_info && $logged_info->member_srl)
		{
			return (object)['member_srl' => (int)$logged_info->member_srl, 'session_key' => ''];
		}

		$key = (string)($_COOKIE[self::COOKIE_NAME] ?? '');
		if (!preg_match('/^[a-f0-9]{32}$/', $key))
		{
			$key = '';
		}
		if ($key === '' && $create_key)
		{
			$key = bin2hex(random_bytes(16));
			setcookie(self::COOKIE_NAME, $key, [
				'expires' => time() + 86400 * 30,
				'path' => '/',
				'httponly' => true,
				'samesite' => 'Lax',
				'secure' => !empty($_SERVER['HTTPS']),
			]);
			$_COOKIE[self::COOKIE_NAME] = $key;
		}
		return (object)['member_srl' => 0, 'session_key' => $key];
	}

	/**
	 * 담기 — 같은 상품+옵션이 있으면 수량 합산.
	 *
	 * @param int $item_srl
	 * @param int $option_srl
	 * @param int $qty
	 * @return \BaseObject
	 */
	public static function add(int $item_srl, int $option_srl, int $qty): \BaseObject
	{
		$owner = self::owner(true);
		if ($owner->member_srl <= 0 && $owner->session_key === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		foreach (self::rows($owner) as $row)
		{
			if ((int)$row->item_srl === $item_srl && (int)$row->option_srl === $option_srl)
			{
				executeQuery('commerce.updateCartQty', (object)[
					'cart_srl' => (int)$row->cart_srl,
					'qty' => min(9999, (int)$row->qty + $qty),
				]);
				return new \BaseObject();
			}
		}

		return executeQuery('commerce.insertCart', (object)[
			'cart_srl' => getNextSequence(),
			'member_srl' => $owner->member_srl,
			'session_key' => $owner->session_key,
			'item_srl' => $item_srl,
			'option_srl' => $option_srl,
			'qty' => min(9999, $qty),
			'regdate' => Base::now(),
		]);
	}

	/**
	 * 원본 행 목록.
	 *
	 * @param ?object $owner
	 * @return array
	 */
	public static function rows(?object $owner = null): array
	{
		$owner = $owner ?: self::owner();
		if ($owner->member_srl <= 0 && $owner->session_key === '')
		{
			return [];
		}

		$args = new \stdClass;
		if ($owner->member_srl > 0)
		{
			$args->member_srl = $owner->member_srl;
		}
		else
		{
			$args->member_srl = 0;
			$args->session_key = $owner->session_key;
		}

		$output = executeQuery('commerce.getCartList', $args);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) { return !empty($row->cart_srl); }));
	}

	/**
	 * 상품·옵션·가격을 해석한 장바구니 목록 + 합계.
	 *
	 * 가격은 항상 지금 시점에 다시 계산한다 — 담을 때 가격을 저장하지 않는 이유다.
	 * 판매 불가로 바뀐 상품은 blocked 로 표시만 하고 합계에서 제외한다.
	 *
	 * @param ?object $owner
	 * @return object {items: array, item_total: int, tax_free_total: int}
	 */
	public static function resolve(?object $owner = null): object
	{
		$items = [];
		$item_total = 0;
		$tax_free_total = 0;

		// 등급별 상품 할인 — 회원 주문에만 적용, 루프 밖에서 1회 조회
		if ($owner === null)
		{
			$owner = self::owner();
		}
		$grade_discount = ((int)($owner->member_srl ?? 0) > 0) ? Grade::discountFor((int)$owner->member_srl) : null;

		foreach (self::rows($owner) as $row)
		{
			$item = Item::get((int)$row->item_srl);
			if (!$item)
			{
				continue;
			}
			$option = null;
			if ((int)$row->option_srl > 0)
			{
				$output = executeQuery('commerce.getOption', (object)['option_srl' => (int)$row->option_srl]);
				$option = ($output->toBool() && is_object($output->data) && !empty($output->data->option_srl)) ? $output->data : null;
			}

			$unit_original = Item::effectivePrice($item) + ($option ? (int)$option->price_add : 0);
			// 등급 할인은 서버 재계산 경로(여기) 한 곳에서만 적용된다 — 장바구니·주문 일관
			$unit = Grade::applyDiscount($unit_original, $grade_discount);
			$qty = max(1, (int)$row->qty);
			$subtotal = $unit * $qty;
			$blocked = !Item::isPurchasable($item) || !Item::isQtyAllowed($item, $qty)
				|| (($item->has_options ?? 'N') === 'Y' && !$option);

			$items[] = (object)[
				'cart_srl' => (int)$row->cart_srl,
				'item' => $item,
				'option' => $option,
				'qty' => $qty,
				'unit_price' => $unit,
				'unit_price_original' => $unit_original,
				'subtotal' => $subtotal,
				'blocked' => $blocked,
			];

			if (!$blocked)
			{
				$item_total += $subtotal;
				if (($item->tax_type ?? 'taxable') === 'free')
				{
					$tax_free_total += $subtotal;
				}
			}
		}

		return (object)['items' => $items, 'item_total' => $item_total, 'tax_free_total' => $tax_free_total];
	}

	/**
	 * 배송비 계산 (기본 정책 + 상품별 정책).
	 *
	 * @param object $resolved resolve() 결과
	 * @return int
	 */
	public static function calcShipFee(object $resolved): int
	{
		$config = Base::config();
		if (!count(array_filter($resolved->items, function($i) { return !$i->blocked; })))
		{
			return 0;
		}

		// 무료배송 기준액
		$free_over = (int)($config->free_ship_over ?? 0);
		if ($free_over > 0 && $resolved->item_total >= $free_over)
		{
			return 0;
		}

		// 상품별 정책: 하나라도 '무료'가 아니면 최댓값 적용(고정 > 기본)
		$fee = 0;
		$all_free = true;
		foreach ($resolved->items as $entry)
		{
			if ($entry->blocked)
			{
				continue;
			}
			$type = $entry->item->ship_fee_type ?? 'default';
			if ($type === 'free')
			{
				continue;
			}
			$all_free = false;
			$item_fee = $type === 'fixed' ? (int)$entry->item->ship_fee : (int)($config->default_ship_fee ?? 0);
			$fee = max($fee, $item_fee);
		}
		return $all_free ? 0 : $fee;
	}

	/**
	 * 항목 삭제 (소유자 검증 포함).
	 *
	 * @param int $cart_srl
	 * @return void
	 */
	public static function remove(int $cart_srl): void
	{
		$owner = self::owner();
		$args = (object)['cart_srl' => $cart_srl];
		if ($owner->member_srl > 0)
		{
			$args->owner_member_srl = $owner->member_srl;
		}
		else
		{
			$args->owner_session_key = $owner->session_key;
		}
		executeQuery('commerce.deleteCart', $args);
	}

	/**
	 * 주문 완료 후 장바구니 비우기.
	 *
	 * @param array $cart_srls
	 * @return void
	 */
	public static function removeMany(array $cart_srls): void
	{
		foreach ($cart_srls as $srl)
		{
			self::remove((int)$srl);
		}
	}
}
