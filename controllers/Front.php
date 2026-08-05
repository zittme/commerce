<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;

/**
 * 프론트 화면.
 */
class Front extends Base
{
	/**
	 * 스킨 경로.
	 *
	 * @return string
	 */
	protected function getSkinPath(): string
	{
		$skin = (string)($this->module_info->skin ?? '');
		if ($skin === '' || $skin === '/USE_DEFAULT/')
		{
			$skin = 'default';
		}
		$skin = preg_replace('/[^A-Za-z0-9_-]/', '', $skin);
		if ($skin === '' || !is_dir($this->module_path . 'skins/' . $skin))
		{
			$skin = 'default';
		}
		return $this->module_path . 'skins/' . $skin . '/';
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
		return array_values(array_filter($data, function($row) { return !empty($row->category_srl); }));
	}

	/**
	 * 상품 목록 — 정렬·필터·품절 정책.
	 */
	public function dispCommerceList()
	{
		$args = new \stdClass;
		// 노출 상태: 판매중 + 품절(설정에 따라). 숨김·중지는 절대 노출하지 않는다
		$args->status_list = 'sale,soldout';

		$category_srl = (int)\Context::get('category');
		if ($category_srl > 0)
		{
			$args->category_srl = $category_srl;
		}
		$keyword = trim((string)\Context::get('q'));
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
		}

		// 정렬: new(기본) / popular / price_low / price_high
		$sort = (string)\Context::get('sort');
		switch ($sort)
		{
			case 'popular':
				$args->sort_index = 'buy_count';
				$args->order_type = 'desc';
				break;
			case 'price_low':
				$args->sort_index = 'sale_price';
				$args->order_type = 'asc';
				break;
			case 'price_high':
				$args->sort_index = 'sale_price';
				$args->order_type = 'desc';
				break;
			default:
				$sort = 'new';
				$args->sort_index = 'item_srl';
				$args->order_type = 'desc';
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

		\Context::set('items', $items);
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('shop_categories', self::getActiveCategories());
		\Context::set('current_category', $category_srl);
		\Context::set('current_sort', $sort);
		\Context::set('current_q', $keyword);
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('list');
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

		\Context::set('item', $item);
		\Context::set('options', ItemModel::getOptions($item_srl, true));
		\Context::set('purchasable', ItemModel::isPurchasable($item));
		\Context::set('effective_price', ItemModel::effectivePrice($item));
		\Context::set('adult_ok', ($item->is_adult ?? 'N') !== 'Y' || Order::isAdultVerified($member_srl));
		\Context::set('cart_count', count(CartModel::rows()));
		\Context::set('shop_config', self::config());
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
		\Context::set('my_coupons', \Zittme\Modules\Commerce\Models\Coupon::listUsableForMember($member_srl, $resolved->item_total));
		\Context::set('credit_balance', \Zittme\Modules\Commerce\Models\Credit::balanceOf($member_srl));

		\Context::set('cart', $resolved);
		\Context::set('ship_fee', $ship_fee);
		\Context::set('payment_price', $resolved->item_total + $ship_fee);
		\Context::set('is_member', $logged_info && $logged_info->member_srl ? true : false);
		\Context::set('pay_available', self::isPayAvailable());
		\Context::set('shop_config', self::config());
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

		\Context::set('order', $order);
		\Context::set('order_items', OrderModel::getItems((int)$order->order_srl));
		\Context::set('order_sellers', OrderModel::getSellerOrders((int)$order->order_srl));
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
