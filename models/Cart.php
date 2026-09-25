<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Cart
{
	public const COOKIE_NAME = 'zm_shop_cart';

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

	public static function resolve(?object $owner = null): object
	{
		$items = [];
		$item_total = 0;
		$item_total_listed = 0;
		$tax_free_total = 0;

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
			$item->item_name = Lang::text((string)$item->item_name);
			$option = null;
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
					$option->option_label = Combo::optionLabel($item, $option);
				}
			}

			if ($option && ($option->option_type ?? 'basic') === 'extra')
			{
				$unit_original = (int)$option->price_add;
			}
			else
			{
				$unit_original = Item::effectivePrice($item) + ($option ? (int)$option->price_add : 0);
			}
			$unit = ($item->grade_discount ?? 'Y') === 'N' ? $unit_original : Grade::applyDiscount($unit_original, $grade_discount);
			$qty = max(1, (int)$row->qty);
			$subtotal = $unit * $qty;
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

			$zone_country = strtoupper(trim((string)($zone['country'] ?? ''))) ?: Address::baseCountry();
			if ($zone_country !== $country)
			{
				continue;
			}

			$zone_region = trim((string)($zone['region'] ?? ''));
			$buyer_region = trim($state);
			if ($zone_region !== '' && $buyer_region !== '' && strcasecmp($zone_region, $buyer_region) === 0)
			{
				return $fee;
			}

			if (!Address::isDomestic($country))
			{
				if ($zone_region === '')
				{
					return $fee;
				}
				continue;
			}

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

	public static function isPinOnly(object $resolved): bool
	{
		$live = array_filter($resolved->items, function($i) { return !$i->blocked; });
		if (!count($live))
		{
			return false;
		}
		foreach ($live as $entry)
		{
			if (($entry->item->is_pin ?? 'N') !== 'Y')
			{
				return false;
			}
		}
		return true;
	}

	public static function calcShipFee(object $resolved): int
	{
		$config = Base::config();
		if (!count(array_filter($resolved->items, function($i) { return !$i->blocked; })))
		{
			return 0;
		}

		if (Seller::isOpen())
		{
			return array_sum(Seller::shipFees($resolved->items));
		}

		$free_over = (int)($config->free_ship_over ?? 0);
		$judge_total = (int)($resolved->item_total_listed ?? $resolved->item_total);
		if ($free_over > 0 && $judge_total >= $free_over)
		{
			return 0;
		}

		$fee = 0;
		$all_free = true;
		foreach ($resolved->items as $entry)
		{
			if ($entry->blocked)
			{
				continue;
			}
			$type = $entry->item->ship_fee_type ?? 'default';
			if ($type === 'free' || ($entry->item->is_pin ?? 'N') === 'Y')
			{
				continue;
			}
			$all_free = false;
			$item_fee = $type === 'fixed' ? (int)$entry->item->ship_fee : (int)($config->default_ship_fee ?? 0);
			$fee = max($fee, $item_fee);
		}
		return $all_free ? 0 : $fee;
	}

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

	public static function removeMany(array $cart_srls): void
	{
		foreach ($cart_srls as $srl)
		{
			self::remove((int)$srl);
		}
	}
}
