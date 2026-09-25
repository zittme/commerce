<?php

namespace Zittme\Modules\Commerce\Models;

class Staff
{
	public const PERMS = ['orders', 'claims', 'qna', 'items', 'promos', 'stats', 'display', 'config', 'export'];

	public const ROLES = ['sub', 'manager'];

	public const SELLER_ROLE = 'seller';

	public const ACT_PERMS = [
		'dispCommerceConsole' => '*',
		'dispCommerceAdminDashboard' => '*',
		'procCommerceAdminGetLangCodes' => '*',
		'procCommerceAdminGetLangCode' => '*',
		'procCommerceAdminSaveLangCode' => 'items|promos|display|config',
		'procCommerceAdminUploadBanner' => 'display|promos|items',

		'dispCommerceAdminOrders' => 'orders',
		'dispCommerceAdminOrderView' => 'orders',
		'dispCommerceAdminOrderInvoice' => 'orders',
		'dispCommerceAdminShipping' => 'orders',
		'procCommerceAdminBulkShipping' => 'orders',
		'procCommerceAdminUpdateOrder' => 'orders',
		'procCommerceAdminDeleteOrders' => '@sub',
		'dispCommerceAdminExportOrders' => 'orders+export',

		'dispCommerceAdminClaims' => 'claims',
		'procCommerceAdminUpdateClaim' => 'claims',

		'dispCommerceAdminQna' => 'qna',
		'procCommerceAdminReviewReply' => 'qna',
		'procCommerceAdminInquiryAnswer' => 'qna',

		'dispCommerceAdminItems' => 'items',
		'dispCommerceAdminItemEdit' => 'items',
		'dispCommerceAdminStock' => 'items',
		'dispCommerceAdminBrands' => 'items',
		'dispCommerceAdminCategories' => 'items',
		'dispCommerceAdminBadges' => 'items',
		'dispCommerceAdminPins' => 'items',
		'procCommerceAdminAddPins' => 'items',
		'procCommerceAdminVoidPins' => 'items',
		'procCommerceAdminStockAdjust' => 'items',
		'procCommerceAdminSaveLowStock' => 'items',
		'procCommerceAdminInsertItem' => 'items',
		'procCommerceAdminDeleteItem' => 'items',
		'procCommerceAdminSortItems' => 'items',
		'procCommerceAdminUploadItemImage' => 'items',
		'procCommerceAdminSaveItemImages' => 'items',
		'procCommerceAdminBuildCombos' => 'items',
		'procCommerceAdminInsertOption' => 'items',
		'procCommerceAdminUpdateOption' => 'items',
		'procCommerceAdminDeleteOption' => 'items',
		'procCommerceAdminInsertCategory' => 'items',
		'procCommerceAdminDeleteCategory' => 'items',
		'procCommerceAdminSortCategories' => 'items',
		'procCommerceAdminBulkItemStatus' => 'items',
		'procCommerceAdminSaveBrand' => 'items',
		'procCommerceAdminCreateBrand' => 'items',
		'procCommerceAdminReorderBrands' => 'items',
		'procCommerceAdminPreviewBrand' => 'items',
		'procCommerceAdminDeleteBrand' => 'items',
		'procCommerceAdminMoveBrand' => 'items',
		'procCommerceAdminMigrateBrands' => 'items',
		'procCommerceAdminInsertBadge' => 'items',
		'procCommerceAdminDeleteBadge' => 'items',

		'dispCommerceAdminPromotions' => 'promos',
		'dispCommerceAdminTimesale' => 'promos',
		'procCommerceAdminSaveTimesale' => 'promos',
		'procCommerceAdminDeleteTimesale' => 'promos',
		'dispCommerceAdminCoupons' => 'promos',
		'dispCommerceAdminCredits' => 'promos',
		'dispCommerceAdminGrades' => 'promos',
		'procCommerceAdminInsertPromotion' => 'promos',
		'procCommerceAdminCreatePromotion' => 'promos',
		'procCommerceAdminPreviewPromotion' => 'promos',
		'procCommerceAdminDeletePromotion' => 'promos',
		'procCommerceAdminInsertCoupon' => 'promos',
		'procCommerceAdminDeleteCoupon' => 'promos',
		'procCommerceAdminIssueCoupon' => 'promos',
		'procCommerceAdminAdjustCredit' => 'promos',
		'procCommerceAdminInsertGrade' => 'promos',
		'procCommerceAdminDeleteGrade' => 'promos',

		'dispCommerceAdminStats' => 'stats',
		'dispCommerceAdminExportStats' => 'stats+export',

		'procCommerceAdminSaveFront' => 'display',
		'procCommerceAdminPreviewConfig' => 'display',
		'dispCommerceAdminConfig' => 'config|display',
		'procCommerceAdminInsertConfig' => 'config|display',
		'procCommerceAdminUpdateSkin' => 'config',

		'dispCommerceAdminStaff' => '@sub',
		'procCommerceAdminSaveStaff' => '@sub',
		'procCommerceAdminStaffStatus' => '@sub',
		'procCommerceAdminDeleteStaff' => '@sub',
		'dispCommerceAdminAudit' => '@sub',
		'procCommerceAdminAuditConfig' => '@root',

		'dispCommerceAdminSellers' => '@sub',
		'procCommerceAdminSellerStatus' => '@sub',
		'procCommerceAdminSellerCommission' => '@sub',
		'dispCommerceAdminSettlements' => '@sub',
		'procCommerceAdminCreateSettlement' => '@sub',
		'procCommerceAdminSettlementPaid' => '@sub',
		'procCommerceAdminCancelSettlement' => '@sub',
		'dispCommerceAdminExportSettlement' => '@sub',
	];

