<?php

namespace Zittme\Modules\Commerce\Models;


class Config
{

	public const DEFAULTS = [
		'enabled' => 'Y',

		'market_mode' => 'single',

		'market_commission' => 10,

		'market_apply' => 'N',
		'code_prefix' => 'O',

		'allow_guest' => 'Y',

		'pending_minutes' => 60,

		'default_ship_fee' => 3000,

		'free_ship_over' => 50000,

		'ship_extra_zones' => '[]',

		'sweettracker_api_key' => '',

		'couriers' => '',

		'review_credit_text' => 0,
		'review_credit_photo' => 0,

		'claim_days' => 7,
		'ship_guide' => '',
		'claim_guide' => '',

		'item_sticky' => 'N',
		'currency_code_prefix' => 'N',

		'shop_main' => 'list',

		'category_layout' => 'top',

		'item_image_size' => 'M',

		'home_show_recommend' => 'Y',
		'home_show_new' => 'Y',
		'home_show_popular' => 'Y',
		'home_show_sale' => 'Y',
		'home_count' => 8,

		'home_banners' => '[]',

		'privacy_text' => '주문 처리를 위해 이름, 연락처, 배송지 정보를 수집합니다. 수집된 정보는 주문 이행 및 배송 목적으로만 사용됩니다.',
		'privacy_version' => '1.0',
		'retention_days' => 1825,


		'credit_rate' => 1,

		'credit_min_use' => 0,

		'low_stock_default' => 5,
		'notify_low_stock' => 'Y',

		'notify_admin' => 'N',

		'notify_admin_email' => '',


		'notify_admin_group' => 0,

		'notify_admin_new_order' => 'Y',
		'notify_admin_claim' => 'Y',
		'notify_buyer_received' => 'Y',
		'notify_buyer_paid' => 'Y',
		'notify_buyer_shipping' => 'Y',
		'notify_buyer_delivered' => 'N',
		'notify_buyer_claim_done' => 'Y',

		'biz_name' => '',
		'biz_ceo' => '',
		'biz_number' => '',
		'biz_address' => '',
		'biz_tel' => '',
		'biz_note' => '',

		'biz_logo' => '',

		'biz_tax_mode' => 'taxable',

		'vat_rate' => 10,

		'price_includes_tax' => 'Y',


		'base_country' => 'KR',

		'allow_overseas' => 'N',

		'address_mode' => 'kr',

		'use_phone_cc' => 'auto',

		'require_state' => 'N',
		'use_coupon' => 'Y',
		'use_credit' => 'Y',


		'auto_confirm_days' => 0,

		'currencies' => [],

		'currency_fallback' => 'convert',
	];


	protected static $_config = null;


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


		$draft = $_SESSION['commerce_preview'] ?? null;
		$logged = \Context::get('logged_info');
		if (\Context::get('zmc_preview') === 'Y' && is_array($draft) && $logged && Staff::can('display') && time() - (int)($draft['time'] ?? 0) < 3600)
		{
			// 모듈 설정 캐시와 같은 객체라 복사본에만 덧씌운다. 그러지 않으면 같은 요청의 저장이 임시 값을 담아 버린다
			$config = clone $config;
			foreach ((array)($draft['values'] ?? []) as $key => $value)
			{
				$config->{$key} = $value;
			}
		}

		return self::$_config = $config;
	}


	public static function setConfig(object $config): object
	{
		$output = \ModuleController::getInstance()->updateModuleConfig('commerce', $config);
		self::$_config = null;
		return $output;
	}
}
