<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Lang as LangModel;
use Zittme\Modules\Commerce\Models\Money as MoneyModel;
use Zittme\Modules\Commerce\Models\Seller as SellerModel;
use Zittme\Modules\Commerce\Models\SellerStaff as SellerStaffModel;
use Zittme\Modules\Commerce\Models\Settlement as SettlementModel;
use Zittme\Modules\Commerce\Models\Shop as ShopModel;
use Zittme\Modules\Commerce\Models\Staff as StaffModel;

class SellerCenter extends Admin
{
	public const PAGES = [
		'dashboard' => 'sellerDashboard',
		'items' => 'dispCommerceAdminItems',
		'item_edit' => 'dispCommerceAdminItemEdit',
		'shipping' => 'dispCommerceAdminShipping',
		'settlements' => 'dispCommerceAdminSettlements',
		'shop_design' => 'sellerShopDesign',
		'shop_cats' => 'sellerShopCats',
		'seller_profile' => 'dispCommerceAdminSellerProfile',
		'staff' => 'sellerStaff',
	];

	protected static function me(): object
	{
		$seller = StaffModel::seller();
		if (!$seller)
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		return SellerModel::get((int)$seller->seller_srl) ?: $seller;
	}

	public function dispCommerceSellerCenter()
	{
		if (!StaffModel::seller())
		{
			if (StaffModel::isStaff())
			{
				\Context::redirect(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'sellers'));
				return;
			}
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		$me = self::me();
		$p = (string)\Context::get('p');
		if (!isset(self::PAGES[$p]) || !SellerModel::canPage($p))
		{
			$p = 'dashboard';
		}

		\Context::set('zmc_console', true);
		\Context::set('zmc_seller_center', true);
		\Context::set('zmc_entry', 'dispCommerceSellerCenter');
		\Context::set('zmc_page', $p);
		\Context::set('zmc_seller', $me);
		\Context::set('zmc_store_url', ShopModel::url((string)($me->shop_id ?? '')));
		\Context::setBrowserTitle(lang('commerce.sc_title'));
		$db = \Zittme\Framework\DB::getInstance();
		$to_ship = $db->query('SELECT COUNT(*) AS cnt FROM commerce_order_seller WHERE seller_srl = ? AND status IN (?, ?)', [(int)$me->seller_srl, 'paid', 'preparing'])->fetchAll();
		\Context::set('sc_role', SellerModel::memberRole());
		\Context::set('zmc_counts', [
			'to_ship' => (int)($to_ship[0]->cnt ?? 0),
		]);
		\Context::set('layout', 'none');
		return $this->{self::PAGES[$p]}();
	}

