<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 커머스 설정.
 */
class Config
{
	/**
	 * 기본값. (extra_var 미설정 undefined 함정 방지 — 반드시 기본값을 둔다)
	 */
	public const DEFAULTS = [
		'enabled' => 'Y',
		// single(독립몰) / open(오픈마켓, 2차)
		'market_mode' => 'single',
		'code_prefix' => 'O',
		// 비회원 주문 허용
		'allow_guest' => 'Y',
		// 결제 대기 주문 유지 시간(분) — 지나면 만료·재고 반환
		'pending_minutes' => 60,
		// 기본 배송비 (판매자 정책이 없을 때)
		'default_ship_fee' => 3000,
		// 이 금액 이상 무료배송 (0 = 사용 안 함)
		'free_ship_over' => 50000,
		// 취소·반품 신청 가능 기간(배송완료 후 일수)
		'claim_days' => 7,
		// 개인정보
		'privacy_text' => '주문 처리를 위해 이름, 연락처, 배송지 정보를 수집합니다. 수집된 정보는 주문 이행 및 배송 목적으로만 사용됩니다.',
		'privacy_version' => '1.0',
		'retention_days' => 1825,
		// 적립금 — 자체 원장 (코어 point 는 커뮤니티 포인트라 연동하지 않는다)
		// 결제 완료 시 실결제 상품 금액의 % 적립 (0 = 사용 안 함)
		'credit_rate' => 1,
		// 최소 사용 단위 (0 = 제한 없음)
		'credit_min_use' => 0,
		// 알림
		'notify_admin' => 'N',
		'notify_admin_email' => '',
	];

	/**
	 * @var ?object
	 */
	protected static $_config = null;

	/**
	 * 설정 읽기 (빠진 키는 기본값).
	 *
	 * @return object
	 */
	public static function getConfig(): object
	{
		if (self::$_config !== null)
		{
			return self::$_config;
		}

		$config = \ModuleModel::getModuleConfig('commerce');
		if (!is_object($config))
		{
			$config = new \stdClass;
		}
		foreach (self::DEFAULTS as $key => $value)
		{
			if (!isset($config->{$key}))
			{
				$config->{$key} = $value;
			}
		}

		return self::$_config = $config;
	}

	/**
	 * 설정 저장.
	 *
	 * @param object $config
	 * @return object
	 */
	public static function setConfig(object $config): object
	{
		$output = \ModuleController::getInstance()->updateModuleConfig('commerce', $config);
		self::$_config = null;
		return $output;
	}
}
