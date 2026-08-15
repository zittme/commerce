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
	 * 상점 운영 중지 관문.
	 *
	 * 중지해도 화면이 열리면 손님이 담고 결제까지 시도하게 된다. 주문만 막는 것으로는
	 * 부족해서 화면 진입에서 함께 끊는다. 관리자는 준비 상태를 봐야 하므로 통과시킨다.
	 *
	 * @return void
	 */
	protected static function assertShopEnabled(): void
	{
		if ((self::config()->enabled ?? 'Y') === 'Y')
		{
			return;
		}
		$logged_info = \Context::get('logged_info');
		if ($logged_info && $logged_info->is_admin === 'Y')
		{
			\Context::set('shop_disabled_notice', true);
			return;
		}
		throw new \Zittme\Framework\Exceptions\TargetNotFound;
	}

	/**
	 * 스키마 자가 치유.
	 *
	 * 파일만 교체된 사이트에서도 동작하도록, 모듈 업데이트와 별개로 요청 경로에서
	 * 누락 컬럼을 직접 붙인다. 성공하면 캐시에 표시해 이후 요청에서는 검사하지 않는다.
	 *
	 * @return void
	 */
	public static function ensureCurrencySchema(): void
	{
		static $checked = false;
		if ($checked || \Zittme\Framework\Cache::get('commerce_schema_ok_v6'))
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
			// 가격순 정렬 전용 실판매가
			if (!$oDB->isColumnExists('commerce_item', 'effective_price'))
			{
				$oDB->addColumn('commerce_item', 'effective_price', 'bigint', null, 0, true);
				\Zittme\Framework\DB::getInstance()->getHandle()
					->exec('UPDATE `' . \Zittme\Modules\Commerce\Controllers\Install::dbPrefix() . 'commerce_item` SET effective_price = CASE WHEN sale_price > 0 THEN sale_price ELSE price END');
			}
			// 주문 시점 SKU 스냅샷
			if (!$oDB->isColumnExists('commerce_order_item', 'sku'))
			{
				$oDB->addColumn('commerce_order_item', 'sku', 'varchar', 100);
			}
			// 리뷰는 주문건 단위. 나중에 붙이는 컬럼은 기본값·notnull 을 함께 지정한다
			if (!$oDB->isColumnExists('commerce_review', 'order_srl'))
			{
				$oDB->addColumn('commerce_review', 'order_srl', 'bigint', null, 0, true);
			}
			// 연결이 비어 있는 리뷰는 상품 단위(0)로 취급되므로 컬럼 추가 여부와 무관하게 보정한다
			self::backfillReviewOrders();
			// 진열 순서 규약: 최신이 앞, list_order = -srl. 미정렬(0)과 등록순 값(+srl)을 맞춘다
			\Zittme\Framework\DB::getInstance()->getHandle()
				->exec('UPDATE `' . \Zittme\Modules\Commerce\Controllers\Install::dbPrefix() . 'commerce_item` SET list_order = -item_srl WHERE list_order = 0 OR list_order = item_srl');
			\Zittme\Framework\Cache::set('commerce_schema_ok_v6', true, 86400);
		}
		catch (\Throwable $e)
		{
			// 실패해도 화면을 죽이지 않는다. 다음 요청이나 모듈 업데이트에서 다시 시도된다.
		}
	}

	/**
	 * 주문 연결이 비어 있는 리뷰에 확정 주문을 채운다.
	 *
	 * @return void
	 */
	public static function backfillReviewOrders(): void
	{
		try
		{
			$p = Install::dbPrefix();
			$handle = \Zittme\Framework\DB::getInstance()->getHandle();
			// 컬럼을 나중에 붙인 사이트는 기존 행이 NULL 일 수 있다. 0 과 NULL 을 함께 본다
			$stmt = $handle->query('SELECT 1 FROM `' . $p . 'commerce_review` WHERE (order_srl = 0 OR order_srl IS NULL) AND member_srl > 0 LIMIT 1');
			// 코어는 버퍼링 없는 쿼리를 쓴다. 다음 쿼리 전에 커서를 닫는다
			$pending = false;
			if ($stmt)
			{
				$pending = (bool)$stmt->fetchColumn();
				$stmt->closeCursor();
			}
			if (!$pending)
			{
				return;
			}
			$handle->exec(
				'UPDATE `' . $p . 'commerce_review` AS r SET r.order_srl = COALESCE((' .
				'SELECT MIN(o.order_srl) FROM `' . $p . 'commerce_order_item` AS oi' .
				' JOIN `' . $p . 'commerce_order` AS o ON o.order_srl = oi.order_srl' .
				' JOIN `' . $p . 'commerce_order_seller` AS os ON os.order_seller_srl = oi.order_seller_srl' .
				" WHERE o.member_srl = r.member_srl AND oi.item_srl = r.item_srl AND o.status = 'paid' AND os.status = 'confirmed'" .
				'), 0) WHERE (r.order_srl = 0 OR r.order_srl IS NULL) AND r.member_srl > 0'
			);
		}
		catch (\Throwable $e)
		{
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
