<?php

/**
 * 스킨 공용 헬퍼. Base 컨트롤러가 로드한다.
 */

use Zittme\Modules\Commerce\Models\Money as CommerceMoney;

if (!function_exists('shop_money_in'))
{
	/**
	 * 특정 통화의 최소단위 정수 금액을 표기한다. 주문서·주문 내역처럼
	 * 금액이 이미 그 통화로 확정된 화면에서 쓴다.
	 *
	 * @param int|float|string $minor
	 * @param string $currency
	 * @return string
	 */
	function shop_money_in($minor, string $currency = ''): string
	{
		$currency = strtoupper(trim($currency)) ?: CommerceMoney::current();
		return CommerceMoney::format((int)$minor, $currency);
	}
}

if (!function_exists('shop_money'))
{
	/**
	 * 기준 통화 최소단위 금액을 현재 표시 통화로 표기한다. 예: 12000 → '12,000원' 또는 '$8.89'
	 *
	 * @param int|float|string $amount
	 * @return string
	 */
	function shop_money($amount): string
	{
		return CommerceMoney::text((int)$amount);
	}
}

if (!function_exists('shop_item_price'))
{
	/**
	 * 상품 카드·상세의 가격 표기. 표시 통화의 값(등록가 또는 환산가)을 쓴다.
	 * 주문서·결제와 같은 규칙이라야 상세에서 본 금액과 결제 금액이 어긋나지 않는다.
	 *
	 * @param object $item setShopContext 를 지난 상품
	 * @param string $kind price(정가) | sale(판매가) | effective(실판매가) | grade(등급가)
	 * @return string
	 */
	function shop_item_price(object $item, string $kind = 'effective'): string
	{
		$fields = ['price' => 'disp_price', 'sale' => 'disp_sale_price', 'effective' => 'disp_effective', 'grade' => 'grade_price'];
		$field = $fields[$kind] ?? 'disp_effective';
		$currency = (string)($item->disp_currency ?? '');
		if ($currency !== '' && isset($item->$field))
		{
			return CommerceMoney::formatItem((int)$item->$field, $currency);
		}

		// 표시 값을 담지 않는 예전 스킨 호환 — 기준 통화 값을 환산해 찍는다
		$legacy = ['price' => 'price', 'sale' => 'sale_price', 'effective' => 'price', 'grade' => 'grade_price'];
		return CommerceMoney::textItem((int)($item->{$legacy[$kind] ?? 'price'} ?? 0));
	}
}

if (!function_exists('shop_item_on_sale'))
{
	/**
	 * 표시 통화 기준으로 할인 중인지.
	 *
	 * @param object $item
	 * @return bool
	 */
	function shop_item_on_sale(object $item): bool
	{
		if (isset($item->disp_price))
		{
			return (int)$item->disp_sale_price > 0 && (int)$item->disp_sale_price < (int)$item->disp_price;
		}
		return (int)($item->sale_price ?? 0) > 0 && (int)$item->sale_price < (int)($item->price ?? 0);
	}
}

if (!function_exists('shop_money_base'))
{
	/**
	 * 기준 통화 최소단위 금액을 기준 통화로 표기한다. 관리자 화면·원장 표기용.
	 *
	 * @param int|float|string $amount
	 * @return string
	 */
	function shop_money_base($amount): string
	{
		return CommerceMoney::format((int)$amount, CommerceMoney::base());
	}
}
