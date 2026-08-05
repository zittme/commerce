<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Config as ConfigModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;

/**
 * 전용 운영 화면 — 예약 모듈과 같은 구조(대시보드 + 업무 화면 세트).
 * 디자인은 관리자 리디자인 토큰(Pretendard, #2677e3)을 따른다.
 */
class Admin extends Base
{
	/**
	 * 설정 저장 허용 키.
	 */
	public const CONFIG_FIELDS = [
		'enabled', 'market_mode', 'code_prefix', 'allow_guest', 'pending_minutes',
		'default_ship_fee', 'free_ship_over', 'claim_days',
		'credit_rate', 'credit_min_use',
		'privacy_text', 'privacy_version', 'retention_days',
		'notify_admin', 'notify_admin_email',
	];

	protected const BOOLEAN_FIELDS = ['enabled', 'allow_guest', 'notify_admin'];
	// 소수점 2자리 허용 (적립률 0.00~100.00%)
	protected const FLOAT_FIELDS = ['credit_rate' => [0, 100]];
	protected const INT_FIELDS = [
		'pending_minutes' => [10, 1440],
		'default_ship_fee' => [0, 1000000],
		'free_ship_over' => [0, 100000000],
		'claim_days' => [0, 90],
		'credit_min_use' => [0, 1000000],
		'retention_days' => [0, 3650],
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
		$map = [];
		if ($output->toBool() && !empty($output->data))
		{
			foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
			{
				if (!empty($row->category_srl))
				{
					$map[(int)$row->category_srl] = $row;
				}
			}
		}
		return $map;
	}

	// ────────────────────────── 화면 ──────────────────────────

	/**
	 * 대시보드.
	 */
	public function dispCommerceAdminDashboard()
	{
		OrderModel::expireStalePending();
		$instance = self::getDefaultInstance();
		\Context::set('shop_mid', $instance ? $instance->mid : self::DEFAULT_MID);
		$this->renderView('dashboard', 'dashboard');
	}

	/**
	 * 상품 관리 목록.
	 */
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
		$args->sort_index = 'item_srl';
		$args->order_type = 'desc';

