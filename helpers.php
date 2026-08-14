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