	protected function sellerDashboard()
	{
		$me = self::me();
		$srl = (int)$me->seller_srl;
		$db = \Zittme\Framework\DB::getInstance();
		$today = date('Ymd') . '000000';
		$count = function (string $sql, array $params) use ($db) {
			$rows = $db->query($sql, $params)->fetchAll();
			return (int)($rows[0]->cnt ?? 0);
		};
		$stats = (object)[
			'today' => $count('SELECT COUNT(*) AS cnt FROM commerce_order_seller WHERE seller_srl = ? AND regdate >= ? AND status <> ?', [$srl, $today, self::SELLER_PENDING]),
			'to_confirm' => $count('SELECT COUNT(*) AS cnt FROM commerce_order_seller WHERE seller_srl = ? AND status = ?', [$srl, self::SELLER_PAID]),
			'to_ship' => $count('SELECT COUNT(*) AS cnt FROM commerce_order_seller WHERE seller_srl = ? AND status = ?', [$srl, self::SELLER_PREPARING]),
			'shipping' => $count('SELECT COUNT(*) AS cnt FROM commerce_order_seller WHERE seller_srl = ? AND status = ?', [$srl, self::SELLER_SHIPPING]),
			'claims' => $count(
				'SELECT COUNT(*) AS cnt FROM commerce_claim JOIN commerce_order_seller ON commerce_order_seller.order_seller_srl = commerce_claim.order_seller_srl'
				. ' WHERE commerce_order_seller.seller_srl = ? AND commerce_claim.status IN (?, ?)',
				[$srl, 'requested', 'approved']
			),
			'items_on' => $count("SELECT COUNT(*) AS cnt FROM commerce_item WHERE seller_srl = ? AND status = 'sale'", [$srl]),
		];

		$preview = SettlementModel::preview('20000101', date('Ymd'), $srl);
		$expected = isset($preview[$srl]) ? (int)$preview[$srl]->settle_amount : 0;
		$ready_rows = $db->query("SELECT SUM(settle_amount) AS total FROM commerce_settlement WHERE seller_srl = ? AND status = 'ready'", [$srl])->fetchAll();
		$stats->expected_text = MoneyModel::format($expected, MoneyModel::base());
		$stats->ready_text = MoneyModel::format((int)($ready_rows[0]->total ?? 0), MoneyModel::base());

		$recent = $db->query(
			'SELECT commerce_order_seller.order_seller_srl, commerce_order_seller.status, commerce_order_seller.regdate, commerce_order_seller.item_total, commerce_order.order_code, commerce_order.currency'
			. ' FROM commerce_order_seller JOIN commerce_order ON commerce_order.order_srl = commerce_order_seller.order_srl'
			. ' WHERE commerce_order_seller.seller_srl = ? AND commerce_order_seller.status <> ? ORDER BY commerce_order_seller.order_seller_srl DESC LIMIT 8',
			[$srl, self::SELLER_PENDING]
		)->fetchAll();
		foreach ($recent as $row)
		{
			$row->regdate_text = $row->regdate ? zdate($row->regdate, 'm.d H:i') : '';
			$row->amount_text = MoneyModel::format((int)$row->item_total, (string)($row->currency ?: MoneyModel::base()));
		}

		\Context::set('sc_stats', $stats);
		\Context::set('sc_recent', $recent);
		\Context::set('sc_me', $me);
		$this->renderView('dashboard', 'seller_dashboard');
	}

	protected function sellerShopDesign()
	{
		$me = self::me();
		$design = ShopModel::design($me);
		$items = executeQueryArray('commerce.getItemList', (object)[
			'seller_srl' => (int)$me->seller_srl,
			'status_list' => 'sale,soldout,hidden',
			'sort_index' => 'list_order',
			'order_type' => 'asc',
			'list_count' => 200,
		]);
		$my_items = $items->toBool() ? array_values((array)$items->data) : [];
		LangModel::textAll($my_items, ['item_name']);

		$labels = [];
		foreach (ShopModel::SECTIONS as $key)
		{
			$labels[$key] = lang('commerce.sc_sec_' . $key);
		}
		\Context::set('sc_me', $me);
		\Context::set('sc_design', $design);
		\Context::set('sc_items', $my_items);
		\Context::set('sc_section_labels', $labels);
		\Context::set('sc_preview_url', ShopModel::url((string)($me->shop_id ?? ''), '', ['preview' => 1]));
		$preview_items = [];
		foreach ($my_items as $pv_item)
		{
			if ((string)($me->shop_id ?? '') !== '' && in_array($pv_item->status, ['sale', 'soldout'], true))
			{
				$preview_items[] = (object)[
					'item_name' => $pv_item->item_name,
					'url' => ShopModel::url((string)$me->shop_id, '', ['item' => (int)$pv_item->item_srl, 'preview' => 1]),
				];
			}
		}
		\Context::set('sc_preview_items', $preview_items);
		$this->renderView('shop_design', 'shop_design');
	}

	protected function sellerShopCats()
	{
		$me = self::me();
		$items = executeQueryArray('commerce.getItemList', (object)[
			'seller_srl' => (int)$me->seller_srl,
			'status_list' => 'sale,soldout,hidden,stop',
			'sort_index' => 'list_order',
			'order_type' => 'asc',
			'list_count' => 200,
		]);
		$my_items = $items->toBool() ? array_values((array)$items->data) : [];
		LangModel::textAll($my_items, ['item_name']);
		\Context::set('sc_me', $me);
		\Context::set('sc_cats', ShopModel::categories((int)$me->seller_srl));
		\Context::set('sc_items', $my_items);
		$this->renderView('shop_cats', 'shop_cats');
	}

	protected function back(string $page): void
	{
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', $page));
	}

