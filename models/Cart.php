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
	/**
	 * 비회원으로 담아 둔 장바구니를 로그인한 회원에게 넘긴다.
	 *
	 * 로그인하면 소유자가 쿠키 키에서 회원 번호로 바뀌므로, 옮기지 않으면
	 * 담아 둔 것이 사라진 것처럼 보인다. 같은 상품·옵션이 양쪽에 있으면
	 * 수량을 합치고 비회원 줄은 지운다.
	 *
	 * @param int $member_srl 로그인한 회원
	 * @return int 옮긴 줄 수
	 */
	public static function mergeGuestCart(int $member_srl): int
	{
		$key = (string)($_COOKIE[self::COOKIE_NAME] ?? '');
		if ($member_srl <= 0 || !preg_match('/^[a-f0-9]{32}$/', $key))
		{
			return 0;
		}

		$guest_rows = self::rows((object)['member_srl' => 0, 'session_key' => $key]);
		if (!count($guest_rows))
		{
			self::forgetGuestKey();
			return 0;
		}

		// 회원이 이미 담아 둔 것 — 같은 상품·옵션이면 수량만 더한다
		$mine = [];
		foreach (self::rows((object)['member_srl' => $member_srl, 'session_key' => '']) as $row)
		{
			$mine[(int)$row->item_srl . ':' . (int)$row->option_srl] = $row;
		}

		$moved = 0;
		$db = \Zittme\Framework\DB::getInstance();
		foreach ($guest_rows as $row)
		{
			$dup = $mine[(int)$row->item_srl . ':' . (int)$row->option_srl] ?? null;
			if ($dup)
			{
				$db->query(
					'UPDATE commerce_cart SET qty = ? WHERE cart_srl = ?',
					(int)$dup->qty + (int)$row->qty, (int)$dup->cart_srl
				);
				$db->query('DELETE FROM commerce_cart WHERE cart_srl = ?', (int)$row->cart_srl);
			}
			else
			{
				$db->query(
					"UPDATE commerce_cart SET member_srl = ?, session_key = '' WHERE cart_srl = ?",
					$member_srl, (int)$row->cart_srl
				);
			}
			$moved++;
		}

		self::forgetGuestKey();
		return $moved;
	}

	/**
	 * 비회원 식별 쿠키를 버린다. 회원에게 넘긴 뒤에는 쓸 일이 없다.
	 */
	protected static function forgetGuestKey(): void
	{
		unset($_COOKIE[self::COOKIE_NAME]);
		if (!headers_sent())
		{
			setcookie(self::COOKIE_NAME, '', [
				'expires' => time() - 3600,
				'path' => '/',
				'httponly' => true,
				'samesite' => 'Lax',
				'secure' => !empty($_SERVER['HTTPS']),
			]);
		}
	}

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
		$item_total_listed = 0;
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
			// 다국어 연결 상품명($user_lang->코드)을 실값으로 - 주문서 표기와 주문 스냅샷이 이 값을 쓴다
			$item->item_name = Lang::text((string)$item->item_name);
			$option = null;
			// 담을 때 있던 옵션이 삭제·숨김됐으면 본품으로 취급하지 않고 변경 안내만 한다
			$changed = false;
			if ((int)$row->option_srl > 0)
			{
				$output = executeQuery('commerce.getOption', (object)['option_srl' => (int)$row->option_srl]);
				$option = ($output->toBool() && is_object($output->data) && !empty($output->data->option_srl)) ? $output->data : null;
				if (!$option || ($option->status ?? 'Y') !== 'Y')
				{
					$option = null;
					$changed = true;
				}
				if ($option)
				{
					$option->option_label = Lang::text((string)$option->option_label);
					// 조합 옵션 이름은 축 값에서 다시 만든다 (저장된 이름은 만든 시점 글자로 굳는다)
					$option->option_label = Combo::optionLabel($item, $option);
				}
			}

			// 추가 옵션(extra)은 별개 추가상품 — 추가금이 곧 단가. 기본 옵션(basic)은 판매가 + 추가금
			if ($option && ($option->option_type ?? 'basic') === 'extra')
			{
				$unit_original = (int)$option->price_add;
			}
			else
			{
				$unit_original = Item::effectivePrice($item) + ($option ? (int)$option->price_add : 0);
			}
			// 등급 할인은 서버 재계산 경로(여기) 한 곳에서만 적용된다 — 장바구니·주문 일관
			$unit = Grade::applyDiscount($unit_original, $grade_discount);
			$qty = max(1, (int)$row->qty);
			$subtotal = $unit * $qty;
			// 본품 행(option 없음)도 허용한다 — '선택 안 함' 선택지가 곧 본품이다
			$blocked = $changed || !Item::isPurchasable($item) || !Item::isQtyAllowed($item, $qty);

			$items[] = (object)[
				'cart_srl' => (int)$row->cart_srl,
				'item' => $item,
				'option' => $option,
				'qty' => $qty,
				'unit_price' => $unit,
				'unit_price_original' => $unit_original,
				'subtotal' => $subtotal,
				'blocked' => $blocked,
				'changed' => $changed,
			];

			if (!$blocked)
			{
				$item_total += $subtotal;
				// 무료배송 기준은 등급 할인 전 금액으로 본다. 할인 때문에 기준을 놓치면
				// 등급이 오를수록 배송비를 더 내는 뒤집힌 혜택이 된다
				$item_total_listed += $unit_original * $qty;
				if (($item->tax_type ?? 'taxable') === 'free')
				{
					$tax_free_total += $subtotal;
				}
			}
		}

		return (object)[
			'items' => $items,
			'item_total' => $item_total,
			'item_total_listed' => $item_total_listed,
			'tax_free_total' => $tax_free_total,
		];
	}

	/**
	 * 배송비 계산 (기본 정책 + 상품별 정책).
	 *
	 * @param object $resolved resolve() 결과
	 * @return int
	 */
	/**
	 * 지역 추가 배송비. 설정 ship_extra_zones(JSON) 한 줄은
	 *   country: 적용 국가 코드 (관리 화면에서 목록으로 고른다)
	 *   region:  국내 시·도 (국가가 KR 일 때만. 목록에서 고른 값)
	 *   zips:    우편번호 패턴 (국내에서 더 좁힐 때. 접두 "63" 또는 범위 "40200-40240")
	 *   fee:     기본 추가금 (구간을 두지 않았을 때, 또는 0원 구간)
	 *   tiers:   구매 금액 구간 [{from: 기준액, fee: 추가금}]. 넘긴 구간 중 가장 높은 것을 쓴다
	 *
	 * 해외는 나라 단위로만 잡는다. 도시나 주 이름은 구매자가 어떻게 적을지 알 수 없어
	 * 글자 맞추기로는 조용히 빗나간다. 국내만 시·도와 우편번호로 좁힌다.
	 *
	 * @param string $zipcode
	 * @param string $address1 배송지 주소 (국내 시·도 판정용)
	 * @param string $country 배송 국가 코드
	 * @return int
	 */
	public static function extraShipFee(string $zipcode, string $address1 = '', string $country = 'KR', string $state = '', string $city = '', int $item_total = 0): int
	{
		$zipcode = preg_replace('/[^0-9]/', '', $zipcode);
		$country = strtoupper(trim($country)) ?: Address::baseCountry();
		$region = $address1 !== '' ? Stats::normalizeRegion(explode(' ', trim($address1))[0]) : '';

		$zones = json_decode((string)(Base::config()->ship_extra_zones ?? '[]'), true);
		if (!is_array($zones))
		{
			return 0;
		}
		foreach ($zones as $zone)
		{
			$zone = (array)$zone;
			$fee = (int)($zone['fee'] ?? 0);
			$tiers = is_array($zone['tiers'] ?? null) ? $zone['tiers'] : [];
			if ($fee <= 0 && !count($tiers))
			{
				continue;
			}

			// 구매 금액 구간. 기준액을 넘긴 구간 중 가장 높은 것을 쓴다.
			// 마지막 구간의 추가금을 0 으로 두면 그 금액부터 면제가 된다.
			$matched_from = -1;
			foreach ($tiers as $tier)
			{
				$tier = (array)$tier;
				$from = (int)($tier['from'] ?? 0);
				if ($item_total >= $from && $from > $matched_from)
				{
					$matched_from = $from;
					$fee = max(0, (int)($tier['fee'] ?? 0));
				}
			}

			// 국가를 비워 둔 예전 설정은 국내 규칙으로 본다
			$zone_country = strtoupper(trim((string)($zone['country'] ?? ''))) ?: Address::baseCountry();
			if ($zone_country !== $country)
			{
				continue;
			}

			// 행정구역 코드로 맞춘다. 구매자도 같은 목록에서 고르므로 표기가 갈리지 않는다
			$zone_region = trim((string)($zone['region'] ?? ''));
			$buyer_region = trim($state);
			if ($zone_region !== '' && $buyer_region !== '' && strcasecmp($zone_region, $buyer_region) === 0)
			{
				return $fee;
			}

			// 해외는 지역을 지정하지 않았으면 나라 단위로 적용한다
			if (!Address::isDomestic($country))
			{
				if ($zone_region === '')
				{
					return $fee;
				}
				continue;
			}

			// 국내는 예전 방식(주소 앞부분의 시·도 이름)도 계속 읽는다
			$zone_names = array_filter(array_map('trim', explode(',',
				$zone_region . ',' . (string)($zone['regions'] ?? ''))));
			foreach ($zone_names as $name)
			{
				if ($region !== '' && Stats::normalizeRegion($name) === $region)
				{
					return $fee;
				}
			}

			if ($zipcode === '')
			{
				continue;
			}
			foreach (array_filter(array_map('trim', explode(',', (string)($zone['zips'] ?? '')))) as $pattern)
			{
				if (strpos($pattern, '-') !== false)
				{
					[$from, $to] = array_map('trim', explode('-', $pattern, 2));
					$zip_num = (int)substr($zipcode, 0, max(strlen($from), 1));
					if ($from !== '' && $to !== '' && $zip_num >= (int)$from && $zip_num <= (int)$to)
					{
						return $fee;
					}
				}
				elseif ($pattern !== '' && strpos($zipcode, $pattern) === 0)
				{
					return $fee;
				}
			}
		}
		return 0;
	}

	public static function calcShipFee(object $resolved): int
	{
		$config = Base::config();
		if (!count(array_filter($resolved->items, function($i) { return !$i->blocked; })))
		{
			return 0;
		}

		// 무료배송 기준액 — 등급 할인 전 금액과 견준다 (배송비 정책은 전체 회원 대상이다)
		$free_over = (int)($config->free_ship_over ?? 0);
		$judge_total = (int)($resolved->item_total_listed ?? $resolved->item_total);
		if ($free_over > 0 && $judge_total >= $free_over)
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
