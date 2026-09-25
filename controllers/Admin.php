<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Address as AddressModel;
use Zittme\Modules\Commerce\Models\Badge as BadgeModel;
use Zittme\Modules\Commerce\Models\Brand as BrandModel;
use Zittme\Modules\Commerce\Models\Combo as ComboModel;
use Zittme\Modules\Commerce\Models\Config as ConfigModel;
use Zittme\Modules\Commerce\Models\Grade as GradeModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Lang as LangModel;
use Zittme\Modules\Commerce\Models\Money as MoneyModel;
use Zittme\Modules\Commerce\Models\Notify as NotifyModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;
use Zittme\Modules\Commerce\Models\Region as RegionModel;
use Zittme\Modules\Commerce\Models\Seller as SellerModel;
use Zittme\Modules\Commerce\Models\Settlement as SettlementModel;
use Zittme\Modules\Commerce\Models\Staff as StaffModel;
use Zittme\Modules\Commerce\Models\Audit as AuditModel;
use Zittme\Modules\Commerce\Models\Stats as StatsModel;
use Zittme\Modules\Commerce\Models\Stock as StockModel;
use Zittme\Modules\Commerce\Models\Tax as TaxModel;

class Admin extends Base
{
	public function init()
	{
		StaffModel::authorize((string)$this->act);
	}

	public function proc()
	{
		$act = (string)$this->act;
		$snap = $this->stop_proc ? [] : AuditModel::snapshot($act);
		$result = parent::proc();
		if (!$this->stop_proc)
		{
			AuditModel::after($act, $this, $snap);
		}
		return $result;
	}

	public const CONFIG_FIELDS = [
		'enabled', 'market_mode', 'market_commission', 'market_apply', 'market_item_review', 'seller_item_in_store', 'code_prefix', 'allow_guest', 'pending_minutes',
		'default_ship_fee', 'free_ship_over', 'claim_days', 'ship_guide', 'claim_guide', 'item_sticky', 'currency_code_prefix', 'sweettracker_api_key', 'couriers',
		'shop_main', 'category_layout', 'item_image_size', 'show_shop_nav', 'show_search', 'show_admin_fab', 'home_show_recommend', 'home_show_new',
		'home_show_popular', 'home_show_sale', 'home_count', 'home_banners', 'ship_extra_zones', 'show_seller_on_card',
		'credit_rate', 'credit_min_use', 'review_credit_text', 'review_credit_photo',
		'privacy_text', 'privacy_version', 'retention_days',
		'biz_name', 'biz_ceo', 'biz_number', 'biz_address', 'biz_tel', 'biz_note', 'biz_logo',
		'biz_tax_mode', 'vat_rate', 'price_includes_tax', 'base_country', 'allow_overseas', 'address_mode',
		'use_phone_cc', 'require_state', 'use_coupon', 'use_credit', 'auto_confirm_days',
		'currencies', 'currency_fallback',
		'notify_admin', 'notify_admin_email', 'notify_admin_group',
		'notify_low_stock', 'low_stock_default',
		'notify_admin_new_order', 'notify_admin_claim',
		'notify_buyer_received', 'notify_buyer_paid', 'notify_buyer_shipping',
		'notify_buyer_delivered', 'notify_buyer_claim_done',
	];

	protected const LANG_CONFIG_FIELDS = ['privacy_text', 'biz_name', 'biz_address', 'biz_note', 'ship_guide', 'claim_guide'];

	protected const BOOLEAN_FIELDS = ['enabled', 'market_apply', 'market_item_review', 'seller_item_in_store', 'show_seller_on_card', 'allow_guest', 'notify_admin', 'item_sticky', 'currency_code_prefix',
		'home_show_recommend', 'home_show_new', 'home_show_popular', 'home_show_sale'];
	protected const FLOAT_FIELDS = ['credit_rate' => [0, 100], 'market_commission' => [0, 100]];
	protected const INT_FIELDS = [
		'pending_minutes' => [10, 1440],
		'claim_days' => [0, 90],
		'retention_days' => [0, 3650],
		'home_count' => [4, 24],
	];
	protected const MONEY_FIELDS = [
		'default_ship_fee' => [0, 100000000],
		'free_ship_over' => [0, 10000000000],
		'credit_min_use' => [0, 100000000],
		'review_credit_text' => [0, 10000000],
		'review_credit_photo' => [0, 10000000],
	];

