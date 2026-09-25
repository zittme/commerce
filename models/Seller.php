<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Seller
{
	public const STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

	public const PAGES = ['dashboard', 'items', 'item_edit', 'shipping', 'settlements', 'shop_design', 'shop_cats', 'seller_profile'];

	public const ACTS = [
		'dispCommerceConsole',
		'dispCommerceSellerCenter',
		'procCommerceSellerCenterSaveDesign',
		'procCommerceSellerCenterPreviewDesign',
		'procCommerceSellerCenterUpload',
		'procCommerceSellerCenterSaveCategory',
		'procCommerceSellerCenterDeleteCategory',
		'procCommerceSellerCenterItemCategory',
		'procCommerceSellerCenterSaveShopId',
		'dispCommerceAdminItems',
		'dispCommerceAdminItemEdit',
		'procCommerceAdminInsertItem',
		'procCommerceAdminDeleteItem',
		'procCommerceAdminBulkItemStatus',
		'procCommerceAdminUploadItemImage',
		'procCommerceAdminSaveItemImages',
		'procCommerceAdminBuildCombos',
		'procCommerceAdminInsertOption',
		'procCommerceAdminUpdateOption',
		'procCommerceAdminDeleteOption',
		'procCommerceAdminStockAdjust',
		'dispCommerceAdminShipping',
		'procCommerceAdminBulkShipping',
		'dispCommerceAdminSettlements',
		'dispCommerceAdminExportSettlement',
		'dispCommerceAdminSellerProfile',
		'procCommerceAdminSaveSellerProfile',
	];

	protected static $cache = [];
	protected static $current = false;
	protected static $operator = false;
	protected static $ready = null;

	public static function isOpen(): bool
	{
		return (Base::config()->market_mode ?? 'single') === 'open' && self::schemaReady();
	}

	public static function schemaReady(): bool
	{
		if (self::$ready !== null)
		{
			return self::$ready;
		}
		if (\Zittme\Framework\Cache::get('commerce_market_schema_v4'))
		{
			return self::$ready = true;
		}
		try
		{
			$oDB = \DB::getInstance();
			self::$ready = $oDB->isTableExists('commerce_settlement')
				&& $oDB->isColumnExists('commerce_order_item', 'commission')
				&& $oDB->isColumnExists('commerce_order_seller', 'settlement_srl')
				&& $oDB->isColumnExists('commerce_seller', 'free_ship_over')
				&& $oDB->isColumnExists('commerce_seller', 'shop_id')
				&& $oDB->isColumnExists('commerce_order_seller', 'operator_fee')
				&& $oDB->isColumnExists('commerce_order_seller', 'settle_refund_commission')
				&& $oDB->isTableExists('commerce_seller_category')
				&& $oDB->isTableExists('commerce_settlement_adjust')
				&& $oDB->isColumnExists('commerce_item', 'hidden_by_market')
				&& $oDB->isTableExists('commerce_seller_shopid')
				&& $oDB->isColumnExists('commerce_settlement', 'carry_out')
				&& $oDB->isColumnExists('commerce_seller', 'carry_balance');
		}
		catch (\Throwable $e)
		{
			self::$ready = false;
		}
		if (self::$ready)
		{
			\Zittme\Framework\Cache::set('commerce_market_schema_v4', true, 86400);
		}
		return self::$ready;
	}

	public static function get(int $seller_srl): ?object
	{
		if ($seller_srl <= 0)
		{
			return null;
		}
		if (array_key_exists($seller_srl, self::$cache))
		{
			return self::$cache[$seller_srl];
		}
		$output = executeQuery('commerce.getSeller', (object)['seller_srl' => $seller_srl]);
		$row = ($output->toBool() && is_object($output->data) && !empty($output->data->seller_srl)) ? $output->data : null;
		return self::$cache[$seller_srl] = $row;
	}

	public static function getByMember(int $member_srl): ?object
	{
		if ($member_srl <= 0)
		{
			return null;
		}
		$output = executeQueryArray('commerce.getSellerByMember', (object)['member_srl' => $member_srl]);
		foreach (($output->toBool() ? (array)$output->data : []) as $row)
		{
			if (!empty($row->seller_srl) && !self::isOperator((int)$row->seller_srl))
			{
				return $row;
			}
		}
		return null;
	}

	public static function operatorSrl(): int
	{
		if (self::$operator === false)
		{
			$row = Base::getDefaultSeller();
			self::$operator = $row ? (int)$row->seller_srl : 0;
		}
		return self::$operator;
	}

	public static function isOperator(int $seller_srl): bool
	{
		return $seller_srl <= 0 || $seller_srl === self::operatorSrl();
	}

	public static function keyOf(object $item): int
	{
		$srl = (int)($item->seller_srl ?? 0);
		return self::isOperator($srl) ? self::operatorSrl() : $srl;
	}

	public static function current(): ?object
	{
		if (self::$current !== false)
		{
			return self::$current;
		}
		self::$current = null;
		if (!self::isOpen())
		{
			return null;
		}
		$logged = \Context::get('logged_info');
		$member_srl = is_object($logged) ? (int)$logged->member_srl : 0;
		$row = self::getByMember($member_srl);
		if ($row && $row->status === 'approved')
		{
			self::$current = $row;
		}
		return self::$current;
	}

	public static function commissionRate(?object $seller): float
	{
		if (!$seller || self::isOperator((int)$seller->seller_srl))
		{
			return 0.0;
		}
		$own = $seller->commission_rate ?? null;
		if ($own !== null && $own !== '')
		{
			return max(0.0, min(100.0, round((float)$own, 2)));
		}
		return max(0.0, min(100.0, round((float)(Base::config()->market_commission ?? 0), 2)));
	}

	public static function commissionOf(int $amount, float $rate): int
	{
		return (int)round($amount * $rate / 100);
	}

	public static function groupEntries(array $entries): array
	{
		$groups = [];
		$operator = self::operatorSrl();
		foreach ($entries as $entry)
		{
			if (!empty($entry->blocked))
			{
				continue;
			}
			$groups[self::keyOf($entry->item)][] = $entry;
		}
		if (isset($groups[$operator]))
		{
			$groups = [$operator => $groups[$operator]] + $groups;
		}
		return $groups;
	}

	public static function groupShipFee(int $seller_srl, array $entries): int
	{
		$config = Base::config();
		if (self::isOperator($seller_srl))
		{
			$default_fee = (int)($config->default_ship_fee ?? 0);
			$free_over = (int)($config->free_ship_over ?? 0);
		}
		else
		{
			$seller = self::get($seller_srl);
			$default_fee = (int)($seller->ship_fee ?? 0);
			$free_over = (int)($seller->free_ship_over ?? 0);
		}

		$listed = 0;
		foreach ($entries as $entry)
		{
			$listed += (int)($entry->unit_price_original ?? $entry->unit_price) * (int)$entry->qty;
		}
		if (!count($entries) || ($free_over > 0 && $listed >= $free_over))
		{
			return 0;
		}

		$fee = 0;
		$all_free = true;
		foreach ($entries as $entry)
		{
			$type = $entry->item->ship_fee_type ?? 'default';
			if ($type === 'free' || ($entry->item->is_pin ?? 'N') === 'Y')
			{
				continue;
			}
			$all_free = false;
			$fee = max($fee, $type === 'fixed' ? (int)$entry->item->ship_fee : $default_fee);
		}
		return $all_free ? 0 : $fee;
	}

	public static function shipFees(array $entries): array
	{
		$fees = [];
		foreach (self::groupEntries($entries) as $seller_srl => $group)
		{
			$fees[$seller_srl] = self::groupShipFee((int)$seller_srl, $group);
		}
		return $fees;
	}

	public static function publicInfo(int $seller_srl): ?object
	{
		if (self::isOperator($seller_srl) || !self::isOpen())
		{
			return null;
		}
		$row = self::get($seller_srl);
		if (!$row || !in_array($row->status, ['approved', 'suspended'], true))
		{
			return null;
		}
		return (object)[
			'seller_srl' => (int)$row->seller_srl,
			'shop_name' => (string)$row->shop_name,
			'biz_name' => (string)($row->biz_name ?? ''),
			'ceo_name' => (string)($row->ceo_name ?? ''),
			'biz_no' => (string)($row->biz_no ?? ''),
			'mailorder_no' => (string)($row->mailorder_no ?? ''),
			'biz_address' => (string)($row->biz_address ?? ''),
			'tel' => (string)($row->tel ?? ''),
			'email' => (string)($row->email ?? ''),
			'intro' => (string)($row->intro ?? ''),
			'shop_id' => (string)($row->shop_id ?? ''),
			'active' => $row->status === 'approved',
		];
	}

	public static function filterInput(bool $with_policy = false): object
	{
		$digits = function ($v, $len) { return mb_substr(preg_replace('/[^0-9\-]/', '', (string)$v), 0, $len); };
		$text = function ($key, $len) { return mb_substr(trim((string)\Context::get($key)), 0, $len); };
		$data = (object)[
			'shop_name' => $text('shop_name', 120),
			'biz_name' => $text('biz_name', 120),
			'ceo_name' => $text('ceo_name', 80),
			'biz_no' => $digits(\Context::get('biz_no'), 20),
			'mailorder_no' => $text('mailorder_no', 40),
			'biz_address' => $text('biz_address', 250),
			'tel' => $digits(\Context::get('tel'), 30),
			'email' => $text('email', 120),
			'bank_name' => $text('bank_name', 40),
			'bank_account' => $digits(\Context::get('bank_account'), 60),
			'bank_holder' => $text('bank_holder', 60),
			'intro' => mb_substr(trim(strip_tags((string)\Context::get('intro'))), 0, 2000),
		];
		foreach (['shop_name', 'biz_name', 'ceo_name', 'mailorder_no', 'biz_address', 'email', 'bank_name', 'bank_holder'] as $plain)
		{
			$data->{$plain} = trim(strip_tags($data->{$plain}));
		}
		if (\Context::get('shop_id') !== null)
		{
			$data->shop_id = strtolower(trim((string)\Context::get('shop_id')));
		}
		if ($with_policy)
		{
			$data->ship_fee = max(0, min(100000000, Money::inputToMinor(\Context::get('ship_fee'))));
			$data->free_ship_over = max(0, min(10000000000, Money::inputToMinor(\Context::get('free_ship_over'))));
		}
		return $data;
	}

	public static function missingField(object $data): string
	{
		foreach (['shop_name', 'biz_name', 'ceo_name', 'biz_no', 'mailorder_no', 'tel', 'bank_name', 'bank_account', 'bank_holder'] as $key)
		{
			if (trim((string)($data->{$key} ?? '')) === '')
			{
				return $key;
			}
		}
		if (($data->email ?? '') !== '' && !filter_var($data->email, \FILTER_VALIDATE_EMAIL))
		{
			return 'email';
		}
		return '';
	}

	public static function apply(int $member_srl, object $data): int
	{
		if ($member_srl <= 0 || self::operatorSrl() <= 0)
		{
			return 0;
		}
		$now = date('YmdHis');
		$old = self::getByMember($member_srl);
		if ($old && $old->status === 'rejected')
		{
			$args = clone $data;
			$args->seller_srl = (int)$old->seller_srl;
			$args->status = 'pending';
			$args->reject_reason = '';
			$args->last_update = $now;
			executeQuery('commerce.updateSeller', $args);
			self::forget((int)$old->seller_srl);
			Shop::reserveId((int)$old->seller_srl, (string)($args->shop_id ?? ''));
			return (int)$old->seller_srl;
		}
		$args = clone $data;
		$args->seller_srl = getNextSequence();
		$args->member_srl = $member_srl;
		$args->status = 'pending';
		$args->ship_fee = (int)(Base::config()->default_ship_fee ?? 0);
		$args->free_ship_over = (int)(Base::config()->free_ship_over ?? 0);
		$args->regdate = $now;
		$args->last_update = $now;
		$output = executeQuery('commerce.insertSeller', $args);
		if (!$output->toBool())
		{
			return 0;
		}
		$operator = self::operatorSrl();
		$rows = \Zittme\Framework\DB::getInstance()->query(
			'SELECT seller_srl FROM commerce_seller WHERE member_srl = ? AND seller_srl <> ? ORDER BY seller_srl ASC',
			[$member_srl, $operator]
		)->fetchAll();
		$first = (int)($rows[0]->seller_srl ?? 0);
		if ($first !== (int)$args->seller_srl)
		{
			\Zittme\Framework\DB::getInstance()->query('DELETE FROM commerce_seller WHERE seller_srl = ? AND status = ?', [(int)$args->seller_srl, 'pending']);
			return 0;
		}
		Shop::reserveId((int)$args->seller_srl, (string)($args->shop_id ?? ''));
		return (int)$args->seller_srl;
	}

	public static function update(int $seller_srl, object $data): bool
	{
		$args = clone $data;
		$args->seller_srl = $seller_srl;
		$args->last_update = date('YmdHis');
		$ok = executeQuery('commerce.updateSeller', $args)->toBool();
		self::forget($seller_srl);
		return $ok;
	}

	public static function setStatus(int $seller_srl, string $status, string $reason = ''): bool
	{
		$row = self::get($seller_srl);
		if (!$row || self::isOperator($seller_srl) || !in_array($status, self::STATUSES, true))
		{
			return false;
		}
		$args = (object)[
			'seller_srl' => $seller_srl,
			'status' => $status,
			'reject_reason' => mb_substr(trim($reason), 0, 250),
			'last_update' => date('YmdHis'),
		];
		if ($status === 'approved' && empty($row->approved_date))
		{
			$args->approved_date = date('YmdHis');
		}
		$ok = executeQuery('commerce.updateSeller', $args)->toBool();
		if ($ok && in_array($status, ['suspended', 'rejected'], true))
		{
			\Zittme\Framework\DB::getInstance()->query(
				"UPDATE commerce_item SET status = 'hidden', last_update = ? WHERE seller_srl = ? AND status IN ('sale', 'soldout')",
				[date('YmdHis'), $seller_srl]
			);
		}
		self::forget($seller_srl);
		return $ok;
	}

	public static function setCommission(int $seller_srl, ?float $rate): void
	{
		if (self::isOperator($seller_srl))
		{
			return;
		}
		$value = $rate === null ? null : max(0.0, min(100.0, round($rate, 2)));
		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_seller SET commission_rate = ?, last_update = ? WHERE seller_srl = ?',
			[$value, date('YmdHis'), $seller_srl]
		);
		self::forget($seller_srl);
	}

	public static function pendingCount(): int
	{
		if (!self::schemaReady())
		{
			return 0;
		}
		$rows = \Zittme\Framework\DB::getInstance()->query('SELECT COUNT(*) AS cnt FROM commerce_seller WHERE status = ?', ['pending'])->fetchAll();
		return (int)($rows[0]->cnt ?? 0);
	}

	public static function nameMap(): array
	{
		$map = [];
		$rows = \Zittme\Framework\DB::getInstance()->query(
			"SELECT seller_srl, shop_name FROM commerce_seller WHERE member_srl > 0 AND status IN ('approved', 'suspended') ORDER BY shop_name ASC"
		)->fetchAll();
		foreach ($rows as $row)
		{
			if (!self::isOperator((int)$row->seller_srl))
			{
				$map[(int)$row->seller_srl] = (string)$row->shop_name;
			}
		}
		return $map;
	}

	public static function ownsItem(int $seller_srl, int $item_srl): bool
	{
		if ($item_srl <= 0)
		{
			return true;
		}
		$item = Item::get($item_srl);
		if ($item)
		{
			return (int)$item->seller_srl === $seller_srl;
		}
		return self::draftOwner($item_srl) === $seller_srl;
	}

	public static function hideMarketItems(): void
	{
		$operator = self::operatorSrl();
		\Zittme\Framework\DB::getInstance()->query(
			"UPDATE commerce_item SET status = 'hidden', hidden_by_market = 'Y', last_update = ? WHERE seller_srl > 0 AND seller_srl <> ? AND status IN ('sale', 'soldout')",
			[date('YmdHis'), $operator]
		);
	}

	public static function restoreMarketItems(): void
	{
		\Zittme\Framework\DB::getInstance()->query(
			"UPDATE commerce_item SET status = 'sale', hidden_by_market = 'N', last_update = ? WHERE hidden_by_market = 'Y' AND status = 'hidden'",
			[date('YmdHis')]
		);
		\Zittme\Framework\DB::getInstance()->query("UPDATE commerce_item SET hidden_by_market = 'N' WHERE hidden_by_market = 'Y'");
	}

	public static function ownsOption(int $seller_srl, int $option_srl, int $item_srl): bool
	{
		if ($option_srl <= 0)
		{
			return true;
		}
		$output = executeQuery('commerce.getOption', (object)['option_srl' => $option_srl]);
		$option = ($output->toBool() && is_object($output->data) && !empty($output->data->option_srl)) ? $output->data : null;
		if (!$option)
		{
			return true;
		}
		if ($item_srl > 0 && (int)$option->item_srl !== $item_srl)
		{
			return false;
		}
		$item = Item::get((int)$option->item_srl);
		return $item ? (int)$item->seller_srl === $seller_srl : self::draftOwner((int)$option->item_srl) === $seller_srl;
	}

	public static function claimDraft(int $item_srl, int $seller_srl): void
	{
		$drafts = $_SESSION['commerce_seller_drafts'] ?? [];
		$drafts[$item_srl] = $seller_srl;
		$_SESSION['commerce_seller_drafts'] = array_slice($drafts, -50, null, true);
	}

	protected static function draftOwner(int $item_srl): int
	{
		return (int)($_SESSION['commerce_seller_drafts'][$item_srl] ?? 0);
	}

	public static function guardRequest(string $act, object $seller): bool
	{
		$seller_srl = (int)$seller->seller_srl;
		if (!in_array($act, self::ACTS, true))
		{
			return false;
		}
		if ($act === 'dispCommerceSellerCenter')
		{
			$p = (string)\Context::get('p');
			if ($p !== '' && !in_array($p, self::PAGES, true))
			{
				return false;
			}
		}

		$item_srl = (int)\Context::get('item_srl');
		if (!self::ownsItem($seller_srl, $item_srl))
		{
			return false;
		}
		$clone_from = (int)\Context::get('clone_from');
		if ($clone_from > 0 && !(Item::get($clone_from) && (int)Item::get($clone_from)->seller_srl === $seller_srl))
		{
			return false;
		}
		if (!self::ownsOption($seller_srl, (int)\Context::get('option_srl'), $item_srl))
		{
			return false;
		}

		$srls = \Context::get('item_srls');
		if ($srls !== null)
		{
			$list = is_array($srls) ? $srls : json_decode((string)$srls, true);
			if (!is_array($list))
			{
				$list = preg_split('/[\s,]+/', (string)$srls);
			}
			foreach ($list as $one)
			{
				if (is_array($one))
				{
					$one = $one['item_srl'] ?? 0;
				}
				if (!is_scalar($one))
				{
					return false;
				}
				$one = (int)$one;
				if ($one > 0 && !(Item::get($one) && (int)Item::get($one)->seller_srl === $seller_srl))
				{
					return false;
				}
			}
		}

		$opt_rows = json_decode((string)\Context::get('options_json'), true);
		foreach (is_array($opt_rows) ? $opt_rows : [] as $opt_row)
		{
			if (!self::ownsOption($seller_srl, (int)($opt_row['option_srl'] ?? 0), $item_srl))
			{
				return false;
			}
		}

		$settlement_srl = (int)\Context::get('settlement_srl');
		if ($settlement_srl > 0)
		{
			$st = Settlement::get($settlement_srl);
			if (!$st || (int)$st->seller_srl !== $seller_srl)
			{
				return false;
			}
		}
		self::scrubLangCodes();
		return true;
	}

	protected static function scrubLangCodes(): void
	{
		foreach ((array)\Context::getRequestVars() as $key => $value)
		{
			if (substr((string)$key, -9) === '_langcode')
			{
				\Context::set($key, '');
			}
			elseif (is_string($value) && strpos($value, '$user_lang->') !== false)
			{
				\Context::set($key, str_replace('$user_lang->', '', $value));
			}
		}
	}

	public static function attachNames(array $items, string $mid = ''): void
	{
		$open = self::isOpen();
		$srls = [];
		foreach ($items as $item)
		{
			if (!is_object($item))
			{
				continue;
			}
			$item->seller_name = '';
			$item->seller_store_url = '';
			$item->item_url = '';
			$srl = (int)($item->seller_srl ?? 0);
			if ($open && !self::isOperator($srl))
			{
				$srls[$srl] = true;
			}
		}
		if (!count($srls))
		{
			return;
		}
		$keys = array_keys($srls);
		$marks = implode(',', array_fill(0, count($keys), '?'));
		$map = [];
		foreach (\Zittme\Framework\DB::getInstance()->query(
			"SELECT seller_srl, shop_name, shop_id FROM commerce_seller WHERE status = 'approved' AND seller_srl IN (" . $marks . ')',
			$keys
		)->fetchAll() as $row)
		{
			$map[(int)$row->seller_srl] = $row;
		}
		foreach ($items as $item)
		{
			$row = is_object($item) ? ($map[(int)($item->seller_srl ?? 0)] ?? null) : null;
			if ($row)
			{
				$item->seller_name = (string)$row->shop_name;
				$item->seller_store_url = (string)$row->shop_id !== ''
					? Shop::url((string)$row->shop_id, $mid)
					: getUrl('', 'mid', $mid, 'v', 'list', 'seller', (int)$row->seller_srl);
				if ((string)$row->shop_id !== '' && Shop::itemsInStore())
				{
					$item->item_url = Shop::itemUrl((string)$row->shop_id, (int)$item->item_srl, $mid);
				}
			}
		}
	}

	public static function needsReview(): bool
	{
		return (Base::config()->market_item_review ?? 'N') === 'Y';
	}

	public static function forget(int $seller_srl = 0): void
	{
		if ($seller_srl > 0)
		{
			unset(self::$cache[$seller_srl]);
		}
		else
		{
			self::$cache = [];
		}
		self::$current = false;
	}
}
