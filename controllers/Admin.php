<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Address as AddressModel;
use Zittme\Modules\Commerce\Models\Badge as BadgeModel;
use Zittme\Modules\Commerce\Models\Combo as ComboModel;
use Zittme\Modules\Commerce\Models\Config as ConfigModel;
use Zittme\Modules\Commerce\Models\Grade as GradeModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Lang as LangModel;
use Zittme\Modules\Commerce\Models\Money as MoneyModel;
use Zittme\Modules\Commerce\Models\Notify as NotifyModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;
use Zittme\Modules\Commerce\Models\Region as RegionModel;
use Zittme\Modules\Commerce\Models\Stats as StatsModel;
use Zittme\Modules\Commerce\Models\Tax as TaxModel;

/**
 * 전용 운영 화면 (대시보드 + 업무 화면 세트).
 */
class Admin extends Base
{
	/**
	 * 설정 저장 허용 키.
	 */
	public const CONFIG_FIELDS = [
		'enabled', 'market_mode', 'code_prefix', 'allow_guest', 'pending_minutes',
		'default_ship_fee', 'free_ship_over', 'claim_days', 'item_sticky', 'sweettracker_api_key',
		'shop_main', 'category_layout', 'home_show_recommend', 'home_show_new',
		'home_show_popular', 'home_show_sale', 'home_count', 'home_banners', 'ship_extra_zones',
		'credit_rate', 'credit_min_use', 'review_credit_text', 'review_credit_photo',
		'privacy_text', 'privacy_version', 'retention_days',
		'biz_name', 'biz_ceo', 'biz_number', 'biz_address', 'biz_tel', 'biz_note', 'biz_logo',
		'biz_tax_mode', 'vat_rate', 'price_includes_tax', 'base_country', 'allow_overseas', 'address_mode',
		'use_phone_cc', 'require_state', 'use_coupon', 'use_credit', 'auto_confirm_days',
		'currencies', 'currency_fallback',
		'notify_admin', 'notify_admin_email', 'notify_admin_group',
		'notify_admin_new_order', 'notify_admin_claim',
		'notify_buyer_received', 'notify_buyer_paid', 'notify_buyer_shipping',
		'notify_buyer_delivered', 'notify_buyer_claim_done',
	];

	// 다국어 버튼으로 문구를 연결할 수 있는 설정 항목
	protected const LANG_CONFIG_FIELDS = ['privacy_text', 'biz_name', 'biz_address', 'biz_note'];

	protected const BOOLEAN_FIELDS = ['enabled', 'allow_guest', 'notify_admin', 'item_sticky',
		'home_show_recommend', 'home_show_new', 'home_show_popular', 'home_show_sale'];
	// 소수점 2자리 허용 (적립률 0.00~100.00%)
	protected const FLOAT_FIELDS = ['credit_rate' => [0, 100]];
	protected const INT_FIELDS = [
		'pending_minutes' => [10, 1440],
		'claim_days' => [0, 90],
		'retention_days' => [0, 3650],
		'home_count' => [4, 24],
	];
	// 금액 설정 — 기준 통화 최소단위로 저장 (소수부 통화는 100.50 형태 입력 허용)
	protected const MONEY_FIELDS = [
		'default_ship_fee' => [0, 100000000],
		'free_ship_over' => [0, 10000000000],
		'credit_min_use' => [0, 100000000],
		'review_credit_text' => [0, 10000000],
		'review_credit_photo' => [0, 10000000],
	];

