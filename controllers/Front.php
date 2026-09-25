<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Address as AddressModel;
use Zittme\Modules\Commerce\Models\Badge as BadgeModel;
use Zittme\Modules\Commerce\Models\Brand as BrandModel;
use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Lang as LangModel;
use Zittme\Modules\Commerce\Models\Money as MoneyModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;

class Front extends Base
{
	protected function setShopContext(array $items = []): void
	{
		$base = MoneyModel::base();
		$now = MoneyModel::current();

		\Context::set('shp_base_currency', $base);
		\Context::set('shp_base_zero', MoneyModel::isZeroDecimal($base));
		\Context::set('shp_unit_label', MoneyModel::unitLabel());
		\Context::set('shp_won_suffix', MoneyModel::useWonSuffix());
		\Context::set('shp_currency', $now);
		\Context::set('shp_currency_zero', MoneyModel::isZeroDecimal($now));
		\Context::set('shp_currency_symbol', MoneyModel::symbol($now));
		\Context::set('shp_currency_rate', MoneyModel::rate($now) ?: 1);

		$choices = [];
		foreach (MoneyModel::currencies() as $code)
		{
			if ($code === $base || MoneyModel::rate($code) > 0)
			{
				$symbol = trim(MoneyModel::symbol($code));
				$choices[$code] = strpos($symbol, $code) === false ? trim($symbol . ' ' . $code) : $symbol;
			}
		}
		\Context::set('shp_currency_choices', $choices);

		\Zittme\Modules\Commerce\Models\Seller::attachNames($items, (string)($this->module_info->mid ?? ''));

		$badge_map = BadgeModel::getMap(true);
		\Context::set('shop_badge_map', $badge_map);

		$brand_mid = (string)($this->module_info->mid ?? '');
		BrandModel::attach($items, $brand_mid);
		$shop_brands = BrandModel::getList(true);
		$brand_counts = BrandModel::itemCounts();
		foreach ($shop_brands as $sb)
		{
			$sb->url = BrandModel::url($sb, $brand_mid);
			$sb->item_count = $brand_counts[(int)$sb->brand_srl] ?? 0;
		}
		\Context::set('shop_brands', $shop_brands);

		$logged = \Context::get('logged_info');
		$grade_discount = (int)($logged->member_srl ?? 0) > 0
			? \Zittme\Modules\Commerce\Models\Grade::discountFor((int)$logged->member_srl)
			: null;
		\Context::set('shp_grade_discount', $grade_discount);

		foreach ($items as $shop_item)
		{
			if (!is_object($shop_item))
			{
				continue;
			}
			// badges 는 번호 문자열 컬럼이다. 덮어쓰면 다음 호출에서 형이 어긋난다
			$shop_item->badge_list = BadgeModel::ofItem($shop_item, $badge_map);

			$disp = ItemModel::displayPrices($shop_item, $now);
			$shop_item->disp_currency = $now;
			$shop_item->disp_price = $disp['price'];
			$shop_item->disp_sale_price = $disp['sale_price'];
			$shop_item->disp_effective = $disp['effective'];
			$shop_item->disp_sellable = $disp['sellable'];

			$graded = ($shop_item->grade_discount ?? 'Y') === 'N'
				? $disp['effective']
				: \Zittme\Modules\Commerce\Models\Grade::applyDiscountIn($disp['effective'], $grade_discount, $now);
			$shop_item->grade_price = $graded < $disp['effective'] ? $graded : 0;
		}
	}

	protected function getSkinPath(): string
	{
		$skin = (string)($this->module_info->skin ?? '');
		if ($skin === '' || $skin === '/USE_DEFAULT/')
		{
			$skin = (string)(\ModuleModel::getModuleDefaultSkin('commerce', 'P') ?: 'default');
		}
		// 일반 이름과 테마 결합명('테마|@|스킨')만 허용 — 경로 조작 방지
		if (!preg_match('/^[A-Za-z0-9_-]+(\|@\|[A-Za-z0-9_-]+)?$/', $skin))
		{
			$skin = 'default';
		}
		$path = zittme_compat_skin_path($this->module_path, $skin, 'commerce');
		if (!is_dir($path))
		{
			$path = $this->module_path . 'skins/default/';
		}
		return rtrim($path, '/') . '/';
	}

	protected static function getActiveCategories(): array
	{
		$output = executeQuery('commerce.getCategoryList', (object)['is_active' => 'Y']);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		$data = array_values(array_filter($data, function($row) { return !empty($row->category_srl); }));

		$children = [];
		foreach ($data as $row)
		{
			$children[(int)$row->parent_srl][] = $row;
		}
		$sorted = [];
		$walk = function($parent, $depth) use (&$walk, &$children, &$sorted) {
			foreach ($children[$parent] ?? [] as $row)
			{
				$row->depth = $depth;
				$sorted[] = $row;
				$walk((int)$row->category_srl, $depth + 1);
			}
		};
		$walk(0, 0);
		foreach ($data as $row)
		{
			if (!in_array($row, $sorted, true))
			{
				$row->depth = 0;
				$sorted[] = $row;
			}
		}
		return LangModel::textAll($sorted, ['title']);
	}

	protected static function categoryWithDescendants(int $srl): array
	{
		$ids = [$srl];
		$added = true;
		while ($added)
		{
			$added = false;
			foreach (self::getActiveCategories() as $c)
			{
				if (in_array((int)$c->parent_srl, $ids, true) && !in_array((int)$c->category_srl, $ids, true))
				{
					$ids[] = (int)$c->category_srl;
					$added = true;
				}
			}
		}
		return $ids;
	}

	protected static function injectAdminPanel(): void
	{
		if (!\Zittme\Modules\Commerce\Models\Staff::isStaff())
		{
			return;
		}
		if ((self::config()->show_admin_fab ?? 'Y') === 'N')
		{
			return;
		}

		$module_path = './modules/commerce/';
		\Context::loadFile([$module_path . 'tpl/css/adminpanel.css', 'all']);

		$template = new \Zittme\Framework\Template($module_path . 'tpl', \Zittme\Modules\Commerce\Models\Staff::can('display') ? 'adminpanel' : 'studiolink');
		\Context::addHtmlFooter($template->compile());
	}

	protected static function applyImageSize(): void
	{
		$size = (string)(self::config()->item_image_size ?? 'M');
		if ($size !== 'S' && $size !== 'L')
		{
			return;
		}
		\Context::addBodyClass('shp-img-' . strtolower($size));
		\Context::loadFile(['./modules/commerce/tpl/css/imagesize.css', 'all']);
	}

	public static function escapeAllowBr(string $text): string
	{
		return str_ireplace(['&lt;br&gt;', '&lt;br /&gt;', '&lt;br/&gt;'], '<br />', escape($text, false));
	}

	public function dispPromotion()
	{
		self::assertShopEnabled();
		$slug = trim((string)\Context::get('p'));
		$promo = $slug !== '' ? \Zittme\Modules\Commerce\Models\Promotion::get(0, $slug) : null;
		$logged_info = \Context::get('logged_info');
		$is_admin = $logged_info && $logged_info->is_admin === 'Y';
		if (!$promo || (($promo->status ?? 'Y') !== 'Y' && !$is_admin))
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}

		$promo_draft = \Zittme\Modules\Commerce\Models\Promotion::previewDraft((int)$promo->promo_srl);
		if ($promo_draft)
		{
			$promo = clone $promo;
			foreach ((array)($promo_draft['values'] ?? []) as $key => $value)
			{
				$promo->{$key} = in_array($key, ['title', 'description'], true) ? LangModel::text((string)$value) : $value;
			}
			if ($promo->title === '')
			{
				$promo->title = lang('commerce.pm_default_title');
			}
		}

