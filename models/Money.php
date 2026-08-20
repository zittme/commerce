<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 통화 유틸 — 짓미페이의 공용 환율(Currency)을 커머스 어휘로 감싼 것.
 *
 * 기준 통화(base)는 짓미페이 기본 설정의 "결제 통화" 하나로 정한다. 커머스 원장
 * (상품 가격, 배송비, 쿠폰, 적립금, 등급, 통계)은 전부 기준 통화의 최소단위 정수다.
 * KRW 는 최소단위가 원이라 기존 데이터와 표기가 그대로 유지된다.
 *
 * 환율·다통화 표시는 기준 통화가 KRW 일 때만 켤 수 있는 부가 기능이다. 공용 환율이
 * "1 통화당 KRW" 기준이라 다른 기준 통화에서는 환산 자체가 성립하지 않는다.
 * 짓미페이 모듈이 없는 설치본에서는 KRW 단일 통화로만 동작한다.
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
	 * 기준 통화. 짓미페이 "결제 통화" 설정을 그대로 따른다.
	 *
	 * @return string
	 */
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

	/**
	 * 기준 통화가 소수부 없는 통화인가 (KRW, JPY 등).
	 *
	 * @param string $currency 비우면 기준 통화
	 * @return bool
	 */
	public static function isZeroDecimal(string $currency = ''): bool
	{
		$currency = $currency !== '' ? strtoupper($currency) : self::base();
		$class = self::currencyClass();
		return $class ? $class::isZeroDecimal($currency) : true;
	}

	/**
	 * 판매 통화 목록. 기준 통화가 항상 앞에 온다.
	 *
	 * 병행 통화는 공용 환율(1통화당 KRW)로 교차 환산하므로 기준 통화와 무관하게 쓸 수 있다.
	 *
	 * @return array ['KRW', 'USD', ...]
	 */
	public static function currencies(): array
	{
		$base = self::base();
		$result = [$base];
		if (!self::currencyClass())
		{
			return $result;
		}
		// 추가 결제 통화는 짓미페이 기본 설정이 유일한 기준이다.
		// 과거 커머스 설정(currencies)을 합치면 짓미페이에서 꺼도 남아 버린다.
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
		return in_array($cookie, $currencies, true) ? $cookie : self::base();
	}

	/**
	 * 1 통화당 KRW 공용 환율. 모르면 0.
	 */
	protected static function krwRate(string $currency): float
	{
		if (strtoupper($currency) === 'KRW')
		{
			return 1.0;
		}
		$class = self::currencyClass();
		return $class ? $class::getRate($currency) : 0;
	}

	/**
	 * 기준 통화 대비 교차 환율 — 1 [currency] 당 기준 통화 금액. 기준 통화는 항상 1.
	 *
	 * 공용 환율이 KRW 기준이라 교차식으로 만든다: KRW(cur) / KRW(base).
	 * 어느 한쪽 환율이 없으면 0 (판매 불가 신호).
	 */
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

	/**
	 * 기준 통화 최소단위 금액 → 다른 통화 최소단위 정수. 환율이 없으면 -1 (판매 불가 신호).
	 *
	 * 기준 통화가 KRW 일 때만 의미가 있다 (다통화 병행 판매).
	 *
	 * @param int $amount 기준 통화 최소단위
	 * @param string $currency
	 * @return int
	 */
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
		// 기준 통화 최소단위 → 기준 통화 값 → 교차 환율로 대상 통화 값 → 최소단위
		$value = $class::fromMinor($amount, self::base()) / $rate;
		if ($value <= 0 && $amount > 0)
		{
			return -1;
		}
		return $class::toMinor($value, $currency);
	}

	/**
	 * 최소단위 정수 → 화면 표기. 예: 1234, 'USD' → $12.34
	 */
	/**
	 * 한국어 화면에서만 '원' 접미사를 쓴다.
	 *
	 * 다른 언어에서 '1,800원' 은 읽히지 않으므로 'KRW 1,800' 으로 낸다.
	 *
	 * @return bool
	 */
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
		return $class::format($class::fromMinor($minor, $currency), $currency);
	}

	/**
	 * 상품 가격 표기 — 소수점 끝이 0 이면 떼고 보여 준다. $200.00 은 $200, $200.50 은 그대로.
	 *
	 * 상품 진열에서만 쓴다. 주문서·명세서·원장은 자릿수를 맞춰야 하므로 format() 을 그대로 쓴다.
	 *
	 * @param int $minor
	 * @param string $currency
	 * @return string
	 */
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

	/**
	 * 기준 통화 금액을 현재 표시 통화로 바꿔 표기한다. 스킨의 가격 출력 공용 헬퍼.
	 *
	 * 환율이 없어 환산할 수 없으면 기준 통화로 표기한다 — 가격이 사라지는 것보다 낫다.
	 *
	 * @param int $amount 기준 통화 최소단위
	 * @return string
	 */
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

	/**
	 * text() 와 같되 상품 진열용 표기 — 소수점 끝이 0 이면 뗀다.
	 *
	 * @param int $amount 기준 통화 최소단위
	 * @return string
	 */
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

	/**
	 * 주문 통화 최소단위 정수 → 기준 통화 환산 (통계·적립 판단용).
	 *
	 * 주문 통화가 기준 통화면 그대로다. 아니면(기준 KRW + 외화 병행 판매 주문)
	 * 결제 시점 환율로 KRW 로 되돌린다.
	 *
	 * @param int $minor
	 * @param string $currency
	 * @param float $rate 0 이면 현재 환율
	 * @return int
	 */
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
		// rate 는 주문에 박제한 교차 환율(1 [currency] 당 기준 통화). 없으면 현재 환율.
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

	/**
	 * 관리자 금액 입력 문자열 → 기준 통화 최소단위 정수.
	 *
	 * KRW 처럼 소수부 없는 통화는 지금까지처럼 정수 그대로다.
	 * 소수부 있는 통화(MXN, USD 등)는 "100.50" 을 10050 으로 저장한다.
	 *
	 * @param mixed $input
	 * @return int
	 */
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

	/**
	 * 최소단위 정수 → 관리자 입력칸 표시값.
	 *
	 * @param int $minor
	 * @return string
	 */
	public static function minorToInput(int $minor): string
	{
		if (self::isZeroDecimal())
		{
			return (string)$minor;
		}
		return number_format($minor / 100, 2, '.', '');
	}

	/**
	 * 관리자 화면에서 금액 입력칸 옆에 붙일 통화 표시.
	 *
	 * @return string
	 */
	public static function unitLabel(): string
	{
		if (self::base() !== 'KRW')
		{
			return self::base();
		}
		return self::useWonSuffix() ? '원' : 'KRW';
	}

	/**
	 * 기준 통화 기호 (MX$, $ 등). KRW 는 접미사 '원' 을 쓰므로 빈 문자열.
	 *
	 * @return string
	 */
	public static function symbol(string $currency = ''): string
	{
		$currency = $currency !== '' ? strtoupper($currency) : self::base();
		if ($currency === 'KRW')
		{
			// 한국어 화면은 접미사 '원' 을 쓰므로 앞에 붙일 것이 없다
			return self::useWonSuffix() ? '' : 'KRW ';
		}
		$class = '\\Zittme\\Modules\\Zittme_pay\\Models\\Currency';
		$map = class_exists($class) ? $class::SYMBOLS : [];
		return (string)($map[$currency] ?? ($currency . ' '));
	}
}
