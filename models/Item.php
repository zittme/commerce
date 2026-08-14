<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 상품.
 */
class Item
{
	/**
	 * 상품 1건.
	 *
	 * @param int $item_srl
	 * @return ?object
	 */
	public static function get(int $item_srl): ?object
	{
		$output = executeQuery('commerce.getItem', (object)['item_srl' => $item_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->item_srl)) ? $output->data : null;
	}

	/**
	 * 옵션 목록.
	 *
	 * @param int $item_srl
	 * @param bool $active_only
	 * @return array
	 */
	public static function getOptions(int $item_srl, bool $active_only = false): array
	{
		$output = executeQuery('commerce.getOptionsByItem', (object)['item_srl' => $item_srl]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) use ($active_only) {
			if (empty($row->option_srl))
			{
				return false;
			}
			return !$active_only || ($row->status ?? 'Y') === 'Y';
		}));
	}

	/**
	 * 실제 판매가 — 판매가가 0 이면 정가.
	 *
	 * @param object $item
	 * @return int
	 */
	public static function effectivePrice(object $item): int
	{
		$sale = (int)($item->sale_price ?? 0);
		return $sale > 0 ? $sale : (int)($item->price ?? 0);
	}

	/**
	 * 상품의 외화 가격 행들. ['USD' => (object)['price'=>..., 'sale_price'=>...], ...]
	 * 금액은 통화 최소단위 정수.
	 *
	 * @param int $item_srl
	 * @return array<string, object>
	 */
	public static function getPrices(int $item_srl): array
	{
		$output = executeQueryArray('commerce.getItemPrices', (object)['item_srl' => $item_srl]);
		$result = [];
		foreach (($output->toBool() ? ($output->data ?: []) : []) as $row)
		{
			if (!empty($row->currency))
			{
				$result[strtoupper((string)$row->currency)] = $row;
			}
		}
		return $result;
	}

	/**
	 * 외화 가격 저장. 넘긴 맵으로 전체를 갈아끼운다.
	 *
	 * @param int $item_srl
	 * @param array $prices ['USD' => ['price' => 1234, 'sale_price' => 0], ...] 최소단위 정수
	 * @return void
	 */
	public static function setPrices(int $item_srl, array $prices): void
	{
		executeQuery('commerce.deleteItemPrices', (object)['item_srl' => $item_srl]);
		foreach ($prices as $currency => $row)
		{
			$currency = strtoupper(trim((string)$currency));
			$price = max(0, (int)($row['price'] ?? 0));
			$sale = max(0, (int)($row['sale_price'] ?? 0));
			if (!preg_match('/^[A-Z]{3}$/', $currency) || $currency === Money::base() || ($price <= 0 && $sale <= 0))
			{
				continue;
			}
			executeQuery('commerce.insertItemPrice', (object)[
				'item_srl' => $item_srl,
				'currency' => $currency,
				'price' => $price,
				'sale_price' => $sale,
			]);
		}
	}

	/**
	 * 특정 통화의 실제 판매가 (최소단위 정수).
	 *
	 * 우선순위: 등록된 통화별 가격 → (설정이 convert 면) KRW 가격 환산 → -1 (판매 불가).
	 *
	 * @param object $item
	 * @param string $currency
	 * @param ?array $prices getPrices() 결과를 이미 들고 있으면 넘겨서 재조회를 피한다
	 * @return int -1 이면 이 통화로 팔 수 없다
	 */
	public static function effectivePriceIn(object $item, string $currency, ?array $prices = null): int
	{
		$currency = strtoupper(trim($currency));
		if ($currency === '' || $currency === Money::base())
		{
			return self::effectivePrice($item);
		}

		$prices = $prices ?? self::getPrices((int)$item->item_srl);
		if (isset($prices[$currency]))
		{
			$row = $prices[$currency];
			$sale = (int)($row->sale_price ?? 0);
			return $sale > 0 ? $sale : (int)($row->price ?? 0);
		}

		if ((Config::getConfig()->currency_fallback ?? 'convert') !== 'convert')
		{
			return -1;
		}
		return Money::convertMinor(self::effectivePrice($item), $currency);
	}

	/**
	 * 지금 판매 가능한가 — 상태 + 판매기간 + 재고까지 종합 판정.
	 *
	 * @param object $item
	 * @return bool
	 */
	/**
	 * 판매 중인 기본 옵션(변형)이 있는지. 있으면 본품 단독 주문은 막는다.
	 *
	 * @param int $item_srl
	 * @return bool
	 */
	public static function hasBasicOptions(int $item_srl): bool
	{
		foreach (self::getOptions($item_srl, true) as $opt)
		{
			if (($opt->option_type ?? 'basic') === 'basic')
			{
				return true;
			}
		}
		return false;
	}

	public static function isPurchasable(object $item): bool
	{
		if (($item->status ?? '') !== 'sale')
		{
			return false;
		}
		$now = Base::now();
		if (!empty($item->sale_start) && $now < $item->sale_start)
		{
			return false;
		}
		if (!empty($item->sale_end) && $now > $item->sale_end)
		{
			return false;
		}
		// 재고 관리를 쓰지 않는 상품은 옵션 여부와 무관하게 항상 구매 가능
		if (($item->use_stock ?? 'Y') !== 'Y')
		{
			return true;
		}
		// 본품 재고가 있으면 구매 가능. 옵션은 변형 상품이라, 옵션이 전부
		// 매진이어도 본품은 팔 수 있다 (매진 옵션은 화면에서 선택만 막는다).
		if ((int)$item->stock > 0)
		{
			return true;
		}
		if (($item->has_options ?? 'N') === 'Y')
		{
			foreach (self::getOptions((int)$item->item_srl, true) as $opt)
			{
				if ((int)$opt->stock > 0)
				{
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * 수량 제한 검사.
	 *
	 * @param object $item
	 * @param int $qty
	 * @return bool
	 */
	public static function isQtyAllowed(object $item, int $qty): bool
	{
		if ($qty < 1)
		{
			return false;
		}
		$min = (int)($item->min_qty ?? 0);
		$max = (int)($item->max_qty ?? 0);
		if ($min > 0 && $qty < $min)
		{
			return false;
		}
		if ($max > 0 && $qty > $max)
		{
			return false;
		}
		return true;
	}

	/**
	 * 재고 소진 상품을 품절 상태로 전환 (옵션 상품 포함).
	 *
	 * @param int $item_srl
	 * @return void
	 */
	public static function syncSoldout(int $item_srl): void
	{
		$item = self::get($item_srl);
		if (!$item || ($item->status ?? '') !== 'sale')
		{
			return;
		}
		$has_stock = self::isPurchasable($item);
		if (!$has_stock)
		{
			executeQuery('commerce.updateItem', (object)[
				'item_srl' => $item_srl,
				'status' => 'soldout',
				'last_update' => Base::now(),
			]);
		}
	}
}
