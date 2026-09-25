<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Address as AddressModel;
use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Coupon as CouponModel;
use Zittme\Modules\Commerce\Models\Credit as CreditModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;
use Zittme\Modules\Commerce\Models\Stock;

class Order extends Base
{
	public function procCommerceOrder()
	{
		$config = self::config();
		if (($config->enabled ?? 'Y') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_shop_disabled');
		}

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		if ($member_srl <= 0 && ($config->allow_guest ?? 'Y') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_shop_login_required');
		}

		OrderModel::expireStalePending();
		$open_pending = OrderModel::findOpenPending($member_srl);
		if ($open_pending)
		{
			$this->setMessage('msg_shop_pending_order_exists');
			$this->setRedirectUrl(getNotEncodedUrl('', 'mid', (string)\Context::get('mid'),
				'act', 'dispCommerceOrderResult', 'code', $open_pending->order_code));
			return;
		}

		$pin_only = CartModel::isPinOnly(CartModel::resolve(CartModel::owner()));

		$orderer_name = trim((string)\Context::get('orderer_name'));
		$orderer_phone = trim((string)\Context::get('orderer_phone'));
		$receiver_name = trim((string)\Context::get('receiver_name')) ?: $orderer_name;
		$address1 = trim((string)\Context::get('address1'));
		if ($orderer_name === '' || $orderer_phone === '' || ($address1 === '' && !$pin_only))
		{
			return new \BaseObject(-1, 'msg_shop_need_fields');
		}

		if (!$pin_only && \Zittme\Modules\Commerce\Models\Address::isOverseasInput((string)\Context::get('country'))
			&& trim((string)\Context::get('city')) === '')
		{
			return new \BaseObject(-1, 'msg_shop_need_city');
		}

		$guest_password = '';
		if ($member_srl <= 0)
		{
			$raw = (string)\Context::get('guest_password');
			if (strlen($raw) < 4)
			{
				return new \BaseObject(-1, 'msg_shop_need_password');
			}
			$guest_password = \Zittme\Framework\Password::hashPassword($raw);
		}

		if (\Context::get('agree_privacy') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_shop_need_agreement');
		}

		$owner = CartModel::owner();
		$resolved = CartModel::resolve($owner);
		$entries = array_values(array_filter($resolved->items, function($e) { return !$e->blocked; }));
		if (!count($entries))
		{
			return new \BaseObject(-1, 'msg_shop_cart_empty');
		}

		foreach ($entries as $entry)
		{
			if (($entry->item->is_adult ?? 'N') === 'Y' && !self::isAdultVerified($member_srl))
			{
				return new \BaseObject(-1, 'msg_shop_adult_required');
			}
		}

		foreach ($entries as $entry)
		{
			if (($entry->item->is_pin ?? 'N') !== 'Y' || (int)($entry->item->pin_daily_limit ?? 0) <= 0)
			{
				continue;
			}
			if ($member_srl <= 0)
			{
				return new \BaseObject(-1, lang('commerce.pin_msg_login'));
			}
			$limit = (int)$entry->item->pin_daily_limit;
			if (\Zittme\Modules\Commerce\Models\Pin::boughtToday((int)$entry->item->item_srl, $member_srl) + (int)$entry->qty > $limit)
			{
				return new \BaseObject(-1, $entry->item->item_name . ': ' . sprintf(lang('commerce.pin_msg_daily'), $limit));
			}
		}

		// 주·도를 반드시 받는 곳. 화면 검사만으로는 우회된다
		if (!$pin_only && AddressModel::requiresState((string)\Context::get('country')) && trim((string)\Context::get('state')) === '')
		{
			return new \BaseObject(-1, 'msg_shop_state_required');
		}

		$item_total = $resolved->item_total;
		$delivery_fee = CartModel::calcShipFee($resolved);
		$delivery_fee += $pin_only ? 0 : CartModel::extraShipFee(
			(string)\Context::get('zipcode'),
			(string)\Context::get('address1'),
			self::filterCountry((string)\Context::get('country')),
			(string)\Context::get('state'),
			(string)\Context::get('city'),
			(int)($resolved->item_total_listed ?? $item_total)
		);

		$reserved = [];
		foreach ($entries as $entry)
		{
			if (Stock::isUnlimited($entry->item))
			{
				continue;
			}
			$ok = Stock::reserve((int)$entry->item->item_srl, $entry->option ? (int)$entry->option->option_srl : 0, $entry->qty);
			if (!$ok)
			{
				foreach ($reserved as $r)
				{
					Stock::release($r[0], $r[1], $r[2]);
				}
				return new \BaseObject(-1, sprintf(lang('commerce.msg_shop_out_of_stock'), $entry->item->item_name));
			}
			$reserved[] = [(int)$entry->item->item_srl, $entry->option ? (int)$entry->option->option_srl : 0, $entry->qty];
		}

		$ts_reserved = [];
		foreach ($entries as $entry)
		{
			$ts = $entry->item->timesale ?? null;
			if (!$ts || ($entry->option && ($entry->option->option_type ?? 'basic') === 'extra'))
			{
				continue;
			}
			$ts_error = \Zittme\Modules\Commerce\Models\Timesale::reserve($ts, (int)$entry->qty, $member_srl);
			if ($ts_error !== '')
			{
				foreach ($reserved as $r)
				{
					Stock::release($r[0], $r[1], $r[2]);
				}
				\Zittme\Modules\Commerce\Models\Timesale::release($ts_reserved);
				return new \BaseObject(-1, $entry->item->item_name . ': ' . $ts_error);
			}
			$ts_reserved[] = [(int)$ts->ts_item_srl, (int)$entry->qty];
			$entry->timesale_item_srl = (int)$ts->ts_item_srl;
		}

		$order_srl = getNextSequence();

		$base_currency = \Zittme\Modules\Commerce\Models\Money::base();
		$order_currency = \Zittme\Modules\Commerce\Models\Money::current();
		$exchange_rate = 1.0;
		if ($order_currency !== $base_currency)
		{
			$exchange_rate = \Zittme\Modules\Commerce\Models\Money::rate($order_currency);
			if ($exchange_rate <= 0)
			{
				foreach ($reserved as $r)
				{
					Stock::release($r[0], $r[1], $r[2]);
				}
				\Zittme\Modules\Commerce\Models\Timesale::release($ts_reserved);
				return new \BaseObject(-1, 'msg_shop_fx_unavailable');
			}
			\Context::set('coupon_issue_srl', 0);
			\Context::set('coupon_code', '');
			\Context::set('use_credit', 0);
		}

		// 설정에서 껐으면 화면에 칸이 없다. 값을 직접 보내 할인을 받는 길도 막는다
		if (self::config()->use_coupon === 'N')
		{
			\Context::set('coupon_issue_srl', 0);
			\Context::set('coupon_code', '');
		}
		if (self::config()->use_credit === 'N')
		{
			\Context::set('use_credit', 0);
		}

		$discount_total = 0;
		$coupon_issue_srl = (int)\Context::get('coupon_issue_srl');
		$coupon_code = trim((string)\Context::get('coupon_code'));
		if ($member_srl > 0 && ($coupon_issue_srl > 0 || $coupon_code !== ''))
		{
			$coupon_error = '';
			if ($coupon_issue_srl > 0)
			{
				$issue = CouponModel::getIssue($coupon_issue_srl);
				$coupon = $issue ? CouponModel::get((int)$issue->coupon_srl) : null;
				$discount = ($coupon && CouponModel::isUsableNow($coupon)) ? CouponModel::discountFor($coupon, $item_total) : null;
				if ($discount === null || !CouponModel::claimIssue($coupon_issue_srl, $member_srl, $order_srl))
				{
					$coupon_error = 'msg_shop_coupon_invalid';
				}
				else
				{
					$discount_total = $discount;
				}
			}
			else
			{
				$redeem = CouponModel::redeemCode($coupon_code, $member_srl, $order_srl, $item_total);
				if (empty($redeem->success))
				{
					$coupon_error = (string)$redeem->message;
				}
				else
				{
					$discount_total = (int)$redeem->discount;
				}
			}
			if ($coupon_error !== '')
			{
				foreach ($reserved as $r)
				{
					Stock::release($r[0], $r[1], $r[2]);
				}
				\Zittme\Modules\Commerce\Models\Timesale::release($ts_reserved);
				return new \BaseObject(-1, $coupon_error);
			}
		}

		$credit_used = 0;
		$want_credit = max(0, \Zittme\Modules\Commerce\Models\Money::inputToMinor(\Context::get('use_credit')));
		if ($member_srl > 0 && $want_credit > 0)
		{
			$min_use = max(0, (int)(self::config()->credit_min_use ?? 0));
			$cap = max(0, $item_total - $discount_total);
			$want_credit = min($want_credit, $cap);
			if ($want_credit > 0 && $want_credit >= $min_use)
			{
				if (!CreditModel::spend($member_srl, $want_credit, $order_srl))
				{
					foreach ($reserved as $r)
					{
						Stock::release($r[0], $r[1], $r[2]);
					}
					\Zittme\Modules\Commerce\Models\Timesale::release($ts_reserved);
					CouponModel::releaseByOrder($order_srl);
					return new \BaseObject(-1, 'msg_shop_credit_insufficient');
				}
				$credit_used = $want_credit;
			}
		}

		if ($order_currency !== $base_currency)
		{
			$fx_item_total = 0;
			foreach ($entries as $entry)
			{
				$fx_unit = ItemModel::effectivePriceIn($entry->item, $order_currency);
				$fx_add = $entry->option ? \Zittme\Modules\Commerce\Models\Money::convertMinor(max(0, (int)($entry->option->price_add ?? 0)), $order_currency) : 0;
				if ($fx_unit < 0 || $fx_add < 0)
				{
					foreach ($reserved as $r)
					{
						Stock::release($r[0], $r[1], $r[2]);
					}
					\Zittme\Modules\Commerce\Models\Timesale::release($ts_reserved);
					return new \BaseObject(-1, sprintf(lang('commerce.msg_shop_fx_not_sellable'), (string)$entry->item->item_name));
				}
				$entry->unit_price = ($entry->item->grade_discount ?? 'Y') === 'N'
					? $fx_unit + $fx_add
					: \Zittme\Modules\Commerce\Models\Grade::applyDiscountIn(
						$fx_unit + $fx_add,
						$member_srl > 0 ? \Zittme\Modules\Commerce\Models\Grade::discountFor($member_srl) : null,
						$order_currency
					);
				$entry->subtotal = $entry->unit_price * $entry->qty;
				$fx_item_total += $entry->subtotal;
			}
			$item_total = $fx_item_total;
			$delivery_fee = max(0, \Zittme\Modules\Commerce\Models\Money::convertMinor($delivery_fee, $order_currency));
		}

		$payment_price = max(0, $item_total - $discount_total - $credit_used) + $delivery_fee;

		$seller = self::getDefaultSeller();
		$order_code = self::generateOrderCode();
		$now = self::now();

		$output = executeQuery('commerce.insertOrder', (object)[
			'order_srl' => $order_srl,
			'order_code' => $order_code,
			'channel' => 'web',
			'member_srl' => $member_srl,
			'orderer_name' => mb_substr($orderer_name, 0, 80),
			'orderer_phone' => mb_substr($orderer_phone, 0, 30),
			'orderer_email' => mb_substr(trim((string)\Context::get('orderer_email')), 0, 120),
			'guest_password' => $guest_password,
			'item_total' => $item_total,
			'delivery_fee_total' => $delivery_fee,
			'discount_total' => $discount_total,
			'credit_used' => $credit_used,
			'payment_price' => $payment_price,
			'currency' => $order_currency,
			'exchange_rate' => $order_currency === $base_currency ? '' : (string)$exchange_rate,
			'pay_order_srl' => 0,
			'status' => self::ORDER_PENDING,
			'memo' => '',
			'ipaddress' => \RX_CLIENT_IP ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
			'regdate' => $now,
			'paid_date' => '',
			'cancelled_date' => '',
		]);
		if (!$output->toBool())
		{
			foreach ($reserved as $r)
			{
				Stock::release($r[0], $r[1], $r[2]);
			}
			\Zittme\Modules\Commerce\Models\Timesale::release($ts_reserved);
			CouponModel::releaseByOrder($order_srl);
			if ($credit_used > 0)
			{
				CreditModel::add($member_srl, $credit_used, 'refund', $order_srl, 'order insert failed');
			}
			return $output;
		}

		$bundles = self::buildBundles($entries, $seller, $item_total, $delivery_fee, $discount_total, $payment_price, $order_currency !== $base_currency ? $order_currency : '');
		$order_seller_srl = 0;
		foreach ($bundles as $bundle)
		{
			$bundle->order_seller_srl = getNextSequence();
			if ($order_seller_srl === 0)
			{
				$order_seller_srl = $bundle->order_seller_srl;
			}
			$bundle_args = (object)[
				'order_seller_srl' => $bundle->order_seller_srl,
				'order_srl' => $order_srl,
				'seller_srl' => $bundle->seller_srl,
				'item_total' => $bundle->item_total,
				'delivery_fee' => $bundle->delivery_fee,
				'discount' => $bundle->discount,
				'settle_amount' => $bundle->settle_amount,
				'status' => self::SELLER_PENDING,
				'regdate' => $now,
			];
			if ($bundle->market)
			{
				$bundle_args->commission = $bundle->commission;
				$bundle_args->operator_fee = (int)($bundle->operator_fee ?? 0);
			}
			executeQuery('commerce.insertOrderSeller', $bundle_args);
			foreach ($bundle->entries as $entry)
			{
				$entry->order_seller_srl = $bundle->order_seller_srl;
				$entry->bundle = $bundle;
			}
		}

		foreach ($entries as $entry)
		{
			$item_args = (object)[
				'order_item_srl' => getNextSequence(),
				'order_seller_srl' => (int)$entry->order_seller_srl,
				'order_srl' => $order_srl,
				'item_srl' => (int)$entry->item->item_srl,
				'option_srl' => $entry->option ? (int)$entry->option->option_srl : 0,
				'item_name' => (string)$entry->item->item_name,
				'option_name' => $entry->option ? (string)$entry->option->option_label : '',
				'sku' => $entry->option && trim((string)($entry->option->sku ?? '')) !== ''
					? trim((string)$entry->option->sku)
					: trim((string)($entry->item->item_code ?? '')),
				'thumb' => (string)($entry->item->thumb ?? ''),
				'price' => $entry->unit_price,
				'qty' => $entry->qty,
				'subtotal' => $entry->subtotal,
				'tax_type' => ($entry->item->tax_type ?? 'taxable') === 'free' ? 'free' : 'taxable',
				'claim_status' => 'none',
				'timesale_item_srl' => (int)($entry->timesale_item_srl ?? 0),
				'regdate' => $now,
			];
			if ($entry->bundle->market)
			{
				$item_args->seller_srl = $entry->bundle->seller_srl;
				$item_args->commission_rate = $entry->bundle->rate;
				$item_args->commission = (int)($entry->commission ?? 0);
			}
			executeQuery('commerce.insertOrderItem', $item_args);
			ItemModel::syncSoldout((int)$entry->item->item_srl);
		}

		executeQuery('commerce.insertOrderAddress', (object)[
			'address_srl' => getNextSequence(),
			'order_srl' => $order_srl,
			'receiver_name' => mb_substr($receiver_name, 0, 80),
			'receiver_phone' => mb_substr(trim((string)\Context::get('receiver_phone')) ?: $orderer_phone, 0, 30),
			'phone_cc' => mb_substr(preg_replace('/[^0-9+]/', '', (string)\Context::get('phone_cc')), 0, 6),
			'country' => self::filterCountry((string)\Context::get('country')),
			'state' => mb_substr(trim((string)\Context::get('state')), 0, 80),
			'city' => mb_substr(trim((string)\Context::get('city')), 0, 80),
			'zipcode' => mb_substr(trim((string)\Context::get('zipcode')), 0, 10),
			'address1' => mb_substr($address1, 0, 250),
			'address2' => mb_substr(trim((string)\Context::get('address2')), 0, 250),
			'delivery_memo' => mb_substr(trim((string)\Context::get('delivery_memo')), 0, 250),
			'regdate' => $now,
		]);

		if ($logged_info && !empty($logged_info->member_srl) && \Context::get('save_phone') === 'Y')
		{
			$new_phone = preg_replace('/[^0-9+]/', '', $orderer_phone);
			if ($new_phone !== '' && $new_phone !== (string)($logged_info->phone_number ?? ''))
			{
				\Zittme\Framework\DB::getInstance()->query(
					'UPDATE member SET phone_number = ? WHERE member_srl = ?',
					$new_phone, (int)$logged_info->member_srl
				);
				\MemberController::clearMemberCache((int)$logged_info->member_srl);
			}
		}

		$save_member_srl = ($logged_info && !empty($logged_info->member_srl)) ? (int)$logged_info->member_srl : 0;
		if ($save_member_srl > 0 && \Context::get('save_address') === 'Y')
		{
			$dup = false;
			$saved_output = executeQuery('commerce.getAddressList', (object)['member_srl' => $save_member_srl]);
			if ($saved_output->toBool() && !empty($saved_output->data))
			{
				foreach (is_array($saved_output->data) ? $saved_output->data : [$saved_output->data] as $saved)
				{
					if ((string)($saved->address1 ?? '') === $address1 && (string)($saved->address2 ?? '') === trim((string)\Context::get('address2')))
					{
						$dup = true;
						break;
					}
				}
			}
			if (!$dup)
			{
				executeQuery('commerce.insertAddress', (object)[
					'address_srl' => getNextSequence(),
					'member_srl' => $save_member_srl,
					'title' => mb_substr(trim((string)\Context::get('address_title')), 0, 60),
					'receiver_name' => mb_substr($receiver_name, 0, 80),
					'receiver_phone' => mb_substr(trim((string)\Context::get('receiver_phone')) ?: $orderer_phone, 0, 30),
					'phone_cc' => mb_substr(preg_replace('/[^0-9+]/', '', (string)\Context::get('phone_cc')), 0, 6),
					'country' => self::filterCountry((string)\Context::get('country')),
					'state' => mb_substr(trim((string)\Context::get('state')), 0, 80),
					'city' => mb_substr(trim((string)\Context::get('city')), 0, 80),
					'zipcode' => mb_substr(trim((string)\Context::get('zipcode')), 0, 10),
					'address1' => mb_substr($address1, 0, 250),
					'address2' => mb_substr(trim((string)\Context::get('address2')), 0, 250),
					'regdate' => $now,
				]);
			}
		}

		OrderModel::log($order_srl, $order_seller_srl, 'create', '', self::ORDER_PENDING, $member_srl);

		$notify_order = OrderModel::get($order_srl);
		OrderModel::notifyMail('new_order', $notify_order);
		OrderModel::notifyMail('received', $notify_order);

		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$result_url = getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order_code);