	/**
	 * 공통 렌더.
	 */
	protected function renderView(string $tab, string $file): void
	{
		\Context::set('shop_tab', $tab);
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->module_path . 'views/admin/');
		$this->setTemplateFile($file);
	}

	/**
	 * 카테고리 전체 (평면, list_order 순).
	 *
	 * @return array<int, object>
	 */
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

		// 트리 순서(상위 → 하위)로 정렬하고 depth 를 붙인 맵을 돌려준다
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
		// 연결된 다국어 문구는 실제 값으로 보여준다 (편집 시 코드는 위젯이 따로 들고 있다)
		LangModel::textAll(array_values($map), ['title']);
		return $map;
	}

	// 화면

	/**
	 * 대시보드.
	 */
	public function dispCommerceAdminDashboard()
	{
		OrderModel::expireStalePending();
		$instance = self::getDefaultInstance();
		\Context::set('shop_mid', $instance ? $instance->mid : self::DEFAULT_MID);

		// 개설 체크리스트 — 결제수단, 기본 설정, 카테고리, 첫 상품
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

		$this->renderView('dashboard', 'dashboard');
	}

	/**
	 * 상품 관리 목록.
	 */
	/**
	 * 재고 관리 — 상품·옵션 재고 현황과 입고/출고/손실 처리, 이동 로그.
	 * 재고 수량 변경은 이 화면에서만 한다 (상품 편집에는 사용 여부만 둔다).
	 */
	public function dispCommerceAdminStock()
	{
		$args = new \stdClass;
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 50;
		$args->sort_index = 'item_srl';
		$args->order_type = 'desc';
		$keyword = trim((string)\Context::get('f_keyword'));
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
		}
		$output = executeQueryArray('commerce.getItemList', $args);
		$items = LangModel::textAll($output->data ?: [], ['item_name']);
		$options_map = [];
		foreach ($items as $stock_item)
		{
			$stock_options = ItemModel::getOptions((int)$stock_item->item_srl);
			// 연결된 다국어 문구는 실제 값으로 보여준다
			LangModel::textAll($stock_options, ['option_label']);
			// 조합 옵션 이름은 축 값에서 다시 만든다 (저장된 이름은 만든 시점 글자로 굳는다)
			foreach ($stock_options as $stock_option)
			{
				$stock_option->option_label = ComboModel::optionLabel($stock_item, $stock_option);
			}
			$options_map[(int)$stock_item->item_srl] = $stock_options;
		}
		\Context::set('stock_items', $items);
		\Context::set('stock_options_map', $options_map);
		\Context::set('stock_page_navigation', $output->page_navigation);

		$log_item = (int)\Context::get('log_item');
		$log_output = \Zittme\Modules\Commerce\Models\Stock::getLogs($log_item, max(1, (int)\Context::get('log_page')));
		\Context::set('stock_logs', $log_output->data ?: []);
		\Context::set('stock_log_item', $log_item);
		\Context::set('stock_log_navigation', $log_output->page_navigation);

		$this->renderView('stock', 'stock');
	}

	/**
	 * 기획전 목록 + 편집.
	 */
	public function dispCommerceAdminPromotions()
	{
		$promotions = \Zittme\Modules\Commerce\Models\Promotion::listAll();
		\Context::set('promotions', $promotions);
		\Context::set('promo_now', self::now());

		// 편집 대상 (promo_srl 지정 시)
		$edit_srl = (int)\Context::get('promo_srl');
		$edit = $edit_srl > 0 ? \Zittme\Modules\Commerce\Models\Promotion::get($edit_srl) : null;
		\Context::set('promo_edit', $edit);
		\Context::set('promo_edit_items', $edit ? \Zittme\Modules\Commerce\Models\Promotion::itemSrlsOf((int)$edit->promo_srl) : []);

		// 상품 선택용 전체 상품 (판매·품절)
		$output = executeQueryArray('commerce.getItemList', (object)['status_list' => 'sale,soldout', 'list_count' => 500, 'sort_index' => 'item_srl', 'order_type' => 'desc']);
		$promo_items = ($output->toBool() && !empty($output->data)) ? $output->data : [];
		// 다국어 코드는 서버에서 푼다. 템플릿이 이스케이프한 뒤에는 코어 치환이 걸리지 않는다
		LangModel::textAll($promo_items, ['item_name']);
		\Context::set('promo_all_items', $promo_items);

		$this->renderView('promotions', 'promotions');
	}

	/**
	 * 기획전 저장 (신규/수정 + 상품 매핑 동기화).
	 */
	public function procCommerceAdminInsertPromotion()
	{
		$promo_srl = (int)\Context::get('promo_srl');
		$title = mb_substr(trim((string)\Context::get('title')), 0, 120);
		if ($title === '')
		{
			return new \BaseObject(-1, lang('commerce.admin_msg_1'));
		}

		// 슬러그: 미입력 시 자동 생성, 영문/숫자/하이픈만
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
			$dates[$k] = strlen($raw) >= 8 ? str_pad(substr($raw, 0, 14), 14, '0') : '';
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

		// 상품 매핑 (순서 포함 JSON 배열)
		$item_srls = json_decode((string)\Context::get('item_srls'), true);
		if (is_array($item_srls))
		{
			\Zittme\Modules\Commerce\Models\Promotion::syncItems($promo_srl, $item_srls);
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminPromotions'));
	}

	/**
	 * 기획전 삭제.
	 */
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

	/**
	 * 재고 조정 처리.
	 */
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

	/**
	 * 프론트 관리 플로팅 — 메인 형태·카테고리 배치·배너를 일괄 저장한다.
	 */
	public function procCommerceAdminSaveFront()
	{
		$config = \ModuleModel::getModuleConfig('commerce') ?: new \stdClass;

		$config->shop_main = \Context::get('shop_main') === 'home' ? 'home' : 'list';
		$config->category_layout = \Context::get('category_layout') === 'side' ? 'side' : 'top';

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

	/**
	 * 프론트 배너 이미지 업로드 — 파일을 받아 URL 을 JSON 으로 돌려준다.
	 */
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

	/**
	 * 리뷰·문의 관리 — 답변 등록과 삭제.
	 */
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

		$inquiry_output = executeQueryArray('commerce.getInquiryList', (object)[
			'list_count' => 30,
			'page' => max(1, (int)\Context::get('i_page')),
		]);
		$inquiries = $inquiry_output->data ?: [];
		// 미답변만 보기 필터 (DB 조건 없이 화면에서 거른다)
		if (\Context::get('f_unanswered') === 'Y')
		{
			$inquiries = array_values(array_filter($inquiries, function($q) { return empty($q->answer); }));
		}
		\Context::set('qna_inquiries', $inquiries);
		\Context::set('qna_inquiry_navigation', $inquiry_output->page_navigation);
		\Context::set('qna_unanswered', \Context::get('f_unanswered') === 'Y');

		$this->renderView('qna', 'qna');
	}

	/**
	 * 리뷰 답변 등록·수정 (빈 값이면 답변 삭제).
	 */
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

	/**
	 * 문의 답변 등록·수정 (빈 값이면 답변 삭제).
	 */
	public function procCommerceAdminInquiryAnswer()
	{
		$inquiry_srl = (int)\Context::get('inquiry_srl');
		if ($inquiry_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		// 빈 값이면 '' 로 지운다 — null 은 쿼리 빌더가 컬럼을 빼버려 SET 절 없는 UPDATE(1064)가 된다
		$answer = trim((string)\Context::get('answer'));
		// 알림 대상을 알아야 하므로 갱신 전에 원글을 집어 둔다
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
		}
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 20;
		// 쇼핑몰에 보이는 차례와 같게 둔다. 여기서 끌어 옮긴 순서가 곧 진열 순서다
		$args->sort_index = 'list_order';
		$args->order_type = 'asc';

		$output = executeQuery('commerce.getItemList', $args);
		$items = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		// 목록은 연결된 다국어 문구를 실제 값으로 보여준다 (편집 화면은 위젯이 따로 처리)
		\Context::set('items', LangModel::textAll($items, ['item_name', 'summary']));
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('categories', self::getCategories());
		\Context::set('filters', (object)['status' => $status, 'category' => $category_srl, 'keyword' => $keyword]);
		$this->renderView('items', 'items');
	}

	/**
	 * 상품 편집 (신규/수정) — 옵션까지 한 화면.
	 */
	public function dispCommerceAdminItemEdit()
	{
		$item_srl = (int)\Context::get('item_srl');
		$item = null;
		$options = [];
		if ($item_srl > 0)
		{
			$item = ItemModel::get($item_srl);
			if (!$item)
			{
				return new \BaseObject(-1, 'msg_shop_no_item');
			}
			$options = ItemModel::getOptions($item_srl);
		}

		// 다통화 판매 — 통화 목록과 이 상품의 외화 가격 (화면 표기용 소수 값으로 변환)
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

		// 기획전 노출 체크박스 (개설된 기획전 + 이 상품의 소속)
		\Context::set('item_promotions', \Zittme\Modules\Commerce\Models\Promotion::listAll());
		\Context::set('item_promo_srls', $item_srl > 0 ? \Zittme\Modules\Commerce\Models\Promotion::promoSrlsOfItem($item_srl) : []);

		// 신규 상품도 에디터 첨부가 귀속될 srl 을 미리 발급한다 (저장 시 이 srl 로 INSERT)
		$editor_target_srl = $item_srl > 0 ? $item_srl : getNextSequence();
		\Context::set('editor_target_srl', $editor_target_srl);

		// 상세설명 — 코어 zittme 에디터(editor 모듈 설정 연동). 일반 textarea 를 쓰지 않는다
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
		// 연결된 다국어 문구는 실제 값으로 보여준다 (편집 시 코드는 위젯이 따로 들고 있다)
		LangModel::textAll($options, ['option_label']);
		// 조합 옵션 이름은 축 값에서 다시 만든다. 저장된 이름은 조합을 만든 시점의
		// 글자라, 뒤늦게 축에 다국어 코드를 연결해도 그대로 남는다.
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
		\Context::set('options', $options);
		\Context::set('categories', self::getCategories());
		\Context::set('badges', BadgeModel::getList(true));
		$this->renderView('items', 'item_edit');
	}

	/**
	 * 카테고리 관리.
	 */
	public function dispCommerceAdminCategories()
	{
		\Context::set('categories', array_values(self::getCategories()));
		$this->renderView('categories', 'categories');
	}

	/**
	 * 카테고리 순서·계층 저장.
	 *
	 * 화면에서 끌어 옮긴 결과를 [번호, 상위번호] 목록으로 받아 그대로 반영한다.
	 * 순서는 목록에 나온 차례를 1 부터 매긴다.
	 */
	public function procCommerceAdminSortCategories()
	{
		$raw = (string)\Context::get('tree');
		$rows = json_decode($raw, true);
		if (!is_array($rows) || !count($rows))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 자기 자신이나 자기 하위를 상위로 삼으면 트리가 끊긴다. 화면에서도 막지만 여기서 한 번 더 본다
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

	/**
	 * $maybe_child 가 $ancestor 의 하위인가 (순환 방지용).
	 *
	 * @param int $maybe_child
	 * @param int $ancestor
	 * @param array<int,int> $parents 번호 => 상위번호
	 * @return bool
	 */
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

	/**
	 * 뱃지 관리 — 상품 카드에 붙일 표시를 직접 만든다.
	 */
	public function dispCommerceAdminBadges()
	{
		\Context::set('badges', BadgeModel::getList());
		$this->renderView('badges', 'badges');
	}

	/**
	 * 뱃지 등록·수정.
	 */
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

	/**
	 * 뱃지 삭제. 상품에 붙어 있던 값은 화면에서 자동으로 무시된다.
	 */
	public function procCommerceAdminDeleteBadge()
	{
		$badge_srl = (int)\Context::get('badge_srl');
		if ($badge_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('commerce.deleteBadge', (object)['badge_srl' => $badge_srl]);

		$this->setMessage('success_deleted');
		// 뱃지 화면은 관리자와 전용 콘솔 두 곳에서 쓰인다 — 지운 자리로 돌아간다
		$return = trim((string)\Context::get('success_return_url'));
		$this->setRedirectUrl($return !== '' ? $return : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminBadges', 'badge_srl', ''));
	}

	/**
	 * 색상값 정리. #RGB · #RRGGBB 형식만 통과시킨다.
	 *
	 * @param string $value
	 * @return string
	 */
	protected static function filterColor(string $value): string
	{
		$value = trim($value);
		return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : '';
	}

	/**
	 * 주문 관리 목록.
	 */
	public function dispCommerceAdminOrders()
	{
		OrderModel::expireStalePending();
		\Zittme\Modules\Commerce\Models\Tracking::syncShipping();

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
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 20;

		$output = executeQuery('commerce.getOrderList', $args);
		$orders = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		// 하위주문 상태(배송 단계)를 함께 표시
		$seller_map = [];
		foreach ($orders as $o)
		{
			$sellers = OrderModel::getSellerOrders((int)$o->order_srl);
			$seller_map[(int)$o->order_srl] = count($sellers) ? $sellers[0] : null;
		}

		\Context::set('orders', $orders);
		\Context::set('seller_map', $seller_map);
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('filters', (object)['status' => $status, 'keyword' => $keyword]);
		$this->renderView('orders', 'orders');
	}

	/**
	 * 주문 상세 — 품목·배송지·이력·클레임까지 한 화면.
	 */
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

		// 짓미페이 결제 정보 — 결제번호 표기와 입금확인 연동
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

	/**
	 * 다국어 문구를 연결했으면 코어 규약값('$user_lang->코드')을, 아니면 입력한 글자를 돌려준다.
	 *
	 * @param string $field 폼 필드 이름
	 * @param string $fallback 다국어를 안 쓸 때 저장할 값
	 * @return string
	 */
	protected static function langValue(string $field, string $fallback): string
	{
		$code = LangModel::filterCode((string)\Context::get($field . '_langcode'));
		return $code !== '' ? LangModel::toValue($code) : $fallback;
	}

	/**
	 * 다국어 코드 목록 — 이미 만들어 둔 코드를 골라 쓰기 위한 검색.
	 */
	public function procCommerceAdminGetLangCodes()
	{
		$rows = [];
		foreach (LangModel::search((string)\Context::get('keyword'), 40) as $row)
		{
			$rows[] = ['code' => $row->code, 'value' => $row->value];
		}
		$this->add('codes', $rows);
	}

	/**
	 * 다국어 코드 저장 — 코어 lang 테이블에 그대로 쓴다.
	 */
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

	/**
	 * 다국어 코드 하나의 언어별 값.
	 */
	public function procCommerceAdminGetLangCode()
	{
		$code = LangModel::filterCode((string)\Context::get('code'));
		$this->add('code', $code);
		$this->add('values', LangModel::values($code));
	}

	/**
	 * CSV 의 주소 칸. 우편번호와 주소2 는 각각 다른 칸에 담기므로 여기서는 뺀다.
	 *
	 * @param object $address
	 * @return string
	 */
	protected static function csvAddressLine(object $address): string
	{
		$country = strtoupper((string)($address->country ?? '')) ?: AddressModel::baseCountry();
		$parts = [(string)($address->address1 ?? '')];

		// 한국 주소는 시·도가 주소1 에 들어 있다. 그 밖의 나라는 칸이 따로다
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

	/**
	 * 주문 CSV 내보내기 — 택배사 송장 업로드용.
	 *
	 * 선택한 주문(order_srls)이 있으면 그것만, 없으면 현재 검색 조건 전체를 내린다.
	 * 엑셀에서 한글이 깨지지 않도록 UTF-8 BOM 을 붙인다.
	 * standalone 이라 코어의 admin 권한 검사를 타지 않으므로 여기서 직접 확인한다.
	 */
	public function dispCommerceAdminExportOrders()
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || $logged_info->is_admin !== 'Y')
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
			// 내보내기는 한 화면 분량이 아니라 조건에 맞는 전체가 대상이다
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
		$seller_labels = ['pending' => lang('commerce.st_order_pending'), 'paid' => lang('commerce.st_sel_paid'), 'preparing' => lang('commerce.st_sel_preparing'), 'shipping' => lang('commerce.st_sel_shipping'), 'delivered' => lang('commerce.st_sel_delivered'), 'cancelled' => lang('commerce.st_order_cancelled'), 'refunded' => lang('commerce.st_sel_refunded')];

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
				(string)(int)$order->payment_price,
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

	/**
	 * 주문서(거래명세서) 인쇄 화면.
	 *
	 * 사이트·관리자 껍데기 없이 A4 로 뽑는 용도라 layout 'none' 으로 띄운다.
	 * order_srl 하나 또는 order_srls(쉼표) 여러 건을 받아 한 창에 이어 출력한다.
	 * standalone 이라 코어의 admin 권한 검사를 타지 않으므로 여기서 직접 확인한다.
	 */
	public function dispCommerceAdminOrderInvoice()
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || $logged_info->is_admin !== 'Y')
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
		// 한 번에 너무 많이 뽑으면 브라우저 인쇄가 버티지 못한다
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
			// 세금 컬럼이 생기기 전 주문은 스냅샷이 비어 있다 — 상품의 현재 설정으로 메운다
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

	/**
	 * 취소·반품 관리.
	 */
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

		// 주문 코드 매핑
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

	/**
	 * 쿠폰 관리.
	 */
	public function dispCommerceAdminCoupons()
	{
		$coupons = \Zittme\Modules\Commerce\Models\Coupon::getList();

		// 발급 수 매핑
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

	/**
	 * 쿠폰 생성·수정.
	 */
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

	/**
	 * 쿠폰 삭제 (발급 이력은 보존).
	 */
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

	/**
	 * 회원에게 쿠폰 발급 — 아이디 또는 이메일로.
	 */
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

	/**
	 * 적립금 관리 — 회원 조회·수동 조정.
	 */
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

		// 최근 원장 (전체) — 회원 번호 대신 닉네임을 보여 주기 위해 회원 정보를 붙인다
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

	/**
	 * 적립금 수동 조정 (+지급 / -회수).
	 */
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

	/**
	 * 구매 등급 관리.
	 */
	public function dispCommerceAdminGrades()
	{
		\Context::set('grades', GradeModel::getList());
		\Context::set('grade_coupons', \Zittme\Modules\Commerce\Models\Coupon::getList());
		\Context::set('grade_groups', \MemberModel::getGroups());
		$this->renderView('grades', 'grades');
	}

	/**
	 * 등급 생성·수정.
	 */
	public function procCommerceAdminInsertGrade()
	{
		$grade_srl = (int)\Context::get('grade_srl');
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 등급별 상품 할인 (정액/정률). 정률은 0~100 으로 제한
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

		// 연동을 걸거나 바꾸면 이미 이 등급인 회원도 함께 옮긴다. 다음 구매까지 기다릴 수 없다
		GradeModel::applyGroupToMembers((int)$args->grade_srl, $old_group, $group_srl);

		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminGrades'));
	}

	/**
	 * 등급 삭제.
	 */
	public function procCommerceAdminDeleteGrade()
	{
		$grade_srl = (int)\Context::get('grade_srl');
		if ($grade_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		// 등급이 사라지면 그 등급으로 넣어 둔 회원도 그룹에서 뺀다
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

	/**
	 * 통계 조회 기간 — 지정이 없으면 최근 30일.
	 *
	 * @return array{0: string, 1: string}
	 */
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

	/**
	 * 통계 CSV 내보내기 — 화면에 보이는 표를 그대로 내린다.
	 */
	public function dispCommerceAdminExportStats()
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || $logged_info->is_admin !== 'Y')
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
				$rows[] = [$r->item_name, (string)$r->qty, (string)$r->orders, (string)$r->sales];
			}
		}
		elseif ($tab === 'region')
		{
			$rows = [[lang('commerce.csv_region'), lang('commerce.csv_order_count'), lang('commerce.csv_revenue')]];
			foreach (StatsModel::byRegion($from, $to) as $r)
			{
				$rows[] = [$r->region, (string)$r->orders, (string)$r->sales];
			}
		}
		else
		{
			$rows = [[lang('commerce.csv_period'), lang('commerce.csv_order_count'), lang('commerce.csv_revenue')]];
			foreach (StatsModel::series($from, $to, $unit) as $r)
			{
				$rows[] = [$r->label, (string)$r->orders, (string)$r->sales];
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

	/**
	 * 설정.
	 */
	/**
	 * 축 정의가 바뀌면 기존 조합 옵션의 축 이름·값을 새 정의로 옮긴다.
	 *
	 * 조합은 축의 차례대로 만들어지므로 자리 번호로 짝을 맞춘다.
	 * 축 개수나 값 개수가 달라졌으면 짝을 확신할 수 없어 손대지 않는다
	 * (그때는 관리자가 조합 만들기를 다시 눌러야 한다).
	 *
	 * @param int $item_srl
	 * @param mixed $old_axes 저장 전 축 정의
	 * @param mixed $new_axes 저장할 축 정의
	 * @return void
	 */
	protected static function remapCombos(int $item_srl, $old_axes, $new_axes): void
	{
		$old = ComboModel::axes($old_axes);
		$new = ComboModel::axes($new_axes);
		if (!count($old) || count($old) !== count($new))
		{
			return;
		}

		// 축 이름과 값의 옛 표기 => 새 표기 대응표
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

		// 지역 추가 배송비는 기준 통화 최소단위로 저장돼 있다. 입력칸에는 사람이 쓰는 표기로 보여 준다
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

		// 스킨 — 테마 패키지(레이아웃+커머스+짓미페이+게시판 스킨) 배포 규약의 일부
		$instance = self::getDefaultInstance();
		$module_info = $instance ? \ModuleModel::getModuleInfoByMid($instance->mid) : null;
		\Context::set('shop_instance', $module_info);
		\Context::set('shop_skins', \ModuleModel::getSkins(\RX_BASEDIR . 'modules/commerce') ?: []);
		\Context::set('shop_mskins', \ModuleModel::getSkins(\RX_BASEDIR . 'modules/commerce', 'm.skins') ?: []);

		// 레이아웃 — 스킨과 한자리에서 고르게 둔다. 모듈 관리 화면까지 찾아가지 않아도 된다
		$layout_model = getModel('layout');
		\Context::set('shop_layouts', $layout_model->getLayoutList(0, 'P') ?: []);
		\Context::set('shop_mlayouts', $layout_model->getLayoutList(0, 'M') ?: []);
		$this->renderView('config', 'config');
	}

	/**
	 * 스킨 저장 — 기본 인스턴스(mid)의 skin/mskin 갱신.
	 */
	public function procCommerceAdminUpdateSkin()
	{
		$instance = self::getDefaultInstance();
		$module_info = $instance ? \ModuleModel::getModuleInfoByMid($instance->mid) : null;
		if (!$module_info || empty($module_info->module_srl))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 테마 결합명('테마|@|스킨')도 저장할 수 있어야 한다
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

		// 레이아웃 — -1 은 사이트 기본, -2 는 모바일에서 PC 설정을 따름
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

	// 처리

	/**
	 * 대표 이미지 업로드 (내용 검사 포함).
	 *
	 * @param int $item_srl
	 * @param string $field
	 * @return ?string
	 */
	/**
	 * 새 상품 등록 화면에서 함께 보낸 옵션들을 등록한다.
	 *
	 * 상품을 먼저 저장해야만 옵션을 넣을 수 있던 흐름을 없애기 위한 것으로,
	 * 이름이 비어 있는 줄은 건너뛴다. 추가상품 가격은 음수를 받지 않는다.
	 *
	 * @param int $item_srl
	 * @return void
	 */
	protected function insertPendingOptions(int $item_srl): void
	{
		$order = 1;
		foreach (['basic', 'extra'] as $type)
		{
			$labels = (array)\Context::get('new_option_label_' . $type);
			$prices = (array)\Context::get('new_option_price_' . $type);
			$stocks = (array)\Context::get('new_option_stock_' . $type);

			foreach ($labels as $i => $label)
			{
				$label = trim((string)$label);
				if ($label === '')
				{
					continue;
				}
				$price = MoneyModel::inputToMinor($prices[$i] ?? 0);
				if ($type === 'extra' && $price < 0)
				{
					$price = 0;
				}
				executeQuery('commerce.insertOption', (object)[
					'option_srl' => getNextSequence(),
					'item_srl' => $item_srl,
					'option_label' => mb_substr($label, 0, 120),
					'option_type' => $type,
					'price_add' => $price,
					'stock' => max(0, (int)($stocks[$i] ?? 0)),
					'sku' => '',
					'list_order' => $order++,
					'status' => 'sale',
					'regdate' => self::now(),
				]);
			}
		}

		if ($order > 1)
		{
			executeQuery('commerce.updateItem', (object)[
				'item_srl' => $item_srl,
				'has_options' => 'Y',
				'last_update' => self::now(),
			]);
		}
	}

	/**
	 * 상품 이미지 즉시 업로드.
	 *
	 * 편집 화면에서 사진을 고르는 즉시 서버에 올려 미리보기를 보여 준다.
	 * 저장을 누르기 전에는 목록(images_json)에만 담겨 있고, 저장 시 상품에 확정된다.
	 */
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

	/**
	 * 상품 이미지 목록 즉시 반영.
	 *
	 * 올리기·삭제·대표 변경을 저장 버튼 없이 바로 상품에 적용한다.
	 * 아직 저장 전인 새 상품은 반영할 행이 없으므로 화면 값만 유지하고 넘어간다.
	 */
	public function procCommerceAdminSaveItemImages()
	{
		$item_srl = (int)\Context::get('item_srl');
		if ($item_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 우리 서버에 올린 파일만 받아들인다
		$decoded = json_decode((string)\Context::get('images_json'), true);
		$images = [];
		if (is_array($decoded))
		{
			foreach ($decoded as $url)
			{
				if (is_string($url) && strpos($url, \RX_BASEURL . 'files/') === 0)
				{
					$images[] = $url;
				}
			}
		}
		$images = array_slice($images, 0, 7);

		if (!ItemModel::get($item_srl))
		{
			// 아직 등록 전이면 저장할 곳이 없다. 폼 저장 때 함께 반영된다
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

	/**
	 * 상품 이미지 다중 업로드 (image_files[]). 검증은 saveImage 와 동일.
	 *
	 * @param int $item_srl
	 * @param int $limit 저장 가능한 남은 장수
	 * @return array 저장된 URL 목록
	 */
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

	/**
	 * 상품 저장 (신규/수정/복제).
	 */
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

		// 판매기간: datetime-local(YYYY-MM-DDTHH:MM) → 14자리
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
			// 다국어 문구를 연결했으면 코어 규약대로 '$user_lang->코드' 를 저장한다
			'item_name' => LangModel::filterCode((string)\Context::get('item_name_langcode')) !== ''
				? LangModel::toValue((string)\Context::get('item_name_langcode'))
				: mb_substr($item_name, 0, 250),
			'item_code' => mb_substr(trim((string)\Context::get('item_code')), 0, 100),
			'price' => max(0, MoneyModel::inputToMinor(\Context::get('price'))),
			'sale_price' => max(0, MoneyModel::inputToMinor(\Context::get('sale_price'))),
			// 가격순 정렬 전용 실판매가 — 할인 있으면 할인가
			'effective_price' => max(0, MoneyModel::inputToMinor(\Context::get('sale_price'))) > 0
				? max(0, MoneyModel::inputToMinor(\Context::get('sale_price')))
				: max(0, MoneyModel::inputToMinor(\Context::get('price'))),
			// 재고 수량은 재고 관리 화면(입고/출고/손실)에서만 바꾼다.
			// 여기서 받으면 저장할 때마다 재고가 폼 값으로 덮인다.
			'use_stock' => \Context::get('use_stock') === 'N' ? 'N' : 'Y',
			'summary' => LangModel::filterCode((string)\Context::get('summary_langcode')) !== ''
				? LangModel::toValue((string)\Context::get('summary_langcode'))
				: mb_substr(trim((string)\Context::get('summary')), 0, 250),
			'content' => (string)\Context::get('content'),
			'sale_start' => $to14((string)\Context::get('sale_start')),
			'sale_end' => $to14((string)\Context::get('sale_end')),
			'min_qty' => max(0, min(9999, (int)\Context::get('min_qty'))),
			'max_qty' => max(0, min(9999, (int)\Context::get('max_qty'))),
			'tax_type' => \Context::get('tax_type') === 'free' ? 'free' : 'taxable',
			'is_adult' => \Context::get('is_adult') === 'Y' ? 'Y' : 'N',
			// 조합형 옵션 축 정의 (조합 행 자체는 아래 옵션 저장에서 만든다)
			'option_axes' => ComboModel::encodeAxes(\Context::get('option_axes')),
			'option_mode' => \Context::get('option_mode') === 'combo' ? 'combo' : 'single',
			'ship_fee_type' => in_array(\Context::get('ship_fee_type'), ['default', 'free', 'fixed'], true) ? \Context::get('ship_fee_type') : 'default',
			'ship_fee' => max(0, MoneyModel::inputToMinor(\Context::get('ship_fee'))),
			'status' => in_array(\Context::get('status'), ['sale', 'soldout', 'hidden', 'stop'], true) ? \Context::get('status') : 'sale',
			'is_recommend' => \Context::get('is_recommend') === 'Y' ? 'Y' : 'N',
			'is_new' => \Context::get('is_new') === 'Y' ? 'Y' : 'N',
			// 직접 만든 뱃지들. 고른 차례대로 저장해 상품 카드에도 같은 차례로 찍힌다
			'badges' => implode(',', array_slice(array_map('intval', array_filter((array)\Context::get('badge_srls'), function($v) {
				return (int)$v > 0;
			})), 0, 10)),
			'list_order' => (int)\Context::get('list_order'),
			'last_update' => self::now(),
		];

		// 편집 화면에서 에디터 첨부 귀속용으로 srl 을 미리 발급하므로,
		// "srl 이 있어도 아직 저장 전"일 수 있다 — 존재 여부로 신규를 판정한다
		$is_new = $item_srl <= 0 || !ItemModel::get($item_srl);
		if ($item_srl <= 0)
		{
			$item_srl = getNextSequence();
		}

		// 신규 상품은 진열 맨 앞(관리 목록 맨 위)으로. 코어 문서와 같은 규약:
		// list_order = -srl 이라 오름차순 정렬에서 최신이 먼저 온다. 드래그 정렬이 이후 값을 덮는다.
		if ($is_new)
		{
			$fields->list_order = -$item_srl;
		}

		// 이미지 갤러리 (최대 7장) — 유지 목록(순서 반영) + 새 업로드. 첫 장이 대표 썸네일이다
		if (\Context::get('images_json') !== null)
		{
			$keep = json_decode((string)\Context::get('images_json'), true);
			$keep = is_array($keep) ? array_values(array_filter($keep, function($u) {
				return is_string($u) && strpos($u, \RX_BASEURL . 'files/') === 0;
			})) : [];
			$new_images = $this->saveImages($item_srl, 7 - count($keep));
			$images = array_slice(array_merge($keep, $new_images), 0, 7);
			// 복제 신규인데 이미지가 하나도 없으면 원본 계승에 맡긴다
			if (count($images) || !$clone_from)
			{
				$fields->images = json_encode($images, \JSON_UNESCAPED_SLASHES);
				$fields->thumb = $images[0] ?? '';
			}
		}
		else
		{
			// 구형 단일 썸네일 폼 호환
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
			// 복제: 원본 값 계승 (요청 값이 우선)
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
			$fields->has_options = 'N';
			// 신규 상품은 재고 0으로 시작 — 수량은 재고 관리에서 입고로 채운다
			$fields->stock = 0;
			// 진열 순서를 정하지 않았으면 번호를 그대로 쓴다. 오름차순으로 정렬하면
			// 등록한 순서대로 나오고, 나중에 목록에서 끌어 옮기면 이 값이 다시 매겨진다
			if ((int)$fields->list_order <= 0)
			{
				$fields->list_order = $item_srl;
			}
			$fields->regdate = self::now();
			$output = executeQuery('commerce.insertItem', $fields);

			// 등록 화면에서 미리 담아 둔 옵션을 함께 넣는다
			if ($output->toBool())
			{
				$this->insertPendingOptions($item_srl);
			}

			// 복제: 옵션도 복사
			if ($output->toBool() && $clone_from > 0)
			{
				foreach (ItemModel::getOptions($clone_from) as $opt)
				{
					executeQuery('commerce.insertOption', (object)[
						'option_srl' => getNextSequence(),
						'item_srl' => $item_srl,
						'option_label' => $opt->option_label,
						'option_type' => $opt->option_type ?? 'basic',
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
			// 축 이름·값을 바꾸면(다국어 문구 연결 포함) 기존 조합 옵션이 옛 값을 들고 있어
			// 구매 화면에서 어느 조합과도 맞지 않는다. 자리 순서를 기준으로 옮겨 준다.
			self::remapCombos($item_srl, ItemModel::get($item_srl)->option_axes ?? '', $fields->option_axes ?? '');
			$output = executeQuery('commerce.updateItem', $fields);
		}
		if (!$output->toBool())
		{
			return $output;
		}

		// 통화별 외화 가격. 화면 입력은 통화 단위 소수(12.34)라 최소단위 정수로 바꿔 담는다.
		// 다통화가 꺼져 있으면 저장을 건드리지 않는다 — 잠시 껐다 켜도 등록해 둔 외화 가격이 남아야 한다.
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

		// 에디터에서 업로드한 첨부(상세 이미지 등)를 상품에 귀속·유효화
		if ((int)\Context::get('editor_sequence') > 0)
		{
			\FileController::getInstance()->setFilesValid($item_srl);
		}

		// 기획전 노출 동기화 — 화면에 보여준 기획전만 담기/빼기 반영 (다른 기획전 소속은 건드리지 않음)
		$shown = json_decode((string)\Context::get('promo_shown'), true);
		if (is_array($shown))
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

		// 변경사항 저장에 실려 온 옵션 일괄 수정 (행별 저장과 같은 규칙)
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
		// 신규 등록 폼의 success_return_url 은 srl 이 비어 새 등록 화면으로 되돌아간다.
		// 등록 직후에는 항상 그 상품의 편집 화면을 연다. 콘솔에서 왔으면 콘솔 주소로
		$edit_url = \Context::get('from_console') === 'Y'
			? getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'item_edit', 'item_srl', $item_srl)
			: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item_srl);
		$this->setRedirectUrl($is_new ? $edit_url : (\Context::get('success_return_url') ?: $edit_url));
	}

	/**
	 * 상품 삭제 — 주문 이력이 있으면 숨김 전환(스냅샷 보존과 별개로 재노출 방지).
	 */
	/**
	 * 상품 진열 순서 저장.
	 *
	 * 목록에서 끌어 옮긴 차례대로 번호를 다시 매긴다. 쇼핑몰 목록의 기본 정렬이
	 * 이 값이라 저장 즉시 같은 순서로 보인다.
	 */
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

		$order = 1;
		foreach ($srls as $srl)
		{
			executeQuery('commerce.updateItem', (object)[
				'item_srl' => $srl,
				'list_order' => $order++,
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

	/**
	 * 옵션 추가.
	 */
	/**
	 * 조합형 옵션 만들기 — 축을 곱해 옵션 행을 채운다.
	 *
	 * 이미 있는 조합은 추가금·재고·SKU 를 그대로 두고, 축에서 사라진 조합은
	 * 판매 이력이 걸려 있을 수 있으므로 지우지 않고 숨김(status=N)으로 돌린다.
	 */
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

		// 지금 있는 조합 옵션을 열쇠로 모아 둔다. 직접 입력해 둔 기본 옵션은 따로 챙긴다
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
				// 값은 건드리지 않고 라벨·차례만 축 정의에 맞춘다
				executeQuery('commerce.updateOption', (object)[
					'option_srl' => (int)$existing[$key]->option_srl,
					'option_label' => $label,
					'combo' => $combo_json,
					'list_order' => $order,
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

		// 축에서 빠진 조합은 숨김 처리 (주문 이력 보존)
		$hidden = 0;
		foreach ($existing as $key => $option)
		{
			if (!isset($keys[$key]) && ($option->status ?? 'Y') !== 'N')
			{
				\Zittme\Framework\DB::getInstance()->query(
					'UPDATE commerce_item_option SET status = ? WHERE option_srl = ?', 'N', (int)$option->option_srl
				);
				$hidden++;
			}
		}

		// 직접 입력해 둔 기본 옵션은 조합과 섞이면 안 되므로 숨긴다 (삭제하지 않는다)
		$put_away = 0;
		foreach ($manual as $option)
		{
			if (($option->status ?? 'Y') !== 'N')
			{
				\Zittme\Framework\DB::getInstance()->query(
					'UPDATE commerce_item_option SET status = ? WHERE option_srl = ?', 'N', (int)$option->option_srl
				);
				$put_away++;
			}
		}

		executeQuery('commerce.updateItem', (object)[
			'item_srl' => $item_srl,
			'option_axes' => $axes_json,
			'option_mode' => 'combo',
			'has_options' => 'Y',
			'last_update' => $now,
		]);

		$this->add('made', $made);
		$this->add('hidden', $hidden);
		$this->add('total', count($combos));
		$notes = [];
		if ($hidden > 0) { $notes[] = '축에서 빠진 조합 ' . $hidden . '개 숨김'; }
		if ($put_away > 0) { $notes[] = '직접 입력한 옵션 ' . $put_away . '개 숨김'; }
		$this->setMessage(sprintf(
			lang('commerce.admin_msg_8'),
			count($combos), $made, count($notes) ? ', ' . implode(', ', $notes) : ''
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

	/**
	 * 옵션 수정.
	 */
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

	/**
	 * 옵션 삭제.
	 */
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

	/**
	 * 카테고리 추가/수정.
	 */
	public function procCommerceAdminInsertCategory()
	{
		$category_srl = (int)\Context::get('category_srl');
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 다국어 문구를 연결했으면 코어 규약대로 '$user_lang->코드' 를 저장한다
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
			$fields->category_srl = getNextSequence();
			$fields->regdate = self::now();
			executeQuery('commerce.insertCategory', $fields);
		}
		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCategories'));
	}

	/**
	 * 카테고리 삭제 — 하위·소속 상품은 미분류(0)로.
	 */
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

	/**
	 * 설정 저장.
	 */
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
				// 다국어 버튼으로 문구를 연결했으면 '$user_lang->코드' 로 담는다
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
			}
			elseif ($key === 'shop_main')
			{
				$value = $value === 'home' ? 'home' : 'list';
			}
			elseif ($key === 'category_layout')
			{
				$value = $value === 'side' ? 'side' : 'top';
			}
			elseif ($key === 'currencies')
			{
				// 병행 판매 통화 — 대표 통화 목록 안에서만 고른다. 기준 통화는 기본이라 뺀다.
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
			elseif ($key === 'home_banners' || $key === 'ship_extra_zones')
			{
				// JSON 배열만 저장 — 깨진 값이면 빈 배열
				$decoded = json_decode((string)$value, true);
				$decoded = is_array($decoded) ? array_values($decoded) : [];
				if ($key === 'ship_extra_zones')
				{
					// 지역 추가 배송비도 다른 금액 항목과 같이 기준 통화 최소단위로 저장한다
					foreach ($decoded as &$zone_row)
					{
						if (is_array($zone_row))
						{
							$zone_row['fee'] = MoneyModel::inputToMinor($zone_row['fee'] ?? 0);
							// 구간의 기준액과 추가금도 같은 단위로 담는다
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

	/**
	 * 주문 처리 — 발주확인 / 송장 등록(배송중) / 배송완료 / 전체 취소.
	 *
	 * 배송 단계 전이는 하위주문(order_seller) 단위 조건부 UPDATE — 중복 클릭에 안전하다.
	 */
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
			case 'confirm': // 발주확인 → 배송준비
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

			case 'ship': // 송장 등록 → 배송중
				$company = trim((string)\Context::get('shipping_company'));
				$invoice = trim((string)\Context::get('shipping_invoice'));
				// 직접배송은 택배사 없이 배송중으로 넘긴다. 송장·배송조회는 생략된다
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

			case 'deliver': // 배송완료
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

			case 'cancel': // 전체 취소 (유료면 전액 환불 시도)
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

	/**
	 * 클레임 처리 — 승인(환불+재입고) / 거절.
	 *
	 * 조건부 전이에 이긴 요청만 환불·재입고를 실행하므로 중복 승인이 없다.
	 */
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
			// 환불액: 관리자 입력값(반품배송비 차감 반영) — 상한은 주문 결제액
			$refund_amount = max(0, MoneyModel::inputToMinor(\Context::get('refund_amount')));
			if ($order)
			{
				$refund_amount = min($refund_amount, (int)$order->payment_price);
			}
			$restock = \Context::get('restock') === 'N' ? 'N' : 'Y';

			// 조건부 전이 승자만 실행
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

			// 환불 (부분 가능)
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

			// 재입고 + 품목 클레임 상태
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
}