	protected function renderView(string $tab, string $file): void
	{
		\Context::set('shop_tab', $tab);
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->module_path . 'views/admin/');
		$this->setTemplateFile($file);
	}

	protected static function collectItemAttrs(): string
	{
		$names = (array)\Context::get('attr_name');
		$values = (array)\Context::get('attr_value');
		$rows = [];
		foreach ($names as $i => $name)
		{
			$name = trim((string)$name);
			$value = trim((string)($values[$i] ?? ''));
			if ($name === '' || $value === '')
			{
				continue;
			}
			$rows[] = ['name' => mb_substr($name, 0, 40), 'value' => mb_substr($value, 0, 200)];
			if (count($rows) >= 20)
			{
				break;
			}
		}
		return count($rows) ? json_encode($rows, \JSON_UNESCAPED_UNICODE) : '';
	}

	protected static function getCategories(): array
	{
		$output = executeQuery('commerce.getCategoryList', new \stdClass);
		$rows = [];
		if ($output->toBool() && !empty($output->data))
		{
			foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
			{
				if (!empty($row->category_srl))
				{
					$rows[] = $row;
				}
			}
		}

		$children = [];
		foreach ($rows as $row)
		{
			$children[(int)$row->parent_srl][] = $row;
		}
		$map = [];
		$walk = function($parent, $depth) use (&$walk, &$children, &$map) {
			foreach ($children[$parent] ?? [] as $row)
			{
				$row->depth = $depth;
				$map[(int)$row->category_srl] = $row;
				$walk((int)$row->category_srl, $depth + 1);
			}
		};
		$walk(0, 0);
		foreach ($rows as $row)
		{
			if (!isset($map[(int)$row->category_srl]))
			{
				$row->depth = 0;
				$map[(int)$row->category_srl] = $row;
			}
		}
		LangModel::textAll(array_values($map), ['title']);
		return $map;
	}

	public function dispCommerceAdminDashboard()
	{
		OrderModel::expireStalePending();
		$instance = self::getDefaultInstance();
		\Context::set('shop_mid', $instance ? $instance->mid : self::DEFAULT_MID);

		$db = \Zittme\Framework\DB::getInstance();
		$saved_config = \ModuleModel::getModuleConfig('commerce');
		$checklist = [
			(object)[
				'key' => 'pay', 'title' => '결제수단 연결',
				'done' => self::isPayAvailable(),
				'url' => getUrl('', 'module', 'admin', 'act', 'dispZittme_payAdminConfig'),
				'hint' => '짓미페이에서 결제수단을 켜야 주문을 받을 수 있습니다.',
			],
			(object)[
				'key' => 'config', 'title' => '기본 설정 저장',
				'done' => is_object($saved_config) && isset($saved_config->enabled),
				'url' => getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminConfig'),
				'hint' => '배송비·적립률 등 기본 설정을 확인하고 저장하세요.',
			],
			(object)[
				'key' => 'category', 'title' => '카테고리 만들기',
				'done' => (int)$db->query('SELECT COUNT(*) FROM commerce_category')->fetchColumn() > 0,
				'url' => getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCategories'),
				'hint' => '상품을 분류할 카테고리를 하나 이상 만드세요.',
			],
			(object)[
				'key' => 'item', 'title' => '첫 상품 등록',
				'done' => (int)$db->query('SELECT COUNT(*) FROM commerce_item')->fetchColumn() > 0,
				'url' => getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit'),
				'hint' => '첫 상품을 등록하면 상점이 열립니다.',
			],
		];
		\Context::set('shop_checklist', $checklist);
		\Context::set('shop_checklist_done', count(array_filter($checklist, function($c) { return $c->done; })));
		\Context::set('shop_stats', StatsModel::dashboard());

		$recent_output = executeQueryArray('commerce.getOrderList', (object)['list_count' => 10, 'page' => 1]);
		\Context::set('recent_orders', $recent_output->data ?: []);
		$claim_output = executeQueryArray('commerce.getClaimList', (object)['list_count' => 10, 'page' => 1]);
		$recent_claims = $claim_output->data ?: [];
		$claim_orders = [];
		foreach ($recent_claims as $rc)
		{
			if (!isset($claim_orders[(int)$rc->order_srl]))
			{
				$claim_orders[(int)$rc->order_srl] = OrderModel::get((int)$rc->order_srl);
			}
		}
		\Context::set('recent_claims', $recent_claims);
		\Context::set('recent_claim_orders', $claim_orders);

		\Context::set('low_stock_rows', StockModel::lowStockRows(50));
		\Context::set('low_stock_default', (int)(self::config()->low_stock_default ?? 0));

		$this->renderView('dashboard', 'dashboard');
	}

	public function dispCommerceAdminStock()
	{
		$args = new \stdClass;
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 50;
		$args->sort_index = 'item_srl';
		$args->order_type = 'desc';
		$keyword = trim((string)\Context::get('f_keyword'));
		$field = (string)\Context::get('f_field') ?: 'name';
		$category_srl = (int)\Context::get('f_category');
		$date_from = preg_replace('/\D/', '', (string)\Context::get('f_from'));
		$date_to = preg_replace('/\D/', '', (string)\Context::get('f_to'));
		if ($category_srl > 0)
		{
			$args->category_srl = $category_srl;
		}
		if ($keyword !== '')
		{
			if ($field === 'code')
			{
				$args->search_code = '%' . $keyword . '%';
			}
			elseif ($field !== 'stock')
			{
				$args->search_keyword = '%' . $keyword . '%';
				$args->search_brand_srl_list = BrandModel::searchSrls($keyword) ?: null;
			}
		}
		if (strlen($date_from) === 8)
		{
			$args->regdate_from = $date_from . '000000';
		}
		if (strlen($date_to) === 8)
		{
			$args->regdate_to = $date_to . '235959';
		}
		$output = executeQueryArray('commerce.getItemList', $args);
		$items = LangModel::textAll($output->data ?: [], ['item_name']);
		$stock_max = ($field === 'stock' && $keyword !== '' && is_numeric($keyword)) ? (int)$keyword : null;
		$options_map = [];
		foreach ($items as $stock_item)
		{
			$stock_options = ItemModel::getOptions((int)$stock_item->item_srl, true);
			LangModel::textAll($stock_options, ['option_label']);
			foreach ($stock_options as $stock_option)
			{
				$stock_option->option_label = ComboModel::optionLabel($stock_item, $stock_option);
			}
			$options_map[(int)$stock_item->item_srl] = $stock_options;
		}
		if ($stock_max !== null)
		{
			$items = array_values(array_filter($items, function($stock_item) use ($options_map, $stock_max) {
				$opts = $options_map[(int)$stock_item->item_srl] ?? [];
				if (!count($opts))
				{
					return (int)$stock_item->stock <= $stock_max;
				}
				foreach ($opts as $opt)
				{
					if ((int)$opt->stock <= $stock_max)
					{
						return true;
					}
				}
				return false;
			}));
		}
		\Context::set('stock_items', $items);
		\Context::set('stock_options_map', $options_map);
		\Context::set('stock_categories', self::getCategories());
		\Context::set('stock_filters', (object)['keyword' => $keyword, 'field' => $field, 'category' => $category_srl, 'from' => $date_from, 'to' => $date_to]);
		\Context::set('stock_page_navigation', $output->page_navigation);
		\Context::set('stock_low_only', \Context::get('f_low') === 'Y');
		\Context::set('stock_low_rows', StockModel::lowStockRows(200));
		\Context::set('stock_low_default', (int)(self::config()->low_stock_default ?? 0));

		$log_item = (int)\Context::get('log_item');
		$log_output = \Zittme\Modules\Commerce\Models\Stock::getLogs($log_item, max(1, (int)\Context::get('log_page')));
		\Context::set('stock_logs', $log_output->data ?: []);
		\Context::set('stock_log_item', $log_item);
		\Context::set('stock_log_navigation', $log_output->page_navigation);

		$this->renderView('stock', 'stock');
	}

	public function dispCommerceAdminPromotions()
	{
		$promotions = \Zittme\Modules\Commerce\Models\Promotion::listAll();
		\Context::set('promotions', $promotions);
		\Context::set('promo_now', self::now());

		$edit_srl = (int)\Context::get('promo_srl');
		$edit = $edit_srl > 0 ? \Zittme\Modules\Commerce\Models\Promotion::get($edit_srl) : null;
		\Context::set('promo_edit', $edit);
		\Context::set('promo_edit_items', $edit ? \Zittme\Modules\Commerce\Models\Promotion::itemSrlsOf((int)$edit->promo_srl) : []);

		$promo_cards = [];
		foreach ($promotions as $pm)
		{
			$promo_cards[(int)$pm->promo_srl] = (object)[
				'banner' => \Zittme\Modules\Commerce\Models\Promotion::bannerOf($pm),
				'count' => count(\Zittme\Modules\Commerce\Models\Promotion::itemSrlsOf((int)$pm->promo_srl)),
			];
		}
		\Context::set('promo_cards', $promo_cards);

		$promo_items = [];
		if ($edit)
		{
			$output = executeQueryArray('commerce.getItemList', (object)['status_list' => 'sale,soldout', 'list_count' => 2000, 'sort_index' => 'item_srl', 'order_type' => 'desc']);
			$promo_items = ($output->toBool() && !empty($output->data)) ? $output->data : [];
			// 다국어 코드는 서버에서 푼다. 템플릿이 이스케이프한 뒤에는 코어 치환이 걸리지 않는다
			LangModel::textAll($promo_items, ['item_name']);
			BrandModel::attach($promo_items);
		}
		\Context::set('promo_all_items', $promo_items);
		\Context::set('promo_categories', $edit ? array_values(self::getCategories()) : []);
		\Context::set('promo_brands', $edit ? BrandModel::getList() : []);
		$shop_mids = \ModuleModel::getMidList((object)['module' => 'commerce'], ['mid']) ?: [];
		$shop_mid = '';
		foreach ($shop_mids as $row) { $shop_mid = (string)$row->mid; break; }
		\Context::set('promo_shop_mid', $shop_mid);

		$this->renderView('promotions', 'promotions');
	}

	public function procCommerceAdminInsertPromotion()
	{
		$promo_srl = (int)\Context::get('promo_srl');
		$title = mb_substr(trim((string)\Context::get('title')), 0, 120);
		if ($title === '')
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_1'));
		}

		$slug = strtolower(trim((string)\Context::get('slug')));
		$slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
		$slug = trim(preg_replace('/-+/', '-', $slug), '-');
		if ($slug === '')
		{
			$slug = 'promo-' . ($promo_srl > 0 ? $promo_srl : getNextSequence());
		}
		foreach (\Zittme\Modules\Commerce\Models\Promotion::listAll() as $existing)
		{
			if ($existing->slug === $slug && (int)$existing->promo_srl !== $promo_srl)
			{
				return new \BaseObject(-1, lang('commerce.admin_msg_2') . $slug);
			}
		}

		$banner = json_decode((string)\Context::get('banner'), true);
		if (is_array($banner) && ($banner['main'] ?? 'N') === 'Y' && trim((string)($banner['logo'] ?? '')) === '')
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_3'));
		}
		$dates = [];
		foreach (['start_date', 'end_date'] as $k)
		{
			$raw = preg_replace('/[^0-9]/', '', (string)\Context::get($k));
			$dates[$k] = strlen($raw) >= 8 ? (strlen($raw) >= 14 ? substr($raw, 0, 14) : substr($raw, 0, 8) . ($k === 'end_date' ? '235959' : '000000')) : '';
		}

		$args = (object)[
			'title' => self::langValue('title', $title),
			'slug' => $slug,
			'banner' => is_array($banner) ? json_encode($banner, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : '',
			'description' => self::langValue('description', trim((string)\Context::get('description'))),
			'start_date' => $dates['start_date'],
			'end_date' => $dates['end_date'],
			'status' => \Context::get('status') === 'N' ? 'N' : 'Y',
			'list_order' => (int)\Context::get('list_order'),
		];
		if ($promo_srl > 0)
		{
			$args->promo_srl = $promo_srl;
			executeQuery('commerce.updatePromotion', $args);
		}
		else
		{
			$promo_srl = getNextSequence();
			$args->promo_srl = $promo_srl;
			$args->regdate = self::now();
			executeQuery('commerce.insertPromotion', $args);
		}

		$item_srls = json_decode((string)\Context::get('item_srls'), true);
		if (is_array($item_srls))
		{
			\Zittme\Modules\Commerce\Models\Promotion::syncItems($promo_srl, $item_srls);
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminPromotions'));
	}

	public function procCommerceAdminCreatePromotion()
	{
		$title = mb_substr(trim((string)\Context::get('title')), 0, 120);
		if ($title === '')
		{
			$title = lang('commerce.pm_default_title');
		}
		$promo_srl = getNextSequence();
		$today = substr(self::now(), 0, 8);
		executeQuery('commerce.insertPromotion', (object)[
			'promo_srl' => $promo_srl,
			'title' => $title,
			'slug' => 'promo-' . $promo_srl,
			'banner' => json_encode(['bg_type' => 'gradient', 'bg_color' => '#26345c', 'bg_color2' => '#151c33', 'text_color' => '#ffffff', 'shadow' => 'Y', 'main' => 'N'], \JSON_UNESCAPED_SLASHES),
			'description' => '',
			'start_date' => $today . '000000',
			'end_date' => date('Ymd', strtotime('+30 days')) . '235959',
			'status' => 'N',
			'list_order' => 0,
			'regdate' => self::now(),
		]);
		$this->add('promo_srl', $promo_srl);
	}

	public function procCommerceAdminPreviewPromotion()
	{
		$promo_srl = (int)\Context::get('promo_srl');
		if ($promo_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$banner = json_decode((string)\Context::get('banner'), true);
		$items = json_decode((string)\Context::get('item_srls'), true);
		$dates = [];
		foreach (['start_date', 'end_date'] as $k)
		{
			$raw = preg_replace('/[^0-9]/', '', (string)\Context::get($k));
			$dates[$k] = strlen($raw) >= 8 ? substr($raw, 0, 8) . ($k === 'end_date' ? '235959' : '000000') : '';
		}
		$_SESSION['commerce_promo_preview'] = [
			'srl' => $promo_srl,
			'time' => time(),
			'values' => [
				'title' => mb_substr(trim((string)\Context::get('title')), 0, 120),
				'description' => trim((string)\Context::get('description')),
				'banner' => is_array($banner) ? json_encode($banner, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : '',
				'start_date' => $dates['start_date'],
				'end_date' => $dates['end_date'],
			],
			'items' => is_array($items) ? array_values(array_filter(array_map('intval', $items))) : null,
		];
		$this->add('saved', 1);
	}

	public function procCommerceAdminDeletePromotion()
	{
		$promo_srl = (int)\Context::get('promo_srl');
		if ($promo_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('commerce.deletePromotion', (object)['promo_srl' => $promo_srl]);
		executeQuery('commerce.deletePromotionItems', (object)['promo_srl' => $promo_srl]);
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminPromotions'));
	}

	public function procCommerceAdminSaveLowStock()
	{
		$rows = json_decode((string)\Context::get('rows'), true);
		if (!is_array($rows))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$prefix = \Zittme\Modules\Commerce\Controllers\Install::dbPrefix();
		$handle = \Zittme\Framework\DB::getInstance()->getHandle();
		$item_stmt = $handle->prepare('UPDATE `' . $prefix . 'commerce_item` SET low_stock = ? WHERE item_srl = ?');
		$opt_stmt = $handle->prepare('UPDATE `' . $prefix . 'commerce_item_option` SET low_stock = ? WHERE option_srl = ?');

		$saved = 0;
		foreach ($rows as $row)
		{
			$limit = max(0, (int)($row['low_stock'] ?? 0));
			$option_srl = (int)($row['option_srl'] ?? 0);
			$item_srl = (int)($row['item_srl'] ?? 0);
			if ($option_srl > 0 && $opt_stmt)
			{
				$opt_stmt->execute([$limit, $option_srl]);
				$opt_stmt->closeCursor();
				$saved++;
			}
			elseif ($item_srl > 0 && $item_stmt)
			{
				$item_stmt->execute([$limit, $item_srl]);
				$item_stmt->closeCursor();
				$saved++;
			}
		}

		foreach ($rows as $row)
		{
			$item_srl = (int)($row['item_srl'] ?? 0);
			$option_srl = (int)($row['option_srl'] ?? 0);
			if ($item_srl > 0)
			{
				StockModel::checkLowStock($item_srl, $option_srl, StockModel::currentStock($item_srl, $option_srl));
			}
		}

		$this->setMessage(sprintf(lang('commerce.adm_low_stock_saved'), $saved));
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock'));
	}

	public function procCommerceAdminDeleteOrders()
	{
		if (($this->user->is_admin ?? '') !== 'Y')
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$srls = array_values(array_filter(array_map('intval', explode(',', (string)\Context::get('order_srls')))));
		if (!count($srls))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$done = 0;
		$skipped = 0;
		foreach (array_slice($srls, 0, 200) as $order_srl)
		{
			if (OrderModel::purge($order_srl))
			{
				$done++;
			}
			else
			{
				$skipped++;
			}
		}

		$this->setMessage(sprintf(lang('commerce.adm_orders_deleted'), $done, $skipped));
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders'));
	}

	public function procCommerceAdminStockAdjust()
	{
		$item_srl = (int)\Context::get('item_srl');
		$option_srl = max(0, (int)\Context::get('option_srl'));
		$type = (string)\Context::get('adjust_type');
		$qty = (int)\Context::get('qty');
		$memo = trim((string)\Context::get('memo'));

		$item = $item_srl > 0 ? ItemModel::get($item_srl) : null;
		if (!$item)
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}

		$member_srl = (int)(\Context::get('logged_info')->member_srl ?? 0);
		$result = \Zittme\Modules\Commerce\Models\Stock::adjust($item_srl, $option_srl, $type, $qty, $memo, $member_srl);
		if (!$result->ok)
		{
			return new \BaseObject(-1, $result->message ?: 'msg_invalid_request');
		}

		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminStock', 'f_keyword', (string)\Context::get('f_keyword')));
	}

	public function procCommerceAdminSaveFront()
	{
		$config = \ModuleModel::getModuleConfig('commerce') ?: new \stdClass;

		$config->shop_main = \Context::get('shop_main') === 'home' ? 'home' : 'list';
		$config->category_layout = \Context::get('category_layout') === 'side' ? 'side' : 'top';
		foreach (['show_shop_nav', 'show_search', 'show_admin_fab'] as $toggle)
		{
			$value = \Context::get($toggle);
			if ($value !== null && $value !== '')
			{
				$config->$toggle = $value === 'N' ? 'N' : 'Y';
			}
		}

		$decoded = json_decode((string)\Context::get('home_banners'), true);
		$config->home_banners = is_array($decoded)
			? json_encode(array_values($decoded), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
			: '[]';

		$output = \ModuleController::getInstance()->insertModuleConfig('commerce', $config);
		if (!$output->toBool())
		{
			return $output;
		}
		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedFullUrl('', 'mid', self::getDefaultInstance()->mid ?? self::DEFAULT_MID));
	}

	public function procCommerceAdminUploadBanner()
	{
		header('Content-Type: application/json; charset=utf-8');
		$file = $_FILES['file'] ?? null;
		if (!$file || !is_uploaded_file($file['tmp_name'] ?? ''))
		{
			echo json_encode(['error' => 1, 'message' => '파일이 없습니다.']); exit;
		}
		if ((int)$file['size'] > 8 * 1024 * 1024)
		{
			echo json_encode(['error' => 1, 'message' => '8MB 이하 이미지만 올릴 수 있습니다.']); exit;
		}
		$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
		{
			echo json_encode(['error' => 1, 'message' => '이미지 파일(jpg/png/gif/webp)만 올릴 수 있습니다.']); exit;
		}

		$dir = \RX_BASEDIR . 'files/attach/images/commerce/banner/';
		\Zittme\Framework\Storage::createDirectory($dir);
		$filename = 'banner_' . date('YmdHis') . '_' . substr(md5((string)mt_rand()), 0, 8) . '.' . $ext;
		if (!\Zittme\Framework\Storage::move($file['tmp_name'], $dir . $filename))
		{
			echo json_encode(['error' => 1, 'message' => '업로드에 실패했습니다.']); exit;
		}
		echo json_encode(['error' => 0, 'url' => \RX_BASEURL . 'files/attach/images/commerce/banner/' . $filename]); exit;
	}

	public function dispCommerceAdminQna()
	{
		$item_output = executeQueryArray('commerce.getItemList', (object)['list_count' => 1000, 'sort_index' => 'item_srl', 'order_type' => 'desc']);
		$item_names = [];
		foreach ($item_output->data ?: [] as $qna_item)
		{
			if (!empty($qna_item->item_srl))
			{
				$item_names[(int)$qna_item->item_srl] = LangModel::text($qna_item->item_name);
			}
		}
		\Context::set('qna_item_names', $item_names);

		$review_output = executeQueryArray('commerce.getReviewList', (object)[
			'list_count' => 30,
			'page' => max(1, (int)\Context::get('r_page')),
		]);
		\Context::set('qna_reviews', $review_output->data ?: []);
		\Context::set('qna_review_navigation', $review_output->page_navigation);

		// 미답변만 보기는 DB 조건이 있는 전용 쿼리를 쓴다. 화면에서 거르면 다음 페이지의 미답변을 놓친다
		$inquiry_query = \Context::get('f_unanswered') === 'Y' ? 'commerce.getUnansweredInquiryList' : 'commerce.getInquiryList';
		$inquiry_output = executeQueryArray($inquiry_query, (object)[
			'list_count' => 30,
			'page' => max(1, (int)\Context::get('i_page')),
		]);
		\Context::set('qna_inquiries', $inquiry_output->data ?: []);
		\Context::set('qna_inquiry_navigation', $inquiry_output->page_navigation);
		\Context::set('qna_unanswered', \Context::get('f_unanswered') === 'Y');

		$this->renderView('qna', 'qna');
	}

	public function procCommerceAdminReviewReply()
	{
		$review_srl = (int)\Context::get('review_srl');
		if ($review_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		// 빈 값이면 '' 로 지운다 — null 은 쿼리 빌더가 컬럼을 빼버려 SET 절 없는 UPDATE(1064)가 된다
		$reply = trim((string)\Context::get('reply'));
		$output = executeQuery('commerce.updateReviewReply', (object)[
			'review_srl' => $review_srl,
			'reply' => $reply,
			'reply_date' => $reply !== '' ? self::now() : '',
		]);
		if (!$output->toBool())
		{
			return $output;
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminQna'));
	}

	public function procCommerceAdminInquiryAnswer()
	{
		$inquiry_srl = (int)\Context::get('inquiry_srl');
		if ($inquiry_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		// 빈 값이면 '' 로 지운다 — null 은 쿼리 빌더가 컬럼을 빼버려 SET 절 없는 UPDATE(1064)가 된다
		$answer = trim((string)\Context::get('answer'));
		$asked = null;
		if ($answer !== '')
		{
			$found = executeQueryArray('commerce.getInquiryList', (object)['inquiry_srl' => $inquiry_srl, 'list_count' => 1]);
			$rows = $found->toBool() ? (array)$found->data : [];
			$asked = count($rows) ? reset($rows) : null;
		}

		$output = executeQuery('commerce.updateInquiryAnswer', (object)[
			'inquiry_srl' => $inquiry_srl,
			'answer' => $answer,
			'answer_date' => $answer !== '' ? self::now() : '',
		]);
		if (!$output->toBool())
		{
			return $output;
		}

		if (is_object($asked) && (int)($asked->member_srl ?? 0) > 0)
		{
			NotifyModel::send(
				(int)$asked->member_srl,
				lang('commerce.nc_inquiry_answered'),
				NotifyModel::itemUrl((int)($asked->item_srl ?? 0))
			);
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminQna'));
	}

	public function dispCommerceAdminItems()
	{
		$args = new \stdClass;
		$status = trim((string)\Context::get('f_status'));
		if ($status !== '')
		{
			$args->status_list = $status;
		}
		$category_srl = (int)\Context::get('f_category');
		if ($category_srl > 0)
		{
			$args->category_srl = $category_srl;
		}
		$keyword = trim((string)\Context::get('f_keyword'));
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
			$args->search_brand_srl_list = BrandModel::searchSrls($keyword) ?: null;
		}
		$brand_srl = (int)(\Context::get('f_brand') ?: \Context::get('brand_srl'));
		if ($brand_srl > 0)
		{
			$args->brand_srl = $brand_srl;
		}
		$my_seller = StaffModel::seller();
		$f_seller = 0;
		if ($my_seller)
		{
			$args->seller_srl = (int)$my_seller->seller_srl;
		}
		elseif (SellerModel::isOpen())
		{
			$f_seller = (int)\Context::get('f_seller');
			if ($f_seller > 0)
			{
				$args->seller_srl = $f_seller;
			}
		}
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 20;
		$args->sort_index = 'list_order';
		$args->order_type = 'asc';

		$output = executeQuery('commerce.getItemList', $args);
		$items = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		$items = LangModel::textAll($items, ['item_name', 'summary']);
		BrandModel::attach($items);
		\Context::set('items', $items);
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('categories', self::getCategories());
		\Context::set('brands', BrandModel::getList());
		$status_counts = ['' => 0, 'sale' => 0, 'soldout' => 0, 'hidden' => 0, 'stop' => 0, 'review' => 0];
		$st = isset($args->seller_srl)
			? \Zittme\Framework\DB::getInstance()->query('SELECT status, COUNT(*) AS cnt FROM commerce_item WHERE seller_srl = ? GROUP BY status', [(int)$args->seller_srl])
			: \Zittme\Framework\DB::getInstance()->query('SELECT status, COUNT(*) AS cnt FROM commerce_item GROUP BY status');
		foreach ($st ? $st->fetchAll() : [] as $row)
		{
			$status_counts[(string)$row->status] = (int)$row->cnt;
			$status_counts[''] += (int)$row->cnt;
		}
		\Context::set('status_counts', $status_counts);
		\Context::set('filters', (object)['status' => $status, 'category' => $category_srl, 'keyword' => $keyword, 'brand' => $brand_srl, 'seller' => $f_seller]);
		\Context::set('seller_mode', $my_seller ? 'seller' : (SellerModel::isOpen() ? 'operator' : ''));
		\Context::set('seller_names', !$my_seller && SellerModel::isOpen() ? SellerModel::nameMap() : []);
		$this->renderView('items', 'items');
	}

	public function procCommerceAdminBulkItemStatus()
	{
		$status = (string)\Context::get('status');
		if (!in_array($status, ['sale', 'soldout', 'hidden', 'stop'], true))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$srls = [];
		foreach ((array)\Context::get('item_srls') as $one)
		{
			if (is_scalar($one) && (int)$one > 0)
			{
				$srls[] = (int)$one;
			}
		}
		$srls = array_values(array_unique($srls));
		$my_seller = StaffModel::seller();
		if ($my_seller && SellerModel::needsReview() && in_array($status, ['sale', 'soldout'], true))
		{
			$status = 'review';
		}
		if ($srls)
		{
			$sql = 'UPDATE commerce_item SET status = ?, last_update = ? WHERE item_srl IN (' . implode(',', $srls) . ')';
			$params = [$status, self::now()];
			if ($my_seller)
			{
				$sql .= ' AND seller_srl = ?';
				$params[] = (int)$my_seller->seller_srl;
			}
			\Zittme\Framework\DB::getInstance()->query($sql, $params);
		}
		$this->setMessage(sprintf(lang('commerce.admin_items_bulk_done'), count($srls)));
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems'));
	}

	public function dispCommerceAdminItemEdit()
	{
		$item_srl = (int)\Context::get('item_srl');
		$clone_from = (int)\Context::get('clone_from');
		$item = null;
		$options = [];
		if ($item_srl <= 0 && $clone_from > 0)
		{
			$source = ItemModel::get($clone_from);
			if ($source)
			{
				$clone = clone $source;
				$clone->item_srl = 0;
				$clone->item_name = trim((string)$source->item_name) . ' ' . lang('commerce.adm_item_copy_suffix');
				$clone->item_code = '';
				$clone->stock = 0;
				$clone->regdate = '';
				$clone->last_update = '';
				\Context::set('clone_item', $clone);
				$options = ItemModel::getOptions($clone_from);
			}
		}
		if ($item_srl > 0)
		{
			$item = ItemModel::get($item_srl);
			$options = ItemModel::getOptions($item_srl);
		}

		$fx_currencies = array_values(array_diff(MoneyModel::currencies(), [MoneyModel::base()]));
		$fx_values = [];
		if (count($fx_currencies) && $item_srl > 0)
		{
			$currency_class = '\\Zittme\\Modules\\Zittme_pay\\Models\\Currency';
			foreach (ItemModel::getPrices($item_srl) as $fx_code => $fx_row)
			{
				if (!class_exists($currency_class))
				{
					break;
				}
				$fx_values[$fx_code] = [
					'price' => (int)$fx_row->price > 0 ? $currency_class::fromMinor((int)$fx_row->price, $fx_code) : '',
					'sale_price' => (int)$fx_row->sale_price > 0 ? $currency_class::fromMinor((int)$fx_row->sale_price, $fx_code) : '',
				];
			}
		}
		\Context::set('fx_currencies', $fx_currencies);
		\Context::set('fx_values', $fx_values);

		\Context::set('item_promotions', StaffModel::seller() ? [] : \Zittme\Modules\Commerce\Models\Promotion::listAll());
		\Context::set('item_promo_srls', $item_srl > 0 ? \Zittme\Modules\Commerce\Models\Promotion::promoSrlsOfItem($item_srl) : []);

		$editor_target_srl = $item_srl > 0 ? $item_srl : getNextSequence();
		\Context::set('editor_target_srl', $editor_target_srl);
		$edit_seller = StaffModel::seller();
		if ($edit_seller && !$item)
		{
			SellerModel::claimDraft($editor_target_srl, (int)$edit_seller->seller_srl);
		}
		\Context::set('seller_mode', $edit_seller ? 'seller' : '');

		\Context::set('content', $item->content ?? '');
		$editor_option = new \stdClass;
		$editor_option->primary_key_name = 'item_srl';
		$editor_option->content_key_name = 'content';
		$editor_option->allow_fileupload = true;
		$editor_option->enable_autosave = false;
		$editor_option->enable_default_component = true;
		$editor_option->enable_component = true;
		$editor_option->disable_html = false;
		$editor_option->height = 420;
		\Context::set('editor', \EditorModel::getEditor($editor_target_srl, $editor_option));

		\Context::set('item', $item);
		LangModel::textAll($options, ['option_label']);
		$edit_axes = ComboModel::axes($item->option_axes ?? '');
		if (count($edit_axes))
		{
			foreach ($edit_axes as $edit_axis)
			{
				foreach ($edit_axis->items as $edit_axis_item)
				{
					$edit_axis_item->value = LangModel::text($edit_axis_item->value);
				}
			}
			foreach ($options as $option)
			{
				if (empty($option->combo))
				{
					continue;
				}
				$edit_key = ComboModel::indexKey($edit_axes, $option->combo);
				$edit_label = ComboModel::labelFromKey($edit_axes, $edit_key);
				if ($edit_label !== '')
				{
					$option->option_label = $edit_label;
				}
			}
		}
		$pending_axes = '';
		if (!$item && count($options))
		{
			$pending_axes = ComboModel::axesFromOptions($options);
		}
		\Context::set('pending_axes', $pending_axes);

		\Context::set('options', $options);
		\Context::set('categories', self::getCategories());
		\Context::set('badges', BadgeModel::getList(true));
		\Context::set('item_brands', BrandModel::getList());
		$this->renderView('items', 'item_edit');
	}

	public function dispCommerceAdminCategories()
	{
		\Context::set('categories', array_values(self::getCategories()));
		$this->renderView('categories', 'categories');
	}

	public function procCommerceAdminSortCategories()
	{
		$raw = (string)\Context::get('tree');
		$rows = json_decode($raw, true);
		if (!is_array($rows) || !count($rows))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$parents = [];
		foreach ($rows as $row)
		{
			$srl = (int)($row['srl'] ?? 0);
			if ($srl > 0)
			{
				$parents[$srl] = (int)($row['parent'] ?? 0);
			}
		}

		$order = 1;
		foreach ($rows as $row)
		{
			$srl = (int)($row['srl'] ?? 0);
			if ($srl <= 0)
			{
				continue;
			}
			$parent = (int)($row['parent'] ?? 0);
			if ($parent === $srl || self::isDescendantCategory($parent, $srl, $parents))
			{
				$parent = 0;
			}
			executeQuery('commerce.updateCategory', (object)[
				'category_srl' => $srl,
				'parent_srl' => $parent,
				'list_order' => $order++,
			]);
		}

		$this->add('sorted', count($rows));
	}

	protected static function isDescendantCategory(int $maybe_child, int $ancestor, array $parents): bool
	{
		$cur = $maybe_child;
		$guard = 0;
		while ($cur > 0 && $guard++ < 20)
		{
			if ($cur === $ancestor)
			{
				return true;
			}
			$cur = $parents[$cur] ?? 0;
		}
		return false;
	}

	public function dispCommerceAdminBadges()
	{
		\Context::set('badges', BadgeModel::getList());
		$this->renderView('badges', 'badges');
	}

	public function dispCommerceAdminBrands()
	{
		\Context::set('brands', BrandModel::getList());
		\Context::set('brand_counts', BrandModel::itemCounts(false));
		$edit = (int)\Context::get('brand_srl');
		\Context::set('brand_edit', $edit > 0 ? BrandModel::get($edit) : null);
		$named = BrandModel::findNamedItems();
		\Context::set('brand_named', $named);
		\Context::set('brand_named_count', array_sum(array_map('count', $named)));
		$instance = self::getDefaultInstance();
		\Context::set('shop_mid', $instance ? $instance->mid : self::DEFAULT_MID);

		$brand_items = [];
		if ($edit > 0)
		{
			$output = executeQueryArray('commerce.getItemList', (object)['list_count' => 3000, 'sort_index' => 'item_srl', 'order_type' => 'desc']);
			$brand_items = ($output->toBool() && !empty($output->data)) ? $output->data : [];
			LangModel::textAll($brand_items, ['item_name']);
			BrandModel::attach($brand_items);
		}
		\Context::set('brand_all_items', $brand_items);
		\Context::set('brand_categories', $edit > 0 ? array_values(self::getCategories()) : []);
		$this->renderView('brands', 'brands');
	}

	public function procCommerceAdminSaveBrand()
	{
		$brand_srl = (int)\Context::get('brand_srl');
		$name = trim((string)\Context::get('name'));
		if ($name === '')
		{
			return new \BaseObject(-1, lang('commerce.admin_brand_need_name'));
		}
		$old = $brand_srl > 0 ? BrandModel::get($brand_srl) : null;
		$upload_target = $brand_srl > 0 ? $brand_srl : getNextSequence();
		$logo = $this->saveImage($upload_target, 'logo_file');
		$cover = $this->saveImage($upload_target, 'cover_file');
		$saved = BrandModel::save($old ? $brand_srl : 0, [
			'name' => self::langValue('name', mb_substr($name, 0, 100)),
			'name_en' => (string)\Context::get('name_en'),
			'slug' => (string)\Context::get('slug'),
			'logo' => $logo ?? (\Context::get('logo_url') !== null ? (string)\Context::get('logo_url') : (\Context::get('logo_clear') === 'Y' ? '' : (string)($old->logo ?? ''))),
			'cover' => $cover ?? (\Context::get('cover_url') !== null ? (string)\Context::get('cover_url') : (\Context::get('cover_clear') === 'Y' ? '' : (string)($old->cover ?? ''))),
			'description' => mb_substr(trim((string)\Context::get('description')), 0, 2000),
			'is_visible' => \Context::get('is_visible') === 'N' ? 'N' : 'Y',
		]);
		if (!$saved)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$item_srls = json_decode((string)\Context::get('item_srls'), true);
		if (is_array($item_srls))
		{
			BrandModel::setItems($saved, $item_srls);
		}
		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'brands', 'module', '', 'mid', ''));
	}

	public function procCommerceAdminCreateBrand()
	{
		$name = mb_substr(trim((string)\Context::get('name')), 0, 100);
		$saved = BrandModel::save(0, ['name' => $name !== '' ? $name : lang('commerce.br_default_name'), 'is_visible' => 'N']);
		if (!$saved)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$this->add('brand_srl', $saved);
	}

	public function procCommerceAdminReorderBrands()
	{
		$srls = json_decode((string)\Context::get('brand_srls'), true);
		BrandModel::reorder(is_array($srls) ? $srls : []);
		$this->add('saved', 1);
	}

	public function procCommerceAdminPreviewBrand()
	{
		$brand_srl = (int)\Context::get('brand_srl');
		if ($brand_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$items = json_decode((string)\Context::get('item_srls'), true);
		$values = [];
		foreach (['name', 'name_en', 'description', 'logo', 'cover'] as $key)
		{
			$values[$key] = trim((string)\Context::get($key));
		}
		$_SESSION['commerce_brand_preview'] = [
			'srl' => $brand_srl,
			'time' => time(),
			'values' => $values,
			'items' => is_array($items) ? array_values(array_filter(array_map('intval', $items))) : null,
		];
		$this->add('saved', 1);
	}

	public function procCommerceAdminDeleteBrand()
	{
		$brand_srl = (int)\Context::get('brand_srl');
		if ($brand_srl > 0)
		{
			BrandModel::delete($brand_srl);
		}
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'brands', 'module', '', 'mid', ''));
	}

	public function procCommerceAdminMoveBrand()
	{
		BrandModel::move((int)\Context::get('brand_srl'), \Context::get('dir') === 'up' ? 'up' : 'down');
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'brands', 'module', '', 'mid', ''));
	}

	public function procCommerceAdminMigrateBrands()
	{
		$r = BrandModel::migrateFromNames();
		$this->setMessage(sprintf(lang('commerce.admin_brand_migrated'), $r['items'], $r['brands']));
		$this->setRedirectUrl(getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'brands', 'module', '', 'mid', ''));
	}

	public function procCommerceAdminInsertBadge()
	{
		$badge_srl = (int)\Context::get('badge_srl');
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$fields = (object)[
			'title' => self::langValue('title', mb_substr($title, 0, 30)),
			'color' => self::filterColor((string)\Context::get('color')),
			'bg_color' => self::filterColor((string)\Context::get('bg_color')),
			'list_order' => (int)\Context::get('list_order'),
			'is_active' => \Context::get('is_active') === 'N' ? 'N' : 'Y',
		];
		if ($badge_srl > 0)
		{
			$fields->badge_srl = $badge_srl;
			executeQuery('commerce.updateBadge', $fields);
		}
		else
		{
			$fields->badge_srl = getNextSequence();
			$fields->regdate = self::now();
			executeQuery('commerce.insertBadge', $fields);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminBadges'));
	}

	public function procCommerceAdminDeleteBadge()
	{
		$badge_srl = (int)\Context::get('badge_srl');
		if ($badge_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('commerce.deleteBadge', (object)['badge_srl' => $badge_srl]);

		$this->setMessage('success_deleted');
		$return = trim((string)\Context::get('success_return_url'));
		$this->setRedirectUrl($return !== '' ? $return : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminBadges', 'badge_srl', ''));
	}

	protected static function filterColor(string $value): string
	{
		$value = trim($value);
		return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : '';
	}

	public function dispCommerceAdminOrders()
	{
		OrderModel::expireStalePending();
		\Zittme\Modules\Commerce\Models\Tracking::syncShipping();

		$args = new \stdClass;
		$status = trim((string)\Context::get('f_status'));
		$show_expired = \Context::get('f_expired') === 'Y';
		if ($status !== '')
		{
			$args->status_list = $status;
		}
		elseif (!$show_expired)
		{
			$args->status_list = implode(',', [self::ORDER_PENDING, self::ORDER_PAID, self::ORDER_CANCELLED, 'failed']);
		}
		$keyword = trim((string)\Context::get('f_keyword'));
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
		}
		$from = trim((string)\Context::get('f_from'));
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))
		{
			$args->from_date = str_replace('-', '', $from) . '000000';
		}
		else
		{
			$from = '';
		}
		$to = trim((string)\Context::get('f_to'));
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))
		{
			$args->to_date = str_replace('-', '', $to) . '235959';
		}
		else
		{
			$to = '';
		}
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 20;

		$ship = trim((string)\Context::get('f_ship'));
		$ship_map = ['to_ship' => 'paid,preparing', 'shipping' => 'shipping', 'delivered' => 'delivered'];
		$order_query = 'commerce.getOrderList';
		if (isset($ship_map[$ship]))
		{
			$args->ship_status_list = $ship_map[$ship];
			$order_query = 'commerce.getOrderListByShipStatus';
		}
		else
		{
			$ship = '';
		}

		$output = executeQuery($order_query, $args);
		$orders = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		$seller_map = [];
		foreach ($orders as $o)
		{
			$sellers = OrderModel::getSellerOrders((int)$o->order_srl);
			$seller_map[(int)$o->order_srl] = count($sellers) ? $sellers[0] : null;
		}

		$hidden_expired = 0;
		if ($status === '' && !$show_expired)
		{
			$expired_output = executeQuery('commerce.getOrderList', (object)['status_list' => self::ORDER_EXPIRED, 'list_count' => 1, 'page' => 1]);
			$hidden_expired = (int)($expired_output->page_navigation->total_count ?? 0);
		}

		\Context::set('orders', $orders);
		\Context::set('seller_map', $seller_map);
		\Context::set('hidden_expired', $hidden_expired);
		\Context::set('is_super_admin', ($this->user->is_admin ?? '') === 'Y');
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('filters', (object)['status' => $status, 'keyword' => $keyword, 'ship' => $ship, 'from' => $from, 'to' => $to, 'expired' => $show_expired ? 'Y' : '']);
		$this->renderView('orders', 'orders');
	}

	public function dispCommerceAdminOrderView()
	{
		$order_srl = (int)\Context::get('order_srl');
		$order = OrderModel::get($order_srl);
		if (!$order)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}

		$address_output = executeQuery('commerce.getOrderAddress', (object)['order_srl' => $order_srl]);
		$logs_output = executeQuery('commerce.getOrderLogs', (object)['order_srl' => $order_srl]);
		$claims_output = executeQuery('commerce.getClaimList', (object)['order_srl' => $order_srl]);

		$to_array = function($output) {
			if (!$output->toBool() || empty($output->data)) return [];
			return is_array($output->data) ? $output->data : [$output->data];
		};

		$pay_order = null;
		if ((int)($order->pay_order_srl ?? 0) > 0 && class_exists('\\Zittme\\Modules\\Zittme_pay\\Models\\Order'))
		{
			$pay_order = \Zittme\Modules\Zittme_pay\Models\Order::get((int)$order->pay_order_srl);
		}
		\Context::set('pay_order', $pay_order);

		\Context::set('order', $order);
		\Context::set('order_items', OrderModel::getItems($order_srl));
		\Context::set('order_sellers', OrderModel::getSellerOrders($order_srl));
		$order_address = count($to_array($address_output)) ? $to_array($address_output)[0] : null;
		\Context::set('order_address', $order_address);
		// 템플릿에서 클래스를 직접 부르면 컴파일 시 네임스페이스 구분자가 유실된다
		\Context::set('order_address_text', $order_address ? AddressModel::format($order_address) : '');
		\Context::set('order_phone_text', $order_address ? AddressModel::formatPhone($order_address) : '');
		\Context::set('order_logs', $to_array($logs_output));
		\Context::set('order_claims', $to_array($claims_output));
		$this->renderView('orders', 'order_view');
	}

	protected static function langValue(string $field, string $fallback): string
	{
		$code = LangModel::filterCode((string)\Context::get($field . '_langcode'));
		return $code !== '' ? LangModel::toValue($code) : $fallback;
	}

	public function procCommerceAdminGetLangCodes()
	{
		$rows = [];
		foreach (LangModel::search((string)\Context::get('keyword'), 40) as $row)
		{
			$rows[] = ['code' => $row->code, 'value' => $row->value];
		}
		$this->add('codes', $rows);
	}

	public function procCommerceAdminSaveLangCode()
	{
		$values = \Context::get('values');
		$code = LangModel::save((string)\Context::get('code'), is_array($values) ? $values : []);
		if ($code === '')
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_4'));
		}
		$this->add('code', $code);
		$this->add('value', LangModel::display($code));
	}

	public function procCommerceAdminGetLangCode()
	{
		$code = LangModel::filterCode((string)\Context::get('code'));
		$this->add('code', $code);
		$this->add('values', LangModel::values($code));
	}

	protected static function csvAddressLine(object $address): string
	{
		$country = strtoupper((string)($address->country ?? '')) ?: AddressModel::baseCountry();
		$parts = [(string)($address->address1 ?? '')];

		if ($country !== 'KR')
		{
			$parts[] = (string)($address->city ?? '');
			$state = (string)($address->state ?? '');
			if ($state !== '')
			{
				$parts[] = RegionModel::name($state);
			}
		}
		if (!AddressModel::isDomestic($country))
		{
			$parts[] = AddressModel::countryName($country);
		}

		return trim(implode(' ', array_filter($parts, function($part) { return trim($part) !== ''; })));
	}

	public function dispCommerceAdminExportOrders()
	{
		if (!StaffModel::isStaff())
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$picked = [];
		foreach (explode(',', (string)\Context::get('order_srls')) as $srl)
		{
			$srl = (int)trim($srl);
			if ($srl > 0)
			{
				$picked[$srl] = $srl;
			}
		}

		if (count($picked))
		{
			$orders = [];
			foreach ($picked as $srl)
			{
				$order = OrderModel::get($srl);
				if ($order)
				{
					$orders[] = $order;
				}
			}
		}
		else
		{
			$args = new \stdClass;
			$status = trim((string)\Context::get('f_status'));
			if ($status !== '')
			{
				$args->status_list = $status;
			}
			$keyword = trim((string)\Context::get('f_keyword'));
			if ($keyword !== '')
			{
				$args->search_keyword = '%' . $keyword . '%';
			}
			$from = trim((string)\Context::get('f_from'));
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))
			{
				$args->from_date = str_replace('-', '', $from) . '000000';
			}
			$to = trim((string)\Context::get('f_to'));
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))
			{
				$args->to_date = str_replace('-', '', $to) . '235959';
			}
			$args->page = 1;
			$args->list_count = 5000;

			$output = executeQuery('commerce.getOrderList', $args);
			$orders = ($output->toBool() && !empty($output->data)) ?
				(is_array($output->data) ? $output->data : [$output->data]) : [];
		}

		$rows = [[
			lang('commerce.csv_order_code'), lang('commerce.csv_order_date'), lang('commerce.csv_orderer'), lang('commerce.csv_orderer_phone'), lang('commerce.csv_orderer_email'),
			lang('commerce.csv_receiver'), lang('commerce.csv_receiver_phone'), lang('commerce.csv_zipcode'), lang('commerce.csv_address'), lang('commerce.csv_address_detail'), lang('commerce.csv_delivery_memo'),
			lang('commerce.csv_items'), lang('commerce.csv_qty_total'), lang('commerce.csv_amount'), lang('commerce.csv_pay_status'), lang('commerce.csv_ship_status'), lang('commerce.csv_carrier'), lang('commerce.csv_tracking_no'),
		]];

		$order_labels = ['pending' => lang('commerce.st_order_pending'), 'paid' => lang('commerce.st_order_paid'), 'cancelled' => lang('commerce.st_order_cancelled'), 'failed' => lang('commerce.st_order_failed'), 'expired' => lang('commerce.st_order_expired')];
		$seller_labels = ['pending' => lang('commerce.st_order_pending'), 'paid' => lang('commerce.st_sel_paid'), 'preparing' => lang('commerce.st_sel_preparing'), 'shipping' => lang('commerce.st_sel_shipping'), 'delivered' => lang('commerce.st_sel_delivered'), 'confirmed' => lang('commerce.st_log_confirmed'), 'cancelled' => lang('commerce.st_order_cancelled'), 'refunded' => lang('commerce.st_sel_refunded')];

		foreach ($orders as $order)
		{
			$order_srl = (int)$order->order_srl;

			$address_output = executeQuery('commerce.getOrderAddress', (object)['order_srl' => $order_srl]);
			$address = ($address_output->toBool() && !empty($address_output->data)) ?
				(is_array($address_output->data) ? $address_output->data[0] : $address_output->data) : null;

			$names = [];
			$qty_total = 0;
			foreach (OrderModel::getItems($order_srl) as $oi)
			{
				$name = (string)$oi->item_name;
				if ($oi->option_name)
				{
					$name .= ' (' . $oi->option_name . ')';
				}
				$names[] = $name . ' x' . (int)$oi->qty;
				$qty_total += (int)$oi->qty;
			}

			$sellers = OrderModel::getSellerOrders($order_srl);
			$seller = count($sellers) ? $sellers[0] : null;

			$rows[] = [
				(string)$order->order_code,
				zdate($order->regdate, 'Y-m-d H:i'),
				(string)$order->orderer_name,
				(string)$order->orderer_phone,
				(string)$order->orderer_email,
				$address ? (string)$address->receiver_name : '',
				$address ? AddressModel::formatPhone($address) : '',
				$address ? (string)$address->zipcode : '',
				$address ? self::csvAddressLine($address) : '',
				$address ? (string)$address->address2 : '',
				$address ? (string)$address->delivery_memo : '',
				implode(' / ', $names),
				(string)$qty_total,
				MoneyModel::minorToInput((int)$order->payment_price),
				$order_labels[$order->status] ?? (string)$order->status,
				$seller ? ($seller_labels[$seller->status] ?? (string)$seller->status) : '',
				$seller ? (string)($seller->shipping_company ?? '') : '',
				$seller ? (string)($seller->shipping_invoice ?? '') : '',
			];
		}

		$filename = 'orders_' . date('Ymd_His') . '.csv';

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-store');

		// 엑셀은 BOM 이 없으면 UTF-8 CSV 를 ANSI 로 읽어 한글이 깨진다
		echo "\xEF\xBB\xBF";
		$fp = fopen('php://output', 'w');
		foreach ($rows as $row)
		{
			fputcsv($fp, $row);
		}
		fclose($fp);
		exit;
	}

	public function dispCommerceAdminOrderInvoice()
	{
		if (!StaffModel::isStaff())
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$srls = [];
		foreach (explode(',', (string)\Context::get('order_srls')) as $srl)
		{
			$srl = (int)trim($srl);
			if ($srl > 0)
			{
				$srls[$srl] = $srl;
			}
		}
		$single = (int)\Context::get('order_srl');
		if ($single > 0)
		{
			$srls[$single] = $single;
		}
		if (!count($srls))
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}
		$srls = array_slice(array_values($srls), 0, 50);

		$invoices = [];
		foreach ($srls as $srl)
		{
			$order = OrderModel::get($srl);
			if (!$order)
			{
				continue;
			}

			$address_output = executeQuery('commerce.getOrderAddress', (object)['order_srl' => $srl]);
			$address = ($address_output->toBool() && !empty($address_output->data)) ?
				(is_array($address_output->data) ? $address_output->data[0] : $address_output->data) : null;

			$items = OrderModel::getItems($srl);
			foreach ($items as $oi)
			{
				if (empty($oi->tax_type))
				{
					$item = ItemModel::get((int)$oi->item_srl);
					$oi->tax_type = ($item && ($item->tax_type ?? '') === 'free') ? 'free' : 'taxable';
				}
			}

			$invoices[] = (object)[
				'order' => $order,
				'items' => $items,
				'address' => $address,
				'address_text' => $address ? AddressModel::format($address) : '',
				'phone_text' => $address ? AddressModel::formatPhone($address) : '',
				'country_name' => $address ? AddressModel::countryName((string)($address->country ?? 'KR')) : '',
				'sellers' => OrderModel::getSellerOrders($srl),
				'tax' => TaxModel::breakdown(
					self::config(),
					$items,
					(int)$order->delivery_fee_total,
					$address ? (string)($address->country ?? 'KR') : 'KR'
				),
			];
		}
		if (!count($invoices))
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}

		\Context::set('invoices', $invoices);
		\Context::set('shop_config', self::config());
		\Context::setBrowserTitle(count($invoices) > 1 ?
			('주문서 ' . count($invoices) . '건') : ('주문서 ' . $invoices[0]->order->order_code));
		\Context::set('layout', 'none');

		$this->setTemplatePath($this->module_path . 'views/admin/');
		$this->setTemplateFile('invoice');
	}

	public function dispCommerceAdminClaims()
	{
		$args = new \stdClass;
		$status = trim((string)\Context::get('f_status'));
		if ($status !== '')
		{
			$args->status_list = $status;
		}
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 20;

		$output = executeQuery('commerce.getClaimList', $args);
		$claims = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		$order_map = [];
		foreach ($claims as $c)
		{
			if (!isset($order_map[(int)$c->order_srl]))
			{
				$order_map[(int)$c->order_srl] = OrderModel::get((int)$c->order_srl);
			}
		}

		\Context::set('claims', $claims);
		\Context::set('order_map', $order_map);
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('filters', (object)['status' => $status]);
		$this->renderView('claims', 'claims');
	}

	public function dispCommerceAdminCoupons()
	{
		$coupons = \Zittme\Modules\Commerce\Models\Coupon::getList();

		$issue_counts = [];
		foreach ($coupons as $c)
		{
			$cnt = executeQuery('commerce.countCouponUses', (object)['coupon_srl' => (int)$c->coupon_srl]);
			$issue_counts[(int)$c->coupon_srl] = $cnt->toBool() ? (int)($cnt->data->count ?? 0) : 0;
		}

		\Context::set('coupons', $coupons);
		\Context::set('issue_counts', $issue_counts);
		$this->renderView('coupons', 'coupons');
	}

	public function procCommerceAdminInsertCoupon()
	{
		$coupon_srl = (int)\Context::get('coupon_srl');
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$code = strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', (string)\Context::get('code')));
		if ($code !== '')
		{
			$dup = \Zittme\Modules\Commerce\Models\Coupon::getByCode($code);
			if ($dup && (int)$dup->coupon_srl !== $coupon_srl)
			{
				return new \BaseObject(-1, 'msg_shop_coupon_code_dup');
			}
		}

		$discount_type = \Context::get('discount_type') === 'percent' ? 'percent' : 'fixed';
		$discount_value = $discount_type === 'percent'
			? min(100, max(0, (int)\Context::get('discount_value')))
			: max(0, MoneyModel::inputToMinor(\Context::get('discount_value')));

		$args = (object)[
			'title' => self::langValue('title', mb_substr($title, 0, 120)),
			'code' => $code,
			'discount_type' => $discount_type,
			'discount_value' => $discount_value,
			'max_discount' => max(0, MoneyModel::inputToMinor(\Context::get('max_discount'))),
			'min_order' => max(0, MoneyModel::inputToMinor(\Context::get('min_order'))),
			'use_start' => preg_replace('/\D/', '', (string)\Context::get('use_start')) ? preg_replace('/\D/', '', (string)\Context::get('use_start')) . '000000' : '',
			'use_end' => preg_replace('/\D/', '', (string)\Context::get('use_end')) ? preg_replace('/\D/', '', (string)\Context::get('use_end')) . '235959' : '',
			'per_member' => max(1, (int)\Context::get('per_member')),
			'total_limit' => max(0, (int)\Context::get('total_limit')),
			'status' => \Context::get('status') === 'N' ? 'N' : 'Y',
		];

		if ($coupon_srl > 0 && \Zittme\Modules\Commerce\Models\Coupon::get($coupon_srl))
		{
			$args->coupon_srl = $coupon_srl;
			$output = executeQuery('commerce.updateCoupon', $args);
		}
		else
		{
			$args->coupon_srl = getNextSequence();
			$args->used_count = 0;
			$args->regdate = self::now();
			$output = executeQuery('commerce.insertCoupon', $args);
		}
		if (!$output->toBool())
		{
			return $output;
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedFullUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCoupons'));
	}

	public function procCommerceAdminDeleteCoupon()
	{
		$coupon_srl = (int)\Context::get('coupon_srl');
		if ($coupon_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('commerce.deleteCoupon', (object)['coupon_srl' => $coupon_srl]);
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedFullUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCoupons'));
	}

	public function procCommerceAdminIssueCoupon()
	{
		$coupon_srl = (int)\Context::get('coupon_srl');
		$coupon = \Zittme\Modules\Commerce\Models\Coupon::get($coupon_srl);
		if (!$coupon)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$target = trim((string)\Context::get('target'));
		if ($target === '')
		{
			return new \BaseObject(-1, 'msg_shop_coupon_no_member');
		}
		$member_srl = strpos($target, '@') !== false
			? (int)\MemberModel::getMemberSrlByEmailAddress($target)
			: (int)\MemberModel::getMemberSrlByUserID($target);
		if ($member_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_shop_coupon_no_member');
		}

		if (!\Zittme\Modules\Commerce\Models\Coupon::issueTo($coupon_srl, $member_srl))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$this->setMessage('msg_shop_coupon_issued');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedFullUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCoupons'));
	}

	public function dispCommerceAdminCredits()
	{
		$target = trim((string)\Context::get('f_target'));
		$member = null;
		$balance = 0;
		$logs = [];
		if ($target !== '')
		{
			$member_srl = strpos($target, '@') !== false
				? (int)\MemberModel::getMemberSrlByEmailAddress($target)
				: (int)\MemberModel::getMemberSrlByUserID($target);
			if ($member_srl > 0)
			{
				$member = \MemberModel::getMemberInfoByMemberSrl($member_srl);
				$balance = \Zittme\Modules\Commerce\Models\Credit::balanceOf($member_srl);
				$logs = \Zittme\Modules\Commerce\Models\Credit::getLogs($member_srl, 50);
			}
		}

		$output = executeQuery('commerce.getCreditLogs', (object)['list_count' => 30]);
		$recent = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];
		$name_cache = [];
		foreach ($recent as $recent_row)
		{
			$row_srl = (int)($recent_row->member_srl ?? 0);
			if ($row_srl > 0 && !isset($name_cache[$row_srl]))
			{
				$row_member = \MemberModel::getMemberInfoByMemberSrl($row_srl);
				$name_cache[$row_srl] = ($row_member && !empty($row_member->member_srl))
					? (string)($row_member->nick_name ?: $row_member->user_id)
					: '';
			}
			$recent_row->member_name = $name_cache[$row_srl] ?? '';
		}

		\Context::set('f_target', $target);
		\Context::set('credit_member', $member);
		\Context::set('credit_balance', $balance);
		\Context::set('credit_logs', $logs);
		\Context::set('recent_logs', $recent);
		$this->renderView('credits', 'credits');
	}

	public function procCommerceAdminAdjustCredit()
	{
		$target = trim((string)\Context::get('target'));
		$amount = MoneyModel::inputToMinor(\Context::get('amount'));
		if ($target === '' || $amount === 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$member_srl = strpos($target, '@') !== false
			? (int)\MemberModel::getMemberSrlByEmailAddress($target)
			: (int)\MemberModel::getMemberSrlByUserID($target);
		if ($member_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_shop_coupon_no_member');
		}

		$memo = mb_substr(trim((string)\Context::get('memo')), 0, 250);
		if (!\Zittme\Modules\Commerce\Models\Credit::add($member_srl, $amount, 'admin', 0, $memo))
		{
			return new \BaseObject(-1, 'msg_shop_credit_adjust_failed');
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedFullUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCredits', 'f_target', $target));
	}

	public function dispCommerceAdminGrades()
	{
		\Context::set('grades', GradeModel::getList());
		\Context::set('grade_coupons', \Zittme\Modules\Commerce\Models\Coupon::getList());
		\Context::set('grade_groups', \MemberModel::getGroups());
		$this->renderView('grades', 'grades');
	}

	public function procCommerceAdminInsertGrade()
	{
		$grade_srl = (int)\Context::get('grade_srl');
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$discount_type = (string)\Context::get('discount_type');
		if (!in_array($discount_type, ['amount', 'percent'], true))
		{
			$discount_type = '';
		}
		$discount_value = $discount_type === 'percent'
			? min(100, max(0, round((float)\Context::get('discount_value'), 2)))
			: max(0, MoneyModel::inputToMinor(\Context::get('discount_value')));
		if ($discount_type === '' || $discount_value <= 0)
		{
			$discount_type = '';
			$discount_value = 0;
		}

		$group_srl = max(0, (int)\Context::get('group_srl'));
		if ($group_srl > 0 && GradeModel::groupTaken($group_srl, $grade_srl))
		{
			return new \BaseObject(-1, 'msg_shop_grade_group_taken');
		}
		$old_group = $grade_srl > 0 ? GradeModel::groupOf($grade_srl) : 0;

		$args = (object)[
			'title' => self::langValue('title', mb_substr($title, 0, 80)),
			'min_spend' => max(0, MoneyModel::inputToMinor(\Context::get('min_spend'))),
			'credit_rate' => max(0, min(100, round((float)\Context::get('credit_rate'), 2))),
			'coupon_srl' => max(0, (int)\Context::get('coupon_srl')),
			'group_srl' => $group_srl,
			'discount_type' => $discount_type,
			'discount_value' => $discount_value,
		];

		if ($grade_srl > 0)
		{
			$args->grade_srl = $grade_srl;
			$output = executeQuery('commerce.updateGrade', $args);
		}
		else
		{
			$args->grade_srl = getNextSequence();
			$args->regdate = self::now();
			$output = executeQuery('commerce.insertGrade', $args);
		}
		if (!$output->toBool())
		{
			return $output;
		}

		GradeModel::applyGroupToMembers((int)$args->grade_srl, $old_group, $group_srl);

		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminGrades'));
	}

	public function procCommerceAdminDeleteGrade()
	{
		$grade_srl = (int)\Context::get('grade_srl');
		if ($grade_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		GradeModel::applyGroupToMembers($grade_srl, GradeModel::groupOf($grade_srl), 0);

		executeQuery('commerce.deleteGrade', (object)['grade_srl' => $grade_srl]);
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminGrades'));
	}

	public function dispCommerceAdminStats()
	{
		$tab = (string)\Context::get('t');
		if (!in_array($tab, ['period', 'item', 'region'], true))
		{
			$tab = 'period';
		}

		$unit = (string)\Context::get('unit');
		if (!isset(StatsModel::UNITS[$unit]))
		{
			$unit = 'day';
		}

		[$from, $to] = self::statsRange();

		\Context::set('st_tab', $tab);
		\Context::set('st_unit', $unit);
		\Context::set('st_units', StatsModel::UNITS);
		\Context::set('st_from', $from);
		\Context::set('st_to', $to);
		\Context::set('st_summary', StatsModel::summary($from, $to));

		if ($tab === 'item')
		{
			\Context::set('st_rows', StatsModel::byItem($from, $to, 200));
		}
		elseif ($tab === 'region')
		{
			\Context::set('st_rows', StatsModel::byRegion($from, $to));
		}
		else
		{
			\Context::set('st_rows', StatsModel::series($from, $to, $unit));
		}

		$this->renderView('stats', 'stats');
	}

	protected static function statsRange(): array
	{
		$clean = function($v) {
			$v = preg_replace('/[^0-9]/', '', (string)$v);
			return strlen($v) === 8 ? $v : '';
		};

		$from = $clean(\Context::get('from'));
		$to = $clean(\Context::get('to'));
		if ($from === '')
		{
			$from = date('Ymd', strtotime('-29 days'));
		}
		if ($to === '')
		{
			$to = date('Ymd');
		}
		if ($from > $to)
		{
			[$from, $to] = [$to, $from];
		}
		return [$from, $to];
	}

	public function dispCommerceAdminExportStats()
	{
		if (!StaffModel::isStaff())
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$tab = (string)\Context::get('t');
		if (!in_array($tab, ['period', 'item', 'region'], true))
		{
			$tab = 'period';
		}
		$unit = (string)\Context::get('unit');
		if (!isset(StatsModel::UNITS[$unit]))
		{
			$unit = 'day';
		}
		[$from, $to] = self::statsRange();

		if ($tab === 'item')
		{
			$rows = [[lang('commerce.csv_item'), lang('commerce.csv_qty_sold'), lang('commerce.csv_order_count'), lang('commerce.csv_revenue')]];
			foreach (StatsModel::byItem($from, $to, 1000) as $r)
			{
				$rows[] = [$r->item_name, (string)$r->qty, (string)$r->orders, MoneyModel::minorToInput((int)$r->sales)];
			}
		}
		elseif ($tab === 'region')
		{
			$rows = [[lang('commerce.csv_region'), lang('commerce.csv_order_count'), lang('commerce.csv_revenue')]];
			foreach (StatsModel::byRegion($from, $to) as $r)
			{
				$rows[] = [$r->region, (string)$r->orders, MoneyModel::minorToInput((int)$r->sales)];
			}
		}
		else
		{
			$rows = [[lang('commerce.csv_period'), lang('commerce.csv_order_count'), lang('commerce.csv_revenue')]];
			foreach (StatsModel::series($from, $to, $unit) as $r)
			{
				$rows[] = [$r->label, (string)$r->orders, MoneyModel::minorToInput((int)$r->sales)];
			}
		}

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="stats_' . $tab . '_' . $from . '_' . $to . '.csv"');
		header('Cache-Control: no-store');

		echo "\xEF\xBB\xBF";
		$fp = fopen('php://output', 'w');
		foreach ($rows as $row)
		{
			fputcsv($fp, $row);
		}
		fclose($fp);
		exit;
	}

	protected static function remapCombos(int $item_srl, $old_axes, $new_axes): void
	{
		$old = ComboModel::axes($old_axes);
		$new = ComboModel::axes($new_axes);
		if (!count($old) || count($old) !== count($new))
		{
			return;
		}

		$name_map = [];
		$value_map = [];
		foreach ($old as $axis_index => $old_axis)
		{
			$new_axis = $new[$axis_index];
			if (count($old_axis->items) !== count($new_axis->items))
			{
				return;
			}
			$name_map[$old_axis->name] = $new_axis->name;
			foreach ($old_axis->items as $value_index => $old_item)
			{
				$value_map[$old_axis->name . "\0" . $old_item->value] = $new_axis->items[$value_index]->value;
			}
		}
		if ($name_map === array_combine(array_keys($name_map), array_keys($name_map))
			&& count(array_filter($value_map, function($v, $k) { return $v !== substr($k, strpos($k, "\0") + 1); }, \ARRAY_FILTER_USE_BOTH)) === 0)
		{
			return;
		}

		foreach (ItemModel::getOptions($item_srl) as $option)
		{
			if (empty($option->combo))
			{
				continue;
			}
			$combo = json_decode((string)$option->combo, true);
			if (!is_array($combo))
			{
				continue;
			}
			$moved = [];
			foreach ($combo as $old_name => $old_value)
			{
				$new_name = $name_map[$old_name] ?? $old_name;
				$moved[$new_name] = $value_map[$old_name . "\0" . $old_value] ?? $old_value;
			}
			executeQuery('commerce.updateOption', (object)[
				'option_srl' => (int)$option->option_srl,
				'option_label' => ComboModel::label($moved),
				'option_type' => $option->option_type ?? 'basic',
				'combo' => json_encode($moved, \JSON_UNESCAPED_UNICODE),
				'price_add' => (int)$option->price_add,
				'stock' => (int)$option->stock,
				'sku' => (string)($option->sku ?? ''),
				'list_order' => (int)$option->list_order,
			]);
		}
	}

	public function dispCommerceAdminConfig()
	{
		\Context::set('pay_available', self::isPayAvailable());

		$zones = json_decode((string)(self::config()->ship_extra_zones ?? '[]'), true);
		$zones = is_array($zones) ? array_values($zones) : [];
		foreach ($zones as $zone_index => $zone_row)
		{
			if (is_array($zone_row))
			{
				$zones[$zone_index]['fee'] = MoneyModel::minorToInput((int)($zone_row['fee'] ?? 0));
				if (is_array($zone_row['tiers'] ?? null))
				{
					foreach ($zone_row['tiers'] as $tier_index => $tier_row)
					{
						if (is_array($tier_row))
						{
							$zones[$zone_index]['tiers'][$tier_index]['from'] = MoneyModel::minorToInput((int)($tier_row['from'] ?? 0));
							$zones[$zone_index]['tiers'][$tier_index]['fee'] = MoneyModel::minorToInput((int)($tier_row['fee'] ?? 0));
						}
					}
				}
			}
		}
		\Context::set('zmc_zones_display', json_encode($zones, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

		$config_row = self::config();
		LangModel::textAll([$config_row], self::LANG_CONFIG_FIELDS);
		\Context::set('shop_config', $config_row);

		$zone_region_data = [];
		foreach (array_keys(RegionModel::REGIONS) as $zone_country_code)
		{
			$zone_region_data[$zone_country_code] = RegionModel::searchData($zone_country_code);
		}
		\Context::set('zmc_country_json', json_encode(AddressModel::countries(), \JSON_UNESCAPED_UNICODE));
		\Context::set('zmc_region_json', json_encode($zone_region_data, \JSON_UNESCAPED_UNICODE));
		\Context::addCSSFile('./modules/commerce/tpl/css/pickbox.css');
		\Context::addJsFile('./modules/commerce/tpl/js/pickbox.js');

		$instance = self::getDefaultInstance();
		$module_info = $instance ? \ModuleModel::getModuleInfoByMid($instance->mid) : null;
		\Context::set('shop_instance', $module_info);
		\Context::set('shop_skins', \ModuleModel::getSkins(\RX_BASEDIR . 'modules/commerce') ?: []);
		\Context::set('shop_mskins', \ModuleModel::getSkins(\RX_BASEDIR . 'modules/commerce', 'm.skins') ?: []);

		$layout_model = getModel('layout');
		\Context::set('shop_layouts', $layout_model->getLayoutList(0, 'P') ?: []);
		\Context::set('shop_mlayouts', $layout_model->getLayoutList(0, 'M') ?: []);
		$this->renderView('config', 'config');
	}

	public function procCommerceAdminUpdateSkin()
	{
		$instance = self::getDefaultInstance();
		$module_info = $instance ? \ModuleModel::getModuleInfoByMid($instance->mid) : null;
		if (!$module_info || empty($module_info->module_srl))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$sanitize = function($v) { return preg_replace('/[^A-Za-z0-9_\-.\/|@]/', '', (string)$v); };
		$skin = $sanitize(\Context::get('skin'));
		$mskin = $sanitize(\Context::get('mskin'));
		if ($skin !== '')
		{
			$module_info->skin = $skin;
			// is_skin_fix 가 N 이면 코어가 저장된 스킨을 무시하고 기본 디자인을 따른다
			$module_info->is_skin_fix = ($skin === '/USE_DEFAULT/') ? 'N' : 'Y';
		}
		if ($mskin !== '')
		{
			$module_info->mskin = $mskin;
			$module_info->is_mskin_fix = ($mskin === '/USE_DEFAULT/' || $mskin === '/USE_RESPONSIVE/') ? 'N' : 'Y';
		}

		$layout_srl = \Context::get('layout_srl');
		if ($layout_srl !== null && $layout_srl !== '')
		{
			$module_info->layout_srl = (int)$layout_srl;
		}
		$mlayout_srl = \Context::get('mlayout_srl');
		if ($mlayout_srl !== null && $mlayout_srl !== '')
		{
			$module_info->mlayout_srl = (int)$mlayout_srl;
		}

		$module_info->isMenuCreate = false;

		$output = \ModuleController::getInstance()->updateModule($module_info);
		if (!$output->toBool())
		{
			return $output;
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminConfig'));
	}

	public function procCommerceAdminUploadItemImage()
	{
		$item_srl = (int)\Context::get('item_srl');
		if ($item_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$urls = $this->saveImages($item_srl, 7);
		if (!count($urls))
		{
			$single = $this->saveImage($item_srl, 'image_file');
			if ($single !== null)
			{
				$urls = [$single];
			}
		}
		if (!count($urls))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$this->add('urls', $urls);
	}

	public function procCommerceAdminSaveItemImages()
	{
		$item_srl = (int)\Context::get('item_srl');
		if ($item_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$decoded = json_decode((string)\Context::get('images_json'), true);
		$images = [];
		if (is_array($decoded))
		{
			foreach ($decoded as $url)
			{
				if (self::imageUrlAllowed($url, $item_srl))
				{
					$images[] = $url;
				}
			}
		}
		$images = array_slice($images, 0, 7);

		if (!ItemModel::get($item_srl))
		{
			$this->add('pending', true);
			return;
		}

		executeQuery('commerce.updateItem', (object)[
			'item_srl' => $item_srl,
			'images' => json_encode($images, \JSON_UNESCAPED_SLASHES),
			'thumb' => $images[0] ?? '',
			'last_update' => self::now(),
		]);

		$this->add('saved', count($images));
	}

	protected function saveImage(int $item_srl, string $field = 'thumb_file'): ?string
	{
		$file = $_FILES[$field] ?? null;
		if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
		{
			return null;
		}
		if ((int)$file['size'] > 10 * 1024 * 1024)
		{
			return null;
		}
		$info = @getimagesize($file['tmp_name']);
		$ext_map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
		if (!$info || !isset($ext_map[$info[2]]))
		{
			return null;
		}
		$dir = \RX_BASEDIR . 'files/attach/images/commerce/' . $item_srl . '/';
		\Zittme\Framework\Storage::createDirectory($dir);
		$filename = 'img_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.' . $ext_map[$info[2]];
		if (!@move_uploaded_file($file['tmp_name'], $dir . $filename))
		{
			return null;
		}
		return \RX_BASEURL . 'files/attach/images/commerce/' . $item_srl . '/' . $filename;
	}

	protected function saveImages(int $item_srl, int $limit): array
	{
		$files = $_FILES['image_files'] ?? null;
		if (!$files || !is_array($files['tmp_name'] ?? null) || $limit <= 0)
		{
			return [];
		}

		$ext_map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
		$dir = \RX_BASEDIR . 'files/attach/images/commerce/' . $item_srl . '/';
		$saved = [];

		foreach ($files['tmp_name'] as $i => $tmp)
		{
			if (count($saved) >= $limit)
			{
				break;
			}
			if (empty($tmp) || !is_uploaded_file($tmp) || (int)$files['size'][$i] > 10 * 1024 * 1024)
			{
				continue;
			}
			$info = @getimagesize($tmp);
			if (!$info || !isset($ext_map[$info[2]]))
			{
				continue;
			}
			\Zittme\Framework\Storage::createDirectory($dir);
			$filename = 'img_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 4) . '.' . $ext_map[$info[2]];
			if (@move_uploaded_file($tmp, $dir . $filename))
			{
				$saved[] = \RX_BASEURL . 'files/attach/images/commerce/' . $item_srl . '/' . $filename;
			}
		}
		return $saved;
	}

	public function procCommerceAdminInsertItem()
	{
		$item_srl = (int)\Context::get('item_srl');
		$clone_from = (int)\Context::get('clone_from');
		$item_name = trim((string)\Context::get('item_name'));
		if ($item_name === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$seller = self::getDefaultSeller();

		$to14 = function(string $v): string {
			$digits = preg_replace('/\D/', '', $v);
			if (strlen($digits) === 12)
			{
				$digits .= '00';
			}
			return strlen($digits) === 14 ? $digits : '';
		};

		$fields = (object)[
			'seller_srl' => $seller ? (int)$seller->seller_srl : 0,
			'category_srl' => max(0, (int)\Context::get('category_srl')),
			'brand_srl' => max(0, (int)\Context::get('brand_srl')),
			'item_name' => LangModel::filterCode((string)\Context::get('item_name_langcode')) !== ''
				? LangModel::toValue((string)\Context::get('item_name_langcode'))
				: mb_substr($item_name, 0, 250),
			'item_code' => mb_substr(trim((string)\Context::get('item_code')), 0, 100),
			'price' => max(0, MoneyModel::inputToMinor(\Context::get('price'))),
			'sale_price' => max(0, MoneyModel::inputToMinor(\Context::get('sale_price'))),
			'effective_price' => max(0, MoneyModel::inputToMinor(\Context::get('sale_price'))) > 0
				? max(0, MoneyModel::inputToMinor(\Context::get('sale_price')))
				: max(0, MoneyModel::inputToMinor(\Context::get('price'))),
			'use_stock' => \Context::get('use_stock') === 'N' ? 'N' : 'Y',
			'summary' => LangModel::filterCode((string)\Context::get('summary_langcode')) !== ''
				? LangModel::toValue((string)\Context::get('summary_langcode'))
				: mb_substr(trim((string)\Context::get('summary')), 0, 250),
			'content' => StaffModel::seller()
				? \Zittme\Framework\Filters\HTMLFilter::clean((string)\Context::get('content'), false, true)
				: (string)\Context::get('content'),
			'sale_start' => $to14((string)\Context::get('sale_start')),
			'sale_end' => $to14((string)\Context::get('sale_end')),
			'min_qty' => max(0, min(9999, (int)\Context::get('min_qty'))),
			'max_qty' => max(0, min(9999, (int)\Context::get('max_qty'))),
			'tax_type' => \Context::get('tax_type') === 'free' ? 'free' : 'taxable',
			'is_adult' => \Context::get('is_adult') === 'Y' ? 'Y' : 'N',
			'grade_discount' => \Context::get('grade_discount') === 'N' ? 'N' : 'Y',
			'is_pin' => \Context::get('is_pin') === 'Y' ? 'Y' : 'N',
			'pin_daily_limit' => max(0, min(999, (int)\Context::get('pin_daily_limit'))),
			'attrs' => self::collectItemAttrs(),
			'option_axes' => ComboModel::encodeAxes(\Context::get('option_axes')),
			'option_mode' => \Context::get('option_mode') === 'combo' ? 'combo' : 'single',
			'ship_fee_type' => in_array(\Context::get('ship_fee_type'), ['default', 'free', 'fixed'], true) ? \Context::get('ship_fee_type') : 'default',
			'ship_fee' => max(0, MoneyModel::inputToMinor(\Context::get('ship_fee'))),
			'status' => in_array(\Context::get('status'), ['sale', 'soldout', 'hidden', 'stop'], true) ? \Context::get('status') : 'sale',
			'is_recommend' => \Context::get('is_recommend') === 'Y' ? 'Y' : 'N',
			'is_new' => \Context::get('is_new') === 'Y' ? 'Y' : 'N',
			'badges' => implode(',', array_slice(array_map('intval', array_filter((array)\Context::get('badge_srls'), function($v) {
				return (int)$v > 0;
			})), 0, 10)),
			'list_order' => (int)\Context::get('list_order'),
			'last_update' => self::now(),
		];

		$is_new = $item_srl <= 0 || !ItemModel::get($item_srl);
		if ($item_srl <= 0)
		{
			$item_srl = getNextSequence();
		}

		$my_seller = StaffModel::seller();
		if ($my_seller)
		{
			$fields->seller_srl = (int)$my_seller->seller_srl;
			$fields->is_recommend = 'N';
			$fields->badges = '';
			if (SellerModel::needsReview() && in_array($fields->status, ['sale', 'soldout'], true))
			{
				$fields->status = 'review';
			}
			$fields->item_name = mb_substr(trim(strip_tags($item_name)), 0, 250);
			$fields->summary = mb_substr(trim(strip_tags((string)\Context::get('summary'))), 0, 250);
			if ($fields->item_name === '')
			{
				return new \BaseObject(-1, 'msg_invalid_request');
			}
			if (!$is_new)
			{
				unset($fields->seller_srl, $fields->is_recommend, $fields->badges, $fields->list_order);
			}
		}
		elseif (!$is_new)
		{
			unset($fields->seller_srl);
		}

		if ($is_new)
		{
			$fields->list_order = -$item_srl;
		}

		if (\Context::get('images_json') !== null)
		{
			$keep = json_decode((string)\Context::get('images_json'), true);
			$keep = is_array($keep) ? array_values(array_filter($keep, function($u) use ($item_srl) {
				return self::imageUrlAllowed($u, $item_srl);
			})) : [];
			$new_images = $this->saveImages($item_srl, 7 - count($keep));
			$images = array_slice(array_merge($keep, $new_images), 0, 7);
			if (count($images) || !$clone_from)
			{
				$fields->images = json_encode($images, \JSON_UNESCAPED_SLASHES);
				$fields->thumb = $images[0] ?? '';
			}
		}
		else
		{
			$thumb = $this->saveImage($item_srl);
			if ($thumb !== null)
			{
				$fields->thumb = $thumb;
			}
			elseif (\Context::get('thumb_delete') === 'Y')
			{
				$fields->thumb = '';
			}
		}

		$fields->item_srl = $item_srl;
		if ($is_new)
		{
			if ($clone_from > 0)
			{
				$src = ItemModel::get($clone_from);
				if ($src)
				{
					$fields->thumb = $fields->thumb ?? (string)$src->thumb;
					$fields->images = (string)($src->images ?? '');
				}
			}
			$fields->thumb = $fields->thumb ?? '';
			$fields->has_options = count(ItemModel::getOptions($item_srl)) ? 'Y' : 'N';
			$fields->stock = 0;
			$fields->regdate = self::now();
			$output = executeQuery('commerce.insertItem', $fields);

			$init_stock = max(0, (int)\Context::get('init_stock'));
			if ($output->toBool() && $init_stock > 0 && $fields->use_stock !== 'N')
			{
				StockModel::adjust($item_srl, 0, 'in', $init_stock, lang('commerce.adm_init_stock_memo'), (int)(\Context::get('logged_info')->member_srl ?? 0));
			}

			if ($output->toBool() && $clone_from > 0)
			{
				foreach (ItemModel::getOptions($clone_from) as $opt)
				{
					executeQuery('commerce.insertOption', (object)[
						'option_srl' => getNextSequence(),
						'item_srl' => $item_srl,
						'option_label' => $opt->option_label,
						'option_type' => $opt->option_type ?? 'basic',
						// 조합 정보를 빼면 복제본의 조합 옵션이 어느 축과도 맞지 않는 껍데기가 된다
						'combo' => (string)($opt->combo ?? ''),
						'price_add' => (int)$opt->price_add,
						'stock' => (int)$opt->stock,
						'sku' => (string)$opt->sku,
						'list_order' => (int)$opt->list_order,
						'status' => $opt->status,
						'regdate' => self::now(),
					]);
				}
				executeQuery('commerce.updateItem', (object)[
					'item_srl' => $item_srl,
					'has_options' => count(ItemModel::getOptions($item_srl)) ? 'Y' : 'N',
					'last_update' => self::now(),
				]);
			}
		}
		else
		{
			self::remapCombos($item_srl, ItemModel::get($item_srl)->option_axes ?? '', $fields->option_axes ?? '');
			$output = executeQuery('commerce.updateItem', $fields);
		}
		if (!$output->toBool())
		{
			return $output;
		}

		$fx_currencies_on = MoneyModel::currencies();
		if (count($fx_currencies_on) > 1)
		{
		$fx_prices = [];
		$fx_price_input = (array)\Context::get('fx_price');
		$fx_sale_input = (array)\Context::get('fx_sale_price');
		foreach ($fx_currencies_on as $fx_currency)
		{
			if ($fx_currency === MoneyModel::base())
			{
				continue;
			}
			$to_minor = function($raw) use ($fx_currency) {
				$raw = trim((string)$raw);
				if ($raw === '' || !is_numeric($raw))
				{
					return 0;
				}
				$class = '\\Zittme\\Modules\\Zittme_pay\\Models\\Currency';
				return class_exists($class) ? max(0, $class::toMinor((float)$raw, $fx_currency)) : 0;
			};
			$fx_prices[$fx_currency] = [
				'price' => $to_minor($fx_price_input[$fx_currency] ?? ''),
				'sale_price' => $to_minor($fx_sale_input[$fx_currency] ?? ''),
			];
		}
		ItemModel::setPrices($item_srl, $fx_prices);
		}

		if ((int)\Context::get('editor_sequence') > 0)
		{
			\FileController::getInstance()->setFilesValid($item_srl);
		}

		$shown = json_decode((string)\Context::get('promo_shown'), true);
		if (is_array($shown) && !$my_seller)
		{
			$checked = array_map('intval', (array)\Context::get('promo_srls'));
			foreach (array_map('intval', $shown) as $promo_srl)
			{
				if ($promo_srl > 0)
				{
					\Zittme\Modules\Commerce\Models\Promotion::setItemMembership($item_srl, $promo_srl, in_array($promo_srl, $checked, true));
				}
			}
		}

		$opt_rows = json_decode((string)\Context::get('options_json'), true);
		if (is_array($opt_rows))
		{
			foreach ($opt_rows as $opt_row)
			{
				$opt_srl = (int)($opt_row['option_srl'] ?? 0);
				$opt_label = trim((string)($opt_row['option_label'] ?? ''));
				if ($opt_srl <= 0 || $opt_label === '')
				{
					continue;
				}
				$opt_price = MoneyModel::inputToMinor($opt_row['price_add'] ?? 0);
				$opt_type = ($opt_row['option_type'] ?? '') === 'extra' ? 'extra' : 'basic';
				if ($opt_type === 'extra' && $opt_price < 0)
				{
					$opt_price = 0;
				}
				executeQuery('commerce.updateOption', (object)[
					'option_srl' => $opt_srl,
					'option_label' => mb_substr($opt_label, 0, 250),
					'option_type' => $opt_type,
					'price_add' => $opt_price,
					'stock' => max(0, (int)($opt_row['stock'] ?? 0)),
					'sku' => mb_substr(trim((string)($opt_row['sku'] ?? '')), 0, 80),
				]);
			}
			ItemModel::syncSoldout($item_srl);
		}

		$this->setMessage('success_registed');
		$edit_url = \Context::get('from_console') === 'Y'
			? getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'item_edit', 'item_srl', $item_srl)
			: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item_srl);
		$this->setRedirectUrl($is_new ? $edit_url : (\Context::get('success_return_url') ?: $edit_url));
	}

	public function procCommerceAdminSortItems()
	{
		$raw = (string)\Context::get('item_srls');
		$srls = [];
		foreach (explode(',', $raw) as $srl)
		{
			$srl = (int)trim($srl);
			if ($srl > 0)
			{
				$srls[] = $srl;
			}
		}
		if (!count($srls))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$slots = [];
		foreach ($srls as $srl)
		{
			$sort_item = ItemModel::get($srl);
			$slots[] = $sort_item ? (int)$sort_item->list_order : 0;
		}
		sort($slots);
		foreach ($srls as $i => $srl)
		{
			executeQuery('commerce.updateItem', (object)[
				'item_srl' => $srl,
				'list_order' => $slots[$i],
				'last_update' => self::now(),
			]);
		}

		$this->add('sorted', count($srls));
	}

	public function procCommerceAdminDeleteItem()
	{
		$item_srl = (int)\Context::get('item_srl');
		if ($item_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'SELECT COUNT(*) AS c FROM commerce_order_item WHERE item_srl = ?', $item_srl
		);
		$has_orders = $stmt && (int)($stmt->fetchObject()->c ?? 0) > 0;

		if ($has_orders)
		{
			executeQuery('commerce.updateItem', (object)[
				'item_srl' => $item_srl,
				'status' => 'hidden',
				'last_update' => self::now(),
			]);
			$this->setMessage('msg_shop_item_hidden');
		}
		else
		{
			\Zittme\Framework\DB::getInstance()->query('DELETE FROM commerce_item_option WHERE item_srl = ?', $item_srl);
			executeQuery('commerce.deleteItem', (object)['item_srl' => $item_srl]);
			$this->setMessage('success_deleted');
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems'));
	}

	public function procCommerceAdminBuildCombos()
	{
		$item_srl = (int)\Context::get('item_srl');
		if ($item_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$axes_json = ComboModel::encodeAxes(\Context::get('option_axes'));
		$axes = ComboModel::axes($axes_json);
		if (!count($axes))
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_5'));
		}

		$combos = ComboModel::expand($axes);
		if (!count($combos))
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_6'));
		}

		$existing = [];
		$manual = [];
		foreach (ItemModel::getOptions($item_srl) as $option)
		{
			if (($option->option_type ?? 'basic') !== 'basic')
			{
				continue;
			}
			if (empty($option->combo))
			{
				$manual[] = $option;
				continue;
			}
			$existing[ComboModel::key($option->combo)] = $option;
		}

		$now = self::now();
		$order = 0;
		$made = 0;
		$keys = [];
		foreach ($combos as $combo)
		{
			$order++;
			$key = ComboModel::key($combo);
			$keys[$key] = true;
			$label = ComboModel::label($combo);
			$combo_json = json_encode($combo, \JSON_UNESCAPED_UNICODE);

			if (isset($existing[$key]))
			{
				executeQuery('commerce.updateOption', (object)[
					'option_srl' => (int)$existing[$key]->option_srl,
					'option_label' => $label,
					'combo' => $combo_json,
					'list_order' => $order,
					'status' => 'Y',
				]);
				continue;
			}

			executeQuery('commerce.insertOption', (object)[
				'option_srl' => getNextSequence(),
				'item_srl' => $item_srl,
				'option_label' => $label,
				'option_type' => 'basic',
				'combo' => $combo_json,
				'price_add' => 0,
				'stock' => 0,
				'sku' => '',
				'list_order' => $order,
				'status' => 'Y',
				'regdate' => $now,
			]);
			$made++;
		}

		$removed = 0;
		foreach ($existing as $key => $option)
		{
			if (!isset($keys[$key]))
			{
				executeQuery('commerce.deleteOption', (object)['option_srl' => (int)$option->option_srl]);
				$removed++;
			}
		}
		foreach ($manual as $option)
		{
			executeQuery('commerce.deleteOption', (object)['option_srl' => (int)$option->option_srl]);
			$removed++;
		}

		executeQuery('commerce.updateItem', (object)[
			'item_srl' => $item_srl,
			'option_axes' => $axes_json,
			'option_mode' => 'combo',
			'has_options' => 'Y',
			'last_update' => $now,
		]);

		$this->add('made', $made);
		$this->add('removed', $removed);
		$this->add('total', count($combos));
		$this->setMessage(sprintf(
			lang('commerce.admin_msg_8'),
			count($combos), $made, $removed > 0 ? ', ' . sprintf(lang('commerce.admin_item_edit_157'), $removed) : ''
		));
	}

	public function procCommerceAdminInsertOption()
	{
		$item_srl = (int)\Context::get('item_srl');
		$label = trim((string)\Context::get('option_label'));
		if ($item_srl <= 0 || $label === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 추가 옵션은 별도 부가상품이라 추가금이 곧 판매가다 — 음수면 -금액짜리 상품이 담기므로 막는다
		$price_add = MoneyModel::inputToMinor(\Context::get('price_add'));
		if (\Context::get('option_type') === 'extra' && $price_add < 0)
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_7'));
		}

		executeQuery('commerce.insertOption', (object)[
			'option_srl' => getNextSequence(),
			'item_srl' => $item_srl,
			'option_label' => self::langValue('option_label', mb_substr($label, 0, 250)),
			'option_type' => \Context::get('option_type') === 'extra' ? 'extra' : 'basic',
			'price_add' => $price_add,
			'stock' => max(0, (int)\Context::get('stock')),
			'sku' => mb_substr(trim((string)\Context::get('sku')), 0, 80),
			'list_order' => (int)\Context::get('list_order'),
			'status' => 'Y',
			'regdate' => self::now(),
		]);
		executeQuery('commerce.updateItem', (object)[
			'item_srl' => $item_srl,
			'has_options' => 'Y',
			'last_update' => self::now(),
		]);

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item_srl));
	}

	public function procCommerceAdminUpdateOption()
	{
		$option_srl = (int)\Context::get('option_srl');
		$item_srl = (int)\Context::get('item_srl');
		$label = trim((string)\Context::get('option_label'));
		if ($option_srl <= 0 || $label === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$price_add = MoneyModel::inputToMinor(\Context::get('price_add'));
		if (\Context::get('option_type') === 'extra' && $price_add < 0)
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_7'));
		}

		executeQuery('commerce.updateOption', (object)[
			'option_srl' => $option_srl,
			'option_label' => self::langValue('option_label', mb_substr($label, 0, 250)),
			'option_type' => \Context::get('option_type') === 'extra' ? 'extra' : 'basic',
			'price_add' => $price_add,
			'stock' => max(0, (int)\Context::get('stock')),
			'sku' => mb_substr(trim((string)\Context::get('sku')), 0, 80),
			'list_order' => (int)\Context::get('list_order'),
		]);
		if ($item_srl > 0)
		{
			executeQuery('commerce.updateItem', (object)[
				'item_srl' => $item_srl,
				'last_update' => self::now(),
			]);
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item_srl));
	}

	public function procCommerceAdminDeleteOption()
	{
		$option_srl = (int)\Context::get('option_srl');
		$item_srl = (int)\Context::get('item_srl');
		if ($option_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('commerce.deleteOption', (object)['option_srl' => $option_srl]);
		if ($item_srl > 0 && !count(ItemModel::getOptions($item_srl)))
		{
			executeQuery('commerce.updateItem', (object)[
				'item_srl' => $item_srl,
				'has_options' => 'N',
				'last_update' => self::now(),
			]);
		}
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item_srl));
	}

	public function procCommerceAdminInsertCategory()
	{
		$category_srl = (int)\Context::get('category_srl');
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$title_code = LangModel::filterCode((string)\Context::get('title_langcode'));
		$fields = (object)[
			'parent_srl' => max(0, (int)\Context::get('parent_srl')),
			'title' => $title_code !== '' ? LangModel::toValue($title_code) : mb_substr($title, 0, 120),
			'list_order' => (int)\Context::get('list_order'),
			'is_active' => \Context::get('is_active') === 'N' ? 'N' : 'Y',
		];
		if ($category_srl > 0)
		{
			$fields->category_srl = $category_srl;
			executeQuery('commerce.updateCategory', $fields);
		}
		else
		{
			if ((int)\Context::get('list_order') <= 0)
			{
				$max_order = 0;
				foreach (self::getCategories() as $exist)
				{
					$max_order = max($max_order, (int)$exist->list_order);
				}
				$fields->list_order = $max_order + 1;
			}
			$fields->category_srl = getNextSequence();
			$fields->regdate = self::now();
			executeQuery('commerce.insertCategory', $fields);
		}
		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCategories'));
	}

	public function procCommerceAdminDeleteCategory()
	{
		$category_srl = (int)\Context::get('category_srl');
		if ($category_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$oDB = \Zittme\Framework\DB::getInstance();
		$oDB->query('UPDATE commerce_item SET category_srl = 0 WHERE category_srl = ?', $category_srl);
		$oDB->query('UPDATE commerce_category SET parent_srl = 0 WHERE parent_srl = ?', $category_srl);
		executeQuery('commerce.deleteCategory', (object)['category_srl' => $category_srl]);
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCategories'));
	}

	public const PREVIEW_FIELDS = ['shop_main', 'category_layout', 'item_image_size', 'show_shop_nav', 'show_search', 'show_admin_fab', 'item_sticky', 'currency_code_prefix',
		'home_show_recommend', 'home_show_new', 'home_show_popular', 'home_show_sale', 'home_count', 'home_banners', 'show_seller_on_card'];

	public function procCommerceAdminPreviewConfig()
	{
		$values = [];
		foreach (self::PREVIEW_FIELDS as $key)
		{
			$value = \Context::get($key);
			if ($value === null)
			{
				continue;
			}
			if ($key === 'shop_main') { $value = $value === 'home' ? 'home' : 'list'; }
			elseif ($key === 'category_layout') { $value = $value === 'side' ? 'side' : 'top'; }
			elseif ($key === 'item_image_size') { $value = in_array($value, ['S', 'L'], true) ? $value : 'M'; }
			elseif ($key === 'home_count') { $value = max(4, min(24, (int)$value)); }
			elseif ($key === 'home_banners')
			{
				$decoded = json_decode((string)$value, true);
				$value = json_encode(is_array($decoded) ? array_values($decoded) : [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
			}
			else { $value = $value === 'Y' ? 'Y' : 'N'; }
			$values[$key] = $value;
		}
		$_SESSION['commerce_preview'] = ['time' => time(), 'values' => $values];
		$this->add('saved', count($values));
	}

	public function procCommerceAdminInsertConfig()
	{
		$config = \ModuleModel::getModuleConfig('commerce') ?: new \stdClass;

		foreach (self::CONFIG_FIELDS as $key)
		{
			$value = \Context::get($key);
			if ($value === null)
			{
				continue;
			}
			if (in_array($key, self::LANG_CONFIG_FIELDS, true))
			{
				$value = self::langValue($key, trim((string)$value));
			}
			elseif (in_array($key, self::BOOLEAN_FIELDS, true))
			{
				$value = $value === 'Y' ? 'Y' : 'N';
			}
			elseif (isset(self::INT_FIELDS[$key]))
			{
				[$min, $max] = self::INT_FIELDS[$key];
				$value = max($min, min($max, (int)$value));
			}
			elseif (isset(self::MONEY_FIELDS[$key]))
			{
				[$min, $max] = self::MONEY_FIELDS[$key];
				$value = max($min, min($max, MoneyModel::inputToMinor($value)));
			}
			elseif (isset(self::FLOAT_FIELDS[$key]))
			{
				[$min, $max] = self::FLOAT_FIELDS[$key];
				$value = max($min, min($max, round((float)$value, 2)));
			}
			elseif ($key === 'market_mode')
			{
				$value = $value === 'open' ? 'open' : 'single';
				if ($value === 'single' && ($config->market_mode ?? 'single') === 'open')
				{
					if (SellerModel::schemaReady())
					{
						SellerModel::hideMarketItems();
					}
				}
				elseif ($value === 'open' && ($config->market_mode ?? 'single') !== 'open' && SellerModel::schemaReady())
				{
					SellerModel::restoreMarketItems();
				}
			}
			elseif ($key === 'shop_main')
			{
				$value = $value === 'home' ? 'home' : 'list';
			}
			elseif ($key === 'category_layout')
			{
				$value = $value === 'side' ? 'side' : 'top';
			}
			elseif ($key === 'item_image_size')
			{
				$value = in_array($value, ['S', 'L'], true) ? $value : 'M';
			}
			elseif ($key === 'currencies')
			{
				$raw = is_array($value) ? $value : preg_split('/[\s,]+/', strtoupper((string)$value));
				$allowed = class_exists('\\Zittme\\Modules\\Zittme_pay\\Models\\Currency')
					? \Zittme\Modules\Zittme_pay\Models\Currency::MAJOR_CURRENCIES : [];
				$codes = [];
				foreach ($raw as $code)
				{
					$code = strtoupper(trim((string)$code));
					if (preg_match('/^[A-Z]{3}$/', $code) && $code !== MoneyModel::base()
						&& (empty($allowed) || isset($allowed[$code])) && !in_array($code, $codes, true))
					{
						$codes[] = $code;
					}
				}
				$value = $codes;
			}
			elseif ($key === 'currency_fallback')
			{
				$value = $value === 'none' ? 'none' : 'convert';
			}
			elseif ($key === 'couriers')
			{
				$value = json_encode(\Zittme\Modules\Commerce\Models\Courier::parseLines((string)$value), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
			}
			elseif ($key === 'home_banners' || $key === 'ship_extra_zones')
			{
				$decoded = json_decode((string)$value, true);
				$decoded = is_array($decoded) ? array_values($decoded) : [];
				if ($key === 'ship_extra_zones')
				{
					foreach ($decoded as &$zone_row)
					{
						if (is_array($zone_row))
						{
							$zone_row['fee'] = MoneyModel::inputToMinor($zone_row['fee'] ?? 0);
							if (is_array($zone_row['tiers'] ?? null))
							{
								foreach ($zone_row['tiers'] as &$zone_tier)
								{
									if (is_array($zone_tier))
									{
										$zone_tier['from'] = MoneyModel::inputToMinor($zone_tier['from'] ?? 0);
										$zone_tier['fee'] = MoneyModel::inputToMinor($zone_tier['fee'] ?? 0);
									}
								}
								unset($zone_tier);
							}
						}
					}
					unset($zone_row);
				}
				$value = json_encode($decoded, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
			}
			else
			{
				$value = trim((string)$value);
			}
			$config->{$key} = $value;
		}

		ConfigModel::setConfig($config);
		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminConfig'));
	}

	public function dispCommerceAdminStaff()
	{
		\Context::set('staff_list', StaffModel::getList());
		\Context::set('staff_perms', StaffModel::PERMS);
		\Context::set('staff_role', StaffModel::role());
		\Context::set('staff_me', (int)(\Context::get('logged_info')->member_srl ?? 0));
		$this->renderView('staff', 'staff');
	}

	public function procCommerceAdminSaveStaff()
	{
		$srl = (int)\Context::get('target_member_srl');
		if ($srl <= 0)
		{
			$member = StaffModel::findMember((string)\Context::get('find'));
			if (!$member)
			{
				return new \BaseObject(-1, lang('commerce.st_msg_no_member'));
			}
			$srl = (int)$member->member_srl;
			\Context::set('target_member_srl', $srl);
		}
		$member = \MemberModel::getMemberInfoByMemberSrl($srl);
		if (empty($member->member_srl))
		{
			return new \BaseObject(-1, lang('commerce.st_msg_no_member'));
		}
		if (($member->is_admin ?? 'N') === 'Y')
		{
			return new \BaseObject(-1, lang('commerce.st_msg_is_owner'));
		}
		$as_seller = SellerModel::getByMember($srl);
		if ($as_seller && in_array($as_seller->status, ['pending', 'approved', 'suspended'], true))
		{
			return new \BaseObject(-1, lang('commerce.sc_msg_seller_not_staff'));
		}
		$role = (string)\Context::get('role');
		$role = in_array($role, StaffModel::ROLES, true) ? $role : 'manager';
		$old = StaffModel::get($srl);
		if (!StaffModel::canManage($old, $role))
		{
			return new \BaseObject(-1, lang('commerce.st_msg_cannot'));
		}
		$perms = \Context::get('perms');
		$perms = is_array($perms) ? $perms : array_filter(explode(',', (string)$perms));
		$actor = (int)(\Context::get('logged_info')->member_srl ?? 0);
		StaffModel::save($srl, $role, $perms, trim((string)\Context::get('memo')), $actor);
		$this->add('member_srl', $srl);
		$this->setMessage('success_saved');
	}

	public function procCommerceAdminStaffStatus()
	{
		$srl = (int)\Context::get('target_member_srl');
		$old = StaffModel::get($srl);
		if (!$old || !StaffModel::canManage($old, $old->role))
		{
			return new \BaseObject(-1, lang('commerce.st_msg_cannot'));
		}
		StaffModel::setStatus($srl, \Context::get('status') === 'suspended' ? 'suspended' : 'active');
		$this->setMessage('success_updated');
	}

	public function procCommerceAdminDeleteStaff()
	{
		$srl = (int)\Context::get('target_member_srl');
		$old = StaffModel::get($srl);
		if (!$old || !StaffModel::canManage($old, $old->role))
		{
			return new \BaseObject(-1, lang('commerce.st_msg_cannot'));
		}
		StaffModel::delete($srl);
		$this->setMessage('success_deleted');
	}

	public function dispCommerceAdminAudit()
	{
		$filters = [
			'member' => (int)\Context::get('f_member'),
			'kind' => in_array(\Context::get('f_kind'), ['change', 'view', 'export', 'denied', 'staff'], true) ? (string)\Context::get('f_kind') : '',
			'alert' => \Context::get('f_alert') === 'Y' ? 'Y' : '',
			'q' => trim((string)\Context::get('f_q')),
			'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)\Context::get('f_from')) ? (string)\Context::get('f_from') : '',
			'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)\Context::get('f_to')) ? (string)\Context::get('f_to') : '',
		];
		$page = max(1, (int)\Context::get('page'));
		$result = AuditModel::getList($filters, $page);
		\Context::set('audit_rows', $result['rows']);
		\Context::set('audit_total', $result['total']);
		\Context::set('audit_pages', $result['pages']);
		\Context::set('audit_page', $page);
		\Context::set('audit_filters', (object)$filters);
		\Context::set('audit_actors', AuditModel::actors());
		\Context::set('audit_days', AuditModel::retentionDays());
		\Context::set('audit_is_owner', StaffModel::isRoot());
		$this->renderView('audit', 'audit');
	}

	public function procCommerceAdminAuditConfig()
	{
		$config = \ModuleModel::getModuleConfig('commerce') ?: new \stdClass;
		$config->audit_days = max(30, min(3650, (int)\Context::get('audit_days') ?: 365));
		\ModuleController::getInstance()->insertModuleConfig('commerce', $config);
		$this->setMessage('success_updated');
	}

	public function dispCommerceAdminTimesale()
	{
		$edit_srl = (int)\Context::get('sale_srl');
		$edit = $edit_srl > 0 ? \Zittme\Modules\Commerce\Models\Timesale::get($edit_srl) : null;
		$is_new = !$edit && \Context::get('new') === 'Y';
		\Context::set('ts_list', \Zittme\Modules\Commerce\Models\Timesale::getList());
		\Context::set('ts_edit', $edit);
		\Context::set('ts_new', $is_new);
		\Context::set('ts_edit_items', $edit ? \Zittme\Modules\Commerce\Models\Timesale::itemsOf($edit_srl) : []);
		$items = [];
		if ($edit || $is_new)
		{
			$output = executeQueryArray('commerce.getItemList', (object)['status_list' => 'sale,soldout', 'list_count' => 2000, 'sort_index' => 'item_srl', 'order_type' => 'desc']);
			$items = ($output->toBool() && !empty($output->data)) ? $output->data : [];
			LangModel::textAll($items, ['item_name']);
		}
		\Context::set('ts_all_items', $items);
		\Context::set('ts_categories', ($edit || $is_new) ? array_values(self::getCategories()) : []);
		$this->renderView('timesale', 'timesale');
	}

	public function procCommerceAdminSaveTimesale()
	{
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, lang('commerce.ts_msg_need_title'));
		}
		$mode = \Context::get('mode') === 'daily' ? 'daily' : 'once';
		$digits = function ($v) { return preg_replace('/\D/', '', (string)$v); };
		if ($mode === 'daily')
		{
			$start = substr($digits(\Context::get('start_day')), 0, 8);
			$end = substr($digits(\Context::get('end_day')), 0, 8);
			$ds = str_pad(substr($digits(\Context::get('daily_start')), 0, 4), 4, '0');
			$de = str_pad(substr($digits(\Context::get('daily_end')), 0, 4), 4, '0');
			if (strlen($start) !== 8 || strlen($end) !== 8 || $ds === $de)
			{
				return new \BaseObject(-1, lang('commerce.ts_msg_bad_time'));
			}
			$start .= '000000';
			$end .= '235959';
		}
		else
		{
			$start = str_pad(substr($digits(\Context::get('start_at')), 0, 12), 12, '0') . '00';
			$end = str_pad(substr($digits(\Context::get('end_at')), 0, 12), 12, '0') . '00';
			$ds = $de = '';
		}
		if (strlen($start) !== 14 || strlen($end) !== 14 || $end <= $start)
		{
			return new \BaseObject(-1, lang('commerce.ts_msg_bad_time'));
		}
		$items = json_decode((string)\Context::get('items'), true);
		$saved = \Zittme\Modules\Commerce\Models\Timesale::save((int)\Context::get('sale_srl'), [
			'title' => $title, 'mode' => $mode, 'start_date' => $start, 'end_date' => $end,
			'daily_start' => $ds, 'daily_end' => $de, 'status' => \Context::get('status') === 'N' ? 'N' : 'Y',
		], is_array($items) ? $items : []);
		$this->add('sale_srl', $saved);
		$this->setMessage('success_saved');
	}

	public function procCommerceAdminDeleteTimesale()
	{
		\Zittme\Modules\Commerce\Models\Timesale::delete((int)\Context::get('sale_srl'));
		$this->setMessage('success_deleted');
	}

	public function dispCommerceAdminPins()
	{
		$output = executeQueryArray('commerce.getItemList', (object)['list_count' => 1000, 'sort_index' => 'item_srl', 'order_type' => 'desc']);
		$pin_items = [];
		foreach (($output->toBool() && !empty($output->data)) ? $output->data : [] as $it)
		{
			if (($it->is_pin ?? 'N') === 'Y')
			{
				$pin_items[] = $it;
			}
		}
		LangModel::textAll($pin_items, ['item_name']);
		$f_item = (int)\Context::get('f_item');
		$f_status = (string)\Context::get('f_status');
		$f_q = trim((string)\Context::get('f_q'));
		$page = max(1, (int)\Context::get('page'));
		$list = \Zittme\Modules\Commerce\Models\Pin::getList($f_item, $f_status, $f_q, $page);
		\Context::set('pin_items', $pin_items);
		\Context::set('pin_counts', \Zittme\Modules\Commerce\Models\Pin::counts());
		\Context::set('pin_rows', $list['rows']);
		\Context::set('pin_total', $list['total']);
		\Context::set('pin_pages', $list['pages']);
		\Context::set('pin_page', $page);
		\Context::set('pin_filters', (object)['item' => $f_item, 'status' => $f_status, 'q' => $f_q]);
		$this->renderView('pins', 'pins');
	}

	public function procCommerceAdminAddPins()
	{
		$item_srl = (int)\Context::get('item_srl');
		$item = ItemModel::get($item_srl);
		if (!$item || ($item->is_pin ?? 'N') !== 'Y')
		{
			return new \BaseObject(-1, lang('commerce.pin_msg_not_pin_item'));
		}
		$r = \Zittme\Modules\Commerce\Models\Pin::add($item_srl, (string)\Context::get('pins'), trim((string)\Context::get('memo')));
		$this->add('added', $r['added']);
		$this->add('dup', $r['dup']);
		$this->add('bad', $r['bad']);
	}

	public function procCommerceAdminVoidPins()
	{
		$srls = json_decode((string)\Context::get('pin_srls'), true);
		$this->add('voided', \Zittme\Modules\Commerce\Models\Pin::voidStock(is_array($srls) ? $srls : []));
	}

	public const SHIP_TABS = ['paid' => 'paid', 'preparing' => 'preparing', 'shipping' => 'shipping', 'delivered' => 'delivered'];

	public function dispCommerceAdminShipping()
	{
		if (!StaffModel::seller() || !\Zittme\Framework\Cache::get('commerce_ship_sync_seller'))
		{
			if (StaffModel::seller())
			{
				\Zittme\Framework\Cache::set('commerce_ship_sync_seller', 1, 600);
			}
			\Zittme\Modules\Commerce\Models\Tracking::syncShipping();
		}
		$db = \Zittme\Framework\DB::getInstance();
		$tab = (string)\Context::get('st');
		if (!isset(self::SHIP_TABS[$tab]))
		{
			$tab = 'paid';
		}

		$my_seller = StaffModel::seller();
		$scope_seller = $my_seller ? (int)$my_seller->seller_srl : (SellerModel::isOpen() ? max(0, (int)\Context::get('f_seller')) : 0);

		$since = date('YmdHis', strtotime('-30 days'));
		$counts = [];
		foreach (self::SHIP_TABS as $key => $status)
		{
			$sql = 'SELECT COUNT(*) FROM commerce_order_seller JOIN commerce_order ON commerce_order.order_srl = commerce_order_seller.order_srl WHERE commerce_order_seller.status = ? AND commerce_order.status = ?';
			$params = [$status, self::ORDER_PAID];
			if ($key === 'delivered') { $sql .= ' AND commerce_order_seller.delivered_date >= ?'; $params[] = $since; }
			if ($scope_seller > 0) { $sql .= ' AND commerce_order_seller.seller_srl = ?'; $params[] = $scope_seller; }
			$counts[$key] = (int)($db->query($sql, $params)->fetchAll(\PDO::FETCH_NUM)[0][0] ?? 0);
		}

		$keyword = trim((string)\Context::get('q'));
		$sql = 'SELECT commerce_order_seller.order_seller_srl, commerce_order_seller.order_srl, commerce_order_seller.seller_srl, commerce_order_seller.status AS ship_status, commerce_order_seller.shipping_company, commerce_order_seller.shipping_invoice, commerce_order_seller.shipped_date, commerce_order_seller.delivered_date,'
			. ' commerce_order.order_code, commerce_order.orderer_name, commerce_order.orderer_phone, commerce_order.regdate, commerce_order.paid_date, commerce_order.memo, commerce_order.payment_price, commerce_order.currency'
			. ' FROM commerce_order_seller JOIN commerce_order ON commerce_order.order_srl = commerce_order_seller.order_srl'
			. ' WHERE commerce_order_seller.status = ? AND commerce_order.status = ?';
		$params = [self::SHIP_TABS[$tab], self::ORDER_PAID];
		if ($tab === 'delivered') { $sql .= ' AND commerce_order_seller.delivered_date >= ?'; $params[] = $since; }
		if ($scope_seller > 0) { $sql .= ' AND commerce_order_seller.seller_srl = ?'; $params[] = $scope_seller; }
		if ($keyword !== '')
		{
			$sql .= ' AND (commerce_order.order_code LIKE ? OR commerce_order.orderer_name LIKE ? OR commerce_order_seller.shipping_invoice LIKE ?)';
			$like = '%' . $keyword . '%';
			array_push($params, $like, $like, $like);
		}
		$sql .= in_array($tab, ['paid', 'preparing'], true) ? ' ORDER BY commerce_order.paid_date ASC, commerce_order.order_srl ASC' : ' ORDER BY commerce_order_seller.shipped_date DESC, commerce_order.order_srl DESC';
		$sql .= ' LIMIT 300';
		$rows = $db->query($sql, $params)->fetchAll();

		$order_srls = [];
		$seller_srls = [];
		foreach ($rows as $row)
		{
			$order_srls[] = (int)$row->order_srl;
			$seller_srls[] = (int)$row->order_seller_srl;
		}
		$addr = [];
		$items = [];
		if (count($order_srls))
		{
			$marks = implode(',', array_fill(0, count($order_srls), '?'));
			foreach ($db->query('SELECT * FROM commerce_order_address WHERE order_srl IN (' . $marks . ')', $order_srls)->fetchAll() as $a)
			{
				$addr[(int)$a->order_srl] = $a;
			}
			$marks = implode(',', array_fill(0, count($seller_srls), '?'));
			foreach ($db->query('SELECT order_seller_srl, item_name, option_name, qty, thumb, claim_status FROM commerce_order_item WHERE order_seller_srl IN (' . $marks . ') ORDER BY order_item_srl ASC', $seller_srls)->fetchAll() as $it)
			{
				$items[(int)$it->order_seller_srl][] = $it;
			}
		}
		foreach ($rows as $row)
		{
			$row->address = $addr[(int)$row->order_srl] ?? null;
			$row->items = $items[(int)$row->order_seller_srl] ?? [];
			LangModel::textAll($row->items, ['item_name', 'option_name']);
		}

		\Context::set('ship_tab', $tab);
		\Context::set('ship_counts', $counts);
		\Context::set('ship_rows', $rows);
		\Context::set('ship_q', $keyword);
		\Context::set('ship_seller', $scope_seller);
		\Context::set('seller_mode', $my_seller ? 'seller' : (SellerModel::isOpen() ? 'operator' : ''));
		\Context::set('seller_names', !$my_seller && SellerModel::isOpen() ? SellerModel::nameMap() : []);
		$this->renderView('shipping', 'shipping');
	}

	public function procCommerceAdminBulkShipping()
	{
		$action = (string)\Context::get('ship_action');
		$rows = json_decode((string)\Context::get('rows'), true);
		if (!in_array($action, ['confirm', 'ship', 'reinvoice', 'deliver'], true) || !is_array($rows) || !count($rows))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$logged_info = \Context::get('logged_info');
		$actor = $logged_info ? (int)$logged_info->member_srl : 0;
		$my_seller = StaffModel::seller();
		if ($my_seller && $action === 'deliver')
		{
			return new \BaseObject(-1, lang('commerce.sc_msg_no_deliver'));
		}
		$done = 0;
		$failed = [];
		foreach (array_slice($rows, 0, 500) as $row)
		{
			$order_srl = (int)($row['order_srl'] ?? 0);
			$only_os = (int)($row['order_seller_srl'] ?? 0);
			$order = $order_srl > 0 ? OrderModel::get($order_srl) : null;
			if (!$order || $order->status !== self::ORDER_PAID)
			{
				$failed[] = $my_seller ? (string)($only_os ?: $order_srl) : (string)($order->order_code ?? $order_srl);
				continue;
			}
			$company = mb_substr(trim((string)($row['company'] ?? '')), 0, 60);
			$invoice = mb_substr(preg_replace('/\s+/', '', (string)($row['invoice'] ?? '')), 0, 60);
			$direct = ($row['direct'] ?? '') === 'Y';
			if ($direct)
			{
				$company = lang('commerce.shop_ship_direct');
				$invoice = '';
			}
			if (in_array($action, ['ship', 'reinvoice'], true) && !$direct && ($company === '' || $invoice === ''))
			{
				$failed[] = $my_seller ? (string)($only_os ?: $order_srl) : (string)$order->order_code;
				continue;
			}
			$changed = false;
			$touched_os = 0;
			foreach (OrderModel::getSellerOrders($order_srl) as $os)
			{
				if ($my_seller && (int)$os->seller_srl !== (int)$my_seller->seller_srl)
				{
					continue;
				}
				if ($only_os > 0 && (int)$os->order_seller_srl !== $only_os)
				{
					continue;
				}
				$args = (object)['order_seller_srl' => (int)$os->order_seller_srl];
				if ($action === 'confirm' && $os->status === self::SELLER_PAID)
				{
					$args->status = self::SELLER_PREPARING;
					$args->from_status_list = self::SELLER_PAID;
				}
				elseif ($action === 'ship' && in_array($os->status, [self::SELLER_PAID, self::SELLER_PREPARING], true))
				{
					$args->status = self::SELLER_SHIPPING;
					$args->from_status_list = self::SELLER_PAID . ',' . self::SELLER_PREPARING;
					$args->shipping_company = $company;
					$args->shipping_invoice = $invoice;
					$args->shipped_date = self::now();
				}
				elseif ($action === 'reinvoice' && $os->status === self::SELLER_SHIPPING)
				{
					$args->status = self::SELLER_SHIPPING;
					$args->from_status_list = self::SELLER_SHIPPING;
					$args->shipping_company = $company;
					$args->shipping_invoice = $invoice;
				}
				elseif ($action === 'deliver' && $os->status === self::SELLER_SHIPPING)
				{
					$args->status = self::SELLER_DELIVERED;
					$args->from_status_list = self::SELLER_SHIPPING;
					$args->delivered_date = self::now();
				}
				else
				{
					continue;
				}
				if (executeQuery('commerce.updateOrderSellerShipping', $args)->toBool())
				{
					$changed = true;
					$touched_os = (int)$os->order_seller_srl;
				}
			}
			if (!$changed)
			{
				$failed[] = $my_seller ? (string)($only_os ?: $order_srl) : (string)$order->order_code;
				continue;
			}
			$done++;
			if ($action === 'confirm')
			{
				OrderModel::log($order_srl, $my_seller ? $touched_os : 0, 'confirm', self::SELLER_PAID, self::SELLER_PREPARING, $actor);
			}
			elseif ($action === 'ship')
			{
				OrderModel::log($order_srl, $my_seller ? $touched_os : 0, 'ship', '', self::SELLER_SHIPPING, $actor, $company . ' ' . $invoice);
				OrderModel::notifyMail('shipping', $order);
			}
			elseif ($action === 'reinvoice')
			{
				OrderModel::log($order_srl, $my_seller ? $touched_os : 0, 'memo', '', '', $actor, lang('commerce.sh_log_reinvoice') . ' ' . $company . ' ' . $invoice);
			}
			else
			{
				OrderModel::log($order_srl, $my_seller ? $touched_os : 0, 'deliver', self::SELLER_SHIPPING, self::SELLER_DELIVERED, $actor);
				OrderModel::notifyMail('delivered', $order);
			}
		}
		$this->add('done', $done);
		$this->add('failed', $failed);
	}

	public function procCommerceAdminUpdateOrder()
	{
		$order_srl = (int)\Context::get('order_srl');
		$order = OrderModel::get($order_srl);
		if (!$order)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}

		$logged_info = \Context::get('logged_info');
		$actor = $logged_info ? (int)$logged_info->member_srl : 0;
		$action = (string)\Context::get('order_action');
		$sellers = OrderModel::getSellerOrders($order_srl);

		switch ($action)
		{
			case 'confirm':
				foreach ($sellers as $os)
				{
					executeQuery('commerce.updateOrderSellerShipping', (object)[
						'order_seller_srl' => (int)$os->order_seller_srl,
						'status' => self::SELLER_PREPARING,
						'from_status_list' => self::SELLER_PAID,
					]);
				}
				OrderModel::log($order_srl, 0, 'confirm', self::SELLER_PAID, self::SELLER_PREPARING, $actor);
				break;

			case 'ship':
				$company = trim((string)\Context::get('shipping_company'));
				$invoice = trim((string)\Context::get('shipping_invoice'));
				$direct_ship = \Context::get('direct_ship') === 'Y';
				if ($direct_ship)
				{
					$company = lang('commerce.shop_ship_direct');
					$invoice = '';
				}
				elseif ($company === '' || $invoice === '')
				{
					return new \BaseObject(-1, 'msg_shop_need_invoice');
				}
				foreach ($sellers as $os)
				{
					executeQuery('commerce.updateOrderSellerShipping', (object)[
						'order_seller_srl' => (int)$os->order_seller_srl,
						'status' => self::SELLER_SHIPPING,
						'from_status_list' => implode(',', [self::SELLER_PAID, self::SELLER_PREPARING]),
						'shipping_company' => mb_substr($company, 0, 60),
						'shipping_invoice' => mb_substr($invoice, 0, 60),
						'shipped_date' => self::now(),
					]);
				}
				OrderModel::log($order_srl, 0, 'ship', '', self::SELLER_SHIPPING, $actor, $company . ' ' . $invoice);
				OrderModel::notifyMail('shipping', $order);
				break;

			case 'deliver':
				foreach ($sellers as $os)
				{
					executeQuery('commerce.updateOrderSellerShipping', (object)[
						'order_seller_srl' => (int)$os->order_seller_srl,
						'status' => self::SELLER_DELIVERED,
						'from_status_list' => self::SELLER_SHIPPING,
						'delivered_date' => self::now(),
					]);
				}
				OrderModel::log($order_srl, 0, 'deliver', self::SELLER_SHIPPING, self::SELLER_DELIVERED, $actor);
				OrderModel::notifyMail('delivered', $order);
				break;

			case 'cancel':
				if ((int)$order->pay_order_srl > 0 && $order->status === self::ORDER_PAID && self::isPayAvailable())
				{
					$refund = \Zittme\Modules\Zittme_pay\PayService::cancel((int)$order->pay_order_srl, lang('commerce.shop_admin_cancel_reason'));
					if (empty($refund->success))
					{
						OrderModel::log($order_srl, 0, 'memo', '', '', $actor, 'refund failed: ' . (string)($refund->message ?? ''));
					}
				}
				OrderModel::cancelAndRestock($order_srl, $actor, 'admin cancel');
				break;

			case 'memo':
				OrderModel::log($order_srl, 0, 'memo', '', '', $actor, (string)\Context::get('admin_memo'));
				break;

			default:
				return new \BaseObject(-1, 'msg_invalid_request');
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $order_srl));
	}

	public function procCommerceAdminUpdateClaim()
	{
		$claim_srl = (int)\Context::get('claim_srl');
		$claim_output = executeQuery('commerce.getClaim', (object)['claim_srl' => $claim_srl]);
		$claim = ($claim_output->toBool() && is_object($claim_output->data) && !empty($claim_output->data->claim_srl)) ? $claim_output->data : null;
		if (!$claim)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$logged_info = \Context::get('logged_info');
		$actor = $logged_info ? (int)$logged_info->member_srl : 0;
		$action = (string)\Context::get('claim_action');
		$order = OrderModel::get((int)$claim->order_srl);

		if ($action === 'reject')
		{
			executeQuery('commerce.updateClaimStatusIf', (object)[
				'claim_srl' => $claim_srl,
				'status' => 'rejected',
				'from_status_list' => 'requested',
				'admin_memo' => mb_substr((string)\Context::get('admin_memo'), 0, 2000),
				'processed_date' => self::now(),
			]);
			OrderModel::log((int)$claim->order_srl, 0, 'claim', 'requested', 'rejected', $actor);
			OrderModel::notifyMail('claim_done', $order);
		}
		elseif ($action === 'approve')
		{
			$refund_amount = max(0, MoneyModel::inputToMinor(\Context::get('refund_amount')));
			if ($order)
			{
				$refund_amount = min($refund_amount, (int)$order->payment_price);
			}
			if ((int)$claim->order_seller_srl > 0)
			{
				$cap_db = \Zittme\Framework\DB::getInstance();
				$cap_os = $cap_db->query('SELECT settle_amount FROM commerce_order_seller WHERE order_seller_srl = ?', [(int)$claim->order_seller_srl])->fetchAll();
				$cap_done = $cap_db->query("SELECT SUM(refund_amount) AS total FROM commerce_claim WHERE order_seller_srl = ? AND status = 'done' AND claim_srl <> ?", [(int)$claim->order_seller_srl, $claim_srl])->fetchAll();
				if (count($cap_os))
				{
					$refund_amount = min($refund_amount, max(0, (int)$cap_os[0]->settle_amount - (int)($cap_done[0]->total ?? 0)));
				}
			}
			$restock = \Context::get('restock') === 'N' ? 'N' : 'Y';

			$won_output = executeQuery('commerce.updateClaimStatusIf', (object)[
				'claim_srl' => $claim_srl,
				'status' => 'done',
				'from_status_list' => 'requested',
				'refund_amount' => $refund_amount,
				'restock' => $restock,
				'admin_memo' => mb_substr((string)\Context::get('admin_memo'), 0, 2000),
				'processed_date' => self::now(),
			]);
			if (!$won_output->toBool() || \DB::getInstance()->getAffectedRows() < 1)
			{
				return new \BaseObject(-1, 'msg_shop_claim_already');
			}

			if ($refund_amount > 0 && $order && (int)$order->pay_order_srl > 0 && self::isPayAvailable())
			{
				$refund = \Zittme\Modules\Zittme_pay\PayService::cancel(
					(int)$order->pay_order_srl,
					lang('commerce.shop_claim_refund_reason'),
					$refund_amount >= (int)$order->payment_price ? 0 : $refund_amount
				);
				if (empty($refund->success))
				{
					OrderModel::log((int)$claim->order_srl, 0, 'memo', '', '', $actor, 'claim refund failed: ' . (string)($refund->message ?? ''));
				}
			}

			$targets = json_decode((string)$claim->items, true) ?: [];
			$order_items = [];
			foreach (OrderModel::getItems((int)$claim->order_srl) as $oi)
			{
				$order_items[(int)$oi->order_item_srl] = $oi;
			}
			foreach ($targets as $t)
			{
				$oi = $order_items[(int)($t['order_item_srl'] ?? 0)] ?? null;
				if (!$oi)
				{
					continue;
				}
				$qty = max(1, min((int)$oi->qty, (int)($t['qty'] ?? 1)));
				if ($restock === 'Y')
				{
					\Zittme\Modules\Commerce\Models\Stock::release((int)$oi->item_srl, (int)$oi->option_srl, $qty);
				}
				\Zittme\Framework\DB::getInstance()->query(
					'UPDATE commerce_order_item SET claim_status = ? WHERE order_item_srl = ?',
					'done', (int)$oi->order_item_srl
				);
			}

			if (in_array((string)$claim->claim_type, SettlementModel::REFUND_CLAIMS, true) && SellerModel::schemaReady())
			{
				SettlementModel::markRefundedBundles((int)$claim->order_srl);
			}
			OrderModel::log((int)$claim->order_srl, 0, 'refund', 'requested', 'done', $actor, 'refund=' . $refund_amount);
			OrderModel::notifyMail('claim_done', $order);
		}
		else
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminClaims'));
	}

	public function dispCommerceAdminSellers()
	{
		if (!SellerModel::isOpen())
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		$status = (string)\Context::get('f_status');
		$status = in_array($status, SellerModel::STATUSES, true) ? $status : '';
		$keyword = trim((string)\Context::get('q'));
		$args = (object)['page' => max(1, (int)\Context::get('page')), 'list_count' => 30];
		if ($status !== '')
		{
			$args->status = $status;
		}
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
		}
		$output = executeQueryArray('commerce.getSellerPage', $args);
		$sellers = [];
		foreach ($output->toBool() ? (array)$output->data : [] as $row)
		{
			if (empty($row->seller_srl) || SellerModel::isOperator((int)$row->seller_srl))
			{
				continue;
			}
			$member = \MemberModel::getMemberInfoByMemberSrl((int)$row->member_srl);
			$row->user_id = (string)($member->user_id ?? '');
			$row->nick_name = (string)($member->nick_name ?? '');
			$row->rate_text = ($row->commission_rate === null || $row->commission_rate === '') ? '' : rtrim(rtrim(number_format((float)$row->commission_rate, 2, '.', ''), '0'), '.');
			$row->carry = max(0, (int)($row->carry_balance ?? 0));
			$row->carry_text = $row->carry > 0 ? MoneyModel::format($row->carry, MoneyModel::base()) : '';
			$row->effective_rate = SellerModel::commissionRate($row);
			$row->regdate_text = $row->regdate ? zdate($row->regdate, 'Y.m.d') : '';
			$row->ship_fee_text = MoneyModel::format((int)$row->ship_fee, MoneyModel::base());
			$row->free_over_text = (int)$row->free_ship_over > 0 ? MoneyModel::format((int)$row->free_ship_over, MoneyModel::base()) : '';
			$sellers[] = $row;
		}

		$counts = ['' => 0];
		foreach (SellerModel::STATUSES as $st)
		{
			$counts[$st] = 0;
		}
		$rows = \Zittme\Framework\DB::getInstance()->query('SELECT seller_srl, status FROM commerce_seller WHERE member_srl > 0')->fetchAll();
		foreach ($rows as $row)
		{
			if (SellerModel::isOperator((int)$row->seller_srl) || !isset($counts[$row->status]))
			{
				continue;
			}
			$counts[$row->status]++;
			$counts['']++;
		}

		\Context::set('mk_sellers', $sellers);
		\Context::set('mk_counts', $counts);
		\Context::set('mk_status', $status);
		\Context::set('mk_q', $keyword);
		\Context::set('mk_default_rate', (float)(self::config()->market_commission ?? 0));
		\Context::set('page_navigation', $output->page_navigation ?? null);
		$this->renderView('sellers', 'sellers');
	}

	public function procCommerceAdminSellerStatus()
	{
		$seller_srl = (int)\Context::get('seller_srl');
		$status = (string)\Context::get('status');
		if (!SellerModel::isOpen() || !in_array($status, ['approved', 'rejected', 'suspended'], true) || !SellerModel::get($seller_srl) || SellerModel::isOperator($seller_srl))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$carry = SettlementModel::carryOf($seller_srl);
		if ($carry > 0 && in_array($status, ['rejected', 'suspended'], true) && \Context::get('carry_ok') !== 'Y')
		{
			return new \BaseObject(-1, sprintf(lang('commerce.sc_warn_carry_status'), MoneyModel::format($carry, MoneyModel::base())));
		}
		if (!SellerModel::setStatus($seller_srl, $status, (string)\Context::get('reason')))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'sellers'));
	}

	public function procCommerceAdminSellerCommission()
	{
		$seller_srl = (int)\Context::get('seller_srl');
		if (!SellerModel::isOpen() || !SellerModel::get($seller_srl) || SellerModel::isOperator($seller_srl))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$raw = trim((string)\Context::get('commission_rate'));
		if ($raw !== '' && !is_numeric($raw))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		SellerModel::setCommission($seller_srl, $raw === '' ? null : (float)$raw);
		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'sellers'));
	}

	public function dispCommerceAdminSettlements()
	{
		if (!SellerModel::schemaReady())
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		$my_seller = StaffModel::seller();
		$f_seller = $my_seller ? (int)$my_seller->seller_srl : max(0, (int)\Context::get('f_seller'));
		$f_status = (string)\Context::get('f_status');
		$result = SettlementModel::getList($f_seller, $f_status, (int)\Context::get('page'));
		$names = SellerModel::nameMap();
		foreach ($result->list as $row)
		{
			$row->shop_name = $names[(int)$row->seller_srl] ?? ((SellerModel::get((int)$row->seller_srl)->shop_name ?? '') ?: '#' . $row->seller_srl);
			$row->period_text = self::ymdText((string)$row->period_start) . ' ~ ' . self::ymdText((string)$row->period_end);
			$row->paid_text = $row->paid_date ? zdate($row->paid_date, 'Y.m.d H:i') : '';
			foreach (['item_total', 'delivery_total', 'refund_total', 'commission_total', 'settle_amount', 'carry_in', 'carry_out'] as $money_key)
			{
				$row->{$money_key . '_text'} = MoneyModel::format((int)($row->{$money_key} ?? 0), MoneyModel::base());
			}
		}

		$preview = [];
		$from = preg_replace('/\D/', '', (string)\Context::get('from'));
		$to = preg_replace('/\D/', '', (string)\Context::get('to'));
		if (strlen($from) !== 8 || strlen($to) !== 8)
		{
			$from = date('Ymd', strtotime('first day of last month'));
			$to = date('Ymd', strtotime('last day of last month'));
		}
		if (!$my_seller && \Context::get('preview') === 'Y')
		{
			foreach (SettlementModel::preview($from, $to, $f_seller) as $row)
			{
				foreach (['item_total', 'delivery_total', 'refund_total', 'commission_total', 'settle_amount', 'carry_in', 'carry_out'] as $money_key)
				{
					$row->{$money_key . '_text'} = MoneyModel::format((int)($row->{$money_key} ?? 0), MoneyModel::base());
				}
				$preview[] = $row;
			}
		}

		$detail = null;
		$detail_lines = [];
		$detail_srl = (int)\Context::get('settlement_srl');
		if ($detail_srl > 0)
		{
			$detail = SettlementModel::get($detail_srl);
			if ($detail && $my_seller && (int)$detail->seller_srl !== (int)$my_seller->seller_srl)
			{
				$detail = null;
			}
			if ($detail)
			{
				foreach (SettlementModel::lines($detail_srl) as $line)
				{
					$line->item_total_text = MoneyModel::format((int)$line->item_total, MoneyModel::base());
					$line->delivery_text = MoneyModel::format((int)$line->delivery_fee, MoneyModel::base());
					$line->refund_text = MoneyModel::format((int)$line->refund, MoneyModel::base());
					$line->commission_text = MoneyModel::format((int)$line->commission, MoneyModel::base());
					$line->settle_text = MoneyModel::format((int)$line->settle_amount, MoneyModel::base());
					$line->delivered_text = $line->delivered_date ? zdate($line->delivered_date, 'Y.m.d') : '';
					$detail_lines[] = $line;
				}
			}
		}

		\Context::set('mk_is_seller', (bool)$my_seller);
		\Context::set('mk_settlements', $result->list);
		$carries = [];
		foreach (SettlementModel::carryBalances() as $carry_srl => $carry_amount)
		{
			if ($my_seller && $carry_srl !== (int)$my_seller->seller_srl)
			{
				continue;
			}
			$carries[] = (object)[
				'seller_srl' => $carry_srl,
				'shop_name' => $names[$carry_srl] ?? ((SellerModel::get($carry_srl)->shop_name ?? '') ?: '#' . $carry_srl),
				'amount_text' => MoneyModel::format($carry_amount, MoneyModel::base()),
			];
		}
		\Context::set('mk_carries', $carries);
		\Context::set('page_navigation', $result->page_navigation);
		\Context::set('mk_seller_names', $my_seller ? [] : $names);
		\Context::set('mk_f_seller', $f_seller);
		\Context::set('mk_f_status', $f_status);
		\Context::set('mk_from', self::ymdText($from, '-'));
		\Context::set('mk_to', self::ymdText($to, '-'));
		\Context::set('mk_preview', $preview);
		\Context::set('mk_preview_on', \Context::get('preview') === 'Y');
		\Context::set('mk_detail', $detail);
		\Context::set('mk_detail_lines', $detail_lines);
		$this->renderView('settlements', 'settlements');
	}

	public function procCommerceAdminCreateSettlement()
	{
		$from = preg_replace('/\D/', '', (string)\Context::get('from'));
		$to = preg_replace('/\D/', '', (string)\Context::get('to'));
		if (!SellerModel::isOpen() || strlen($from) !== 8 || strlen($to) !== 8 || $from > $to || $to > date('Ymd'))
		{
			return new \BaseObject(-1, lang('commerce.mk_msg_bad_period'));
		}
		$actor = (int)(\Context::get('logged_info')->member_srl ?? 0);
		$count = SettlementModel::create($from, $to, max(0, (int)\Context::get('seller_srl')), $actor);
		if ($count <= 0)
		{
			return new \BaseObject(-1, lang('commerce.mk_msg_nothing'));
		}
		$this->setMessage(sprintf(lang('commerce.mk_msg_created'), $count));
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'settlements'));
	}

	public function procCommerceAdminSettlementPaid()
	{
		$settlement_srl = (int)\Context::get('settlement_srl');
		if (!SettlementModel::markPaid($settlement_srl, trim((string)\Context::get('memo'))))
		{
			return new \BaseObject(-1, SettlementModel::$error !== '' ? lang('commerce.' . SettlementModel::$error) : 'msg_invalid_request');
		}
		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'settlements'));
	}

	public function procCommerceAdminCancelSettlement()
	{
		if (!SettlementModel::cancel((int)\Context::get('settlement_srl')))
		{
			return new \BaseObject(-1, SettlementModel::$error !== '' ? lang('commerce.' . SettlementModel::$error) : 'msg_invalid_request');
		}
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'settlements'));
	}

	public function dispCommerceAdminExportSettlement()
	{
		if (!SellerModel::schemaReady() || !StaffModel::isStaff())
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		$my_seller = StaffModel::seller();
		$settlement_srl = (int)\Context::get('settlement_srl');
		$rows = [];
		if ($settlement_srl > 0)
		{
			$st = SettlementModel::get($settlement_srl);
			if (!$st || ($my_seller && (int)$st->seller_srl !== (int)$my_seller->seller_srl))
			{
				throw new \Zittme\Framework\Exceptions\NotPermitted;
			}
			$rows[] = [lang('commerce.mk_col_order'), lang('commerce.mk_col_delivered'), lang('commerce.mk_col_sales'), lang('commerce.mk_col_delivery'), lang('commerce.mk_col_refund'), lang('commerce.mk_col_commission'), lang('commerce.mk_col_settle')];
			foreach (SettlementModel::lines($settlement_srl) as $line)
			{
				$rows[] = [(string)$line->order_code, (string)$line->delivered_date, (int)$line->item_total, (int)$line->delivery_fee, (int)$line->refund, (int)$line->commission, (int)$line->settle_amount];
			}
			$filename = 'settlement_' . $settlement_srl . '.csv';
		}
		else
		{
			$f_seller = $my_seller ? (int)$my_seller->seller_srl : max(0, (int)\Context::get('f_seller'));
			$rows[] = [lang('commerce.mk_col_no'), lang('commerce.mk_col_seller'), lang('commerce.mk_col_period'), lang('commerce.mk_col_orders'), lang('commerce.mk_col_sales'), lang('commerce.mk_col_delivery'), lang('commerce.mk_col_refund'), lang('commerce.mk_col_commission'), lang('commerce.mk_col_settle'), lang('commerce.mk_col_status'), lang('commerce.mk_col_bank'), lang('commerce.mk_col_paid')];
			$page = 1;
			do
			{
				$result = SettlementModel::getList($f_seller, (string)\Context::get('f_status'), $page);
				foreach ($result->list as $st)
				{
					$shop = SellerModel::get((int)$st->seller_srl);
					$rows[] = [
						(int)$st->settlement_srl, (string)($shop->shop_name ?? ''), $st->period_start . '-' . $st->period_end, (int)$st->order_count,
						(int)$st->item_total, (int)$st->delivery_total, (int)$st->refund_total, (int)$st->commission_total, (int)$st->settle_amount,
						lang('commerce.mk_st_' . $st->status), trim($st->bank_name . ' ' . $st->bank_account . ' ' . $st->bank_holder), (string)$st->paid_date,
					];
				}
				$page++;
			}
			while (count($result->list) && $page <= 100);
			$filename = 'settlements_' . date('Ymd_His') . '.csv';
		}

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-store');
		echo "\xEF\xBB\xBF";
		$fp = fopen('php://output', 'w');
		foreach ($rows as $row)
		{
			fputcsv($fp, array_map(function ($cell) {
				return is_string($cell) && preg_match('/^[=+\-@]/', $cell) ? "'" . $cell : $cell;
			}, $row));
		}
		fclose($fp);
		exit;
	}

	public function dispCommerceAdminSellerProfile()
	{
		$my_seller = StaffModel::seller();
		if (!$my_seller)
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		$my_seller = SellerModel::get((int)$my_seller->seller_srl);
		$my_seller->ship_fee_input = MoneyModel::minorToInput((int)$my_seller->ship_fee);
		$my_seller->free_over_input = (int)$my_seller->free_ship_over > 0 ? MoneyModel::minorToInput((int)$my_seller->free_ship_over) : '';
		\Context::set('mk_me', $my_seller);
		\Context::set('mk_rate', SellerModel::commissionRate($my_seller));
		\Context::set('mk_store_url', \Zittme\Modules\Commerce\Models\Shop::url((string)($my_seller->shop_id ?? '')));
		$this->renderView('seller_profile', 'seller_profile');
	}

	public function procCommerceAdminSaveSellerProfile()
	{
		$my_seller = StaffModel::seller();
		if (!$my_seller)
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		$data = SellerModel::filterInput(true);
		$save = (object)[
			'tel' => $data->tel,
			'email' => $data->email,
			'bank_name' => $data->bank_name,
			'bank_account' => $data->bank_account,
			'bank_holder' => $data->bank_holder,
			'intro' => $data->intro,
			'ship_fee' => $data->ship_fee,
			'free_ship_over' => $data->free_ship_over,
		];
		if ($save->tel === '' || $save->bank_name === '' || $save->bank_account === '' || $save->bank_holder === '')
		{
			return new \BaseObject(-1, lang('commerce.mk_msg_need_fields'));
		}
		$before = SellerModel::get((int)$my_seller->seller_srl);
		SellerModel::update((int)$my_seller->seller_srl, $save);
		if ($before && ((string)$before->bank_name !== $save->bank_name || (string)$before->bank_account !== $save->bank_account || (string)$before->bank_holder !== $save->bank_holder))
		{
			\Zittme\Modules\Commerce\Models\Notify::toAdmins(
				sprintf(lang('commerce.sc_msg_bank_changed'), (string)$before->shop_name),
				\Zittme\Modules\Commerce\Models\Notify::consoleUrl('sellers')
			);
		}
		$this->setMessage('success_saved');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'act', 'dispCommerceSellerCenter', 'p', 'seller_profile'));
	}

	protected static function imageUrlAllowed($url, int $item_srl): bool
	{
		if (!is_string($url) || strpos($url, '..') !== false)
		{
			return false;
		}
		if (StaffModel::seller())
		{
			$prefix = \RX_BASEURL . 'files/attach/images/commerce/' . $item_srl . '/';
			return $item_srl > 0 && strpos($url, $prefix) === 0 && preg_match('/^[A-Za-z0-9_.-]+$/', substr($url, strlen($prefix)));
		}
		return strpos($url, \RX_BASEURL . 'files/') === 0;
	}

	protected static function ymdText(string $ymd, string $sep = '.'): string
	{
		return strlen($ymd) === 8 ? substr($ymd, 0, 4) . $sep . substr($ymd, 4, 2) . $sep . substr($ymd, 6, 2) : $ymd;
	}
}
