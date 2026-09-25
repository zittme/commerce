<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Config as ConfigModel;

class Install extends Base
{
	public const ADDED_COLUMNS = [
		['commerce_order', 'channel', 'varchar', 10],
		['commerce_item_option', 'option_type', 'varchar', 10],
		['commerce_item', 'badges', 'varchar', 250],
		['commerce_review', 'images', 'text', null],
		['commerce_item', 'images', 'text', null],
		['commerce_item', 'sale_start', 'char', 14],
		['commerce_item', 'sale_end', 'char', 14],
		['commerce_item', 'min_qty', 'int', null],
		['commerce_item', 'max_qty', 'int', null],
		['commerce_item', 'tax_type', 'varchar', 10],
		['commerce_item', 'is_adult', 'char', 1],
		['commerce_item', 'option_axes', 'text', null],
		['commerce_item_option', 'combo', 'varchar', 250],
		['commerce_item', 'option_mode', 'varchar', 10],
		['commerce_order_item', 'tax_type', 'varchar', 10],
		['commerce_order_address', 'country', 'varchar', 2],
		['commerce_order_address', 'phone_cc', 'varchar', 6],
		['commerce_order_address', 'state', 'varchar', 80],
		['commerce_order_address', 'city', 'varchar', 80],
		['commerce_address', 'phone_cc', 'varchar', 6],
		['commerce_address', 'country', 'varchar', 2],
		['commerce_address', 'state', 'varchar', 80],
		['commerce_address', 'city', 'varchar', 80],
		['commerce_item', 'low_stock', 'int', null],
		['commerce_item', 'low_stock_alerted', 'char', 1],
		['commerce_item_option', 'low_stock', 'int', null],
		['commerce_item_option', 'low_stock_alerted', 'char', 1],
		['commerce_order', 'credit_used', 'bigint', null],
		['commerce_grade', 'discount_type', 'varchar', 10],
		['commerce_grade', 'discount_value', 'float', null],
		['commerce_item', 'brand_srl', 'bigint', null],
		['commerce_order_item', 'timesale_item_srl', 'bigint', null],
		['commerce_item', 'is_pin', 'char', 1],
		['commerce_item', 'pin_daily_limit', 'int', null],
		['commerce_grade', 'group_srl', 'bigint', null],
		['commerce_item', 'grade_discount', 'char', 1],
		['commerce_item', 'attrs', 'text', null],
		['commerce_order', 'currency', 'varchar', 8],
		['commerce_order', 'exchange_rate', 'varchar', 16],
		['commerce_item', 'effective_price', 'bigint', null],
		['commerce_order_item', 'sku', 'varchar', 100],
		['commerce_review', 'order_srl', 'bigint', null],
		['commerce_seller', 'intro', 'text', null],
		['commerce_seller', 'ship_fee', 'bigint', null],
		['commerce_seller', 'free_ship_over', 'bigint', null],
		['commerce_seller', 'reject_reason', 'varchar', 250],
		['commerce_seller', 'approved_date', 'char', 14],
		['commerce_seller', 'last_update', 'char', 14],
		['commerce_order_item', 'seller_srl', 'bigint', null],
		['commerce_order_item', 'commission_rate', 'decimal', '6,2'],
		['commerce_order_item', 'commission', 'bigint', null],
		['commerce_order_seller', 'commission', 'bigint', null],
		['commerce_order_seller', 'settlement_srl', 'bigint', null],
		['commerce_seller', 'shop_id', 'varchar', 30],
		['commerce_seller', 'shop_prev_id', 'varchar', 30],
		['commerce_seller', 'shop_logo', 'varchar', 250],
		['commerce_seller', 'shop_cover', 'varchar', 250],
		['commerce_seller', 'shop_design', 'text', null],
		['commerce_item', 'seller_category_srl', 'bigint', null],
		['commerce_order_seller', 'operator_fee', 'bigint', null],
		['commerce_order_seller', 'settle_refund', 'bigint', null],
		['commerce_order_seller', 'settle_refund_commission', 'bigint', null],
		['commerce_item', 'hidden_by_market', 'char', 1],
		['commerce_seller', 'carry_balance', 'bigint', null],
		['commerce_settlement', 'carry_in', 'bigint', null],
		['commerce_settlement', 'carry_out', 'bigint', null],
	];

