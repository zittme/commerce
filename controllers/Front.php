<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Address as AddressModel;
use Zittme\Modules\Commerce\Models\Badge as BadgeModel;
use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Lang as LangModel;
use Zittme\Modules\Commerce\Models\Money as MoneyModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;

/**
 * 프론트 화면.
 */
class Front extends Base
{
	/**
	 * 스킨이 쓸 통화 값과 뱃지를 미리 담는다.
	 *
	 * 스킨에서 모델을 직접 부르면 컴파일 단계에서 네임스페이스 구분자가 유실돼
	 * 클래스를 못 찾는 오류가 난다. 화면에 필요한 값은 전부 여기서 만들어 넘긴다.
	 *
	 * @param array<int, object> $items 뱃지를 붙일 상품 목록 (없으면 통화 값만)
	 * @return void
	 */
	protected function setShopContext(array $items = []): void
	{
		$base = MoneyModel::base();
		$now = MoneyModel::current();

		\Context::set('shp_base_currency', $base);
		\Context::set('shp_base_zero', MoneyModel::isZeroDecimal($base));
		\Context::set('shp_unit_label', MoneyModel::unitLabel());
		// KRW 접미사 '원' 은 한국어 화면에서만 쓴다. 스킨의 JS 합계도 같은 기준을 따른다
		\Context::set('shp_won_suffix', MoneyModel::useWonSuffix());
		\Context::set('shp_currency', $now);
		\Context::set('shp_currency_zero', MoneyModel::isZeroDecimal($now));
		\Context::set('shp_currency_symbol', MoneyModel::symbol($now));
		\Context::set('shp_currency_rate', MoneyModel::rate($now) ?: 1);

		// 통화 알약에 쓸 목록 — 기준 통화이거나 환율이 있는 것만 고른다
		$choices = [];
		foreach (MoneyModel::currencies() as $code)
		{
			if ($code === $base || MoneyModel::rate($code) > 0)
			{
				$choices[$code] = MoneyModel::symbol($code);
			}
		}
		\Context::set('shp_currency_choices', $choices);

		$badge_map = BadgeModel::getMap(true);
		\Context::set('shop_badge_map', $badge_map);

		// 등급 할인이 걸린 회원에게는 할인된 값을 함께 담아 화면이 그 값을 보여 주게 한다
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

			$listed = (int)($shop_item->sale_price ?? 0) > 0
				? (int)$shop_item->sale_price
				: (int)($shop_item->price ?? 0);
			$graded = \Zittme\Modules\Commerce\Models\Grade::applyDiscount($listed, $grade_discount);
			// 등급 할인이 실제로 값을 낮췄을 때만 표시를 바꾼다
			$shop_item->grade_price = $graded < $listed ? $graded : 0;
		}
	}

	/**
	 * 스킨 경로.
	 *
	 * @return string
	 */
	protected function getSkinPath(): string
	{
		$skin = (string)($this->module_info->skin ?? '');
		// 기본 스킨 위임이면 사이트 기본 디자인 값을 따른다 (테마 적용이 여길 바꾼다)
		if ($skin === '' || $skin === '/USE_DEFAULT/')
		{
			$skin = (string)(\ModuleModel::getModuleDefaultSkin('commerce', 'P') ?: 'default');
		}
		// 일반 이름과 테마 결합명('테마|@|스킨')만 허용 — 경로 조작 방지
		if (!preg_match('/^[A-Za-z0-9_-]+(\|@\|[A-Za-z0-9_-]+)?$/', $skin))
		{
			$skin = 'default';
		}
		$path = \Zittme\Framework\Theme::resolveSkinPath($this->module_path, $skin, 'skins');
		if (!is_dir($path))
		{
			$path = $this->module_path . 'skins/default/';
		}
		return rtrim($path, '/') . '/';
	}

	/**
	 * 노출용 카테고리.
	 *
	 * @return array
	 */
	protected static function getActiveCategories(): array
	{
		$output = executeQuery('commerce.getCategoryList', (object)['is_active' => 'Y']);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		$data = array_values(array_filter($data, function($row) { return !empty($row->category_srl); }));

		// 트리 순서(상위 → 하위)로 정렬하고 depth 를 붙인다
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
		// 고아 카테고리(비활성 상위의 하위)는 최상위 취급으로 뒤에 붙인다
		foreach ($data as $row)
		{
			if (!in_array($row, $sorted, true))
			{
				$row->depth = 0;
				$sorted[] = $row;
			}
		}
		// 다국어 문구를 연결한 이름은 스킨이 escape 로 찍기 전에 미리 바꿔 둔다
		return LangModel::textAll($sorted, ['title']);
	}

	/**
	 * 지정 카테고리와 모든 하위 카테고리의 srl 목록.
	 *
	 * @param int $srl
	 * @return array<int>
	 */
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

	/**
	 * 프론트 관리 플로팅 패널을 화면 끝에 붙인다.
	 *
	 * 이 패널은 모듈 기능이므로 스킨이 들고 있으면 안 된다 — 스킨 제작자가
	 * 매번 옮겨 담아야 하기 때문이다. 모듈이 자기 템플릿·CSS 로 직접 싣는다.
	 *
	 * @return void
	 */
	protected static function injectAdminPanel(): void
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || $logged_info->is_admin !== 'Y')
		{
			return;
		}

		$module_path = './modules/commerce/';
		\Context::loadFile([$module_path . 'tpl/css/adminpanel.css', 'all']);

		$template = new \Zittme\Framework\Template($module_path . 'tpl', 'adminpanel');
		\Context::addHtmlFooter($template->compile());
	}

	/**
	 * 이스케이프하되 <br> 태그만 살린다 (배너 제목·문구용).
	 */
	public static function escapeAllowBr(string $text): string
	{
		return str_ireplace(['&lt;br&gt;', '&lt;br /&gt;', '&lt;br/&gt;'], '<br />', escape($text, false));
	}

	/**
	 * 기획전 전용 페이지.
	 */
	public function dispPromotion()
	{
		$slug = trim((string)\Context::get('p'));
		$promo = $slug !== '' ? \Zittme\Modules\Commerce\Models\Promotion::get(0, $slug) : null;
		$logged_info = \Context::get('logged_info');
		$is_admin = $logged_info && $logged_info->is_admin === 'Y';
		if (!$promo || (($promo->status ?? 'Y') !== 'Y' && !$is_admin))
		{
			throw new \Rhymix\Framework\Exceptions\TargetNotFound;
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
		self::attachReviewStats($items);

		\Context::set('promo', $promo);
		\Context::set('promo_state', $state);
		\Context::set('promo_banner', \Zittme\Modules\Commerce\Models\Promotion::bannerOf($promo));
		\Context::set('items', $items);
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
		$this->setShopContext($items);
		\Context::setBrowserTitle($promo->title);
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('promo');
	}

	/**
	 * 상품 카드에 리뷰 수·평균 평점을 붙인다 (목록·홈 공용).
	 *
	 * @param array $items
	 */
	protected static function attachReviewStats(array $items): void
	{
		// 상품 카드가 지나는 공통 길목 — 다국어 문구를 여기서 한 번에 바꿔 둔다
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
			$stmt = \Rhymix\Framework\DB::getInstance()->query(
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
			// 통계 실패는 목록 노출을 막지 않는다
		}
	}

	/**
	 * 상품 목록 — 정렬·필터·품절 정책.
	 */
	public function dispCommerceList()
	{
		// 기획전 페이지 (?v=promo&p=슬러그)
		if (\Context::get('v') === 'promo')
		{
			return $this->dispPromotion();
		}

		// 쇼핑 홈 모드: 필터 없이 진입하면 배너·섹션 구성 홈을 보여준다 (v=list 로 목록 강제)
		$config = self::config();
		if (($config->shop_main ?? 'list') === 'home'
			&& \Context::get('v') !== 'list'
			&& !\Context::get('category') && trim((string)\Context::get('q')) === ''
			&& !\Context::get('sort') && (int)\Context::get('page') <= 1)
		{
			return $this->dispShopHome();
		}

		$args = new \stdClass;
		// 노출 상태: 판매중 + 품절(설정에 따라). 숨김·중지는 절대 노출하지 않는다
		$args->status_list = 'sale,soldout';

		$category_srl = (int)\Context::get('category');
		if ($category_srl > 0)
		{
			// 상위 카테고리 선택 시 하위 카테고리 상품도 함께 보여준다
			$args->category_srl_list = implode(',', self::categoryWithDescendants($category_srl));
		}
		$keyword = trim((string)\Context::get('q'));
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
		}

		// 섹션 페이지: f=recommend|new|popular|sale. 할인은 컬럼 비교라 넉넉히 받아 PHP 에서 거른다
		$filter = in_array(\Context::get('f'), ['recommend', 'new', 'popular', 'sale'], true) ? (string)\Context::get('f') : '';
		if ($filter === 'recommend')
		{
			$args->is_recommend = 'Y';
		}
		elseif ($filter === 'sale')
		{
			$args->list_count = 200;
		}

		// 정렬: new(기본) / popular / price_low / price_high — 인기 상품 페이지는 판매량순이 기본
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
				// 기본은 판매자가 정한 진열 순서. 목록에서 끌어 옮긴 순서가 그대로 보인다
				$sort = 'display';
				$args->sort_index = 'list_order';
				$args->order_type = 'asc';
		}

		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 24;

		$output = executeQuery('commerce.getItemList', $args);
		$items = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		// 판매기간 밖 상품은 노출에서 제외
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
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('list');
	}

	/**
	 * 쇼핑 홈 — 배너 슬라이더 + 추천/신상품/인기/할인 섹션.
	 * 섹션 데이터는 판매중 상품 풀에서 뽑는다 (별도 집계 테이블 없이 v1).
	 */
	protected function dispShopHome()
	{
		$config = self::config();
		$count = max(4, min(24, (int)($config->home_count ?? 8)));

		// 판매기간 안의 판매중 상품 풀
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

		// 진행 중 기획전 카드 (홈 섹션 + 상단 메뉴)
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
		// 제목·문구에 다국어 문구를 연결했으면 실제 값으로 바꿔 둔다
		foreach ($banners as &$bn_lang)
		{
			$bn_lang['title'] = LangModel::text($bn_lang['title'] ?? '');
			$bn_lang['text'] = LangModel::text($bn_lang['text'] ?? '');
		}
		unset($bn_lang);

		// 배경 스타일은 여기서 계산해서 스킨은 출력만 하게 한다 (bg_type: gradient|color|image)
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
			// 글자 색·그림자: 연한 배경에서도 읽히게 배너별로 지정할 수 있다
			$bn['text_color'] = (isset($bn['text_color']) && preg_match('/^#[0-9a-fA-F]+$/', (string)$bn['text_color'])) ? $bn['text_color'] : '#ffffff';
			$bn['shadow'] = ($bn['shadow'] ?? 'Y') === 'N' ? 'N' : 'Y';
			// 제목·문구에 <br> 만 허용 (그 외 태그는 이스케이프)
			$bn['title_html'] = self::escapeAllowBr((string)($bn['title'] ?? ''));
			$bn['text_html'] = self::escapeAllowBr((string)($bn['text'] ?? ''));
			$banners[$i] = $bn;
		}

		\Context::set('home_banners', $banners);
		\Context::set('home_sections', $sections);
		\Context::set('shop_categories', self::getActiveCategories());
		// 홈은 섹션마다 상품이 들어 있다
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
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('home');
	}

	/**
	 * 상품 상세.
	 */
	public function dispCommerceItem()
	{
		$item_srl = (int)\Context::get('item_srl');
		$item = ItemModel::get($item_srl);
		if (!$item || in_array($item->status, ['hidden', 'stop'], true))
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}

		// 조회수 (원자 증가)
		\Rhymix\Framework\DB::getInstance()->query(
			'UPDATE commerce_item SET view_count = view_count + 1 WHERE item_srl = ?', $item_srl
		);

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		// 브레드크럼: 전체 > 상위 카테고리 > 하위 카테고리
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

		// 리뷰·문의
		$reviews_output = executeQuery('commerce.getReviewList', (object)['item_srl' => $item_srl, 'list_count' => 20]);
		$reviews = ($reviews_output->toBool() && !empty($reviews_output->data)) ? (is_array($reviews_output->data) ? $reviews_output->data : [$reviews_output->data]) : [];
		$inquiries_output = executeQuery('commerce.getInquiryList', (object)['item_srl' => $item_srl, 'list_count' => 20]);
		$inquiries = ($inquiries_output->toBool() && !empty($inquiries_output->data)) ? (is_array($inquiries_output->data) ? $inquiries_output->data : [$inquiries_output->data]) : [];
		$is_admin = $logged_info && $logged_info->is_admin === 'Y';
		\Context::set('reviews', array_values(array_filter($reviews, function($r) { return !empty($r->review_srl); })));
		\Context::set('inquiries', array_values(array_filter($inquiries, function($r) { return !empty($r->inquiry_srl); })));
		\Context::set('is_logged', $member_srl > 0);
		\Context::set('is_shop_admin', $is_admin);
		// 관리자도 예외 없이 구매확정한 상품만 리뷰 가능
		// 리뷰를 아직 안 쓴 확정 주문이 하나라도 있으면 작성 가능 (주문건 단위)
		\Context::set('can_review', Review::canReviewNow($member_srl, $item_srl));

		// 적립 혜택 표기용 적립률 (등급 적립률 > 기본 설정)
		\Context::set('credit_rate', \Zittme\Modules\Commerce\Models\Grade::creditRateFor($member_srl));

		LangModel::textAll([$item], ['item_name', 'summary']);
		\Context::set('item', $item);
		$shop_options = ItemModel::getOptions($item_srl, true);
		// 조합형 옵션 축 — 스킨이 모델을 직접 부르지 않도록 여기서 풀어 넘긴다.
		// 방식을 직접 입력으로 되돌린 상품은 축 정의가 남아 있어도 쓰지 않는다.
		$shop_axes = ($item->option_mode ?? 'single') === 'combo'
			? \Zittme\Modules\Commerce\Models\Combo::axes($item->option_axes ?? '')
			: [];
		// 구매 화면 대조는 자리 번호로 한다 (표시 글자는 언어마다 달라진다)
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
		// 조합 옵션 이름은 축 값에서 다시 만든다. 저장된 이름은 조합을 만든 시점의
		// 글자라, 뒤늦게 축에 다국어 코드를 연결해도 그대로 남는다.
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
		\Context::set('adult_ok', ($item->is_adult ?? 'N') !== 'Y' || Order::isAdultVerified($member_srl));
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
		$this->setShopContext([$item]);
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('item');
	}

	/**
	 * 장바구니.
	 */
	public function dispCommerceCart()
	{
		$resolved = CartModel::resolve();
		\Context::set('cart', $resolved);
		\Context::set('ship_fee', CartModel::calcShipFee($resolved));
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setShopContext();
		$this->setTemplateFile('cart');
	}

	/**
	 * 주문서.
	 */
	public function dispCommerceCheckout()
	{
		$resolved = CartModel::resolve();
		$valid = array_values(array_filter($resolved->items, function($e) { return !$e->blocked; }));
		if (!count($valid))
		{
			return new \BaseObject(-1, 'msg_shop_cart_empty');
		}

		$logged_info = \Context::get('logged_info');
		$ship_fee = CartModel::calcShipFee($resolved);

		// 회원 보유 쿠폰 (지금 주문에 적용 가능한 것만)
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		// 저장된 배송지 (회원)
		$my_addresses = [];
		if ($member_srl > 0)
		{
			$addr_output = executeQuery('commerce.getAddressList', (object)['member_srl' => $member_srl]);
			if ($addr_output->toBool() && !empty($addr_output->data))
			{
				foreach (is_array($addr_output->data) ? $addr_output->data : [$addr_output->data] as $addr)
				{
					if (!empty($addr->address_srl))
					{
						$my_addresses[] = $addr;
					}
				}
			}
		}
		\Context::set('my_addresses', $my_addresses);
		\Context::set('my_coupons', \Zittme\Modules\Commerce\Models\Coupon::listUsableForMember($member_srl, $resolved->item_total));
		\Context::set('credit_balance', \Zittme\Modules\Commerce\Models\Credit::balanceOf($member_srl));

		// 표시 통화 — 기준 통화가 기본이다. 기준이 KRW 인 상점의 외화 병행 표시일 때만
		// 주문 생성(procCommerceOrder)과 같은 규칙으로 금액을 재계산한다.
		// 병행 통화에서는 쿠폰·적립금을 쓸 수 없어 화면에서 숨긴다.
		$base_currency = \Zittme\Modules\Commerce\Models\Money::base();
		$fx_currency = \Zittme\Modules\Commerce\Models\Money::current();
		$fx_rate = \Zittme\Modules\Commerce\Models\Money::rate($fx_currency);
		if ($fx_currency !== $base_currency && $fx_rate > 0)
		{
			$fx_item_total = 0;
			$fx_ok = true;
			foreach ($valid as $entry)
			{
				$fx_unit = \Zittme\Modules\Commerce\Models\Item::effectivePriceIn($entry->item, $fx_currency);
				$fx_add = $entry->option ? \Zittme\Modules\Commerce\Models\Money::convertMinor(max(0, (int)($entry->option->price_add ?? 0)), $fx_currency) : 0;
				if ($fx_unit < 0 || $fx_add < 0)
				{
					$fx_ok = false;
					break;
				}
				$entry->subtotal = ($fx_unit + $fx_add) * $entry->qty;
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
				// 병행 통화로 팔 수 없는 상품이 있으면 이 주문서는 기준 통화로 되돌린다
				$fx_currency = $base_currency;
			}
		}
		else
		{
			$fx_currency = $base_currency;
		}
		\Context::set('shp_currency', $fx_currency);
		\Context::set('shp_base_currency', $base_currency);
		\Context::set('shp_fx_rate', $fx_currency === $base_currency ? 1 : $fx_rate);
		\Context::set('shp_fx_zero_decimal', \Zittme\Modules\Commerce\Models\Money::isZeroDecimal($fx_currency));

		\Context::set('cart', $resolved);
		\Context::set('ship_fee', $ship_fee);
		\Context::set('payment_price', $resolved->item_total + $ship_fee);
		\Context::set('is_member', $logged_info && $logged_info->member_srl ? true : false);
		\Context::set('pay_available', self::isPayAvailable());
		\Context::set('shop_config', self::config());
		\Context::set('shop_address_mode', AddressModel::mode());
		\Context::set('shop_need_country', AddressModel::needsCountry());
		\Context::set('shop_countries', AddressModel::countries());

		// 행정구역은 목록에서 골라야 배송비 규칙과 어긋나지 않는다. 목록이 있는 나라만 넘긴다
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

	/**
	 * 주문 결과·상세.
	 */
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

		// 접근 제어: 관리자 / 본인 / 비회원(비밀번호) / 결제 복귀 직후 5분
		$authorized = $is_admin;
		if (!$authorized && (int)$order->member_srl > 0)
		{
			$authorized = $member_srl === (int)$order->member_srl;
		}
		elseif (!$authorized)
		{
			$gp = (string)\Context::get('gp');
			$authorized = $gp !== '' && !empty($order->guest_password)
				&& \Rhymix\Framework\Password::checkPassword($gp, $order->guest_password);
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

		\Context::set('order', $order);
		\Context::set('order_items', OrderModel::getItems((int)$order->order_srl));
		$order_sellers = OrderModel::getSellerOrders((int)$order->order_srl);

		// 배송 조회: 저장된 조회 결과(이력 포함)를 보여준다 — 페이지 열람이 API 호출을 유발하지 않는다
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

		// 구매확정 주문: 이 주문에서 아직 리뷰를 안 쓴 상품 (리뷰는 주문건 단위)
		$unreviewed = [];
		if ($member_srl > 0 && OrderModel::displayStatus($order, $order_sellers) === 'confirmed')
		{
			$unreviewed = \Zittme\Modules\Commerce\Controllers\Review::unreviewedItems($member_srl, (int)$order->order_srl);
		}
		\Context::set('unreviewed_items', $unreviewed);
		\Context::set('order_sellers', $order_sellers);
		\Context::set('display_status', OrderModel::displayStatus($order, $order_sellers));
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('result');
	}

	/**
	 * 내 주문 (회원) / 비회원 조회 폼.
	 */
	public function dispCommerceMyOrders()
	{
		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		\Zittme\Modules\Commerce\Models\Tracking::syncShipping();

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
						// 리뷰 작성 버튼: 이 주문건에 아직 리뷰 안 쓴 상품이 있을 때만
						$row->needs_review = $row->display_status === 'confirmed'
							&& count(\Zittme\Modules\Commerce\Controllers\Review::unreviewedItems($member_srl, (int)$row->order_srl)) > 0;
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
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('my');
	}
}
