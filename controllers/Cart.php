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
		$option_srl = max(0, (int)\Context::get('option_srl'));
		$qty = max(1, min(9999, (int)(\Context::get('qty') ?: 1)));

		$item = ItemModel::get($item_srl);
		if (!$item || !ItemModel::isPurchasable($item))
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}
		if (!ItemModel::isQtyAllowed($item, $qty))
		{
			return new \BaseObject(-1, 'msg_shop_qty_not_allowed');
		}
		// 옵션 상품은 옵션 필수 + 소속 검증
		if (($item->has_options ?? 'N') === 'Y')
		{
			if ($option_srl <= 0)
			{
				return new \BaseObject(-1, 'msg_shop_need_option');
			}
			$found = false;
			foreach (ItemModel::getOptions($item_srl, true) as $opt)
			{
				if ((int)$opt->option_srl === $option_srl)
				{
					$found = true;
					break;
				}
			}
			if (!$found)
			{
				return new \BaseObject(-1, 'msg_shop_need_option');
			}
		}
		else
		{
			$option_srl = 0;
		}

		$output = CartModel::add($item_srl, $option_srl, $qty);
		if (!$output->toBool())
		{
			return $output;
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