	public function procCommerceSellerCenterSaveDesign()
	{
		$me = self::me();
		$design = ShopModel::fromRequest((int)$me->seller_srl);
		$draft = ShopModel::draft((int)$me->seller_srl) ?: [];
		ShopModel::saveDesign((int)$me->seller_srl, $design);
		ShopModel::cleanUploads((int)$me->seller_srl, array_merge(ShopModel::referencedUrls($design), ShopModel::referencedUrls($draft)));
		unset($_SESSION['commerce_shop_preview']);
		$this->setMessage('success_saved');
		$this->back('shop_design');
	}

	public function procCommerceSellerCenterPreviewDesign()
	{
		$me = self::me();
		ShopModel::setDraft((int)$me->seller_srl, ShopModel::fromRequest((int)$me->seller_srl));
		$this->add('saved', 1);
	}

	public function procCommerceSellerCenterUpload()
	{
		$me = self::me();
		header('Content-Type: application/json; charset=utf-8');
		$result = ShopModel::saveUpload($_FILES['file'] ?? [], (int)$me->seller_srl);
		if (isset($result['error']))
		{
			echo json_encode(['error' => 1, 'message' => lang('commerce.' . $result['error'])]);
			exit;
		}
		echo json_encode(['error' => 0, 'url' => $result['url']]);
		exit;
	}

	public function procCommerceSellerCenterSaveCategory()
	{
		$me = self::me();
		$saved = ShopModel::saveCategory((int)$me->seller_srl, (int)\Context::get('category_srl'), (string)\Context::get('title'), (int)\Context::get('list_order'));
		if (!$saved)
		{
			return new \BaseObject(-1, lang('commerce.sc_msg_cat_fail'));
		}
		$this->setMessage('success_saved');
		$this->back('shop_cats');
	}

	public function procCommerceSellerCenterDeleteCategory()
	{
		$me = self::me();
		if (!ShopModel::deleteCategory((int)$me->seller_srl, (int)\Context::get('category_srl')))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$this->setMessage('success_deleted');
		$this->back('shop_cats');
	}

	public function procCommerceSellerCenterItemCategory()
	{
		$me = self::me();
		$map = \Context::get('cat_of');
		foreach (is_array($map) ? array_slice($map, 0, 200, true) : [] as $item_srl => $category_srl)
		{
			ShopModel::setItemCategory((int)$me->seller_srl, (int)$item_srl, (int)$category_srl);
		}
		$this->setMessage('success_saved');
		$this->back('shop_cats');
	}

	public function procCommerceSellerCenterSaveShopId()
	{
		$me = self::me();
		$id = strtolower(trim((string)\Context::get('shop_id')));
		$error = ShopModel::idError($id, (int)$me->seller_srl);
		if ($error !== '')
		{
			return new \BaseObject(-1, lang('commerce.' . $error));
		}
		if (!ShopModel::changeId((int)$me->seller_srl, $id))
		{
			return new \BaseObject(-1, lang('commerce.sc_msg_id_taken'));
		}
		$this->setMessage('success_saved');
		$this->back('seller_profile');
	}

	protected function sellerStaff()
	{
		$me = self::me();
		\Context::set('sc_me', $me);
		\Context::set('ss_list', SellerStaffModel::listOf((int)$me->seller_srl));
		\Context::set('ss_roles', SellerStaffModel::ROLES);
		\Context::set('ss_logs', SellerStaffModel::recentLogs((int)$me->seller_srl));
		\Context::set('ss_owner_srl', (int)$me->member_srl);
		$this->renderView('staff', 'seller_staff');
	}

	public function procCommerceSellerCenterInviteMember()
	{
		$me = self::me();
		$member = StaffModel::findMember((string)\Context::get('find'));
		if (!$member)
		{
			return new \BaseObject(-1, lang('commerce.st_msg_no_member'));
		}
		\Context::set('target_member_srl', (int)$member->member_srl);
		$reason = SellerStaffModel::blockReason($member, (int)$me->seller_srl);
		if ($reason !== '')
		{
			return new \BaseObject(-1, lang('commerce.' . $reason));
		}
		$actor = (int)(\Context::get('logged_info')->member_srl ?? 0);
		if (!SellerStaffModel::invite((int)$me->seller_srl, (int)$member->member_srl, (string)\Context::get('role'), $actor))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		\Zittme\Modules\Commerce\Models\Notify::send(
			(int)$member->member_srl,
			sprintf(lang('commerce.ss_notify_invited'), (string)$me->shop_name),
			getNotEncodedFullUrl('', 'module', 'commerce', 'mid', '', 'act', 'dispCommerceSellerInvite')
		);
		$this->setMessage(lang('commerce.ss_msg_invited'));
		$this->back('staff');
	}