	public const PAGE_PERMS = [
		'dashboard' => '*',
		'orders' => 'orders', 'shipping' => 'orders', 'order_view' => 'orders',
		'claims' => 'claims', 'qna' => 'qna',
		'items' => 'items', 'item_edit' => 'items', 'brands' => 'items', 'categories' => 'items', 'badges' => 'items', 'stock' => 'items', 'pins' => 'items',
		'promotions' => 'promos', 'timesale' => 'promos', 'coupons' => 'promos', 'credits' => 'promos', 'grades' => 'promos',
		'stats' => 'stats',
		'config_display' => 'display',
		'config' => 'config', 'config_shipping' => 'config', 'config_rewards' => 'config', 'config_notify' => 'config', 'config_policy' => 'config',
		'staff' => '@sub', 'audit' => '@sub',
		'sellers' => '@sub', 'settlements' => '@sub',
	];

	protected static $current = false;

	public static function isRoot(): bool
	{
		$logged = \Context::get('logged_info');
		return $logged && ($logged->is_admin ?? 'N') === 'Y';
	}

	public static function current(): ?object
	{
		if (self::$current !== false)
		{
			return self::$current;
		}
		self::$current = null;
		$logged = \Context::get('logged_info');
		if ($logged && (int)$logged->member_srl > 0)
		{
			$row = self::get((int)$logged->member_srl);
			if ($row && $row->status === 'active')
			{
				self::$current = $row;
			}
		}
		return self::$current;
	}

	public static function role(): string
	{
		if (self::isRoot())
		{
			return 'owner';
		}
		$me = self::current();
		if ($me)
		{
			return (string)$me->role;
		}
		return self::seller() ? self::SELLER_ROLE : 'none';
	}

	public static function seller(): ?object
	{
		if (self::isRoot() || self::current() !== null)
		{
			return null;
		}
		return Seller::current();
	}

	public static function isStaff(): bool
	{
		return self::isRoot() || self::current() !== null || self::seller() !== null;
	}

	public static function allows(string $need): bool
	{
		if (self::isRoot())
		{
			return true;
		}
		$me = self::current();
		if (!$me || $need === '@root')
		{
			return false;
		}
		if ($me->role === 'sub')
		{
			return true;
		}
		if ($need === '*')
		{
			return true;
		}
		if ($need === '' || $need[0] === '@')
		{
			return false;
		}
		$perms = $me->perm_list;
		foreach (explode('|', $need) as $any)
		{
			$ok = true;
			foreach (explode('+', $any) as $one)
			{
				if (!in_array($one, $perms, true))
				{
					$ok = false;
					break;
				}
			}
			if ($ok)
			{
				return true;
			}
		}
		return false;
	}

	public static function can(string $perm): bool
	{
		return self::allows($perm);
	}

	public static function canPage(string $page): bool
	{
		if (self::seller())
		{
			return in_array($page, Seller::PAGES, true);
		}
		if ($page === 'seller_profile')
		{
			return false;
		}
		if (($page === 'sellers' && !Seller::isOpen()) || ($page === 'settlements' && !Seller::schemaReady()))
		{
			return false;
		}
		return self::allows(self::PAGE_PERMS[$page] ?? '@sub');
	}

