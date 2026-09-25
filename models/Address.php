<?php

namespace Zittme\Modules\Commerce\Models;

class Address
{
	public const MODES = ['kr', 'intl', 'both'];

	public static function mode(): string
	{
		$config = Config::getConfig();
		$mode = (string)($config->address_mode ?? 'kr');
		return in_array($mode, self::MODES, true) ? $mode : 'kr';
	}

	public static function needsCountry(): bool
	{
		$config = Config::getConfig();
		return ($config->allow_overseas ?? 'N') === 'Y';
	}

	public static function needsPhoneCode(): bool
	{
		$config = Config::getConfig();
		$value = (string)($config->use_phone_cc ?? 'auto');
		if ($value === 'Y')
		{
			return true;
		}
		if ($value === 'N')
		{
			return false;
		}
		return ($config->allow_overseas ?? 'N') === 'Y';
	}

	public static function requiresState(string $country = ''): bool
	{
		$config = Config::getConfig();
		if (($config->require_state ?? 'N') !== 'Y')
		{
			return false;
		}
		return Region::has(strtoupper(trim($country)) ?: self::baseCountry());
	}

	public static function baseCountry(): string
	{
		$config = Config::getConfig();
		$country = strtoupper(trim((string)($config->base_country ?? '')));
		return preg_match('/^[A-Z]{2}$/', $country) ? $country : 'KR';
	}

	public static function isDomestic(string $country): bool
	{
		$country = strtoupper(trim($country));
		return $country === '' || $country === self::baseCountry();
	}

	public static function isOverseasInput(string $country): bool
	{
		$mode = self::mode();
		if ($mode === 'intl')
		{
			return true;
		}
		if ($mode === 'kr')
		{
			return false;
		}
		return !self::isDomestic($country);
	}

	public static function format($address, bool $with_country = true): string
	{
		$get = function($key) use ($address) {
			$value = is_array($address) ? ($address[$key] ?? '') : ($address->{$key} ?? '');
			return trim((string)$value);
		};

		$country = strtoupper($get('country')) ?: self::baseCountry();
		$domestic = self::isDomestic($country);
		$parts = [];

		if ($country === 'KR')
		{
			$zipcode = $get('zipcode');
			if ($zipcode !== '')
			{
				$parts[] = '(' . $zipcode . ')';
			}
			$parts[] = $get('address1');
			$parts[] = $get('address2');
		}
		else
		{
			$parts[] = $get('address1');
			$parts[] = $get('address2');
			$parts[] = $get('city');
			$state = $get('state');
			if ($state !== '')
			{
				$parts[] = Region::name($state);
			}
			$parts[] = $get('zipcode');
			if ($with_country && !$domestic)
			{
				$parts[] = self::countryName($country);
			}
		}

		return implode(' ', array_filter($parts, function($part) { return $part !== ''; }));
	}

	public static function formatPhone($address): string
	{
		$get = function($key) use ($address) {
			$value = is_array($address) ? ($address[$key] ?? '') : ($address->{$key} ?? '');
			return trim((string)$value);
		};

		$phone = $get('receiver_phone');
		$cc = $get('phone_cc');
		if ($phone === '' || $cc === '')
		{
			return $phone;
		}

		$cc = '+' . ltrim($cc, '+');
		return $cc . ' ' . ltrim($phone, '0');
	}

	public const COUNTRY_CODES = [
		'KR', 'US', 'JP', 'CN', 'TW', 'HK', 'SG', 'VN', 'TH', 'MY', 'ID', 'PH', 'IN',
		'AU', 'NZ', 'CA', 'GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'RU', 'AE', 'MN', 'TR', 'BR', 'MX',
	];

