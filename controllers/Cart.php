<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;

/**
 * 장바구니 (proc).
 */
class Cart extends Base
{
	/**
	 * 담기.
	 */
	public function procCommerceCartAdd()
	{
		$item_srl = (int)\Context::get('item_srl');

		$item = ItemModel::get($item_srl);
		if (!$item || !ItemModel::isPurchasable($item))
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}

		// 선택 행: 본품·추가옵션을 여러 줄로 한 번에 담는다 (option_srls[]/qtys[]).
		// 구형 폼 호환: 배열이 없으면 단일 option_srl/qty 로 처리한다.
		$rows = [];
		$option_srls = \Context::get('option_srls');
		$qtys = \Context::get('qtys');
		if (is_array($option_srls) && count($option_srls))
		{
			$qtys = is_array($qtys) ? $qtys : [];
			foreach (array_values($option_srls) as $i => $opt_srl)
			{
				$rows[] = [max(0, (int)$opt_srl), max(1, min(9999, (int)($qtys[$i] ?? 1)))];
			}
		}
		else
		{
			$rows[] = [max(0, (int)\Context::get('option_srl')), max(1, min(9999, (int)(\Context::get('qty') ?: 1)))];
		}

		// 같은 옵션 행은 수량 합산
		$merged = [];
		foreach ($rows as $row)
		{
			$merged[$row[0]] = ($merged[$row[0]] ?? 0) + $row[1];
		}

		// 고른 옵션은 소속을 검증한다. 본품은 option_srl 0 이지만,
		// 기본 옵션(변형)이 있는 상품은 본품 단독으로는 담을 수 없다.
		$valid_options = [];
		$has_basic = false;
		foreach (ItemModel::getOptions($item_srl, true) as $opt)
		{
			$valid_options[(int)$opt->option_srl] = true;
			if (($opt->option_type ?? 'basic') === 'basic')
			{
				$has_basic = true;
			}
		}
		foreach ($merged as $opt_srl => $qty)
		{
			if ($opt_srl > 0 && !isset($valid_options[$opt_srl]))
			{
				return new \BaseObject(-1, 'msg_shop_need_option');
			}
			// 본품(option_srl 0)은 기본 옵션이 있어도 '선택 안 함'으로 담을 수 있다 — 자체 재고로 판매
			if (!ItemModel::isQtyAllowed($item, $qty))
			{
				return new \BaseObject(-1, 'msg_shop_qty_not_allowed');
			}
		}

		foreach ($merged as $opt_srl => $qty)
		{
			$output = CartModel::add($item_srl, (int)$opt_srl, $qty);
			if (!$output->toBool())
			{
				return $output;
			}
		}

		$this->setMessage('msg_shop_cart_added');
		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		// 바로 구매: 담은 뒤 곧장 주문서로
		if (\Context::get('direct') === 'Y')
		{
			$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceCheckout'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceCart'));
		}
	}

	/**
	 * 수량 변경.
	 */
	public function procCommerceCartUpdate()
	{
		$cart_srl = (int)\Context::get('cart_srl');
		$qty = max(1, min(9999, (int)\Context::get('qty')));

		// 소유 검증: 내 장바구니 행인지 확인
		$mine = false;
		foreach (CartModel::rows() as $row)
		{
			if ((int)$row->cart_srl === $cart_srl)
			{
				$mine = true;
				break;
			}
		}
		if (!$mine)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		executeQuery('commerce.updateCartQty', (object)['cart_srl' => $cart_srl, 'qty' => $qty]);
		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceCart'));
	}

	/**
	 * 삭제.
	 */
	public function procCommerceCartDelete()
	{
		$cart_srl = (int)\Context::get('cart_srl');
		if ($cart_srl > 0)
		{
			CartModel::remove($cart_srl);
		}
		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceCart'));
	}
}
