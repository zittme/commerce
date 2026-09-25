<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Config as ConfigModel;

require_once __DIR__ . '/../helpers.php';

class Base extends \ModuleObject
{
	public const DEFAULT_MID = 'shop';

	public const ORDER_PENDING = 'pending';
	public const ORDER_PAID = 'paid';
	public const ORDER_CANCELLED = 'cancelled';
	public const ORDER_FAILED = 'failed';
	public const ORDER_EXPIRED = 'expired';

	public const SELLER_PENDING = 'pending';
	public const SELLER_PAID = 'paid';
	public const SELLER_PREPARING = 'preparing';
	public const SELLER_SHIPPING = 'shipping';
	public const SELLER_DELIVERED = 'delivered';
	public const SELLER_CONFIRMED = 'confirmed';
	public const SELLER_CANCELLED = 'cancelled';
	public const SELLER_REFUNDED = 'refunded';

	protected static $_default_instance = null;

	public static function config(): object
	{
		self::ensureCurrencySchema();
		return ConfigModel::getConfig();
	}

	protected static function assertShopEnabled(): void
	{
		if ((self::config()->enabled ?? 'Y') === 'Y')
		{
			return;
		}
		if (\Zittme\Modules\Commerce\Models\Staff::isStaff())
		{
			\Context::set('shop_disabled_notice', true);
			return;
		}
		throw new \Zittme\Framework\Exceptions\TargetNotFound;
	}

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
			if (!$oDB->isColumnExists('commerce_item', 'effective_price'))
			{
				$oDB->addColumn('commerce_item', 'effective_price', 'bigint', null, 0, true);
				\Zittme\Framework\DB::getInstance()->getHandle()
					->exec('UPDATE `' . \Zittme\Modules\Commerce\Controllers\Install::dbPrefix() . 'commerce_item` SET effective_price = CASE WHEN sale_price > 0 THEN sale_price ELSE price END');
			}
			if (!$oDB->isColumnExists('commerce_order_item', 'sku'))
			{
				$oDB->addColumn('commerce_order_item', 'sku', 'varchar', 100);
			}
			if (!$oDB->isColumnExists('commerce_review', 'order_srl'))
			{
				$oDB->addColumn('commerce_review', 'order_srl', 'bigint', null, 0, true);
			}
			self::backfillReviewOrders();
			\Zittme\Framework\DB::getInstance()->getHandle()
				->exec('UPDATE `' . \Zittme\Modules\Commerce\Controllers\Install::dbPrefix() . 'commerce_item` SET list_order = -item_srl WHERE list_order = 0 OR list_order = item_srl');
			\Zittme\Framework\Cache::set('commerce_schema_ok_v6', true, 86400);
		}
		catch (\Throwable $e)
		{
		}
	}

	public static function backfillReviewOrders(): void
	{
		try
		{
			$p = Install::dbPrefix();
			$handle = \Zittme\Framework\DB::getInstance()->getHandle();
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

	public static function isPayAvailable(): bool
	{
		return class_exists('\\Zittme\\Modules\\Zittme_pay\\PayService')
			&& \Zittme\Modules\Zittme_pay\PayService::isAvailable();
	}

	public static function getDefaultInstance(): ?object
	{
		if (self::$_default_instance === null)
		{
			$list = \ModuleModel::getMidList((object)['module' => 'commerce']);
			self::$_default_instance = is_array($list) && count($list) ? reset($list) : false;
		}
		return self::$_default_instance ?: null;
	}

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

	public static function now(): string
	{
		return date('YmdHis');
	}

	public static function generateOrderCode(): string
	{
		$prefix = trim((string)(self::config()->code_prefix ?? 'O'));
		return sprintf('%s%s-%s', $prefix !== '' ? $prefix : 'O', date('Ymd'), strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)));
	}
}