	public function procCommerceSellerCenterUpdateMember()
	{
		$me = self::me();
		if (!SellerStaffModel::setRole((int)$me->seller_srl, (int)\Context::get('target_member_srl'), (string)\Context::get('role')))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$this->setMessage('success_saved');
		$this->back('staff');
	}

	public function procCommerceSellerCenterRemoveMember()
	{
		$me = self::me();
		if (!SellerStaffModel::remove((int)$me->seller_srl, (int)\Context::get('target_member_srl')))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$this->setMessage('success_deleted');
		$this->back('staff');
	}

	public function dispCommerceSellerInvite()
	{
		$logged = \Context::get('logged_info');
		$member_srl = (int)($logged->member_srl ?? 0);
		if ($member_srl <= 0)
		{
			throw new \Zittme\Framework\Exceptions\MustLogin;
		}
		$row = SellerStaffModel::get($member_srl);
		$shop = $row ? SellerModel::get((int)$row->seller_srl) : null;
		if ($row && $row->status === 'active' && $shop)
		{
			\Context::redirect(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter'));
			return;
		}
		\Context::set('ss_invite', ($row && $row->status === 'invited' && $shop) ? $row : null);
		\Context::set('ss_shop', $shop);
		\Context::set('layout', 'none');
		\Context::setBrowserTitle(lang('commerce.ss_invite_title'));
		$this->setTemplatePath($this->module_path . 'views/admin/');
		$this->setTemplateFile('seller_invite');
	}

	public function procCommerceSellerInviteAnswer()
	{
		$logged = \Context::get('logged_info');
		$member_srl = (int)($logged->member_srl ?? 0);
		$row = $member_srl > 0 ? SellerStaffModel::get($member_srl) : null;
		if (!$row || $row->status !== 'invited')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		\Context::set('target_member_srl', $member_srl);
		if (\Context::get('answer') === 'accept')
		{
			if (!SellerStaffModel::accept($member_srl))
			{
				return new \BaseObject(-1, lang('commerce.ss_msg_is_operator_staff'));
			}
			\Zittme\Modules\Commerce\Models\Audit::note('procCommerceSellerInviteAccept', (int)$row->seller_srl);
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter'));
			return;
		}
		SellerStaffModel::decline($member_srl);
		\Zittme\Modules\Commerce\Models\Audit::note('procCommerceSellerInviteDecline', (int)$row->seller_srl);
		$this->setMessage(lang('commerce.ss_msg_declined'));
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', '', 'mid', '', 'act', ''));
	}

	public function dispCommerceSellerDoc()
	{
		$seller = SellerModel::get((int)\Context::get('seller_srl'));
		if (!$seller || SellerModel::isOperator((int)$seller->seller_srl))
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		$logged = \Context::get('logged_info');
		$member_srl = (int)($logged->member_srl ?? 0);
		$mine = StaffModel::seller();
		$allowed = (!$mine && StaffModel::allows('@sub'))
			|| ($member_srl > 0 && (int)$seller->member_srl === $member_srl)
			|| ($mine && (int)$mine->seller_srl === (int)$seller->seller_srl && SellerModel::canPage('seller_profile'));
		if (!$allowed)
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		$path = \Zittme\Modules\Commerce\Models\ApplyForm::filePath($seller, (string)\Context::get('field'));
		if ($path === '')
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		$ext = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
		$types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
		while (ob_get_level())
		{
			ob_end_clean();
		}
		header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
		header('Content-Length: ' . filesize($path));
		header('Content-Disposition: inline; filename="' . ($seller->shop_id ?: 'seller') . '_' . preg_replace('/[^a-z0-9_]/', '', (string)\Context::get('field')) . '.' . $ext . '"');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: private, no-store');
		readfile($path);
		exit;
	}
}
