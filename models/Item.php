<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Item
{
	public static function get(int $item_srl): ?object
	{
		$output = executeQuery('commerce.getItem', (object)['item_srl' => $item_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->item_srl)) ? $output->data : null;
	}

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

	public static function effectivePrice(object $item): int
	{
		$sale = (int)($item->sale_price ?? 0);
		$base = $sale > 0 ? $sale : (int)($item->price ?? 0);
		$ts = Timesale::forItem($item);
		return $ts ? min($base, (int)$ts->price) : $base;
	}

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
			$foreign = $sale > 0 ? $sale : (int)($row->price ?? 0);
			$ts = Timesale::forItem($item);
			return $ts ? min($foreign, Timesale::priceIn($ts, $foreign, $currency)) : $foreign;
		}

		if ((Config::getConfig()->currency_fallback ?? 'convert') !== 'convert')
		{
			return -1;
		}
		return Money::convertMinor(self::effectivePrice($item), $currency);
	}

	public static function displayPrices(object $item, string $currency): array
	{
		$price = (int)($item->price ?? 0);
		$sale = (int)($item->sale_price ?? 0);
		$currency = strtoupper(trim($currency));

		if ($currency === '' || $currency === Money::base())
		{
			$effective = self::effectivePrice($item);
			if (!empty($item->timesale))
			{
				$sale = $effective;
			}
			return ['price' => $price, 'sale_price' => $sale, 'effective' => $effective, 'sellable' => true];
		}

		$rows = self::getPrices((int)$item->item_srl);
		if (isset($rows[$currency]))
		{
			$price = (int)($rows[$currency]->price ?? 0);
			$sale = (int)($rows[$currency]->sale_price ?? 0);
			$effective = self::effectivePriceIn($item, $currency, $rows);
			if (!empty($item->timesale))
			{
				$sale = $effective;
			}
			return ['price' => $price, 'sale_price' => $sale, 'effective' => $effective > 0 ? $effective : ($sale > 0 ? $sale : $price), 'sellable' => true];
		}

		if ((Config::getConfig()->currency_fallback ?? 'convert') !== 'convert')
		{
			return ['price' => 0, 'sale_price' => 0, 'effective' => 0, 'sellable' => false];
		}

		$price = max(0, Money::convertMinor($price, $currency));
		$sale = $sale > 0 ? max(0, Money::convertMinor($sale, $currency)) : 0;
		$effective = $sale > 0 ? $sale : $price;
		if (Timesale::forItem($item))
		{
			$sale = $effective = max(0, Money::convertMinor(self::effectivePrice($item), $currency));
		}
		return ['price' => $price, 'sale_price' => $sale, 'effective' => $effective, 'sellable' => true];
	}

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
		if (!Seller::isOperator((int)($item->seller_srl ?? 0)) && !Seller::isOpen())
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
		if (($item->use_stock ?? 'Y') !== 'Y')
		{
			return true;
		}
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