	public const ADDED_INDEXES = [
		['commerce_seller', 'unique_shop_id', 'shop_id', true],
		['commerce_seller', 'idx_shop_prev_id', 'shop_prev_id', false],
		['commerce_item', 'idx_seller_category_srl', 'seller_category_srl', false],
		['commerce_order_seller', 'idx_settlement_srl', 'settlement_srl', false],
		['commerce_order_item', 'idx_seller_srl', 'seller_srl', false],
		['commerce_settlement_adjust', 'unique_settlement_os', ['settlement_srl', 'order_seller_srl'], true],
		['commerce_item', 'idx_hidden_by_market', 'hidden_by_market', false],
	];

	public const LATE_TABLES = ['commerce_brand', 'commerce_staff', 'commerce_audit', 'commerce_timesale', 'commerce_timesale_item', 'commerce_pin', 'commerce_settlement', 'commerce_seller_category', 'commerce_settlement_adjust', 'commerce_seller_shopid'];

	public const ZERO_DEFAULT_COLUMNS = [
		['commerce_seller', 'ship_fee'],
		['commerce_seller', 'free_ship_over'],
		['commerce_order_item', 'seller_srl'],
		['commerce_order_item', 'commission_rate'],
		['commerce_order_item', 'commission'],
		['commerce_order_seller', 'commission'],
		['commerce_order_seller', 'settlement_srl'],
		['commerce_item', 'seller_category_srl'],
		['commerce_order_seller', 'operator_fee'],
		['commerce_order_seller', 'settle_refund'],
		['commerce_order_seller', 'settle_refund_commission'],
		['commerce_item', 'pin_daily_limit'],
		['commerce_order_item', 'timesale_item_srl'],
		['commerce_seller', 'carry_balance'],
		['commerce_settlement', 'carry_in'],
		['commerce_settlement', 'carry_out'],
	];

	public const N_DEFAULT_COLUMNS = [
		['commerce_item', 'is_pin'],
		['commerce_item', 'hidden_by_market'],
	];

	public function moduleInstall()
	{
		$this->prepareConfig();
		self::createDefaultSeller();
		self::createDefaultInstance();
		self::registerNamespace();
		self::enableMemberPhoneField();
		return new \BaseObject();
	}

	protected static function enableMemberPhoneField(): void
	{
		try
		{
			$member_config = \ModuleModel::getModuleConfig('member');
			if (!is_object($member_config) || !isset($member_config->signupForm) || !is_array($member_config->signupForm))
			{
				return;
			}
			$changed = false;
			foreach ($member_config->signupForm as $field)
			{
				if (is_object($field) && ($field->name ?? '') === 'phone_number' && ($field->isUse ?? false) !== true)
				{
					$field->isUse = true;
					$changed = true;
				}
			}
			if ($changed)
			{
				\ModuleController::getInstance()->insertModuleConfig('member', $member_config);
			}
		}
		catch (\Exception $e)
		{
		}
	}

	protected static function registerNamespace(): void
	{
		$name = 'Zittme\\Modules\\Commerce';
		$namespaces = config('namespaces') ?? [];
		if (!is_array($namespaces))
		{
			$namespaces = [];
		}
		if (isset($namespaces['mapping'][$name]))
		{
			return;
		}
		$namespaces['mapping'][$name] = 'modules/commerce';
		$regexp = [];
		foreach ($namespaces['mapping'] as $ns => $path)
		{
			$regexp[] = preg_quote(strtr($ns, '\\', '/'), '!');
		}
		usort($regexp, function($a, $b) { return strlen($b) - strlen($a); });
		$namespaces['regexp'] = '!^(' . implode('|', $regexp) . ')/((?:\\w+/)*)(\\w+)$!';
		\Zittme\Framework\Config::set('namespaces', $namespaces);
		\Zittme\Framework\Config::save();
	}

