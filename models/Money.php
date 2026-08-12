<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 통화 유틸 — 짓미페이의 공용 환율(Currency)을 커머스 어휘로 감싼 것.
 *
 * 커머스 원장(상품 KRW 가격, 통계)은 KRW 기준이고, 외화는 판매 통화 목록에 넣은 것만 쓴다.
 * 짓미페이 모듈이 없는 설치본에서는 KRW 단일 통화로만 동작한다 (외화 기능 자동 비활성).
 */
class Money
{
	/**
	 * 짓미페이 Currency 클래스. 없으면 null.
	 */
	protected static function currencyClass(): ?string
	{
		$class = '\\Zittme\\Modules\\Zittme_pay\\Models\\Currency';
		return class_exists($class) ? $class : null;
	}

	/**
	 * 판매 통화 목록. KRW 가 항상 앞에 온다.
	 *
	 * @return array ['KRW', 'USD', ...]
	 */
	public static function currencies(): array
	{
		$config = Config::getConfig();
		$list = $config->currencies ?? [];
		if (!is_array($list) || !self::currencyClass())
		{
			$list = [];
		}
		$result = ['KRW'];
		foreach ($list as $code)
		{
			$code = strtoupper(trim((string)$code));
			if (preg_match('/^[A-Z]{3}$/', $code) && $code !== 'KRW' && !in_array($code, $result, true))
			{
				$result[] = $code;
			}
		}
		return $result;
	}

	/**
	 * 다통화 판매가 켜져 있는가.
	 */
	public static function isMultiCurrency(): bool
	{
		return count(self::currencies()) > 1;
	}

	/**
	 * 지금 화면의 표시 통화. 쿠키(shp_currency)로 기억한다.
	 *
	 * @return string
	 */
	public static function current(): string
	{
		$currencies = self::currencies();
		$cookie = strtoupper(trim((string)($_COOKIE['shp_currency'] ?? '')));
		return in_array($cookie, $currencies, true) ? $cookie : 'KRW';
	}

	/**
	 * 1 통화당 KRW 환율. 짓미페이가 없거나 환율 미등록이면 0.
	 */
	public static function rate(string $currency): float
	{
		$class = self::currencyClass();
		return $class ? $class::getRate($currency) : ($currency === 'KRW' ? 1.0 : 0);
	}

	/**
	 * KRW 금액 → 통화 최소단위 정수. 환율이 없으면 -1 (판매 불가 신호).
	 *
	 * @param int $krw
	 * @param string $currency
	 * @return int
	 */
	public static function convertMinor(int $krw, string $currency): int
	{
		if ($currency === 'KRW')
		{
			return $krw;
		}
		$class = self::currencyClass();
		if (!$class)
		{
			return -1;
		}
		$value = $class::fromKRW($krw, $currency);
		if ($value <= 0 && $krw > 0)
		{
			return -1;
		}
		return $class::toMinor($value, $currency);
	}

	/**
	 * 최소단위 정수 → 화면 표기. 예: 1234, 'USD' → $12.34
	 */
	public static function format(int $minor, string $currency): string
	{
		if ($currency === 'KRW')
		{
			return number_format($minor) . '원';
		}
		$class = self::currencyClass();
		if (!$class)
		{
			return number_format($minor);
		}
		return $class::format($class::fromMinor($minor, $currency), $currency);
	}

	/**
	 * KRW 금액을 현재 표시 통화로 바꿔 표기한다. 스킨의 가격 출력 공용 헬퍼.
	 *
	 * 환율이 없어 환산할 수 없으면 KRW 로 표기한다 — 가격이 사라지는 것보다 낫다.
	 *
	 * @param int $krw
	 * @return string
	 */
	public static function text(int $krw): string
	{
		$currency = self::current();
		if ($currency === 'KRW')
		{
			return number_format($krw) . '원';
		}
		$minor = self::convertMinor($krw, $currency);
		if ($minor < 0)
		{
			return number_format($krw) . '원';
		}
		return self::format($minor, $currency);
	}

	/**
	 * 최소단위 정수 → KRW 환산 (통계·적립 판단용).
	 *
	 * @param int $minor
	 * @param string $currency
	 * @param float $rate 0 이면 현재 환율
	 * @return int
	 */
	public static function minorToKRW(int $minor, string $currency, float $rate = 0): int
	{
		if ($currency === 'KRW')
		{
			return $minor;
		}
		$class = self::currencyClass();
		if (!$class)
		{
			return 0;
		}
		return $class::toKRW($class::fromMinor($minor, $currency), $currency, $rate);
	}
}