		$now = self::now();
		$state = 'open';
		if (!empty($promo->start_date) && $now < $promo->start_date)
		{
			$state = 'upcoming';
		}
		elseif (!empty($promo->end_date) && $now > $promo->end_date)
		{
			$state = 'ended';
		}

		$items = $state === 'upcoming' && !$is_admin
			? []
			: \Zittme\Modules\Commerce\Models\Promotion::itemsOf((int)$promo->promo_srl);
		if ($promo_draft && is_array($promo_draft['items'] ?? null))
		{
			$items = \Zittme\Modules\Commerce\Models\Promotion::itemsBySrls($promo_draft['items']);
		}
		self::attachReviewStats($items);

		\Context::set('promo', $promo);
		\Context::set('promo_state', $state);
		\Context::set('promo_banner', \Zittme\Modules\Commerce\Models\Promotion::bannerOf($promo));
		\Context::set('items', $items);
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
		$this->setShopContext($items);
		\Context::setBrowserTitle($promo->title);
		self::applyImageSize();
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('promo');
	}

	protected static function attachReviewStats(array $items): void
	{
		LangModel::textAll($items, ['item_name', 'summary']);

		$srls = [];
		foreach ($items as $it)
		{
			if (!empty($it->item_srl))
			{
				$srls[] = (int)$it->item_srl;
			}
		}
		if (!count($srls))
		{
			return;
		}
		try
		{
			$stmt = \Zittme\Framework\DB::getInstance()->query(
				'SELECT item_srl, COUNT(*) AS review_count, AVG(rating) AS rating_avg FROM commerce_review WHERE item_srl IN (' . implode(',', $srls) . ') GROUP BY item_srl'
			);
			$stats = [];
			foreach ($stmt as $row)
			{
				$stats[(int)$row->item_srl] = $row;
			}
			foreach ($items as $it)
			{
				$stat = $stats[(int)$it->item_srl] ?? null;
				$it->review_count = $stat ? (int)$stat->review_count : 0;
				$it->rating_avg = $stat ? round((float)$stat->rating_avg, 1) : 0;
			}
		}
		catch (\Throwable $e)
		{
		}
	}

	public function dispCommerceList()
	{
		self::assertShopEnabled();
		if (\Context::get('v') === 'promo')
		{
			return $this->dispPromotion();
		}
		if (\Context::get('v') === 'store')
		{
			return $this->dispCommerceStore();
		}

		$config = self::config();
		if (($config->shop_main ?? 'list') === 'home'
			&& \Context::get('v') !== 'list'
			&& !\Context::get('category') && !\Context::get('seller') && trim((string)\Context::get('q')) === ''
			&& !\Context::get('sort') && (int)\Context::get('page') <= 1)
		{
			return $this->dispShopHome();
		}

		$args = new \stdClass;
		$args->status_list = 'sale,soldout';

		$category_srl = (int)\Context::get('category');
		if ($category_srl > 0)
		{
			$args->category_srl_list = implode(',', self::categoryWithDescendants($category_srl));
		}
		$keyword = trim((string)\Context::get('q'));
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
			$args->search_brand_srl_list = BrandModel::searchSrls($keyword, true) ?: null;
		}

		$brand = BrandModel::find((string)\Context::get('brand'));
		$brand_draft = $brand ? BrandModel::previewDraft((int)$brand->brand_srl) : null;
		if ($brand_draft)
		{
			$brand = clone $brand;
			foreach ((array)($brand_draft['values'] ?? []) as $key => $value)
			{
				$brand->{$key} = $key === 'name' ? (LangModel::text((string)$value) ?: $brand->name) : $value;
			}
			$brand->is_visible = 'Y';
		}
		if ($brand && ($brand->is_visible ?? 'Y') !== 'N')
		{
			$args->brand_srl = (int)$brand->brand_srl;
			if ($brand_draft && is_array($brand_draft['items'] ?? null))
			{
				unset($args->brand_srl);
				$args->item_srl_list = count($brand_draft['items']) ? implode(',', $brand_draft['items']) : '0';
			}
			$brand->url = BrandModel::url($brand, (string)($this->module_info->mid ?? ''));
			\Context::setBrowserTitle($brand->name);
		}
		else
		{
			$brand = null;
		}
		\Context::set('current_brand', $brand);

		$current_seller = null;
		$seller_srl = (int)\Context::get('seller');
		if ($seller_srl > 0)
		{
			$current_seller = \Zittme\Modules\Commerce\Models\Seller::publicInfo($seller_srl);
			if ($current_seller && $current_seller->active)
			{
				$args->seller_srl = $seller_srl;
				\Context::setBrowserTitle($current_seller->shop_name);
			}
			else
			{
				$current_seller = null;
			}
		}
		\Context::set('current_seller', $current_seller);

		$filter = in_array(\Context::get('f'), ['recommend', 'new', 'popular', 'sale', 'timesale'], true) ? (string)\Context::get('f') : '';
		if ($filter === 'recommend')
		{
			$args->is_recommend = 'Y';
		}
		elseif ($filter === 'timesale')
		{
			$ts_srls = \Zittme\Modules\Commerce\Models\Timesale::openItemSrls();
			$args->item_srl_list = count($ts_srls) ? implode(',', $ts_srls) : '0';
		}
		elseif ($filter === 'sale')
		{
			$args->list_count = 200;
		}

		$sort = (string)\Context::get('sort');
		if ($sort === '' && $filter === 'popular')
		{
			$sort = 'popular';
		}
		switch ($sort)
		{
			case 'popular':
				$args->sort_index = 'buy_count';
				$args->order_type = 'desc';
				break;
			case 'price_low':
				// sale_price 는 할인 없는 상품이 0 이라 정렬이 어긋난다. 실판매가 컬럼을 쓴다
				$args->sort_index = 'effective_price';
				$args->order_type = 'asc';
				break;
			case 'price_high':
				$args->sort_index = 'effective_price';
				$args->order_type = 'desc';
				break;
			case 'new':
				$args->sort_index = 'item_srl';
				$args->order_type = 'desc';
				break;
			default:
				$sort = 'display';
				$args->sort_index = 'list_order';
				$args->order_type = 'asc';
		}

		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 24;

		$output = executeQuery('commerce.getItemList', $args);
		$items = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		$now = self::now();
		$items = array_values(array_filter($items, function($it) use ($now) {
			if (!empty($it->sale_start) && $now < $it->sale_start) return false;
			if (!empty($it->sale_end) && $now > $it->sale_end) return false;
			return true;
		}));


		if ($filter === 'sale')
		{
			$items = array_values(array_filter($items, function($it) {
				return (int)($it->sale_price ?? 0) > 0 && (int)$it->sale_price < (int)$it->price;
			}));
		}

		self::attachReviewStats($items);

		\Context::set('items', $items);
		\Context::set('current_filter', $filter);
		\Context::set('page_navigation', $filter === 'sale' ? null : ($output->page_navigation ?? null));
		\Context::set('shop_categories', self::getActiveCategories());
		$this->setShopContext($items ?? []);
		\Context::set('current_category', $category_srl);
		\Context::set('current_sort', $sort);
		\Context::set('current_q', $keyword);
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
		self::injectAdminPanel();
		self::applyImageSize();
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('list');
	}

	protected function dispShopHome()
	{
		$config = self::config();
		$count = max(4, min(24, (int)($config->home_count ?? 8)));

		$output = executeQuery('commerce.getItemList', (object)[
			'status_list' => 'sale,soldout',
			'sort_index' => 'item_srl',
			'order_type' => 'desc',
			'list_count' => 200,
		]);
		$pool = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];
		$now = self::now();
		$pool = array_values(array_filter($pool, function($it) use ($now) {
			if (empty($it->item_srl)) return false;
			if (!empty($it->sale_start) && $now < $it->sale_start) return false;
			if (!empty($it->sale_end) && $now > $it->sale_end) return false;
			return true;
		}));

		$sections = [];
		$ts_srls = \Zittme\Modules\Commerce\Models\Timesale::openItemSrls();
		if (count($ts_srls))
		{
			$rows = array_values(array_filter($pool, function($it) use ($ts_srls) { return in_array((int)$it->item_srl, $ts_srls, true); }));
			if (count($rows)) { $sections[] = (object)['key' => 'timesale', 'title' => lang('commerce.shop_home_timesale'), 'items' => array_slice($rows, 0, $count)]; }
		}
		if (($config->home_show_recommend ?? 'Y') === 'Y')
		{
			$rows = array_values(array_filter($pool, function($it) { return ($it->is_recommend ?? 'N') === 'Y'; }));
			if (count($rows)) { $sections[] = (object)['key' => 'recommend', 'title' => lang('commerce.shop_home_recommend'), 'items' => array_slice($rows, 0, $count)]; }
		}
		if (($config->home_show_new ?? 'Y') === 'Y')
		{
			$rows = array_values(array_filter($pool, function($it) { return ($it->is_new ?? 'N') === 'Y'; }));
			if (!count($rows)) { $rows = $pool; }
			if (count($rows)) { $sections[] = (object)['key' => 'new', 'title' => lang('commerce.shop_home_new'), 'items' => array_slice($rows, 0, $count)]; }
		}
		if (($config->home_show_popular ?? 'Y') === 'Y')
		{
			$rows = $pool;
			usort($rows, function($a, $b) { return (int)($b->buy_count ?? 0) <=> (int)($a->buy_count ?? 0); });
			$rows = array_values(array_filter($rows, function($it) { return (int)($it->buy_count ?? 0) > 0; }));
			if (count($rows)) { $sections[] = (object)['key' => 'popular', 'title' => lang('commerce.shop_home_popular'), 'items' => array_slice($rows, 0, $count)]; }
		}
		if (($config->home_show_sale ?? 'Y') === 'Y')
		{
			$rows = array_values(array_filter($pool, function($it) { return (int)($it->sale_price ?? 0) > 0 && (int)$it->sale_price < (int)$it->price; }));
			if (count($rows)) { $sections[] = (object)['key' => 'sale', 'title' => lang('commerce.shop_home_sale'), 'items' => array_slice($rows, 0, $count)]; }
		}

		foreach ($sections as $section)
		{
			self::attachReviewStats($section->items);
		}

		$home_promotions = [];
		foreach (\Zittme\Modules\Commerce\Models\Promotion::activeList() as $promo)
		{
			$promo->banner_data = \Zittme\Modules\Commerce\Models\Promotion::bannerOf($promo);
			$promo->item_count = count(\Zittme\Modules\Commerce\Models\Promotion::itemSrlsOf((int)$promo->promo_srl));
			$home_promotions[] = $promo;
		}
		\Context::set('home_promotions', $home_promotions);

		$banners = json_decode((string)($config->home_banners ?? '[]'), true);
		$banners = is_array($banners) ? array_values(array_filter($banners, 'is_array')) : [];
		foreach ($banners as &$bn_lang)
		{
			$bn_lang['title'] = LangModel::text($bn_lang['title'] ?? '');
			$bn_lang['text'] = LangModel::text($bn_lang['text'] ?? '');
		}
		unset($bn_lang);

		foreach ($banners as $i => $bn)
		{
			$type = $bn['bg_type'] ?? (!empty($bn['image']) ? 'image' : 'gradient');
			$c1 = (isset($bn['bg_color']) && preg_match('/^#[0-9a-fA-F]+$/', (string)$bn['bg_color'])) ? $bn['bg_color'] : '#1a1f2e';
			$c2 = (isset($bn['bg_color2']) && preg_match('/^#[0-9a-fA-F]+$/', (string)$bn['bg_color2'])) ? $bn['bg_color2'] : '#0d1019';
			if ($type === 'image' && !empty($bn['image']))
			{
				$bn['bg_style'] = 'background-image:url(' . escape($bn['image']) . ')';
			}
			elseif ($type === 'color')
			{
				$bn['bg_style'] = 'background:' . $c1;
			}
			else
			{
				$bn['bg_style'] = 'background:linear-gradient(120deg,' . $c1 . ',' . $c2 . ')';
			}
			$bn['text_color'] = (isset($bn['text_color']) && preg_match('/^#[0-9a-fA-F]+$/', (string)$bn['text_color'])) ? $bn['text_color'] : '#ffffff';
			$bn['shadow'] = ($bn['shadow'] ?? 'Y') === 'N' ? 'N' : 'Y';
			$bn['title'] = LangModel::text((string)($bn['title'] ?? ''));
			$bn['text'] = LangModel::text((string)($bn['text'] ?? ''));
			$bn['title_html'] = self::escapeAllowBr((string)$bn['title']);
			$bn['text_html'] = self::escapeAllowBr((string)$bn['text']);
			$banners[$i] = $bn;
		}

		\Context::set('home_banners', $banners);
		\Context::set('home_sections', $sections);
		\Context::set('shop_categories', self::getActiveCategories());
		$home_items = [];
		foreach ($sections as $home_section)
		{
			foreach ((array)($home_section->items ?? []) as $home_row)
			{
				$home_items[] = $home_row;
			}
		}
		$this->setShopContext($home_items);
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', $config);
		self::injectAdminPanel();
		self::applyImageSize();
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('home');
	}

	public function dispCommerceItem()
	{
		self::assertShopEnabled();
		$item_srl = (int)\Context::get('item_srl');
		$item = ItemModel::get($item_srl);
		if (!$item || in_array($item->status, ['hidden', 'stop', 'review'], true))
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}
		$store_seller = \Zittme\Modules\Commerce\Models\Shop::itemsInStore() ? \Zittme\Modules\Commerce\Models\Shop::sellerForItem($item) : null;
		if ($store_seller && !\Context::get('zmc_store_frame'))
		{
			\Context::redirect(\Zittme\Modules\Commerce\Models\Shop::itemUrl((string)$store_seller->shop_id, $item_srl, (string)($this->module_info->mid ?? ''), true), 301);
			return;
		}

		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_item SET view_count = view_count + 1 WHERE item_srl = ?', $item_srl
		);

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		$breadcrumb = [];
		if (!empty($item->category_srl))
		{
			$by_srl = [];
			foreach (self::getActiveCategories() as $cat)
			{
				$by_srl[(int)$cat->category_srl] = $cat;
			}
			$cursor = (int)$item->category_srl;
			$guard = 0;
			while ($cursor > 0 && isset($by_srl[$cursor]) && $guard++ < 10)
			{
				array_unshift($breadcrumb, $by_srl[$cursor]);
				$cursor = (int)$by_srl[$cursor]->parent_srl;
			}
		}
		\Context::set('breadcrumb', $breadcrumb);

		$reviews_output = executeQuery('commerce.getReviewList', (object)['item_srl' => $item_srl, 'list_count' => 20]);
		$reviews = ($reviews_output->toBool() && !empty($reviews_output->data)) ? (is_array($reviews_output->data) ? $reviews_output->data : [$reviews_output->data]) : [];
		$inquiries_output = executeQuery('commerce.getInquiryList', (object)['item_srl' => $item_srl, 'list_count' => 20]);
		$inquiries = ($inquiries_output->toBool() && !empty($inquiries_output->data)) ? (is_array($inquiries_output->data) ? $inquiries_output->data : [$inquiries_output->data]) : [];
		$is_admin = $logged_info && $logged_info->is_admin === 'Y';
		\Context::set('reviews', array_values(array_filter($reviews, function($r) { return !empty($r->review_srl); })));
		\Context::set('inquiries', array_values(array_filter($inquiries, function($r) { return !empty($r->inquiry_srl); })));
		\Context::set('is_logged', $member_srl > 0);
		\Context::set('is_shop_admin', $is_admin);
		\Context::set('can_review', Review::canReviewNow($member_srl, $item_srl));

		\Context::set('credit_rate', \Zittme\Modules\Commerce\Models\Grade::creditRateFor($member_srl));

		LangModel::textAll([$item], ['item_name', 'summary']);
		\Context::set('item', $item);
		$item_seller = \Zittme\Modules\Commerce\Models\Seller::publicInfo((int)($item->seller_srl ?? 0));
		if ($item_seller)
		{
			// 입점 판매자 본문은 저장할 때 거르지만, 그 전에 저장된 본문도 있어 내보낼 때 한 번 더 거른다
			$item->content = \Zittme\Framework\Filters\HTMLFilter::clean((string)($item->content ?? ''), false, true);
			$item_seller->store_url = $item_seller->shop_id !== ''
				? \Zittme\Modules\Commerce\Models\Shop::url($item_seller->shop_id, (string)($this->module_info->mid ?? ''))
				: getUrl('', 'mid', (string)($this->module_info->mid ?? ''), 'v', 'list', 'seller', $item_seller->seller_srl);
		}
		\Context::set('item_seller', $item_seller);
		$this->setItemSeo($item);
		$shop_options = ItemModel::getOptions($item_srl, true);
		$shop_axes = ($item->option_mode ?? 'single') === 'combo'
			? \Zittme\Modules\Commerce\Models\Combo::axes($item->option_axes ?? '')
			: [];
		foreach ($shop_options as $shop_option)
		{
			$shop_option->combo_key = empty($shop_option->combo)
				? ''
				: \Zittme\Modules\Commerce\Models\Combo::indexKey($shop_axes, $shop_option->combo);
		}
		// 다국어 코드는 서버에서 실값으로 푼다. 템플릿이 이스케이프한 뒤에는 코어 치환이 걸리지 않는다
		LangModel::textAll($shop_options, ['option_label']);
		foreach ($shop_axes as $shop_axis)
		{
			$shop_axis->name = LangModel::text($shop_axis->name);
			foreach ($shop_axis->values as $shop_axis_i => $shop_axis_value)
			{
				$shop_axis->values[$shop_axis_i] = LangModel::text($shop_axis_value);
			}
			foreach ($shop_axis->items as $shop_axis_item)
			{
				$shop_axis_item->value = LangModel::text($shop_axis_item->value);
			}
		}
		foreach ($shop_options as $shop_option)
		{
			if (empty($shop_option->combo_key))
			{
				continue;
			}
			$shop_combo_label = \Zittme\Modules\Commerce\Models\Combo::labelFromKey($shop_axes, $shop_option->combo_key);
			if ($shop_combo_label !== '')
			{
				$shop_option->option_label = $shop_combo_label;
			}
		}
		\Context::set('options', $shop_options);
		\Context::set('shop_axes', $shop_axes);
		\Context::set('purchasable', ItemModel::isPurchasable($item));
		\Context::set('effective_price', ItemModel::effectivePrice($item));
		$item_disp_currency = MoneyModel::current();
		$item_disp = ItemModel::displayPrices($item, $item_disp_currency);
		\Context::set('disp_currency', $item_disp_currency);
		\Context::set('disp_effective_price', $item_disp['effective']);
		foreach ($shop_options as $shop_option)
		{
			$shop_option->disp_price_add = $item_disp_currency === MoneyModel::base()
				? (int)$shop_option->price_add
				: (int)MoneyModel::convertMinor((int)$shop_option->price_add, $item_disp_currency);
		}
		\Context::set('adult_ok', ($item->is_adult ?? 'N') !== 'Y' || Order::isAdultVerified($member_srl));
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
		$this->setShopContext([$item]);
		$this->addItemStructuredData($item, $item_disp['effective'], $item_disp_currency, $reviews);
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('item');
	}

	protected function setItemSeo(object $item): void
	{
		$name = trim(strip_tags((string)($item->item_name ?? '')));
		if ($name !== '')
		{
			$site_title = trim((string)\Context::getSiteTitle());
			\Context::setBrowserTitle($site_title !== '' && $site_title !== $name ? ($name . ' - ' . $site_title) : $name);
		}

		$summary = trim(utf8_normalize_spaces(strip_tags((string)($item->summary ?? ''))));
		if ($summary === '' && !empty($item->content))
		{
			$summary = trim(utf8_normalize_spaces(strip_tags((string)$item->content)));
		}
		if ($summary !== '')
		{
			if (mb_strlen($summary) > 160)
			{
				$summary = mb_substr($summary, 0, 157) . '...';
			}
			\Context::addMetaTag('description', $summary);
			\Context::addOpenGraphData('og:description', $summary);
		}

		$thumb = trim((string)($item->thumb ?? ''));
		if ($thumb !== '')
		{
			if (preg_match('#^https?://#', $thumb))
			{
				\Context::addOpenGraphData('og:image', $thumb);
			}
			else
			{
				\Context::addMetaImage($thumb);
			}
		}

		\Context::addOpenGraphData('og:type', 'product');
		$frame = \Context::get('zmc_store_frame');
		\Context::setCanonicalURL($frame
			? \Zittme\Modules\Commerce\Models\Shop::itemUrl((string)$frame->shop_id, (int)$item->item_srl, (string)$this->mid, true)
			: getNotEncodedFullUrl('', 'mid', $this->mid, 'act', 'dispCommerceItem', 'item_srl', (int)$item->item_srl));
	}

	protected function addItemStructuredData(object $item, int $price_minor, string $currency, array $reviews = []): void
	{
		if (!method_exists('\Context', 'addStructuredData'))
		{
			return;
		}

		$image = trim((string)($item->thumb ?? ''));
		if ($image !== '' && !preg_match('#^https?://#', $image))
		{
			$image = \Zittme\Framework\URL::getCurrentDomainURL('/') . ltrim(preg_replace('#^\./#', '', $image), '/');
		}

		$rating = [];
		$scores = [];
		foreach ($reviews as $review)
		{
			if (!empty($review->review_srl) && (int)($review->rating ?? 0) > 0)
			{
				$scores[] = (int)$review->rating;
			}
		}
		if (count($scores))
		{
			$rating = [
				'@type' => 'AggregateRating',
				'ratingValue' => round(array_sum($scores) / count($scores), 1),
				'reviewCount' => count($scores),
				'bestRating' => 5,
			];
		}

		$price = MoneyModel::isZeroDecimal($currency) ? (string)$price_minor : number_format($price_minor / 100, 2, '.', '');

		\Context::addStructuredData('Product', [
			'name' => trim((string)($item->item_name ?? '')),
			'description' => trim(utf8_normalize_spaces(strip_tags((string)($item->summary ?? '')))),
			'sku' => trim((string)($item->item_code ?? '')),
			'image' => $image,
			'aggregateRating' => $rating,
			'offers' => [
				'@type' => 'Offer',
				'price' => $price,
				'priceCurrency' => $currency,
				'availability' => ItemModel::isPurchasable($item) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'itemCondition' => 'https://schema.org/NewCondition',
				'url' => \Context::getCanonicalURL() ?: \Zittme\Framework\URL::getCurrentURL(),
			],
		]);
	}

	public function dispCommerceCart()
	{
		self::assertShopEnabled();
		$resolved = CartModel::resolve();
		\Context::set('cart', $resolved);
		\Zittme\Modules\Commerce\Models\Seller::attachNames(array_map(function ($e) { return $e->item; }, (array)($resolved->items ?? [])), (string)($this->module_info->mid ?? ''));
		$last_store = $_SESSION['commerce_last_store'] ?? null;
		\Context::set('shop_continue_url', (is_array($last_store) && time() - (int)($last_store['time'] ?? 0) < 7200) ? (string)$last_store['url'] : '');
		\Context::set('ship_fee', CartModel::calcShipFee($resolved));
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setShopContext();
		$this->setTemplateFile('cart');
	}

	public function dispCommerceCheckout()
	{
		self::assertShopEnabled();
		$resolved = CartModel::resolve();
		$valid = array_values(array_filter($resolved->items, function($e) { return !$e->blocked; }));
		if (!count($valid))
		{
			$pending = self::findPendingOrder();
			if ($pending)
			{
				$this->setMessage('msg_shop_pending_order_exists');
				$this->setRedirectUrl(getNotEncodedUrl('', 'mid', (string)\Context::get('mid'),
					'act', 'dispCommerceOrderResult', 'code', $pending->order_code));
				return;
			}
			return new \BaseObject(-1, 'msg_shop_cart_empty');
		}

		$logged_info = \Context::get('logged_info');
		$ship_fee = CartModel::calcShipFee($resolved);
		if (CartModel::isPinOnly($resolved))
		{
			\Context::set('checkout_pin_only', true);
			$pin_title = json_encode(lang('commerce.pin_checkout_title'), \JSON_UNESCAPED_UNICODE);
			$pin_text = json_encode(lang('commerce.pin_checkout_text'), \JSON_UNESCAPED_UNICODE);
			\Context::addHtmlFooter('<script>(function(){var a=document.querySelector(\'input[name="address1"]\');if(!a)return;var box=a.closest(".shp-box")||a.closest("fieldset")||a.parentNode.parentNode;box.querySelectorAll("[required]").forEach(function(e){e.removeAttribute("required");});box.hidden=true;box.style.display="none";var n=document.createElement("div");n.className=box.className;var h=document.createElement("h3");h.textContent=' . $pin_title . ';var p=document.createElement("p");p.style.cssText="margin:0;font-size:14px;line-height:1.7;color:#4e5968";p.textContent=' . $pin_text . ';n.appendChild(h);n.appendChild(p);box.parentNode.insertBefore(n,box);})();</script>');
		}

		OrderModel::expireStalePending();
		$open_pending = OrderModel::findOpenPending(($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0);
		if ($open_pending)
		{
			$open_pending->pending_deadline = OrderModel::pendingDeadline($open_pending);
			$open_pending->resume_pay_url = OrderModel::resumePayUrl($open_pending);
		}
		\Context::set('pending_order', $open_pending);

		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		$my_addresses = [];
		if ($member_srl > 0)
		{
			$addr_output = executeQuery('commerce.getAddressList', (object)['member_srl' => $member_srl]);
			if ($addr_output->toBool() && !empty($addr_output->data))
			{
				$only_base = !AddressModel::needsCountry();
				foreach (is_array($addr_output->data) ? $addr_output->data : [$addr_output->data] as $addr)
				{
					if (empty($addr->address_srl))
					{
						continue;
					}
					$addr_country = strtoupper(trim((string)($addr->country ?? ''))) ?: 'KR';
					if ($only_base && $addr_country !== AddressModel::baseCountry())
					{
						continue;
					}
					$my_addresses[] = $addr;
				}
			}
		}
		\Context::set('my_addresses', $my_addresses);
		\Context::set('my_coupons', \Zittme\Modules\Commerce\Models\Coupon::listUsableForMember($member_srl, $resolved->item_total));
		\Context::set('credit_balance', \Zittme\Modules\Commerce\Models\Credit::balanceOf($member_srl));

		$base_currency = \Zittme\Modules\Commerce\Models\Money::base();
		$fx_currency = \Zittme\Modules\Commerce\Models\Money::current();
		$fx_rate = \Zittme\Modules\Commerce\Models\Money::rate($fx_currency);
		if ($fx_currency !== $base_currency && $fx_rate > 0)
		{
			$fx_item_total = 0;
			$fx_ok = true;
			$fx_member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
			$fx_discount = $fx_member_srl > 0 ? \Zittme\Modules\Commerce\Models\Grade::discountFor($fx_member_srl) : null;
			foreach ($valid as $entry)
			{
				$fx_unit = \Zittme\Modules\Commerce\Models\Item::effectivePriceIn($entry->item, $fx_currency);
				$fx_add = $entry->option ? \Zittme\Modules\Commerce\Models\Money::convertMinor(max(0, (int)($entry->option->price_add ?? 0)), $fx_currency) : 0;
				if ($fx_unit < 0 || $fx_add < 0)
				{
					$fx_ok = false;
					break;
				}
				$fx_graded = ($entry->item->grade_discount ?? 'Y') === 'N'
					? $fx_unit + $fx_add
					: \Zittme\Modules\Commerce\Models\Grade::applyDiscountIn($fx_unit + $fx_add, $fx_discount, $fx_currency);
				$entry->unit_price = $fx_graded;
				$entry->subtotal = $fx_graded * $entry->qty;
			}
			if ($fx_ok)
			{
				foreach ($valid as $entry)
				{
					$fx_item_total += (int)$entry->subtotal;
				}
				$resolved->item_total = $fx_item_total;
				$ship_fee = max(0, \Zittme\Modules\Commerce\Models\Money::convertMinor($ship_fee, $fx_currency));
			}
			else
			{
				$fx_currency = $base_currency;
			}
		}
		else
		{
			$fx_currency = $base_currency;
		}
		\Context::set('shp_currency', $fx_currency);
		\Context::set('shp_currency_symbol', \Zittme\Modules\Commerce\Models\Money::symbol($fx_currency));
		\Context::set('shp_base_currency', $base_currency);
		\Context::set('shp_fx_rate', $fx_currency === $base_currency ? 1 : $fx_rate);
		\Context::set('shp_fx_zero_decimal', \Zittme\Modules\Commerce\Models\Money::isZeroDecimal($fx_currency));

		\Context::set('cart', $resolved);
		\Zittme\Modules\Commerce\Models\Seller::attachNames(array_map(function ($e) { return $e->item; }, (array)($resolved->items ?? [])), (string)($this->module_info->mid ?? ''));
		\Context::set('ship_fee', $ship_fee);
		\Context::set('payment_price', $resolved->item_total + $ship_fee);
		\Context::set('is_member', $logged_info && $logged_info->member_srl ? true : false);
		\Context::set('pay_available', self::isPayAvailable());
		\Context::set('shop_config', self::config());
		\Context::set('shop_address_mode', AddressModel::mode());
		\Context::set('shop_base_country', AddressModel::baseCountry());
		\Context::set('shop_need_country', AddressModel::needsCountry());
		\Context::set('shop_need_phone_cc', AddressModel::needsPhoneCode());
		\Context::set('shop_require_state', AddressModel::requiresState());
		\Context::set('shop_use_coupon', self::config()->use_coupon !== 'N');
		\Context::set('shop_use_credit', self::config()->use_credit !== 'N');
		\Context::set('shop_countries', AddressModel::countries());

		$region_data = [];
		foreach (array_keys(\Zittme\Modules\Commerce\Models\Region::REGIONS) as $region_country)
		{
			$region_data[$region_country] = \Zittme\Modules\Commerce\Models\Region::searchData($region_country);
		}
		\Context::set('shp_region_json', json_encode($region_data, \JSON_UNESCAPED_UNICODE));
		\Context::set('shp_state_value', (string)\Context::get('state'));
		\Context::addCSSFile('./modules/commerce/tpl/css/pickbox.css');
		\Context::addJsFile('./modules/commerce/tpl/js/pickbox.js');
		\Context::set('shop_default_country', AddressModel::mode() === 'intl' ? '' : 'KR');
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('checkout');
	}

	protected static function findPendingOrder(): ?object
	{
		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		if ($member_srl <= 0)
		{
			return null;
		}

		$minutes = max(10, (int)(self::config()->pending_minutes ?? 60));
		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'SELECT order_srl, order_code FROM commerce_order'
			. ' WHERE member_srl = ? AND status = ? AND regdate >= ?'
			. ' ORDER BY order_srl DESC LIMIT 1',
			$member_srl, self::ORDER_PENDING, date('YmdHis', time() - 60 * $minutes)
		);
		$row = $stmt ? ($stmt->fetchObject() ?: null) : null;
		if ($stmt)
		{
			$stmt->closeCursor();
		}
		return ($row && !empty($row->order_code)) ? $row : null;
	}

	protected static function clearOrderedFromCart(object $order): void
	{
		$owner = CartModel::owner();
		if ($owner->member_srl <= 0 && $owner->session_key === '')
		{
			return;
		}

		$ordered = [];
		foreach (OrderModel::getItems((int)$order->order_srl) as $item)
		{
			$ordered[(int)$item->item_srl . ':' . (int)$item->option_srl] = true;
		}
		if (!count($ordered))
		{
			return;
		}

		$remove = [];
		foreach (CartModel::rows($owner) as $row)
		{
			if (isset($ordered[(int)$row->item_srl . ':' . (int)$row->option_srl]))
			{
				$remove[] = (int)$row->cart_srl;
			}
		}
		if (count($remove))
		{
			CartModel::removeMany($remove);
		}
	}

	public function dispCommerceOrderResult()
	{
		$code = trim((string)\Context::get('code'));
		$order = $code !== '' ? OrderModel::getByCode($code) : null;
		if (!$order)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		$is_admin = $logged_info && $logged_info->is_admin === 'Y';

		$authorized = $is_admin;
		if (!$authorized && (int)$order->member_srl > 0)
		{
			$authorized = $member_srl === (int)$order->member_srl;
		}
		elseif (!$authorized)
		{
			$gp = (string)\Context::get('gp');
			$authorized = $gp !== '' && !empty($order->guest_password)
				&& \Zittme\Framework\Password::checkPassword($gp, $order->guest_password);
			if (!$authorized)
			{
				$age = time() - (strtotime(sprintf(
					'%s-%s-%s %s:%s:%s',
					substr($order->regdate, 0, 4), substr($order->regdate, 4, 2), substr($order->regdate, 6, 2),
					substr($order->regdate, 8, 2), substr($order->regdate, 10, 2), substr($order->regdate, 12, 2)
				)) ?: 0);
				$authorized = $age >= 0 && $age < 300;
			}
		}
		if (!$authorized)
		{
			return new \BaseObject(-1, 'msg_shop_not_yours');
		}

		\Zittme\Modules\Commerce\Models\Tracking::syncShipping();

		self::clearOrderedFromCart($order);

		\Context::set('order', $order);
		\Context::set('pending_deadline', OrderModel::pendingDeadline($order));
		\Context::set('resume_pay_url', OrderModel::resumePayUrl($order));
		\Context::set('order_items', OrderModel::getItems((int)$order->order_srl));
		$order_sellers = OrderModel::getSellerOrders((int)$order->order_srl);
		foreach ($order_sellers as $track_os)
		{
			$track_os->tracking_url = empty($track_os->shipping_invoice) ? '' : \Zittme\Modules\Commerce\Models\Courier::trackUrl((string)$track_os->shipping_company, (string)$track_os->shipping_invoice);
		}

		$tracking_info = null;
		foreach ($order_sellers as $track_os)
		{
			if (!empty($track_os->shipping_invoice))
			{
				$tracking_info = \Zittme\Modules\Commerce\Models\Tracking::getForSeller((int)$track_os->order_seller_srl);
				break;
			}
		}
		\Context::set('tracking_info', $tracking_info);

		$unreviewed = [];
		if ($member_srl > 0 && OrderModel::displayStatus($order, $order_sellers) === 'confirmed')
		{
			$unreviewed = \Zittme\Modules\Commerce\Controllers\Review::unreviewedItems($member_srl, (int)$order->order_srl);
		}
		\Context::set('unreviewed_items', $unreviewed);
		foreach ($order_sellers as $label_os)
		{
			$label_info = \Zittme\Modules\Commerce\Models\Seller::publicInfo((int)$label_os->seller_srl);
			$label_os->shop_label = $label_info ? $label_info->shop_name : lang('commerce.mk_direct');
			$label_os->claimable = !in_array($label_os->status, [self::SELLER_CONFIRMED, self::SELLER_CANCELLED, self::SELLER_REFUNDED, self::SELLER_PENDING], true);
		}
		\Context::set('order_sellers', $order_sellers);
		$claimable_sellers = array_values(array_filter($order_sellers, function ($os) { return !empty($os->claimable); }));
		$claim_blocked_reason = '';
		if ($order->status === self::ORDER_PAID && !count($claimable_sellers))
		{
			$all_confirmed = count($order_sellers) > 0;
			foreach ($order_sellers as $os_check)
			{
				if ($os_check->status !== self::SELLER_CONFIRMED)
				{
					$all_confirmed = false;
				}
			}
			$claim_blocked_reason = lang($all_confirmed ? 'commerce.sc_claim_blocked_confirmed' : 'commerce.sc_claim_blocked_none');
		}
		\Context::set('claimable_sellers', $claimable_sellers);
		\Context::set('claim_blocked_reason', $claim_blocked_reason);
		\Context::set('display_status', OrderModel::displayStatus($order, $order_sellers));
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('result');
	}

	public function dispCommerceMyOrders()
	{
		self::assertShopEnabled();
		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		\Zittme\Modules\Commerce\Models\Tracking::syncShipping();
		OrderModel::expireStalePending();

		$orders = [];
		if ($member_srl > 0)
		{
			$output = executeQuery('commerce.getOrderListByMember', (object)['member_srl' => $member_srl, 'list_count' => 50]);
			if ($output->toBool() && !empty($output->data))
			{
				foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
				{
					if (!empty($row->order_srl))
					{
						$row->display_status = OrderModel::displayStatus($row);
						$row->needs_review = $row->display_status === 'confirmed'
							&& count(\Zittme\Modules\Commerce\Controllers\Review::unreviewedItems($member_srl, (int)$row->order_srl)) > 0;
						$row->pending_deadline = OrderModel::pendingDeadline($row);
						$row->resume_pay_url = OrderModel::resumePayUrl($row);
						$orders[] = $row;
					}
				}
			}
		}

		\Context::set('is_member', $member_srl > 0);
		\Context::set('orders', $orders);
		\Context::set('credit_balance', \Zittme\Modules\Commerce\Models\Credit::balanceOf($member_srl));
		\Context::set('credit_logs', $member_srl > 0 ? \Zittme\Modules\Commerce\Models\Credit::getLogs($member_srl, 30) : []);
		\Context::set('my_grade', $member_srl > 0 ? \Zittme\Modules\Commerce\Models\Grade::getForMember($member_srl) : null);
		\Context::set('my_coupons', $member_srl > 0 ? \Zittme\Modules\Commerce\Models\Coupon::listUsableForMember($member_srl, 0) : []);
		\Context::set('seller_apply_link', $member_srl > 0 && \Zittme\Modules\Commerce\Models\Seller::isOpen()
			&& ((self::config()->market_apply ?? 'N') === 'Y' || \Zittme\Modules\Commerce\Models\Seller::getByMember($member_srl)));
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('my');
	}

	public function dispCommerceGrades()
	{
		self::assertShopEnabled();
		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		$config = self::config();
		$default_rate = (float)($config->credit_rate ?? 0);

		$grades = [];
		foreach (\Zittme\Modules\Commerce\Models\Grade::getList() as $row)
		{
			$row->min_spend = (int)($row->min_spend ?? 0);
			$row->credit_rate = (float)($row->credit_rate ?? 0);
			$row->credit_rate_effective = $row->credit_rate > 0 ? $row->credit_rate : $default_rate;
			$row->discount_type = (string)($row->discount_type ?? '');
			$row->discount_value = (float)($row->discount_value ?? 0);
			$row->coupon = !empty($row->coupon_srl) ? \Zittme\Modules\Commerce\Models\Coupon::get((int)$row->coupon_srl) : null;
			if ($row->coupon)
			{
				$row->coupon->title = LangModel::text((string)$row->coupon->title);
			}
			$grades[] = $row;
		}

		$my_grade = $member_srl > 0 ? \Zittme\Modules\Commerce\Models\Grade::getForMember($member_srl) : null;
		$total_spend = $my_grade ? (int)($my_grade->total_spend ?? 0) : 0;
		$next_grade = null;
		$progress = 0;
		if ($member_srl > 0)
		{
			foreach ($grades as $g)
			{
				if ($g->min_spend > $total_spend)
				{
					$next_grade = $g;
					break;
				}
			}
			if ($next_grade)
			{
				$floor = $my_grade ? (int)($my_grade->min_spend ?? 0) : 0;
				$span = max(1, $next_grade->min_spend - $floor);
				$progress = (int)min(100, max(0, round(($total_spend - $floor) / $span * 100)));
			}
			else
			{
				$progress = 100;
			}
		}

		\Context::set('is_member', $member_srl > 0);
		\Context::set('grades', $grades);
		\Context::set('my_grade', $my_grade);
		\Context::set('my_total_spend', $total_spend);
		\Context::set('next_grade', $next_grade);
		\Context::set('next_remaining', $next_grade ? max(0, $next_grade->min_spend - $total_spend) : 0);
		\Context::set('grade_progress', $progress);
		\Context::set('default_credit_rate', $default_rate);
		\Context::set('shop_config', $config);
		$skin_path = $this->getSkinPath();
		if (!is_file($skin_path . 'grades.html'))
		{
			$skin_path = $this->module_path . 'skins/default/';
		}
		$this->setTemplatePath($skin_path);
		$this->setTemplateFile('grades');
	}

	protected function setStoreFrame(object $seller, array $design, string $mid, bool $is_preview): object
	{
		$shop_id = (string)$seller->shop_id;
		$cats = \Zittme\Modules\Commerce\Models\Shop::categories((int)$seller->seller_srl);
		foreach ($cats as $c)
		{
			$c->url = \Zittme\Modules\Commerce\Models\Shop::url($shop_id, $mid, ['cat' => (int)$c->category_srl]);
		}
		$store = (object)[
			'seller_srl' => (int)$seller->seller_srl,
			'shop_id' => $shop_id,
			'shop_name' => (string)$seller->shop_name,
			'intro' => (string)($seller->intro ?? ''),
			'logo' => $design['logo'] ?? '',
			'cover' => $design['cover'] ?? '',
			'url' => \Zittme\Modules\Commerce\Models\Shop::url($shop_id, $mid),
			'all_url' => \Zittme\Modules\Commerce\Models\Shop::url($shop_id, $mid, ['sort' => 'display']),
		];
		\Context::set('store', $store);
		\Context::set('store_info', \Zittme\Modules\Commerce\Models\Seller::publicInfo((int)$seller->seller_srl));
		\Context::set('store_design', $design);
		\Context::set('store_color', $design['color']);
		\Context::set('store_cats', $cats);
		\Context::set('store_preview', $is_preview);
		return $store;
	}

	protected function dispStoreItem(object $seller, array $design, string $mid, bool $is_preview)
	{
		$item_srl = (int)\Context::get('item');
		$item = ItemModel::get($item_srl);
		if (!$item || (int)$item->seller_srl !== (int)$seller->seller_srl)
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		$store = $this->setStoreFrame($seller, $design, $mid, $is_preview);
		\Context::set('zmc_store_frame', $store);
		$_SESSION['commerce_last_store'] = ['url' => $store->url, 'time' => time()];
		\Context::set('store_cat', null);
		\Context::set('store_q', '');
		\Context::set('store_sort', '');
		\Context::set('store_browsing', true);
		\Context::set('item_srl', $item_srl);
		$output = $this->dispCommerceItem();
		if ($output instanceof \BaseObject && !$output->toBool())
		{
			return $output;
		}
		$seller_info = \Context::get('item_seller');
		\Context::set('store_item_seller', $seller_info);
		\Context::set('item_seller', null);
		\Context::set('store_item_url', \Zittme\Modules\Commerce\Models\Shop::itemUrl($store->shop_id, $item_srl, $mid));

		$skin_path = $this->getSkinPath();
		if (!is_file($skin_path . '_storeframe_item.html'))
		{
			$skin_path = $this->module_path . 'skins/default/';
		}
		$this->setTemplatePath($skin_path);
		$this->setTemplateFile('_storeframe_item');
	}

	public function dispCommerceStore()
	{
		self::assertShopEnabled();
		if (!\Zittme\Modules\Commerce\Models\Seller::isOpen())
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		$found = \Zittme\Modules\Commerce\Models\Shop::find((string)\Context::get('shop'));
		if (!$found || $found->seller->status !== 'approved')
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		$seller = $found->seller;
		$seller_srl = (int)$seller->seller_srl;
		$mid = (string)($this->module_info->mid ?? '');
		if ($found->moved)
		{
			$keep = [];
			foreach (['cat', 'sort', 'page', 'item'] as $key)
			{
				$value = (string)\Context::get($key);
				if ($value !== '' && preg_match('/^[a-z0-9_]{1,20}$/', $value))
				{
					$keep[$key] = $value;
				}
			}
			\Context::redirect(htmlspecialchars_decode(\Zittme\Modules\Commerce\Models\Shop::url((string)$seller->shop_id, $mid, $keep)), 301);
			return;
		}

		$design = \Zittme\Modules\Commerce\Models\Shop::design($seller);
		$me = \Zittme\Modules\Commerce\Models\Staff::seller();
		$is_preview = \Context::get('preview') && $me && (int)$me->seller_srl === $seller_srl;
		if ($is_preview)
		{
			$draft = \Zittme\Modules\Commerce\Models\Shop::draft($seller_srl);
			if ($draft)
			{
				$design = $draft;
			}
			\Context::addMetaTag('robots', 'noindex');
		}
		if ((int)\Context::get('item') > 0)
		{
			return $this->dispStoreItem($seller, $design, $mid, $is_preview);
		}
		$count = (int)$design['count'];

		$now = self::now();
		$in_period = function ($it) use ($now) {
			if (empty($it->item_srl)) return false;
			if (!empty($it->sale_start) && $now < $it->sale_start) return false;
			if (!empty($it->sale_end) && $now > $it->sale_end) return false;
			return true;
		};
		$fetch = function (array $extra) use ($seller_srl, $in_period) {
			$output = executeQuery('commerce.getItemList', (object)($extra + ['seller_srl' => $seller_srl, 'status_list' => 'sale,soldout']));
			$rows = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];
			return (object)['items' => array_values(array_filter($rows, $in_period)), 'nav' => $output->page_navigation ?? null];
		};

		$cats = \Zittme\Modules\Commerce\Models\Shop::categories($seller_srl);
		$cat_srl = (int)\Context::get('cat');
		$current_cat = null;
		foreach ($cats as $c)
		{
			$c->url = \Zittme\Modules\Commerce\Models\Shop::url((string)$seller->shop_id, $mid, ['cat' => (int)$c->category_srl]);
			if ((int)$c->category_srl === $cat_srl)
			{
				$current_cat = $c;
			}
		}
		$keyword = mb_substr(trim((string)\Context::get('q')), 0, 50);
		$sort = in_array(\Context::get('sort'), ['display', 'new', 'popular', 'price_low', 'price_high'], true) ? (string)\Context::get('sort') : '';
		$browsing = $current_cat || $keyword !== '' || $sort !== '' || (int)\Context::get('page') > 1;

		$all_items = [];
		$sections = [];
		if (!$browsing)
		{
			foreach ($design['sections'] as $sec)
			{
				if (empty($sec['on']))
				{
					continue;
				}
				$key = $sec['key'];
				$block = (object)['key' => $key, 'title' => lang('commerce.sc_sec_' . $key), 'items' => [], 'groups' => []];
				if ($key === 'featured')
				{
					if (!count($design['featured']))
					{
						continue;
					}
					$rows = $fetch(['item_srl_list' => implode(',', $design['featured']), 'list_count' => 50])->items;
					$order = array_flip($design['featured']);
					usort($rows, function ($a, $b) use ($order) { return ($order[(int)$a->item_srl] ?? 99) <=> ($order[(int)$b->item_srl] ?? 99); });
					$block->items = $rows;
				}
				elseif ($key === 'new')
				{
					$block->items = $fetch(['sort_index' => 'item_srl', 'order_type' => 'desc', 'list_count' => $count])->items;
				}
				elseif ($key === 'cats')
				{
					foreach (array_slice($cats, 0, 6) as $c)
					{
						$rows = $fetch(['seller_category_srl' => (int)$c->category_srl, 'list_count' => $count])->items;
						if (count($rows))
						{
							$block->groups[] = (object)['title' => $c->title, 'url' => $c->url, 'items' => $rows];
						}
					}
					if (!count($block->groups))
					{
						continue;
					}
				}
				elseif ($key === 'notice' && trim($design['notice']) === '')
				{
					continue;
				}
				elseif ($key === 'banner' && !count($design['banners']))
				{
					continue;
				}
				$sections[] = $block;
			}
		}

		$args = ['page' => max(1, (int)\Context::get('page')), 'list_count' => 24];
		if ($current_cat)
		{
			$args['seller_category_srl'] = (int)$current_cat->category_srl;
		}
		if ($keyword !== '')
		{
			$args['search_keyword'] = $keyword;
		}
		switch ($sort)
		{
			case 'popular': $args['sort_index'] = 'buy_count'; $args['order_type'] = 'desc'; break;
			case 'price_low': $args['sort_index'] = 'effective_price'; $args['order_type'] = 'asc'; break;
			case 'price_high': $args['sort_index'] = 'effective_price'; $args['order_type'] = 'desc'; break;
			case 'new': $args['sort_index'] = 'item_srl'; $args['order_type'] = 'desc'; break;
			default: $args['sort_index'] = 'list_order'; $args['order_type'] = 'asc';
		}
		$listing = $fetch($args);

		$every = $listing->items;
		foreach ($sections as $block)
		{
			$every = array_merge($every, $block->items);
			foreach ($block->groups as $g)
			{
				$every = array_merge($every, $g->items);
			}
		}
		LangModel::textAll($every, ['item_name']);
		self::attachReviewStats($every);
		$this->setShopContext($every);

		$store = (object)[
			'seller_srl' => $seller_srl,
			'shop_id' => (string)$seller->shop_id,
			'shop_name' => (string)$seller->shop_name,
			'intro' => (string)($seller->intro ?? ''),
			'logo' => $design['logo'] ?? '',
			'cover' => $design['cover'] ?? '',
			'url' => \Zittme\Modules\Commerce\Models\Shop::url((string)$seller->shop_id, $mid),
		];
		\Context::setBrowserTitle($store->shop_name);
		\Context::set('store', $store);
		\Context::set('store_info', \Zittme\Modules\Commerce\Models\Seller::publicInfo($seller_srl));
		\Context::set('store_design', $design);
		\Context::set('store_sections', $sections);
		\Context::set('store_cats', $cats);
		\Context::set('store_cat', $current_cat);
		\Context::set('store_q', $keyword);
		\Context::set('store_sort', $sort);
		\Context::set('store_browsing', $browsing);
		\Context::set('store_items', $listing->items);
		\Context::set('page_navigation', $listing->nav);
		\Context::set('store_preview', $is_preview);
		\Context::set('store_color', $design['color']);
		$_SESSION['commerce_last_store'] = ['url' => $store->url, 'time' => time()];
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
		self::injectAdminPanel();

		$skin_path = $this->getSkinPath();
		if (!is_file($skin_path . 'store.html'))
		{
			$skin_path = $this->module_path . 'skins/default/';
		}
		$this->setTemplatePath($skin_path);
		$this->setTemplateFile('store');
	}

	public function dispCommerceSellerApply()
	{
		self::assertShopEnabled();
		if (!\Zittme\Modules\Commerce\Models\Seller::isOpen())
		{
			throw new \Zittme\Framework\Exceptions\TargetNotFound;
		}
		$logged_info = \Context::get('logged_info');
		$member_srl = is_object($logged_info) ? (int)$logged_info->member_srl : 0;
		$mine = $member_srl > 0 ? \Zittme\Modules\Commerce\Models\Seller::getByMember($member_srl) : null;
		if ($mine)
		{
			$mine->regdate_text = $mine->regdate ? zdate($mine->regdate, 'Y.m.d') : '';
		}
		\Context::set('is_member', $member_srl > 0);
		\Context::set('my_seller', $mine);
		\Context::set('apply_open', (self::config()->market_apply ?? 'N') === 'Y');
		\Context::set('apply_rate', (float)(self::config()->market_commission ?? 0));
		\Context::set('console_url', getNotEncodedUrl('', 'mid', '', 'act', 'dispCommerceSellerCenter'));
		\Context::set('shop_config', self::config());
		$skin_path = $this->getSkinPath();
		if (!is_file($skin_path . 'seller_apply.html'))
		{
			$skin_path = $this->module_path . 'skins/default/';
		}
		$this->setTemplatePath($skin_path);
		$this->setTemplateFile('seller_apply');
	}

	public function procCommerceSellerApply()
	{
		if (!\Zittme\Modules\Commerce\Models\Seller::isOpen() || (self::config()->market_apply ?? 'N') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$logged_info = \Context::get('logged_info');
		$member_srl = is_object($logged_info) ? (int)$logged_info->member_srl : 0;
		if ($member_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_shop_login_required');
		}
		$old = \Zittme\Modules\Commerce\Models\Seller::getByMember($member_srl);
		if ($old && $old->status !== 'rejected')
		{
			return new \BaseObject(-1, lang('commerce.mk_msg_already'));
		}
		if (\Context::get('agree') !== 'Y')
		{
			return new \BaseObject(-1, lang('commerce.mk_msg_need_agree'));
		}
		$data = \Zittme\Modules\Commerce\Models\Seller::filterInput();
		$missing = \Zittme\Modules\Commerce\Models\Seller::missingField($data);
		if ($missing !== '')
		{
			return new \BaseObject(-1, sprintf(lang('commerce.mk_msg_missing'), lang('commerce.mk_f_' . $missing)));
		}
		if (\Zittme\Modules\Commerce\Models\Seller::operatorSrl() <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$id_error = \Zittme\Modules\Commerce\Models\Shop::idError((string)($data->shop_id ?? ''), $old ? (int)$old->seller_srl : 0);
		if ($id_error !== '')
		{
			return new \BaseObject(-1, lang('commerce.' . $id_error));
		}
		if (!\Zittme\Modules\Commerce\Models\Seller::apply($member_srl, $data))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$this->setMessage(lang('commerce.mk_msg_applied'));
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', (string)\Context::get('mid'), 'act', 'dispCommerceSellerApply'));
	}
}
