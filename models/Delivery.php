<?php

namespace Zittme\Modules\Commerce\Models;

class Delivery
{
	public const TYPES = ['normal', 'fast', 'dawn', 'today'];

	protected static $sellers = [];

	public static function types(): array
	{
		$out = [];
		foreach (self::TYPES as $type)
		{
			$out[$type] = lang('commerce.ship_type_' . $type);
		}
		return $out;
	}

	public static function normalize($type): string
	{
		$type = (string)$type;
		return in_array($type, self::TYPES, true) ? $type : 'normal';
	}

	public static function enabled(): bool
	{
		return (Config::getConfig()->ship_types_enabled ?? 'Y') === 'Y';
	}

	public static function cutoffFor(string $type, ?object $seller = null): string
	{
		$config = Config::getConfig();
		$cutoff = (string)($config->{'ship_cutoff_' . $type} ?? '');
		$seller_cutoff = $seller ? trim((string)($seller->ship_cutoff ?? '')) : '';
		if ($seller_cutoff !== '' && in_array($type, ['normal', 'fast'], true))
		{
			$cutoff = $seller_cutoff;
		}
		return self::validTime($cutoff) ? $cutoff : ($type === 'today' ? '11:00' : ($type === 'dawn' ? '23:00' : ($type === 'fast' ? '24:00' : '15:00')));
	}

