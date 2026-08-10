<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 부가세 계산 — 거래명세서 등 세액 표기가 필요한 곳에서 쓴다.
 *
 * 규칙
 *  - 상품의 tax_type 이 free 면 면세, 그 외는 과세로 본다.
 *  - 배송 국가가 국내(KR)가 아니면 수출로 보아 전 품목을 영세율(세액 0)로 처리한다.
 *  - 사업자 구분이 exempt(면세)·simplified(간이)면 세액을 계산하지 않는다.
 *  - 가격이 부가세 포함가면 역산하고, 별도면 표시가에 세액을 얹는다.
 *  - 반올림 오차는 마지막 과세 항목에서 흡수해 합계가 결제금액과 어긋나지 않게 한다.
 */
class Tax
{
	/**
	 * 세액을 표기하는 사업자인지.
	 *
	 * @param object $config
	 * @return bool
	 */
	public static function isEnabled(object $config): bool
	{
		return ($config->biz_tax_mode ?? 'taxable') === 'taxable';
	}

	/**
	 * 부가세율 (0.1 = 10%).
	 *
	 * @param object $config
	 * @return float
	 */
	public static function rate(object $config): float
	{
		$rate = (float)($config->vat_rate ?? 10);
		if ($rate < 0 || $rate > 100)
		{
			$rate = 10;
		}
		return $rate / 100;
	}

	/**
	 * 주문 한 건의 세액 내역.
	 *
	 * @param object $config 커머스 설정
	 * @param array<int, object> $items 주문 품목 (tax_type, subtotal)
	 * @param int $delivery_fee 배송비 (과세로 본다)
	 * @param string $country 배송 국가 (KR 이 아니면 영세율)
	 * @return object {enabled, zero_rated, rate, lines, taxable_supply, vat, free_supply, delivery_supply, delivery_vat, total_supply, total_vat}
	 */
	public static function breakdown(object $config, array $items, int $delivery_fee = 0, string $country = 'KR'): object
	{
		$enabled = self::isEnabled($config);
		$zero_rated = strtoupper($country) !== 'KR';
		$rate = self::rate($config);
		$included = ($config->price_includes_tax ?? 'Y') !== 'N';

		$lines = [];
		$taxable_supply = 0;
		$vat = 0;
		$free_supply = 0;
		$last_taxable = -1;
		$taxable_gross = 0;

		foreach ($items as $i => $item)
		{
			$amount = (int)$item->subtotal;
			$is_free = (($item->tax_type ?? 'taxable') === 'free') || $zero_rated || !$enabled;

			if ($is_free)
			{
				$supply = $amount;
				$line_vat = 0;
				$free_supply += $supply;
			}
			else
			{
				[$supply, $line_vat] = self::split($amount, $rate, $included);
				$taxable_supply += $supply;
				$vat += $line_vat;
				$taxable_gross += $supply + $line_vat;
				$last_taxable = $i;
			}

			$lines[$i] = (object)['supply' => $supply, 'vat' => $line_vat, 'free' => $is_free];
		}

		// 배송비는 과세 (영세율·면세사업자 제외)
		$delivery_supply = $delivery_fee;
		$delivery_vat = 0;
		if ($delivery_fee > 0 && $enabled && !$zero_rated)
		{
			[$delivery_supply, $delivery_vat] = self::split($delivery_fee, $rate, $included);
		}

		// 포함가 역산은 항목마다 반올림되므로 합계가 어긋날 수 있다 — 마지막 과세 항목에서 흡수
		if ($included && $last_taxable >= 0)
		{
			$gross_expected = 0;
			foreach ($items as $i => $item)
			{
				if (!$lines[$i]->free)
				{
					$gross_expected += (int)$item->subtotal;
				}
			}
			$diff = $gross_expected - $taxable_gross;
			if ($diff !== 0)
			{
				$lines[$last_taxable]->supply += $diff;
				$taxable_supply += $diff;
			}
		}

		return (object)[
			'enabled' => $enabled,
			'zero_rated' => $zero_rated,
			'rate' => $rate,
			'included' => $included,
			'lines' => $lines,
			'taxable_supply' => $taxable_supply,
			'vat' => $vat,
			'free_supply' => $free_supply,
			'delivery_supply' => $delivery_supply,
			'delivery_vat' => $delivery_vat,
			'total_supply' => $taxable_supply + $free_supply + $delivery_supply,
			'total_vat' => $vat + $delivery_vat,
		];
	}

	/**
	 * 금액 하나를 공급가액·세액으로 쪼갠다.
	 *
	 * @param int $amount
	 * @param float $rate
	 * @param bool $included 포함가 여부
	 * @return array{0: int, 1: int}
	 */
	protected static function split(int $amount, float $rate, bool $included): array
	{
		if ($rate <= 0)
		{
			return [$amount, 0];
		}
		if ($included)
		{
			$supply = (int)round($amount / (1 + $rate));
			return [$supply, $amount - $supply];
		}
		return [$amount, (int)round($amount * $rate)];
	}
}