	public static function authorize(string $act): void
	{
		if (!preg_match('/^(disp|proc)CommerceAdmin[A-Z]|^dispCommerceConsole$|^(disp|proc)CommerceSellerCenter/', $act))
		{
			return;
		}
		$seller = self::seller();
		if ($seller)
		{
			if (!Seller::guardRequest($act, $seller))
			{
				Audit::denied($act);
				throw new \Zittme\Framework\Exceptions\NotPermitted;
			}
			return;
		}
		$need = self::ACT_PERMS[$act] ?? '@sub';
		$ok = self::allows($need);
		if ($ok && $act === 'procCommerceAdminInsertConfig' && !self::allows('config'))
		{
			$ok = self::onlyDisplayFields();
		}
		if (!$ok)
		{
			if (self::current() !== null || (\Context::get('logged_info')->member_srl ?? 0))
			{
				Audit::denied($act);
			}
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		self::touch();
	}

	protected static function onlyDisplayFields(): bool
	{
		$display = \Zittme\Modules\Commerce\Controllers\Admin::PREVIEW_FIELDS;
		foreach (\Zittme\Modules\Commerce\Controllers\Admin::CONFIG_FIELDS as $key)
		{
			if (\Context::get($key) !== null && !in_array($key, $display, true))
			{
				return false;
			}
		}
		return true;
	}

	protected static function touch(): void
	{
		$me = self::current();
		if (!$me || self::isRoot())
		{
			return;
		}
		$now = date('YmdHis');
		if ((string)$me->last_seen !== '' && strtotime(self::iso($now)) - strtotime(self::iso((string)$me->last_seen)) < 300)
		{
			return;
		}
		\Zittme\Framework\DB::getInstance()->query('UPDATE commerce_staff SET last_seen = ? WHERE member_srl = ?', [$now, (int)$me->member_srl]);
	}

	protected static function iso(string $ymdhis): string
	{
		return substr($ymdhis, 0, 4) . '-' . substr($ymdhis, 4, 2) . '-' . substr($ymdhis, 6, 2) . ' ' . substr($ymdhis, 8, 2) . ':' . substr($ymdhis, 10, 2) . ':' . substr($ymdhis, 12, 2);
	}

	public static function get(int $member_srl): ?object
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_staff WHERE member_srl = ?', [$member_srl]);
		$rows = $st ? $st->fetchAll() : [];
		return count($rows) ? self::decorate($rows[0]) : null;
	}

	public static function getList(): array
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_staff ORDER BY role DESC, regdate ASC');
		$rows = $st ? $st->fetchAll() : [];
		foreach ($rows as $row)
		{
			self::decorate($row);
			$m = \MemberModel::getMemberInfoByMemberSrl((int)$row->member_srl);
			$row->user_id = $m->user_id ?? '';
			$row->nick_name = $m->nick_name ?? '';
			$row->email_address = $m->email_address ?? '';
			$row->is_site_admin = ($m->is_admin ?? 'N') === 'Y';
		}
		return $rows;
	}

	protected static function decorate(object $row): object
	{
		$perms = json_decode((string)($row->perms ?? ''), true);
		$row->perm_list = is_array($perms) ? array_values(array_intersect(self::PERMS, $perms)) : [];
		return $row;
	}

	public static function findMember(string $key): ?object
	{
		$key = trim($key);
		if ($key === '')
		{
			return null;
		}
		$m = \MemberModel::getMemberInfoByUserID($key);
		if (empty($m->member_srl) && strpos($key, '@') !== false)
		{
			$m = \MemberModel::getMemberInfoByEmailAddress($key);
		}
		if (empty($m->member_srl))
		{
			$srl = \MemberModel::getMemberSrlByNickName($key);
			$m = $srl ? \MemberModel::getMemberInfoByMemberSrl((int)$srl) : null;
		}
		return !empty($m->member_srl) ? $m : null;
	}

	public static function save(int $member_srl, string $role, array $perms, string $memo, int $actor_srl): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		$role = in_array($role, self::ROLES, true) ? $role : 'manager';
		$perms_json = json_encode(array_values(array_intersect(self::PERMS, $perms)));
		$now = date('YmdHis');
		if (self::get($member_srl))
		{
			$db->query('UPDATE commerce_staff SET role = ?, perms = ?, memo = ?, last_update = ? WHERE member_srl = ?', [$role, $perms_json, mb_substr($memo, 0, 250), $now, $member_srl]);
		}
		else
		{
			$db->query('INSERT INTO commerce_staff (member_srl, role, perms, status, memo, added_by, regdate, last_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$member_srl, $role, $perms_json, 'active', mb_substr($memo, 0, 250), $actor_srl, $now, $now]);
		}
		self::$current = false;
	}

	public static function setStatus(int $member_srl, string $status): void
	{
		\Zittme\Framework\DB::getInstance()->query('UPDATE commerce_staff SET status = ?, last_update = ? WHERE member_srl = ?', [$status === 'suspended' ? 'suspended' : 'active', date('YmdHis'), $member_srl]);
		self::$current = false;
	}

	public static function delete(int $member_srl): void
	{
		\Zittme\Framework\DB::getInstance()->query('DELETE FROM commerce_staff WHERE member_srl = ?', [$member_srl]);
		self::$current = false;
	}

	public static function canManage(?object $target, string $new_role = 'manager'): bool
	{
		if (self::isRoot())
		{
			return true;
		}
		$me = self::current();
		if (!$me || $me->role !== 'sub')
		{
			return false;
		}
		if ($new_role === 'sub' || ($target && ($target->role === 'sub' || (int)$target->member_srl === (int)$me->member_srl)))
		{
			return false;
		}
		return true;
	}
}
