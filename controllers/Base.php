<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Config as ConfigModel;

require_once __DIR__ . '/../helpers.php';

/**
 * 커머스 모듈.
 *
 * 전략: "오픈마켓 스키마, 독립몰 릴리즈" — 주문은 처음부터 3계층이다:
 *   commerce_order(결제 단위) → commerce_order_seller(판매자별) → commerce_order_item(스냅샷)
 * 독립몰 모드에서도 order_seller 는 1건을 생성한다.
 *
 * 결제는 zittme_pay 에 위임한다 (의존 방향: commerce → zittme_pay 단방향).
 * 부가 모듈 — 엔진 배포본에 포함하지 않는다.
 *
 * 철칙: 주문 금액은 항상 서버가 재계산한다. 재고는 조건부 UPDATE 원자 경로로만 차감한다.
 */
class Base extends \ModuleObject
{
	/**
	 * 기본 인스턴스 주소 (단일 인스턴스 모델).
	 */
	public const DEFAULT_MID = 'shop';

	/**
	 * 주문 상태.
	 */
	public const ORDER_PENDING = 'pending';
	public const ORDER_PAID = 'paid';
	public const ORDER_CANCELLED = 'cancelled';
	public const ORDER_FAILED = 'failed';
	public const ORDER_EXPIRED = 'expired';

	/**
	 * 판매자 하위주문 상태.
	 */
	public const SELLER_PENDING = 'pending';
	public const SELLER_PAID = 'paid';
	public const SELLER_PREPARING = 'preparing';
	public const SELLER_SHIPPING = 'shipping';
	public const SELLER_DELIVERED = 'delivered';
	// 구매확정 — 배송완료 후 구매자가 확정. 확정해야 리뷰를 쓸 수 있다
	public const SELLER_CONFIRMED = 'confirmed';
	public const SELLER_CANCELLED = 'cancelled';
	public const SELLER_REFUNDED = 'refunded';

	/**
	 * 기본 인스턴스 캐시.
	 *
	 * @var object|false|null
	 */
	protected static $_default_instance = null;

	/**
	 * 모듈 설정.
	 *
	 * @return object
	 */
	public static function config(): object
	{
		self::ensureCurrencySchema();
		return ConfigModel::getConfig();
	}

	/**
	 * 다통화 컬럼 자가 치유 (0.2.0 → 0.2.1 핫픽스).
	 *
	 * 0.2.0 배포본이 업데이트 목록에 currency 컬럼을 등록하지 않아, 파일만 교체된
	 * 사이트는 컬럼이 없는 채로 currency 를 참조하는 쿼리가 전부 죽었다.
	 * 관리자가 모듈 업데이트를 누르기 전에도 동작하도록 요청 경로에서 직접 붙인다.
	 * 성공하면 캐시에 표시해 이후 요청에서는 검사하지 않는다.
	 *
	 * @return void
	 */
	public static function ensureCurrencySchema(): void
	{
		static $checked = false;
		if ($checked || \Zittme\Framework\Cache::get('commerce_currency_schema_ok'))
		{
			$checked = true;
			return;
		}
		$checked = true;

		try
		{
			$oDB = \DB::getInstance();
			if (!$oDB->isColumnExists('commerce_order', 'currency'))
			{
				$oDB->addColumn('commerce_order', 'currency', 'varchar', 8, 'KRW', true);
			}
			if (!$oDB->isColumnExists('commerce_order', 'exchange_rate'))
			{
				$oDB->addColumn('commerce_order', 'exchange_rate', 'varchar', 16);
			}
			if (!$oDB->isTableExists('commerce_item_price'))
			{
				$oDB->createTableByXmlFile(\RX_BASEDIR . 'modules/commerce/schemas/commerce_item_price.xml');
			}
			\Zittme\Framework\Cache::set('commerce_currency_schema_ok', true, 86400);
		}
		catch (\Throwable $e)
		{
			// 실패해도 화면을 죽이지 않는다. 다음 요청이나 모듈 업데이트에서 다시 시도된다.
		}
	}

	/**
	 * zittme_pay 사용 가능 여부. 없으면 결제만 비활성.
	 *
	 * @return bool
	 */
	public static function isPayAvailable(): bool
	{
		return class_exists('\\Zittme\\Modules\\Zittme_pay\\PayService')
			&& \Zittme\Modules\Zittme_pay\PayService::isAvailable();
	}

	/**
	 * 기본 인스턴스(shop mid).
	 *
	 * @return ?object
	 */
	public static function getDefaultInstance(): ?object
	{
		if (self::$_default_instance === null)
		{
			$list = \ModuleModel::getMidList((object)['module' => 'commerce']);
			self::$_default_instance = is_array($list) && count($list) ? reset($list) : false;
		}
		return self::$_default_instance ?: null;
	}

	/**
	 * 기본 판매자 (독립몰 = 사이트 운영자).
	 *
	 * @return ?object
	 */
	public static function getDefaultSeller(): ?object
	{
		$output = executeQuery('commerce.getSellerList', (object)['list_count' => 1]);
		if (!$output->toBool() || empty($output->data))
		{
			return null;
		}
		$data = is_array($output->data) ? reset($output->data) : $output->data;
		return (is_object($data) && !empty($data->seller_srl)) ? $data : null;
	}

	/**
	 * 지금 시각 (14자리).
	 *
	 * @return string
	 */
	public static function now(): string
	{
		return date('YmdHis');
	}

	/**
	 * 주문번호 생성. 예: O20260730-4F7A2C
	 *
	 * @return string
	 */
	public static function generateOrderCode(): string
	{
		$prefix = trim((string)(self::config()->code_prefix ?? 'O'));
		return sprintf('%s%s-%s', $prefix !== '' ? $prefix : 'O', date('Ymd'), strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)));
	}
}