		if ($payment_price > 0)
		{
			if (!self::isPayAvailable())
			{
				OrderModel::cancelAndRestock($order_srl, $member_srl, 'pay unavailable');
				return new \BaseObject(-1, 'msg_shop_pay_unavailable');
			}
			$first = $entries[0];
			$pay = \Zittme\Modules\Zittme_pay\PayService::createOrder([
				'source_module' => 'commerce',
				'source_srl' => $order_srl,
				'source_code' => $order_code,
				'member_srl' => $member_srl,
				'amount' => $payment_price,
				'currency' => $order_currency,
				'title' => $first->item->item_name . (count($entries) > 1 ? ' 외 ' . (count($entries) - 1) . '건' : ''),
				'payer' => ['name' => $orderer_name, 'phone' => $orderer_phone, 'email' => (string)\Context::get('orderer_email')],
				'return_url' => $result_url,
			]);
			if (empty($pay->success))
			{
				OrderModel::cancelAndRestock($order_srl, $member_srl, 'pay create failed');
				return new \BaseObject(-1, $pay->message ?: 'msg_shop_pay_failed');
			}
			executeQuery('commerce.updateOrderStatusIf', (object)[
				'order_srl' => $order_srl,
				'status' => self::ORDER_PENDING,
				'from_status_list' => self::ORDER_PENDING,
				'pay_order_srl' => (int)$pay->order_srl,
			]);

			$this->add('order_code', $order_code);
			$this->setRedirectUrl((string)$pay->pay_url ?: $result_url);
			return;
		}

