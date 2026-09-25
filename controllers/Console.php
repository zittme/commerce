<?php

namespace Zittme\Modules\Commerce\Controllers;

class Console extends Admin
{
	public const PAGES = [
		'dashboard' => 'dispCommerceAdminDashboard',
		'orders' => 'dispCommerceAdminOrders',
		'shipping' => 'dispCommerceAdminShipping',
		'order_view' => 'dispCommerceAdminOrderView',
		'items' => 'dispCommerceAdminItems',
		'item_edit' => 'dispCommerceAdminItemEdit',
		'stock' => 'dispCommerceAdminStock',
		'categories' => 'dispCommerceAdminCategories',
		'brands' => 'dispCommerceAdminBrands',
		'badges' => 'dispCommerceAdminBadges',
		'promotions' => 'dispCommerceAdminPromotions',
		'qna' => 'dispCommerceAdminQna',
		'claims' => 'dispCommerceAdminClaims',
		'coupons' => 'dispCommerceAdminCoupons',
		'credits' => 'dispCommerceAdminCredits',
		'grades' => 'dispCommerceAdminGrades',
		'stats' => 'dispCommerceAdminStats',
		'config' => 'dispCommerceAdminConfig',
		'config_shipping' => 'dispCommerceAdminConfig',
		'config_display' => 'dispCommerceAdminConfig',
		'config_rewards' => 'dispCommerceAdminConfig',
		'config_notify' => 'dispCommerceAdminConfig',
		'config_policy' => 'dispCommerceAdminConfig',
		'timesale' => 'dispCommerceAdminTimesale',
		'pins' => 'dispCommerceAdminPins',
		'staff' => 'dispCommerceAdminStaff',
		'audit' => 'dispCommerceAdminAudit',
		'sellers' => 'dispCommerceAdminSellers',
		'settlements' => 'dispCommerceAdminSettlements',
		'seller_profile' => 'dispCommerceAdminSellerProfile',
	];

	public function dispCommerceConsole()
	{
		if (!\Zittme\Modules\Commerce\Models\Staff::isStaff())
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$p = (string)\Context::get('p');
		if (\Zittme\Modules\Commerce\Models\Staff::seller())
		{
			$to = in_array($p, \Zittme\Modules\Commerce\Models\Seller::PAGES, true) ? $p : 'dashboard';
			$params = ['', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', $to];
			foreach (['item_srl', 'clone_from', 'tab', 'settlement_srl', 'page'] as $keep)
			{
				if (\Context::get($keep) !== null && \Context::get($keep) !== '')
				{
					$params[] = $keep;
					$params[] = (string)(int)\Context::get($keep) === (string)\Context::get($keep) ? (int)\Context::get($keep) : preg_replace('/[^a-z0-9_]/', '', (string)\Context::get($keep));
				}
			}
			\Context::redirect((string)call_user_func_array('getNotEncodedUrl', $params));
			return;
		}
		if (!isset(self::PAGES[$p]))
		{
			$p = 'dashboard';
		}
		if (!\Zittme\Modules\Commerce\Models\Staff::canPage($p))
		{
			\Zittme\Modules\Commerce\Models\Audit::denied(self::PAGES[$p]);
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		\Context::set('zmc_console', true);
		\Context::set('zmc_entry', 'dispCommerceConsole');
		\Context::set('zmc_page', $p);
		\Context::setBrowserTitle(lang('commerce.admin_console_title'));

		$db = \Zittme\Framework\DB::getInstance();
		\Context::set('zmc_counts', [
			'sellers' => \Zittme\Modules\Commerce\Models\Seller::isOpen() ? \Zittme\Modules\Commerce\Models\Seller::pendingCount() : 0,
			'to_ship' => (int)$db->query('SELECT COUNT(*) FROM commerce_order_seller WHERE status IN (?, ?)', 'paid', 'preparing')->fetchColumn(),
			'claims' => (int)$db->query('SELECT COUNT(*) FROM commerce_claim WHERE status = ?', 'requested')->fetchColumn(),
			'unanswered' => (int)$db->query("SELECT COUNT(*) FROM commerce_inquiry WHERE answer IS NULL OR answer = ''")->fetchColumn(),
		]);

		\Context::set('layout', 'none');

		return $this->{self::PAGES[$p]}();
	}
}
