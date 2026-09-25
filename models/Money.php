<?php

namespace Zittme\Modules\Commerce\Models;

class Money
{
	protected static function currencyClass(): ?string
	{
		$class = '\\Zittme\\Modules\\Zittme_pay\\Models\\Currency';
		return class_exists($class) ? $class : null;
	}

	public static function base(): string
	{
		static $base = null;
		if ($base !== null)
		{
			return $base;
		}
		$base = 'KRW';
		if (class_exists('\\Zittme\\Modules\\Zittme_pay\\Models\\Config'))
		{
			$code = strtoupper(trim((string)(\Zittme\Modules\Zittme_pay\Models\Config::getConfig()->currency ?? '')));
			if (preg_match('/^[A-Z]{3}$/', $code))
			{
				$base = $code;
			}
		}
		return $base;
	}

	public static function isZeroDecimal(string $currency = ''): bool
	{
		$currency = $currency !== '' ? strtoupper($currency) : self::base();
		$class = self::currencyClass();
		return $class ? $class::isZeroDecimal($currency) : true;
	}

	public static function currencies(): array
	{
		$base = self::base();
		$result = [$base];
		if (!self::currencyClass())
		{
			return $result;
		}
		$extra = \Zittme\Modules\Zittme_pay\Models\Config::getConfig()->extra_currencies ?? [];
		$list = is_array($extra) ? $extra : [];
		foreach ($list as $code)
		{
			$code = strtoupper(trim((string)$code));
			if (preg_match('/^[A-Z]{3}$/', $code) && $code !== $base && !in_array($code, $result, true))
			{
				$result[] = $code;
			}
		}
		return $result;
	}

	public static function isMultiCurrency(): bool
	{
		return count(self::currencies()) > 1;
	}

	public static function current(): string
	{
		$currencies = self::currencies();
		$cookie = strtoupper(trim((string)($_COOKIE['shp_currency'] ?? '')));
		return in_array($cookie, $currencies, true) ? $cookie : self::base();
	}

	protected static function krwRate(string $currency): float
	{
		if (strtoupper($currency) === 'KRW')
		{
			return 1.0;
		}
		$class = self::currencyClass();
		return $class ? $class::getRate($currency) : 0;
	}

	public static function rate(string $currency): float
	{
		if (strtoupper($currency) === self::base())
		{
			return 1.0;
		}
		$cur = self::krwRate($currency);
		$base = self::krwRate(self::base());
		if ($cur <= 0 || $base <= 0)
		{
			return 0;
		}
		return $cur / $base;
	}

	public static function convertMinor(int $amount, string $currency): int
	{
		$currency = strtoupper($currency);
		if ($currency === self::base())
		{
			return $amount;
		}
		$class = self::currencyClass();
		$rate = self::rate($currency);
		if (!$class || $rate <= 0)
		{
			return -1;
		}
		$value = $class::fromMinor($amount, self::base()) / $rate;
		if ($value <= 0 && $amount > 0)
		{
			return -1;
		}
		return $class::toMinor($value, $currency);
	}

	public static function useWonSuffix(): bool
	{
		return \Context::getLangType() === 'ko';
	}

	public static function format(int $minor, string $currency): string
	{
		if ($currency === 'KRW')
		{
			return self::useWonSuffix() ? number_format($minor) . '원' : 'KRW ' . number_format($minor);
		}
		$class = self::currencyClass();
		if (!$class)
		{
			return number_format($minor);
		}
		$decimals = self::isZeroDecimal($currency) ? 0 : 2;
		return self::symbol($currency) . number_format($class::fromMinor($minor, $currency), $decimals, '.', ',');
	}

	public static function formatItem(int $minor, string $currency): string
	{
		$text = self::format($minor, $currency);
		if (self::isZeroDecimal($currency))
		{
			return $text;
		}
		// 소수 두 자리가 모두 0 일 때만 뗀다. 0 을 여러 개 지우면 천 단위 구분('1,000')까지 깎인다
		return preg_replace('/([.,])00(\D*)$/', '$2', $text);
	}

	public static function text(int $amount): string
	{
		$base = self::base();
		$currency = self::current();
		if ($currency === $base)
		{
			return self::format($amount, $base);
		}
		$minor = self::convertMinor($amount, $currency);
		if ($minor < 0)
		{
			return self::format($amount, $base);
		}
		return self::format($minor, $currency);
	}

	public static function textItem(int $amount): string
	{
		$base = self::base();
		$currency = self::current();
		if ($currency === $base)
		{
			return self::formatItem($amount, $base);
		}
		$minor = self::convertMinor($amount, $currency);
		return $minor < 0 ? self::formatItem($amount, $base) : self::formatItem($minor, $currency);
	}

	public static function minorToBase(int $minor, string $currency, float $rate = 0): int
	{
		$currency = strtoupper($currency);
		if ($currency === self::base())
		{
			return $minor;
		}
		$class = self::currencyClass();
		if (!$class)
		{
			return $minor;
		}
		$rate = $rate > 0 ? $rate : self::rate($currency);
		if ($rate <= 0)
		{
			return $minor;
		}
		return $class::toMinor($class::fromMinor($minor, $currency) * $rate, self::base());
	}

	/**
	 * @deprecated minorToBase 로 대체. 기준 통화가 KRW 인 설치본에서는 동작이 같다.
	 */
	public static function minorToKRW(int $minor, string $currency, float $rate = 0): int
	{
		return self::minorToBase($minor, $currency, $rate);
	}

	public static function inputToMinor($input): int
	{
		$raw = str_replace(',', '', trim((string)$input));
		if ($raw === '' || !preg_match('/^-?\d*\.?\d*$/', $raw))
		{
			$raw = preg_replace('/[^\-0-9.]/', '', $raw);
		}
		if ($raw === '' || $raw === '-' || $raw === '.')
		{
			return 0;
		}
		if (self::isZeroDecimal())
		{
			return (int)round((float)$raw);
		}
		return (int)round((float)$raw * 100);
	}

	public static function minorToInput(int $minor): string
	{
		if (self::isZeroDecimal())
		{
			return (string)$minor;
		}
		return number_format($minor / 100, 2, '.', '');
	}

	public static function unitLabel(): string
	{
		if (self::base() !== 'KRW')
		{
			return self::base();
		}
		return self::useWonSuffix() ? '원' : 'KRW';
	}

	public static function symbol(string $currency = ''): string
	{
		$currency = $currency !== '' ? strtoupper($currency) : self::base();
		if ($currency === 'KRW')
		{
			return self::useWonSuffix() ? '' : 'KRW ';
		}
		$class = '\\Zittme\\Modules\\Zittme_pay\\Models\\Currency';
		$map = class_exists($class) ? $class::SYMBOLS : [];
		$symbol = (string)($map[$currency] ?? ($currency . ' '));
		if ((Config::getConfig()->currency_code_prefix ?? 'N') !== 'Y' && preg_match('/^[A-Z]{3} (\S+)$/', $symbol, $m))
		{
			$symbol = $m[1];
		}
		return $symbol;
	}
}