	public static function validTime(string $value): bool
	{
		if (!preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m))
		{
			return false;
		}
		$h = (int)$m[1];
		$i = (int)$m[2];
		return ($h < 24 && $i < 60) || ($h === 24 && $i === 0);
	}

	public static function parseHolidays(string $text): array
	{
		$out = [];
		foreach (preg_split('/[\s,]+/', $text) as $token)
		{
			$token = trim($token);
			if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $token, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]))
			{
				$out[$m[1] . $m[2] . $m[3]] = true;
			}
		}
		return $out;
	}

	public static function workdays(): array
	{
		$raw = (string)(Config::getConfig()->ship_workdays ?? '1,2,3,4,5,6');
		$days = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), function ($d) { return $d >= 0 && $d <= 6; })));
		return count($days) ? $days : [1, 2, 3, 4, 5, 6];
	}

	protected static function seller(int $seller_srl): ?object
	{
		if ($seller_srl <= 0)
		{
			return null;
		}
		if (!array_key_exists($seller_srl, self::$sellers))
		{
			self::$sellers[$seller_srl] = Seller::get($seller_srl);
		}
		return self::$sellers[$seller_srl];
	}

	public static function estimate(object $item, ?object $seller = null, ?int $ts = null): object
	{
		$ts = $ts ?? time();
		$type = self::normalize($item->ship_type ?? 'normal');
		if ($seller === null && !empty($item->seller_srl))
		{
			$seller = self::seller((int)$item->seller_srl);
		}
		$holidays = self::parseHolidays((string)(Config::getConfig()->ship_holidays ?? ''));
		if ($seller)
		{
			$holidays += self::parseHolidays((string)($seller->ship_holidays ?? ''));
		}
		$workdays = self::workdays();
		$is_work = function (int $day) use ($holidays, $workdays): bool {
			return in_array((int)date('w', $day), $workdays, true) && !isset($holidays[date('Ymd', $day)]);
		};
		$next_work = function (int $day) use ($is_work): int {
			for ($i = 0; $i < 60; $i++)
			{
				$day = strtotime('+1 day', $day);
				if ($is_work($day))
				{
					return $day;
				}
			}
			return $day;
		};

		$today = strtotime(date('Y-m-d 00:00:00', $ts));
		$cutoff = self::cutoffFor($type, $seller);
		[$ch, $cm] = array_map('intval', explode(':', $cutoff));
		$cutoff_ts = $today + $ch * 3600 + $cm * 60;

		$ship_day = $today;
		if (!$is_work($today) || $ts >= $cutoff_ts)
		{
			$ship_day = $next_work($today);
		}

		if ($type === 'today')
		{
			$arrive = $ship_day;
		}
		elseif ($type === 'normal')
		{
			$arrive = $ship_day;
			$days = max(1, min(14, (int)(Config::getConfig()->ship_days_normal ?? 2)));
			for ($i = 0; $i < $days; $i++)
			{
				$arrive = $next_work($arrive);
			}
		}
		else
		{
			$arrive = $next_work($ship_day);
		}

		$left = ($ship_day === $today) ? max(0, $cutoff_ts - $ts) : 0;
		$when = self::dayLabel($arrive, $today);
		$key = $type === 'normal' ? 'eta_expect' : ($type === 'dawn' ? 'eta_dawn' : ($type === 'today' ? 'eta_today_by' : 'eta_guarantee'));

		return (object)[
			'type' => $type,
			'badge' => $type === 'normal' ? '' : lang('commerce.ship_type_' . $type),
			'date' => date('Y-m-d', $arrive),
			'ts' => $arrive,
			'guaranteed' => $type !== 'normal',
			'when' => $when,
			'text' => sprintf(lang('commerce.' . $key), $when),
			'cutoff' => $cutoff,
			'left' => $left,
			'left_text' => $left > 0 ? sprintf(lang('commerce.eta_left'), intdiv($left, 3600), intdiv($left % 3600, 60)) : '',
		];
	}

	public static function fromShipped(string $ship_type, string $shipped_date): object
	{
		$ts = strtotime(substr($shipped_date, 0, 4) . '-' . substr($shipped_date, 4, 2) . '-' . substr($shipped_date, 6, 2) . ' ' . substr($shipped_date, 8, 2) . ':' . substr($shipped_date, 10, 2) . ':00');
		return self::estimate((object)['ship_type' => $ship_type], null, $ts ?: time());
	}

	protected static function toTs(string $ymdhis): int
	{
		if (strlen($ymdhis) < 12)
		{
			return 0;
		}
		return (int)strtotime(substr($ymdhis, 0, 4) . '-' . substr($ymdhis, 4, 2) . '-' . substr($ymdhis, 6, 2) . ' ' . substr($ymdhis, 8, 2) . ':' . substr($ymdhis, 10, 2) . ':00');
	}

	public static function attachSellerOrders(object $order, array $order_sellers, array $order_items): void
	{
		$rank = ['quick' => -1, 'today' => 0, 'dawn' => 1, 'fast' => 2, 'normal' => 3];
		foreach ($order_sellers as $os)
		{
			$type = 'normal';
			foreach ($order_items as $oi)
			{
				if ((int)($oi->seller_srl ?? 0) === (int)$os->seller_srl || count($order_sellers) === 1)
				{
					$t = ($oi->ship_type ?? '') === 'quick' ? 'quick' : self::normalize($oi->ship_type ?? 'normal');
					if ($rank[$t] < $rank[$type])
					{
						$type = $t;
					}
				}
			}
			if (!empty($os->delivered_date))
			{
				$os->eta = null;
				continue;
			}
			if ($type === 'quick')
			{
				$os->eta = (object)['type' => 'quick', 'badge' => lang('commerce.ship_type_quick'), 'date' => '', 'ts' => 0, 'guaranteed' => false, 'when' => '', 'text' => self::quickText(), 'cutoff' => '', 'left' => 0, 'left_text' => ''];
				continue;
			}
			if (!self::enabled())
			{
				$os->eta = null;
				continue;
			}
			$base = !empty($os->shipped_date) ? self::toTs((string)$os->shipped_date) : self::toTs((string)($order->paid_date ?: $order->regdate));
			$seller = self::seller((int)$os->seller_srl);
			$os->eta = self::estimate((object)['ship_type' => $type], $seller, $base ?: time());
			$os->eta->when = self::dayLabel($os->eta->ts);
			$key = $type === 'normal' ? 'eta_expect' : ($type === 'dawn' ? 'eta_dawn' : ($type === 'today' ? 'eta_today_by' : 'eta_guarantee'));
			$os->eta->text = sprintf(lang('commerce.' . $key), $os->eta->when);
		}
	}

	public static function dayLabel(int $day, ?int $today = null): string
	{
		$today = $today ?? strtotime(date('Y-m-d 00:00:00'));
		$wd = explode(',', (string)lang('commerce.eta_weekdays'));
		$w = $wd[(int)date('w', $day)] ?? date('D', $day);
		$diff = (int)round(($day - $today) / 86400);
		if ($diff === 0)
		{
			return sprintf(lang('commerce.eta_fmt_rel'), lang('commerce.eta_today'), $w);
		}
		if ($diff === 1)
		{
			return sprintf(lang('commerce.eta_fmt_rel'), lang('commerce.eta_tomorrow'), $w);
		}
		return sprintf(lang('commerce.eta_fmt_date'), date('n', $day), date('j', $day), $w);
	}

	public static function attach(array $items, ?int $ts = null): void
	{
		if (!self::enabled())
		{
			return;
		}
		foreach ($items as $item)
		{
			if (is_object($item) && !empty($item->item_srl))
			{
				$item->eta = self::estimate($item, null, $ts);
			}
		}
	}

	public static function quickEnabled(): bool
	{
		return (Config::getConfig()->ship_quick_enabled ?? 'N') === 'Y';
	}

	public static function quickText(): string
	{
		return lang('commerce.ship_type_quick') . ' · ' . lang('commerce.quick_cod_note');
	}

	public static function parseRegions(string $text): array
	{
		$out = [];
		foreach (preg_split('/[\r\n,]+/', $text) as $line)
		{
			$line = trim(preg_replace('/\s+/u', ' ', $line));
			if ($line === '')
			{
				continue;
			}
			if (preg_match('/^(\d{2,5})\s*-\s*(\d{2,5})$/', $line, $m))
			{
				$out[] = ['zip_from' => $m[1], 'zip_to' => $m[2]];
			}
			elseif (preg_match('/^\d{2,5}$/', $line))
			{
				$out[] = ['zip' => $line];
			}
			else
			{
				$out[] = ['words' => array_slice(explode(' ', mb_substr($line, 0, 60)), 0, 3)];
			}
			if (count($out) >= 300)
			{
				break;
			}
		}
		return $out;
	}

	public static function cleanRegions(string $text): string
	{
		$lines = [];
		foreach (self::parseRegions($text) as $rule)
		{
			$lines[] = isset($rule['words']) ? implode(' ', $rule['words']) : (isset($rule['zip']) ? $rule['zip'] : $rule['zip_from'] . '-' . $rule['zip_to']);
		}
		return implode("\n", array_values(array_unique($lines)));
	}

	public static function regionMatches(array $rules, string $zipcode, string $address1): bool
	{
		$zipcode = preg_replace('/[^0-9]/', '', $zipcode);
		$words = array_values(array_filter(explode(' ', trim(preg_replace('/\s+/u', ' ', $address1))), 'strlen'));
		foreach ($rules as $rule)
		{
			if (isset($rule['zip']))
			{
				if ($zipcode !== '' && strpos($zipcode, $rule['zip']) === 0)
				{
					return true;
				}
			}
			elseif (isset($rule['zip_from']))
			{
				$head = (int)substr($zipcode, 0, max(strlen($rule['zip_from']), 1));
				if ($zipcode !== '' && $head >= (int)$rule['zip_from'] && $head <= (int)$rule['zip_to'])
				{
					return true;
				}
			}
			elseif (count($words))
			{
				$ok = true;
				foreach ($rule['words'] as $i => $word)
				{
					$have = (string)($words[$i] ?? '');
					$ok = $have !== '' && ($i === 0 ? Stats::normalizeRegion($have) === Stats::normalizeRegion($word) : mb_strpos($have, $word) === 0);
					if (!$ok)
					{
						break;
					}
				}
				if ($ok)
				{
					return true;
				}
			}
		}
		return false;
	}

	protected static function isMarketSeller(int $seller_srl): bool
	{
		return $seller_srl > 0 && Seller::isOpen() && !Seller::isOperator($seller_srl);
	}

	protected static function rulesOf(int $seller_srl, string $key): string
	{
		if (self::isMarketSeller($seller_srl))
		{
			$seller = self::seller($seller_srl);
			return (string)($seller->{$key} ?? '');
		}
		return (string)(Config::getConfig()->{$key} ?? '');
	}

	public static function canShipTo(object $item, string $zipcode, string $address1, string $country, bool $quick = false): bool
	{
		$seller_srl = (int)($item->seller_srl ?? 0);
		$domestic = Address::isDomestic($country);
		$rules = self::parseRegions(self::rulesOf($seller_srl, 'ship_regions'));
		if (count($rules) && (!$domestic || !self::regionMatches($rules, $zipcode, $address1)))
		{
			return false;
		}
		if ($quick)
		{
			if (!$domestic)
			{
				return false;
			}
			$quick_rules = self::parseRegions(self::rulesOf($seller_srl, 'ship_quick_regions'));
			if (count($quick_rules) && !self::regionMatches($quick_rules, $zipcode, $address1))
			{
				return false;
			}
		}
		return true;
	}

	public static function quickAllowed(object $item): bool
	{
		if (!self::quickEnabled() || ($item->allow_quick ?? 'N') !== 'Y' || ($item->is_pin ?? 'N') === 'Y')
		{
			return false;
		}
		$seller_srl = (int)($item->seller_srl ?? 0);
		if (self::isMarketSeller($seller_srl))
		{
			$seller = self::seller($seller_srl);
			return $seller && ($seller->ship_quick ?? 'N') === 'Y';
		}
		return true;
	}

	public static function quickAvailable(array $entries): bool
	{
		$count = 0;
		foreach ($entries as $entry)
		{
			if (!empty($entry->blocked) || ($entry->item->is_pin ?? 'N') === 'Y')
			{
				continue;
			}
			if (!self::quickAllowed($entry->item))
			{
				return false;
			}
			$count++;
		}
		return $count > 0;
	}

	public static function zoneFeeFor(int $seller_srl, int $zone_index): ?int
	{
		if (!self::isMarketSeller($seller_srl))
		{
			return null;
		}
		$seller = self::seller($seller_srl);
		$map = json_decode((string)($seller->ship_zone_fees ?? ''), true);
		$key = (string)$zone_index;
		if (!is_array($map) || !array_key_exists($key, $map) || $map[$key] === '' || $map[$key] === null)
		{
			return null;
		}
		return max(0, (int)$map[$key]);
	}

	protected static function parcelGroups(array $entries): array
	{
		$groups = Seller::isOpen() ? Seller::groupEntries($entries) : [0 => $entries];
		$out = [];
		foreach ($groups as $seller_srl => $group)
		{
			foreach ($group as $entry)
			{
				if (empty($entry->blocked) && ($entry->item->is_pin ?? 'N') !== 'Y')
				{
					$out[(int)$seller_srl] = $group;
					break;
				}
			}
		}
		return $out;
	}

	public static function zoneFees(array $entries, string $zipcode, string $address1, string $country, string $state, string $city, int $listed_total): object
	{
		$out = (object)['shop' => 0, 'sellers' => [], 'total' => 0];
		[$zone_index, $shop_fee] = Cart::extraShipZone($zipcode, $address1, $country, $state, $city, $listed_total);
		if ($zone_index < 0)
		{
			return $out;
		}
		$shop_needed = false;
		foreach (array_keys(self::parcelGroups($entries)) as $seller_srl)
		{
			$own = self::zoneFeeFor((int)$seller_srl, $zone_index);
			if ($own === null)
			{
				$shop_needed = true;
			}
			elseif ($own > 0)
			{
				$out->sellers[(int)$seller_srl] = $own;
			}
		}
		$out->shop = $shop_needed ? $shop_fee : 0;
		$out->total = $out->shop + array_sum($out->sellers);
		return $out;
	}

	public static function effectiveZones(array $entries, int $listed_total): string
	{
		$zones = json_decode((string)(Config::getConfig()->ship_extra_zones ?? '[]'), true);
		$zones = is_array($zones) ? array_values($zones) : [];
		$sellers = array_keys(self::parcelGroups($entries));
		$overridden = false;
		foreach ($sellers as $seller_srl)
		{
			foreach (array_keys($zones) as $zone_index)
			{
				if (self::zoneFeeFor((int)$seller_srl, (int)$zone_index) !== null)
				{
					$overridden = true;
					break 2;
				}
			}
		}
		if (!$overridden)
		{
			return json_encode($zones, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
		}
		foreach ($zones as $zone_index => $zone)
		{
			$zone = (array)$zone;
			$shop_needed = false;
			$sum = 0;
			foreach ($sellers as $seller_srl)
			{
				$own = self::zoneFeeFor((int)$seller_srl, (int)$zone_index);
				if ($own === null)
				{
					$shop_needed = true;
				}
				else
				{
					$sum += $own;
				}
			}
			$zone['fee'] = ($shop_needed ? Cart::zoneFee($zone, $listed_total) : 0) + $sum;
			$zone['tiers'] = [];
			$zones[$zone_index] = $zone;
		}
		return json_encode($zones, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
	}
}