	public function checkUpdate()
	{
		$config = \ModuleModel::getModuleConfig('commerce');
		if (!is_object($config) || !isset($config->enabled))
		{
			return true;
		}
		if (!self::getDefaultSeller())
		{
			return true;
		}
		if (!self::getDefaultInstance())
		{
			return true;
		}

		$oDB = \DB::getInstance();
		foreach (self::ADDED_COLUMNS as [$table, $column])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				return true;
			}
		}

		foreach (['commerce_coupon', 'commerce_coupon_issue', 'commerce_credit_balance', 'commerce_credit_log', 'commerce_grade', 'commerce_member_grade', 'commerce_stock_log', 'commerce_review', 'commerce_inquiry', 'commerce_address', 'commerce_tracking', 'commerce_promotion', 'commerce_promotion_item', 'commerce_badge', 'commerce_item_price', 'commerce_brand', 'commerce_staff', 'commerce_audit', 'commerce_timesale', 'commerce_timesale_item', 'commerce_pin', 'commerce_settlement', 'commerce_seller_category', 'commerce_settlement_adjust', 'commerce_seller_shopid'] as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				return true;
			}
		}
		foreach (self::ADDED_INDEXES as [$table, $index])
		{
			if (!$oDB->isIndexExists($table, $index))
			{
				return true;
			}
		}

		if ($oDB->isTableExists('commerce_grade'))
		{
			try
			{
				if (self::isGradeRateInt())
				{
					return true;
				}
			}
			catch (\Exception $e)
			{
			}
		}

		try
		{
			if (self::isSellerRateInt())
			{
				return true;
			}
		}
		catch (\Exception $e)
		{
		}

		if ($oDB->isTableExists('commerce_item') && self::hasUnorderedItems())
		{
			return true;
		}

		return false;
	}

	protected static function hasUnorderedItems(): bool
	{
		try
		{
			$stmt = \Zittme\Framework\DB::getInstance()->getHandle()
				->query('SELECT COUNT(*) FROM `' . self::dbPrefix() . 'commerce_item` WHERE list_order = 0 OR list_order = item_srl');
			if (!$stmt)
			{
				return false;
			}
			$count = (int)$stmt->fetchColumn();
			$stmt->closeCursor();
			return $count > 0;
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}

	public function moduleUpdate()
	{
		$this->prepareConfig();
		self::createDefaultSeller();
		self::createDefaultInstance();
		self::enableMemberPhoneField();
		self::registerNamespace();

		$oDB = \DB::getInstance();
		foreach (self::LATE_TABLES as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				$oDB->createTable(__DIR__ . '/../schemas/' . $table . '.xml');
			}
		}
		foreach (self::ADDED_COLUMNS as [$table, $column, $type, $size])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				if (in_array([$table, $column], self::ZERO_DEFAULT_COLUMNS, true))
				{
					$oDB->addColumn($table, $column, $type, $size, 0, true);
				}
				elseif (in_array([$table, $column], self::N_DEFAULT_COLUMNS, true))
				{
					$oDB->addColumn($table, $column, $type, $size, 'N', true);
				}
				else
				{
					$oDB->addColumn($table, $column, $type, $size);
				}
			}
		}

		foreach (self::ADDED_INDEXES as [$table, $index, $column, $unique])
		{
			$columns = (array)$column;
			if ($oDB->isTableExists($table) && $oDB->isColumnExists($table, $columns[0]) && !$oDB->isIndexExists($table, $index))
			{
				$added = $oDB->addIndex($table, $index, $columns, $unique ? 'UNIQUE' : '');
				if (!$added->toBool())
				{
					error_log('commerce: index ' . $table . '.' . $index . ' failed: ' . $added->getMessage());
				}
			}
		}

		self::alterToDecimal('commerce_seller', 'commission_rate', 'DECIMAL(6,2) NULL DEFAULT NULL');
		self::alterToDecimal('commerce_order_item', 'commission_rate', 'DECIMAL(6,2) NOT NULL DEFAULT 0');

		if ($oDB->isTableExists('commerce_grade'))
		{
			try
			{
				if (self::isGradeRateInt())
				{
					// SHOW/ALTER 는 프레임워크의 자동 프리픽스 재작성과 충돌하므로 PDO 핸들로 직접 실행
					\Zittme\Framework\DB::getInstance()->getHandle()->exec(
						'ALTER TABLE `' . self::dbPrefix() . 'commerce_grade` MODIFY `credit_rate` DECIMAL(6,2) NOT NULL DEFAULT 0'
					);
				}
			}
			catch (\Exception $e)
			{
			}
		}

		if ($oDB->isTableExists('commerce_item') && self::hasUnorderedItems())
		{
			try
			{
				\Zittme\Framework\DB::getInstance()->getHandle()
					->exec('UPDATE `' . self::dbPrefix() . 'commerce_item` SET list_order = -item_srl WHERE list_order = 0 OR list_order = item_srl');
			}
			catch (\Throwable $e)
			{
			}
		}

		Base::backfillReviewOrders();

		if ($oDB->isTableExists('commerce_item'))
		{
			try
			{
				\Zittme\Framework\DB::getInstance()->getHandle()
					->exec('UPDATE `' . self::dbPrefix() . 'commerce_item` SET effective_price = CASE WHEN sale_price > 0 THEN sale_price ELSE price END WHERE effective_price = 0 OR effective_price IS NULL');
			}
			catch (\Throwable $e)
			{
			}
		}

		return new \BaseObject();
	}

	public function recompileCache()
	{
	}

	public static function dbPrefix(): string
	{
		return (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
	}

	protected static function isGradeRateInt(): bool
	{
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->query(
			'SHOW COLUMNS FROM `' . self::dbPrefix() . 'commerce_grade` LIKE \'credit_rate\''
		);
		$col = null;
		if ($stmt)
		{
			$col = $stmt->fetchObject() ?: null;
			// 뒤이어 ALTER 를 실행하므로 커서를 반드시 닫는다
			$stmt->closeCursor();
		}
		return $col && stripos((string)$col->Type, 'int') !== false;
	}

	protected static function isSellerRateInt(): bool
	{
		return self::needsDecimal('commerce_seller', 'commission_rate') || self::needsDecimal('commerce_order_item', 'commission_rate');
	}

	protected static function needsDecimal(string $table, string $column): bool
	{
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->query(
			'SHOW COLUMNS FROM `' . self::dbPrefix() . $table . '` LIKE \'' . $column . '\''
		);
		$col = null;
		if ($stmt)
		{
			$col = $stmt->fetchObject() ?: null;
			$stmt->closeCursor();
		}
		return $col && stripos((string)$col->Type, 'decimal') !== 0;
	}

	protected static function alterToDecimal(string $table, string $column, string $definition): void
	{
		try
		{
			if (self::needsDecimal($table, $column))
			{
				\Zittme\Framework\DB::getInstance()->getHandle()->exec(
					'ALTER TABLE `' . self::dbPrefix() . $table . '` MODIFY `' . $column . '` ' . $definition
				);
			}
		}
		catch (\Throwable $e)
		{
			error_log('commerce: ALTER ' . $table . '.' . $column . ' failed: ' . $e->getMessage());
		}
	}

	protected function prepareConfig(): void
	{
		$config = \ModuleModel::getModuleConfig('commerce');
		if (!is_object($config))
		{
			$config = new \stdClass;
		}

		$changed = false;
		foreach (ConfigModel::DEFAULTS as $key => $value)
		{
			if (!isset($config->{$key}))
			{
				$config->{$key} = $value;
				$changed = true;
			}
		}

		if ($changed)
		{
			ConfigModel::setConfig($config);
		}
	}

	protected static function createDefaultSeller(): void
	{
		if (self::getDefaultSeller())
		{
			return;
		}

		executeQuery('commerce.insertSeller', (object)[
			'seller_srl' => getNextSequence(),
			'member_srl' => 0,
			'shop_name' => \Context::getSiteTitle() ?: 'Shop',
			'status' => 'active',
			'regdate' => self::now(),
		]);
	}

	protected static function createDefaultInstance(): void
	{
		if (self::getDefaultInstance())
		{
			return;
		}

		$mid = self::DEFAULT_MID;
		if (\ModuleModel::isIDExists($mid))
		{
			$mid = \ModuleModel::getNextAvailableMid($mid) ?: ($mid . '_' . time());
		}

		\ModuleController::getInstance()->insertModule((object)[
			'mid' => $mid,
			'module' => 'commerce',
			'browser_title' => lang('commerce.commerce') ?: 'Shop',
			'description' => '',
			'layout_srl' => -1,
			'mlayout_srl' => -1,
			'skin' => '/USE_DEFAULT/',
			'mskin' => '/USE_DEFAULT/',
			'isMenuCreate' => false,
		]);

		self::$_default_instance = null;
	}
}