		OrderModel::markPaid($order_srl);
		$this->add('order_code', $order_code);
		$this->setRedirectUrl($result_url);
	}

	protected static function buildBundles(array $entries, ?object $seller, int $item_total, int $delivery_fee, int $discount_total, int $payment_price, string $fx_currency = ''): array
	{
		if (!\Zittme\Modules\Commerce\Models\Seller::isOpen())
		{
			return [(object)[
				'market' => false,
				'seller_srl' => $seller ? (int)$seller->seller_srl : 0,
				'entries' => $entries,
				'item_total' => $item_total,
				'delivery_fee' => $delivery_fee,
				'discount' => $discount_total,
				'settle_amount' => $payment_price,
				'commission' => 0,
				'rate' => 0,
			]];
		}

		$bundles = [];
		$fee_sum = 0;
		foreach (\Zittme\Modules\Commerce\Models\Seller::groupEntries($entries) as $seller_srl => $group)
		{
			$fee = \Zittme\Modules\Commerce\Models\Seller::groupShipFee((int)$seller_srl, $group);
			if ($fx_currency !== '')
			{
				$fee = max(0, \Zittme\Modules\Commerce\Models\Money::convertMinor($fee, $fx_currency));
			}
			$rate = \Zittme\Modules\Commerce\Models\Seller::commissionRate(\Zittme\Modules\Commerce\Models\Seller::get((int)$seller_srl));
			$group_total = 0;
			$commission = 0;
			foreach ($group as $entry)
			{
				$entry->commission = \Zittme\Modules\Commerce\Models\Seller::commissionOf((int)$entry->subtotal, $rate);
				$group_total += (int)$entry->subtotal;
				$commission += $entry->commission;
			}
			$fee_sum += $fee;
			$bundles[] = (object)[
				'market' => true,
				'seller_srl' => (int)$seller_srl,
				'entries' => $group,
				'item_total' => $group_total,
				'delivery_fee' => $fee,
				'discount' => 0,
				'settle_amount' => $group_total + $fee,
				'commission' => $commission,
				'rate' => $rate,
			];
		}
		if (!count($bundles))
		{
			return $bundles;
		}

		$first = $bundles[0];
		$first->operator_fee = max(0, $delivery_fee - $fee_sum);
		$first->delivery_fee = max(0, $first->delivery_fee + ($delivery_fee - $fee_sum));
		$first->discount = $discount_total;
		$others = 0;
		foreach (array_slice($bundles, 1) as $bundle)
		{
			$others += $bundle->settle_amount;
		}
		$first->settle_amount = $payment_price - $others;
		return $bundles;
	}

	public function procCommerceGuestLookup()
	{
		$code = trim((string)\Context::get('order_code'));
		$order = $code !== '' ? OrderModel::getByCode($code) : null;
		if (!$order)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}
		$raw = (string)\Context::get('guest_password');
		if ((int)$order->member_srl > 0 || $raw === '' || empty($order->guest_password)
			|| !\Zittme\Framework\Password::checkPassword($raw, $order->guest_password))
		{
			return new \BaseObject(-1, 'msg_shop_wrong_password');
		}

		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order->order_code, 'gp', $raw));
	}

	public function procCommerceConfirmPurchase()
	{
		$code = trim((string)\Context::get('order_code'));
		$order = $code !== '' ? OrderModel::getByCode($code) : null;
		if (!$order)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		if ((int)$order->member_srl > 0)
		{
			if ($member_srl !== (int)$order->member_srl)
			{
				return new \BaseObject(-1, 'msg_shop_not_yours');
			}
		}
		else
		{
			$raw = (string)\Context::get('guest_password');
			if ($raw === '' || empty($order->guest_password)
				|| !\Zittme\Framework\Password::checkPassword($raw, $order->guest_password))
			{
				return new \BaseObject(-1, 'msg_shop_wrong_password');
			}
		}

		if ($order->status !== self::ORDER_PAID)
		{
			return new \BaseObject(-1, 'msg_shop_confirm_not_allowed');
		}

		$output = executeQuery('commerce.updateOrderSellersStatus', (object)[
			'order_srl' => (int)$order->order_srl,
			'status' => self::SELLER_CONFIRMED,
			'from_status_list' => self::SELLER_DELIVERED,
		]);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('msg_shop_confirmed');
		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);

		$confirm_items = OrderModel::getItems((int)$order->order_srl);
		$distinct = [];
		foreach ($confirm_items as $ci)
		{
			if ((int)$ci->item_srl > 0)
			{
				$distinct[(int)$ci->item_srl] = true;
			}
		}
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order->order_code, 'gp', (string)\Context::get('guest_password'), 'review', '1'));
	}

	public function procCommercePinReveal()
	{
		$code = trim((string)\Context::get('order_code'));
		$order = $code !== '' ? OrderModel::getByCode($code) : null;
		if (!$order || $order->status !== self::ORDER_PAID)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}
		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		if ((int)$order->member_srl > 0)
		{
			if ($member_srl !== (int)$order->member_srl)
			{
				return new \BaseObject(-1, 'msg_shop_not_yours');
			}
		}
		else
		{
			$raw = (string)\Context::get('guest_password');
			if ($raw === '' || empty($order->guest_password) || !\Zittme\Framework\Password::checkPassword($raw, $order->guest_password))
			{
				return new \BaseObject(-1, 'msg_shop_wrong_password');
			}
		}
		$order_item_srl = (int)\Context::get('order_item_srl');
		$pins = \Zittme\Modules\Commerce\Models\Pin::reveal($order_item_srl, (int)$order->order_srl);
		if (!count($pins))
		{
			return new \BaseObject(-1, lang('commerce.pin_msg_none'));
		}
		$this->add('pins', $pins);
	}

	public function procCommerceClaim()
	{
		$code = trim((string)\Context::get('order_code'));
		$order = $code !== '' ? OrderModel::getByCode($code) : null;
		if (!$order)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		if ((int)$order->member_srl > 0)
		{
			if ($member_srl !== (int)$order->member_srl)
			{
				return new \BaseObject(-1, 'msg_shop_not_yours');
			}
		}
		else
		{
			$raw = (string)\Context::get('guest_password');
			if ($raw === '' || empty($order->guest_password)
				|| !\Zittme\Framework\Password::checkPassword($raw, $order->guest_password))
			{
				return new \BaseObject(-1, 'msg_shop_wrong_password');
			}
		}

		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$result_url = getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order->order_code, 'gp', (string)\Context::get('guest_password'));

		if ($order->status === self::ORDER_PENDING)
		{
			OrderModel::cancelAndRestock((int)$order->order_srl, $member_srl, 'buyer cancel (pending)');
			$this->setMessage('msg_shop_cancelled');
			$this->setRedirectUrl($result_url);
			return;
		}

		if ($order->status !== self::ORDER_PAID)
		{
			return new \BaseObject(-1, 'msg_shop_claim_not_allowed');
		}
		if (\Zittme\Modules\Commerce\Models\Pin::revealedCount((int)$order->order_srl) > 0)
		{
			return new \BaseObject(-1, lang('commerce.pin_msg_no_cancel'));
		}

		$claim_type = in_array(\Context::get('claim_type'), ['cancel', 'return', 'exchange'], true)
			? (string)\Context::get('claim_type') : 'cancel';
		$all_sellers = OrderModel::getSellerOrders((int)$order->order_srl);
		$want_os = (int)\Context::get('order_seller_srl');
		$target_os = null;
		foreach ($all_sellers as $one_os)
		{
			if ($want_os > 0 ? (int)$one_os->order_seller_srl === $want_os : count($all_sellers) === 1)
			{
				$target_os = $one_os;
			}
		}
		if (!$target_os)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		if (in_array($target_os->status, [self::SELLER_CONFIRMED, self::SELLER_CANCELLED, self::SELLER_REFUNDED], true))
		{
			return new \BaseObject(-1, 'msg_shop_claim_not_allowed');
		}
		$sellers = [$target_os];
		$seller_status = (string)$target_os->status;
		if ($claim_type === 'cancel' && in_array($seller_status, [self::SELLER_SHIPPING, self::SELLER_DELIVERED], true))
		{
			$claim_type = 'return';
		}
		$delivered = $target_os->status === self::SELLER_DELIVERED;
		if ($delivered && !empty($target_os->delivered_date))
		{
			$claim_days = max(0, (int)(self::config()->claim_days ?? 7));
			$deadline = date('YmdHis', strtotime(substr($target_os->delivered_date, 0, 8)) + 86400 * ($claim_days + 1));
			if (self::now() > $deadline)
			{
				return new \BaseObject(-1, 'msg_shop_claim_deadline');
			}
		}

		$targets = [];
		foreach (OrderModel::getItems((int)$order->order_srl) as $oi)
		{
			if ((int)($oi->order_seller_srl ?? 0) !== (int)$target_os->order_seller_srl)
			{
				continue;
			}
			if (($oi->claim_status ?? 'none') === 'none')
			{
				$targets[] = ['order_item_srl' => (int)$oi->order_item_srl, 'qty' => (int)$oi->qty];
			}
		}
		if (!count($targets))
		{
			return new \BaseObject(-1, 'msg_shop_claim_already');
		}

		executeQuery('commerce.insertClaim', (object)[
			'claim_srl' => getNextSequence(),
			'order_srl' => (int)$order->order_srl,
			'order_seller_srl' => count($sellers) ? (int)$sellers[0]->order_seller_srl : 0,
			'member_srl' => $member_srl,
			'claim_type' => $claim_type,
			'reason' => mb_substr(trim((string)\Context::get('reason')), 0, 2000),
			'items' => json_encode($targets),
			'status' => 'requested',
			'refund_amount' => 0,
			'restock' => 'Y',
			'regdate' => self::now(),
		]);
		OrderModel::log((int)$order->order_srl, 0, 'claim', '', 'requested', $member_srl, $claim_type);
		OrderModel::notifyMail('claim', $order, '유형: ' . $claim_type . ' / 사유: ' . mb_substr(trim((string)\Context::get('reason')), 0, 200));

		foreach ($targets as $t)
		{
			\Zittme\Framework\DB::getInstance()->query(
				'UPDATE commerce_order_item SET claim_status = ? WHERE order_item_srl = ?',
				'requested', (int)$t['order_item_srl']
			);
		}

		$this->setMessage('msg_shop_claim_requested');
		$this->setRedirectUrl($result_url);
	}

	public static function filterCountry(string $country): string
	{
		$country = strtoupper(trim($country));
		if (strlen($country) !== 2 || !ctype_alpha($country))
		{
			return AddressModel::baseCountry();
		}
		if (!AddressModel::needsCountry() && !AddressModel::isDomestic($country))
		{
			return AddressModel::baseCountry();
		}
		return $country;
	}

	public static function isAdultVerified(int $member_srl): bool
	{
		if ($member_srl <= 0)
		{
			return false;
		}
		if (!class_exists('\\Zittme\\Modules\\Member\\Identity\\IdentityModel'))
		{
			return false;
		}
		$identity = \Zittme\Modules\Member\Identity\IdentityModel::getInstance()->getByMemberSrl($member_srl);
		if (!$identity || empty($identity->birthday))
		{
			return false;
		}
		$age = \Zittme\Modules\Member\Identity\Base::getAgeFromBirthday((string)$identity->birthday);
		return $age >= 19;
	}
}
