<?php

namespace Zittme\Modules\Commerce\Models;

class SellerStaff
{
	public const ROLES = ['admin', 'items', 'orders'];

	public const MAX_PER_SELLER = 30;

	public const ROLE_PAGES = [
		'owner' => ['dashboard', 'items', 'item_edit', 'shipping', 'settlements', 'shop_design', 'shop_cats', 'seller_profile', 'staff'],
		'admin' => ['dashboard', 'items', 'item_edit', 'shipping', 'settlements', 'shop_design', 'shop_cats', 'seller_profile'],
		'items' => ['dashboard', 'items', 'item_edit', 'shop_cats'],
		'orders' => ['dashboard', 'shipping'],
	];

	public const ACT_PAGES = [
		'dispCommerceConsole' => 'dashboard',
		'procCommerceSellerCenterSaveDesign' => 'shop_design',
		'procCommerceSellerCenterPreviewDesign' => 'shop_design',
		'procCommerceSellerCenterUpload' => 'shop_design',
		'procCommerceSellerCenterSaveCategory' => 'shop_cats',
		'procCommerceSellerCenterDeleteCategory' => 'shop_cats',
		'procCommerceSellerCenterItemCategory' => 'shop_cats',
		'procCommerceSellerCenterSaveShopId' => 'seller_profile',
		'dispCommerceAdminItems' => 'items',
		'dispCommerceAdminItemEdit' => 'items',
		'procCommerceAdminInsertItem' => 'items',
		'procCommerceAdminDeleteItem' => 'items',
		'procCommerceAdminBulkItemStatus' => 'items',
		'procCommerceAdminUploadItemImage' => 'items',
		'procCommerceAdminSaveItemImages' => 'items',
		'procCommerceAdminBuildCombos' => 'items',
		'procCommerceAdminInsertOption' => 'items',
		'procCommerceAdminUpdateOption' => 'items',
		'procCommerceAdminDeleteOption' => 'items',
		'procCommerceAdminStockAdjust' => 'items',
		'dispCommerceAdminShipping' => 'shipping',
		'procCommerceAdminBulkShipping' => 'shipping',
		'dispCommerceAdminSettlements' => 'settlements',
		'dispCommerceAdminExportSettlement' => 'settlements',
		'dispCommerceAdminSellerProfile' => 'seller_profile',
		'procCommerceAdminSaveSellerProfile' => 'seller_profile',
		'procCommerceSellerCenterInviteMember' => 'staff',
		'procCommerceSellerCenterUpdateMember' => 'staff',
		'procCommerceSellerCenterRemoveMember' => 'staff',
	];

	protected static $ready = null;

	public static function ready(): bool
	{
		if (self::$ready === null)
		{
			try
			{
				self::$ready = \DB::getInstance()->isTableExists('commerce_seller_staff');
			}
			catch (\Throwable $e)
			{
				self::$ready = false;
			}
		}
		return self::$ready;
	}

	public static function get(int $member_srl): ?object
	{
		if ($member_srl <= 0 || !self::ready())
		{
			return null;
		}
		$rows = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_seller_staff WHERE member_srl = ?', [$member_srl])->fetchAll();
		return $rows[0] ?? null;
	}

	public static function activeOf(int $member_srl): ?object
	{
		$row = self::get($member_srl);
		return ($row && $row->status === 'active' && in_array($row->role, self::ROLES, true)) ? $row : null;
	}

	public static function pageAllowed(string $role, string $page): bool
	{
		return in_array($page, self::ROLE_PAGES[$role] ?? [], true);
	}

	public static function actAllowed(string $role, string $act): bool
	{
		if ($role === 'owner')
		{
			return true;
		}
		if ($act === 'dispCommerceSellerCenter')
		{
			$p = (string)\Context::get('p');
			return self::pageAllowed($role, $p === '' ? 'dashboard' : $p);
		}
		$page = self::ACT_PAGES[$act] ?? '';
		return $page !== '' && self::pageAllowed($role, $page);
	}

	public static function listOf(int $seller_srl): array
	{
		if (!self::ready())
		{
			return [];
		}
		$rows = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_seller_staff WHERE seller_srl = ? ORDER BY regdate ASC', [$seller_srl])->fetchAll();
		foreach ($rows as $row)
		{
			$m = \MemberModel::getMemberInfoByMemberSrl((int)$row->member_srl);
			$row->user_id = (string)($m->user_id ?? '');
			$row->nick_name = (string)($m->nick_name ?? '');
			$row->regdate_text = $row->regdate ? zdate($row->regdate, 'Y.m.d') : '';
			$row->accepted_text = $row->accepted_date ? zdate($row->accepted_date, 'Y.m.d') : '';
		}
		return $rows;
	}