		$output = executeQuery('commerce.getItemList', $args);
		$items = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		\Context::set('items', $items);
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
		\Context::set('options', $options);
		\Context::set('categories', self::getCategories());
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
	 * 주문 관리 목록.
	 */
	public function dispCommerceAdminOrders()
	{
		OrderModel::expireStalePending();

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

		\Context::set('order', $order);
		\Context::set('order_items', OrderModel::getItems($order_srl));
		\Context::set('order_sellers', OrderModel::getSellerOrders($order_srl));
		\Context::set('order_address', count($to_array($address_output)) ? $to_array($address_output)[0] : null);
		\Context::set('order_logs', $to_array($logs_output));
		\Context::set('order_claims', $to_array($claims_output));
		$this->renderView('orders', 'order_view');
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
		$discount_value = max(0, (int)\Context::get('discount_value'));
		if ($discount_type === 'percent')
		{
			$discount_value = min(100, $discount_value);
		}

		$args = (object)[
			'title' => mb_substr($title, 0, 120),
			'code' => $code,
			'discount_type' => $discount_type,
			'discount_value' => $discount_value,
			'max_discount' => max(0, (int)\Context::get('max_discount')),
			'min_order' => max(0, (int)\Context::get('min_order')),
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

		// 최근 원장 (전체)
		$output = executeQuery('commerce.getCreditLogs', (object)['list_count' => 30]);
		$recent = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

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
		$amount = (int)\Context::get('amount');
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
		\Context::set('grades', \Zittme\Modules\Commerce\Models\Grade::getList());
		\Context::set('grade_coupons', \Zittme\Modules\Commerce\Models\Coupon::getList());
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
		$discount_value = max(0, round((float)\Context::get('discount_value'), 2));
		if ($discount_type === 'percent')
		{
			$discount_value = min(100, $discount_value);
		}
		if ($discount_type === '' || $discount_value <= 0)
		{
			$discount_type = '';
			$discount_value = 0;
		}

		$args = (object)[
			'title' => mb_substr($title, 0, 80),
			'min_spend' => max(0, (int)\Context::get('min_spend')),
			'credit_rate' => max(0, min(100, round((float)\Context::get('credit_rate'), 2))),
			'coupon_srl' => max(0, (int)\Context::get('coupon_srl')),
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
		executeQuery('commerce.deleteGrade', (object)['grade_srl' => $grade_srl]);
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminGrades'));
	}

	public function dispCommerceAdminStats() { $this->renderView('stats', 'placeholder_orders'); }

	/**
	 * 설정.
	 */
	public function dispCommerceAdminConfig()
	{
		\Context::set('pay_available', self::isPayAvailable());

		// 스킨 — 테마 패키지(레이아웃+커머스+짓미페이+게시판 스킨) 배포 규약의 일부
		$instance = self::getDefaultInstance();
		$module_info = $instance ? \ModuleModel::getModuleInfoByMid($instance->mid) : null;
		\Context::set('shop_instance', $module_info);
		\Context::set('shop_skins', \ModuleModel::getSkins(\RX_BASEDIR . 'modules/commerce') ?: []);
		\Context::set('shop_mskins', \ModuleModel::getSkins(\RX_BASEDIR . 'modules/commerce', 'm.skins') ?: []);
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

		$sanitize = function($v) { return preg_replace('/[^A-Za-z0-9_\-.\/]/', '', (string)$v); };
		$skin = $sanitize(\Context::get('skin'));
		$mskin = $sanitize(\Context::get('mskin'));
		if ($skin !== '')
		{
			$module_info->skin = $skin;
		}
		if ($mskin !== '')
		{
			$module_info->mskin = $mskin;
		}
		$module_info->isMenuCreate = false;

		$output = \ModuleController::getInstance()->updateModule($module_info);
		if (!$output->toBool())
		{
			return $output;
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminConfig'));
	}

	// ────────────────────────── 처리 ──────────────────────────

	/**
	 * 대표 이미지 업로드 (내용 검사, 예약 모듈과 같은 방식).
	 *
	 * @param int $item_srl
	 * @param string $field
	 * @return ?string
	 */
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
		\Rhymix\Framework\Storage::createDirectory($dir);
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
			\Rhymix\Framework\Storage::createDirectory($dir);
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
			'item_name' => mb_substr($item_name, 0, 250),
			'item_code' => mb_substr(trim((string)\Context::get('item_code')), 0, 100),
			'price' => max(0, (int)preg_replace('/\D/', '', (string)\Context::get('price'))),
			'sale_price' => max(0, (int)preg_replace('/\D/', '', (string)\Context::get('sale_price'))),
			'stock' => max(0, (int)\Context::get('stock')),
			'use_stock' => \Context::get('use_stock') === 'N' ? 'N' : 'Y',
			'summary' => mb_substr(trim((string)\Context::get('summary')), 0, 250),
			'content' => (string)\Context::get('content'),
			'sale_start' => $to14((string)\Context::get('sale_start')),
			'sale_end' => $to14((string)\Context::get('sale_end')),
			'min_qty' => max(0, min(9999, (int)\Context::get('min_qty'))),
			'max_qty' => max(0, min(9999, (int)\Context::get('max_qty'))),
			'tax_type' => \Context::get('tax_type') === 'free' ? 'free' : 'taxable',
			'is_adult' => \Context::get('is_adult') === 'Y' ? 'Y' : 'N',
			'ship_fee_type' => in_array(\Context::get('ship_fee_type'), ['default', 'free', 'fixed'], true) ? \Context::get('ship_fee_type') : 'default',
			'ship_fee' => max(0, (int)preg_replace('/\D/', '', (string)\Context::get('ship_fee'))),
			'status' => in_array(\Context::get('status'), ['sale', 'soldout', 'hidden', 'stop'], true) ? \Context::get('status') : 'sale',
			'is_recommend' => \Context::get('is_recommend') === 'Y' ? 'Y' : 'N',
			'is_new' => \Context::get('is_new') === 'Y' ? 'Y' : 'N',
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
			$fields->regdate = self::now();
			$output = executeQuery('commerce.insertItem', $fields);

			// 복제: 옵션도 복사
			if ($output->toBool() && $clone_from > 0)
			{
				foreach (ItemModel::getOptions($clone_from) as $opt)
				{
					executeQuery('commerce.insertOption', (object)[
						'option_srl' => getNextSequence(),
						'item_srl' => $item_srl,
						'option_label' => $opt->option_label,
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
			$output = executeQuery('commerce.updateItem', $fields);
		}
		if (!$output->toBool())
		{
			return $output;
		}

		// 에디터에서 업로드한 첨부(상세 이미지 등)를 상품에 귀속·유효화
		if ((int)\Context::get('editor_sequence') > 0)
		{
			\FileController::getInstance()->setFilesValid($item_srl);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item_srl));
	}

	/**
	 * 상품 삭제 — 주문 이력이 있으면 숨김 전환(스냅샷 보존과 별개로 재노출 방지).
	 */
	public function procCommerceAdminDeleteItem()
	{
		$item_srl = (int)\Context::get('item_srl');
		if ($item_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$stmt = \Rhymix\Framework\DB::getInstance()->query(
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
			\Rhymix\Framework\DB::getInstance()->query('DELETE FROM commerce_item_option WHERE item_srl = ?', $item_srl);
			executeQuery('commerce.deleteItem', (object)['item_srl' => $item_srl]);
			$this->setMessage('success_deleted');
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems'));
	}

	/**
	 * 옵션 추가.
	 */
	public function procCommerceAdminInsertOption()
	{
		$item_srl = (int)\Context::get('item_srl');
		$label = trim((string)\Context::get('option_label'));
		if ($item_srl <= 0 || $label === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		executeQuery('commerce.insertOption', (object)[
			'option_srl' => getNextSequence(),
			'item_srl' => $item_srl,
			'option_label' => mb_substr($label, 0, 250),
			'price_add' => (int)preg_replace('/[^\-\d]/', '', (string)\Context::get('price_add')),
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

		$fields = (object)[
			'parent_srl' => max(0, (int)\Context::get('parent_srl')),
			'title' => mb_substr($title, 0, 120),
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
		$oDB = \Rhymix\Framework\DB::getInstance();
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
			if (in_array($key, self::BOOLEAN_FIELDS, true))
			{
				$value = $value === 'Y' ? 'Y' : 'N';
			}
			elseif (isset(self::INT_FIELDS[$key]))
			{
				[$min, $max] = self::INT_FIELDS[$key];
				$value = max($min, min($max, (int)$value));
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
				if ($company === '' || $invoice === '')
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
		}
		elseif ($action === 'approve')
		{
			// 환불액: 관리자 입력값(반품배송비 차감 반영) — 상한은 주문 결제액
			$refund_amount = max(0, (int)preg_replace('/\D/', '', (string)\Context::get('refund_amount')));
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
				\Rhymix\Framework\DB::getInstance()->query(
					'UPDATE commerce_order_item SET claim_status = ? WHERE order_item_srl = ?',
					'done', (int)$oi->order_item_srl
				);
			}

			OrderModel::log((int)$claim->order_srl, 0, 'refund', 'requested', 'done', $actor, 'refund=' . $refund_amount);
		}
		else
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminClaims'));
	}
}