	protected const COUNTRY_NAMES = [
		'en' => [
			'KR' => 'South Korea', 'US' => 'United States', 'JP' => 'Japan', 'CN' => 'China',
			'TW' => 'Taiwan', 'HK' => 'Hong Kong', 'SG' => 'Singapore', 'VN' => 'Vietnam',
			'TH' => 'Thailand', 'MY' => 'Malaysia', 'ID' => 'Indonesia', 'PH' => 'Philippines',
			'IN' => 'India', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'CA' => 'Canada',
			'GB' => 'United Kingdom', 'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy',
			'ES' => 'Spain', 'NL' => 'Netherlands', 'RU' => 'Russia', 'AE' => 'United Arab Emirates',
			'MN' => 'Mongolia', 'TR' => 'Turkiye', 'BR' => 'Brazil', 'MX' => 'Mexico',
		],
		'ko' => [
			'KR' => '대한민국', 'US' => '미국', 'JP' => '일본', 'CN' => '중국',
			'TW' => '대만', 'HK' => '홍콩', 'SG' => '싱가포르', 'VN' => '베트남',
			'TH' => '태국', 'MY' => '말레이시아', 'ID' => '인도네시아', 'PH' => '필리핀',
			'IN' => '인도', 'AU' => '오스트레일리아', 'NZ' => '뉴질랜드', 'CA' => '캐나다',
			'GB' => '영국', 'DE' => '독일', 'FR' => '프랑스', 'IT' => '이탈리아',
			'ES' => '스페인', 'NL' => '네덜란드', 'RU' => '러시아', 'AE' => '아랍에미리트',
			'MN' => '몽골', 'TR' => '튀르키예', 'BR' => '브라질', 'MX' => '멕시코',
		],
		'ja' => [
			'KR' => '韓国', 'US' => 'アメリカ合衆国', 'JP' => '日本', 'CN' => '中国',
			'TW' => '台湾', 'HK' => '香港', 'SG' => 'シンガポール', 'VN' => 'ベトナム',
			'TH' => 'タイ', 'MY' => 'マレーシア', 'ID' => 'インドネシア', 'PH' => 'フィリピン',
			'IN' => 'インド', 'AU' => 'オーストラリア', 'NZ' => 'ニュージーランド', 'CA' => 'カナダ',
			'GB' => 'イギリス', 'DE' => 'ドイツ', 'FR' => 'フランス', 'IT' => 'イタリア',
			'ES' => 'スペイン', 'NL' => 'オランダ', 'RU' => 'ロシア', 'AE' => 'アラブ首長国連邦',
			'MN' => 'モンゴル', 'TR' => 'トルコ', 'BR' => 'ブラジル', 'MX' => 'メキシコ',
		],
		'zh-CN' => [
			'KR' => '韩国', 'US' => '美国', 'JP' => '日本', 'CN' => '中国',
			'TW' => '台湾', 'HK' => '香港', 'SG' => '新加坡', 'VN' => '越南',
			'TH' => '泰国', 'MY' => '马来西亚', 'ID' => '印度尼西亚', 'PH' => '菲律宾',
			'IN' => '印度', 'AU' => '澳大利亚', 'NZ' => '新西兰', 'CA' => '加拿大',
			'GB' => '英国', 'DE' => '德国', 'FR' => '法国', 'IT' => '意大利',
			'ES' => '西班牙', 'NL' => '荷兰', 'RU' => '俄罗斯', 'AE' => '阿联酋',
			'MN' => '蒙古', 'TR' => '土耳其', 'BR' => '巴西', 'MX' => '墨西哥',
		],
		'zh-TW' => [
			'KR' => '韓國', 'US' => '美國', 'JP' => '日本', 'CN' => '中國',
			'TW' => '台灣', 'HK' => '香港', 'SG' => '新加坡', 'VN' => '越南',
			'TH' => '泰國', 'MY' => '馬來西亞', 'ID' => '印尼', 'PH' => '菲律賓',
			'IN' => '印度', 'AU' => '澳洲', 'NZ' => '紐西蘭', 'CA' => '加拿大',
			'GB' => '英國', 'DE' => '德國', 'FR' => '法國', 'IT' => '義大利',
			'ES' => '西班牙', 'NL' => '荷蘭', 'RU' => '俄羅斯', 'AE' => '阿聯',
			'MN' => '蒙古', 'TR' => '土耳其', 'BR' => '巴西', 'MX' => '墨西哥',
		],
	];

	public static function countries(): array
	{
		$lang_type = (string)(\Context::getLangType() ?: 'ko');
		$names = self::COUNTRY_NAMES[$lang_type] ?? self::COUNTRY_NAMES['en'];
		$fallback = self::COUNTRY_NAMES['en'];

		$list = [];
		foreach (self::COUNTRY_CODES as $code)
		{
			$list[$code] = $names[$code] ?? $fallback[$code] ?? $code;
		}
		return $list;
	}

	public static function countryName(string $code): string
	{
		$code = strtoupper(trim($code));
		$countries = self::countries();
		return $countries[$code] ?? $code;
	}
}
