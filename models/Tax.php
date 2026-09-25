<?php

namespace Zittme\Modules\Commerce\Models;

class Tax
{
	public static function isEnabled(object $config): bool
	{
		return ($config->biz_tax_mode ?? 'taxable') === 'taxable';
	}

	public static function rate(object $config): float
	{
		$rate = (float)($config->vat_rate ?? 10);
		if ($rate < 0 || $rate > 100)
		{
			$rate = 10;
		}
		return $rate / 100;
	}

	public static function breakdown(object $config, array $items, int $delivery_fee = 0, string $country = ''): object
	{
		$enabled = self::isEnabled($config);
		$zero_rated = !Address::isDomestic($country);
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
