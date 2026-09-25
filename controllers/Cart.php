<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;

class Cart extends Base
{
	public function procCommerceCartAdd()
	{
		$item_srl = (int)\Context::get('item_srl');

		$item = ItemModel::get($item_srl);
		if (!$item || !ItemModel::isPurchasable($item))
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}

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

		$merged = [];
		foreach ($rows as $row)
		{
			$merged[$row[0]] = ($merged[$row[0]] ?? 0) + $row[1];
		}

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

		$resolved = CartModel::resolve();
		$ship_fee = CartModel::calcShipFee($resolved);
		$qty_total = 0;
		foreach ($resolved->items ?? [] as $entry)
		{
			$qty_total += (int)($entry->qty ?? 0);
		}
		$this->add('qty_total', $qty_total);
		$this->add('item_total', (int)($resolved->item_total ?? 0));
		$this->add('item_total_text', shop_money((int)($resolved->item_total ?? 0)));
		$this->add('ship_fee', (int)$ship_fee);
		$this->add('ship_fee_text', $ship_fee > 0 ? shop_money((int)$ship_fee) : '');

		if (\Context::get('direct') === 'Y')
		{
			$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceCheckout'));
		}
		else
		{
			$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceCart'));
		}
	}

	public function procCommerceCartUpdate()
	{
		$cart_srl = (int)\Context::get('cart_srl');
		$qty = max(1, min(9999, (int)\Context::get('qty')));

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