	public static function blockReason(object $member, int $seller_srl): string
	{
		$member_srl = (int)$member->member_srl;
		if (($member->is_admin ?? 'N') === 'Y')
		{
			return 'ss_msg_is_admin';
		}
		if (Staff::get($member_srl))
		{
			return 'ss_msg_is_operator_staff';
		}
		$own = \Zittme\Framework\DB::getInstance()->query('SELECT seller_srl FROM commerce_seller WHERE member_srl = ?', [$member_srl])->fetchAll();
		if (count($own))
		{
			return 'ss_msg_is_seller';
		}
		$row = self::get($member_srl);
		if ($row)
		{
			return (int)$row->seller_srl === $seller_srl ? 'ss_msg_already' : 'ss_msg_other_seller';
		}
		$count = \Zittme\Framework\DB::getInstance()->query('SELECT COUNT(*) AS cnt FROM commerce_seller_staff WHERE seller_srl = ?', [$seller_srl])->fetchAll();
		if ((int)($count[0]->cnt ?? 0) >= self::MAX_PER_SELLER)
		{
			return 'ss_msg_too_many';
		}
		return '';
	}

	public static function invite(int $seller_srl, int $member_srl, string $role, int $actor_srl): bool
	{
		if (!in_array($role, self::ROLES, true) || !self::ready())
		{
			return false;
		}
		$now = date('YmdHis');
		try
		{
			\Zittme\Framework\DB::getInstance()->query(
				'INSERT INTO commerce_seller_staff (member_srl, seller_srl, role, status, added_by, regdate, last_update) VALUES (?, ?, ?, ?, ?, ?, ?)',
				[$member_srl, $seller_srl, $role, 'invited', $actor_srl, $now, $now]
			);
		}
		catch (\Throwable $e)
		{
			return false;
		}
		Seller::forget();
		return true;
	}

	public static function setRole(int $seller_srl, int $member_srl, string $role): bool
	{
		if (!in_array($role, self::ROLES, true))
		{
			return false;
		}
		$row = self::get($member_srl);
		if (!$row || (int)$row->seller_srl !== $seller_srl)
		{
			return false;
		}
		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_seller_staff SET role = ?, last_update = ? WHERE member_srl = ? AND seller_srl = ?',
			[$role, date('YmdHis'), $member_srl, $seller_srl]
		);
		Seller::forget();
		return true;
	}

	public static function remove(int $seller_srl, int $member_srl): bool
	{
		$row = self::get($member_srl);
		if (!$row || (int)$row->seller_srl !== $seller_srl)
		{
			return false;
		}
		\Zittme\Framework\DB::getInstance()->query('DELETE FROM commerce_seller_staff WHERE member_srl = ? AND seller_srl = ?', [$member_srl, $seller_srl]);
		Seller::forget();
		return true;
	}

	public static function accept(int $member_srl): ?object
	{
		$row = self::get($member_srl);
		if (!$row || $row->status !== 'invited')
		{
			return null;
		}
		$logged = \Context::get('logged_info');
		$member = (object)['member_srl' => $member_srl, 'is_admin' => $logged->is_admin ?? 'N'];
		if (($member->is_admin ?? 'N') === 'Y' || Staff::get($member_srl))
		{
			return null;
		}
		$now = date('YmdHis');
		\Zittme\Framework\DB::getInstance()->query(
			"UPDATE commerce_seller_staff SET status = 'active', accepted_date = ?, last_update = ? WHERE member_srl = ? AND status = 'invited'",
			[$now, $now, $member_srl]
		);
		Seller::forget();
		return self::get($member_srl);
	}

	public static function decline(int $member_srl): bool
	{
		$row = self::get($member_srl);
		if (!$row || $row->status !== 'invited')
		{
			return false;
		}
		\Zittme\Framework\DB::getInstance()->query("DELETE FROM commerce_seller_staff WHERE member_srl = ? AND status = 'invited'", [$member_srl]);
		Seller::forget();
		return true;
	}

	public static function memberSrls(int $seller_srl): array
	{
		$srls = [];
		$seller = Seller::get($seller_srl);
		if ($seller)
		{
			$srls[] = (int)$seller->member_srl;
		}
		foreach (self::listOf($seller_srl) as $row)
		{
			$srls[] = (int)$row->member_srl;
		}
		return array_values(array_unique(array_filter($srls)));
	}

	public static function recentLogs(int $seller_srl, int $limit = 20): array
	{
		$srls = self::memberSrls($seller_srl);
		if (!count($srls))
		{
			return [];
		}
		$marks = implode(',', array_fill(0, count($srls), '?'));
		$rows = \Zittme\Framework\DB::getInstance()->query(
			'SELECT member_srl, actor, act, summary, result, regdate FROM commerce_audit WHERE member_srl IN (' . $marks . ") AND kind <> 'view' ORDER BY log_srl DESC LIMIT " . (int)$limit,
			$srls
		)->fetchAll();
		foreach ($rows as $row)
		{
			$row->regdate_text = $row->regdate ? zdate($row->regdate, 'm.d H:i') : '';
		}
		return $rows;
	}

	public static function purgeSeller(int $seller_srl): void
	{
		if (self::ready())
		{
			\Zittme\Framework\DB::getInstance()->query('DELETE FROM commerce_seller_staff WHERE seller_srl = ?', [$seller_srl]);
		}
	}
}
