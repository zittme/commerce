<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Coupon as CouponModel;
use Zittme\Modules\Commerce\Models\Credit as CreditModel;
use Zittme\Modules\Commerce\Models\Item as ItemModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;
use Zittme\Modules\Commerce\Models\Stock;

/**
 * 주문 생성·조회·클레임.
 *
 * ★ 철칙: 금액은 브라우저 값을 절대 믿지 않는다 — 장바구니를 서버에서 다시 해석해
 *   단가·합계·배송비를 전부 재계산한다. 재고는 품목마다 원자 선점하고,
 *   중간에 하나라도 실패하면 이미 선점한 것을 전부 되돌린다.
 */
class Order extends Base
{
	/**
	 * 주문 생성 — 장바구니 전체 주문.
	 */
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

		// 주문자·배송지
		$orderer_name = trim((string)\Context::get('orderer_name'));
		$orderer_phone = trim((string)\Context::get('orderer_phone'));
		$receiver_name = trim((string)\Context::get('receiver_name')) ?: $orderer_name;
		$address1 = trim((string)\Context::get('address1'));
		if ($orderer_name === '' || $orderer_phone === '' || $address1 === '')
		{
			return new \BaseObject(-1, 'msg_shop_need_fields');
		}

		// 비회원 조회 비밀번호
		$guest_password = '';
		if ($member_srl <= 0)
		{
			$raw = (string)\Context::get('guest_password');
			if (strlen($raw) < 4)
			{
				return new \BaseObject(-1, 'msg_shop_need_password');
			}
			$guest_password = \Rhymix\Framework\Password::hashPassword($raw);
		}

		if (\Context::get('agree_privacy') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_shop_need_agreement');
		}

		// ★ 서버 재계산 — 장바구니를 지금 시점 가격으로 다시 해석
		$owner = CartModel::owner();
		$resolved = CartModel::resolve($owner);
		$entries = array_values(array_filter($resolved->items, function($e) { return !$e->blocked; }));
		if (!count($entries))
		{
			return new \BaseObject(-1, 'msg_shop_cart_empty');
		}

		// 성인 상품 게이트 — 본인인증으로 성인 확인된 회원만
		foreach ($entries as $entry)
		{
			if (($entry->item->is_adult ?? 'N') === 'Y' && !self::isAdultVerified($member_srl))
			{
				return new \BaseObject(-1, 'msg_shop_adult_required');
			}
		}

		$item_total = $resolved->item_total;
		$delivery_fee = CartModel::calcShipFee($resolved);

		// ★ 재고 원자 선점 — 실패 시 이미 선점한 것 전부 롤백
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

		$order_srl = getNextSequence();

		// 쿠폰 (회원 전용) — 원자 점유. 이 아래에서 실패하면 재고와 함께 반환한다
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
				return new \BaseObject(-1, $coupon_error);
			}
		}

		// 적립금 사용 (회원 전용, 원자 차감 — 잔액 부족이면 실패)
		$credit_used = 0;
		$want_credit = max(0, (int)\Context::get('use_credit'));
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
					CouponModel::releaseByOrder($order_srl);
					return new \BaseObject(-1, 'msg_shop_credit_insufficient');
				}
				$credit_used = $want_credit;
			}
		}

		$payment_price = max(0, $item_total - $discount_total - $credit_used) + $delivery_fee;

		// 주문 3계층 생성 (독립몰: order_seller 1건 — 분기하지 않는 규약)
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
			CouponModel::releaseByOrder($order_srl);
			if ($credit_used > 0)
			{
				CreditModel::add($member_srl, $credit_used, 'refund', $order_srl, 'order insert failed');
			}
			return $output;
		}

		$order_seller_srl = getNextSequence();
		executeQuery('commerce.insertOrderSeller', (object)[
			'order_seller_srl' => $order_seller_srl,
			'order_srl' => $order_srl,
			'seller_srl' => $seller ? (int)$seller->seller_srl : 0,
			'item_total' => $item_total,
			'delivery_fee' => $delivery_fee,
			'discount' => $discount_total,
			'settle_amount' => $payment_price,
			'status' => self::SELLER_PENDING,
			'regdate' => $now,
		]);

		foreach ($entries as $entry)
		{
			executeQuery('commerce.insertOrderItem', (object)[
				'order_item_srl' => getNextSequence(),
				'order_seller_srl' => $order_seller_srl,
				'order_srl' => $order_srl,
				'item_srl' => (int)$entry->item->item_srl,
				'option_srl' => $entry->option ? (int)$entry->option->option_srl : 0,
				'item_name' => (string)$entry->item->item_name,
				'option_name' => $entry->option ? (string)$entry->option->option_label : '',
				'thumb' => (string)($entry->item->thumb ?? ''),
				'price' => $entry->unit_price,
				'qty' => $entry->qty,
				'subtotal' => $entry->subtotal,
				'claim_status' => 'none',
				'regdate' => $now,
			]);
			ItemModel::syncSoldout((int)$entry->item->item_srl);
		}

		executeQuery('commerce.insertOrderAddress', (object)[
			'address_srl' => getNextSequence(),
			'order_srl' => $order_srl,
			'receiver_name' => mb_substr($receiver_name, 0, 80),
			'receiver_phone' => mb_substr(trim((string)\Context::get('receiver_phone')) ?: $orderer_phone, 0, 30),
			'zipcode' => mb_substr(trim((string)\Context::get('zipcode')), 0, 10),
			'address1' => mb_substr($address1, 0, 250),
			'address2' => mb_substr(trim((string)\Context::get('address2')), 0, 250),
			'delivery_memo' => mb_substr(trim((string)\Context::get('delivery_memo')), 0, 250),
			'regdate' => $now,
		]);

		OrderModel::log($order_srl, $order_seller_srl, 'create', '', self::ORDER_PENDING, $member_srl);

		// 주문된 항목만 장바구니에서 제거
		CartModel::removeMany(array_map(function($e) { return $e->cart_srl; }, $entries));

		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$result_url = getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order_code);

		// 결제 — 0원(전액 무료)이 아니면 zittme_pay 로
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
				'member_srl' => $member_srl,
				'amount' => $payment_price,
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

		// 0원 주문: 즉시 결제 완료 처리
		OrderModel::markPaid($order_srl);
		$this->add('order_code', $order_code);
		$this->setRedirectUrl($result_url);
	}

	/**
	 * 비회원 주문 조회.
	 */
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
			|| !\Rhymix\Framework\Password::checkPassword($raw, $order->guest_password))
		{
			return new \BaseObject(-1, 'msg_shop_wrong_password');
		}

		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order->order_code, 'gp', $raw));
	}

	/**
	 * 구매자 클레임 — 결제대기/결제완료는 취소, 배송완료 후에는 반품·교환 신청.
	 *
	 * 결제대기(pending) 주문은 즉시 취소(돈이 안 나갔으므로 승인 불필요).
	 * 그 외에는 클레임을 만들어 관리자가 승인·환불한다.
	 */
	public function procCommerceClaim()
	{
		$code = trim((string)\Context::get('order_code'));
		$order = $code !== '' ? OrderModel::getByCode($code) : null;
		if (!$order)
		{
			return new \BaseObject(-1, 'msg_shop_order_not_found');
		}

		// 본인 확인 (회원 본인 / 비회원 비밀번호)
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
				|| !\Rhymix\Framework\Password::checkPassword($raw, $order->guest_password))
			{
				return new \BaseObject(-1, 'msg_shop_wrong_password');
			}
		}

		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$result_url = getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order->order_code, 'gp', (string)\Context::get('guest_password'));

		// 결제대기: 즉시 취소
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

		// 배송완료 후 신청 기한 검사 (반품·교환)
		$claim_type = in_array(\Context::get('claim_type'), ['cancel', 'return', 'exchange'], true)
			? (string)\Context::get('claim_type') : 'cancel';
		$sellers = OrderModel::getSellerOrders((int)$order->order_srl);
		$delivered = count($sellers) && $sellers[0]->status === self::SELLER_DELIVERED;
		if ($delivered && !empty($sellers[0]->delivered_date))
		{
			$claim_days = max(0, (int)(self::config()->claim_days ?? 7));
			$deadline = date('YmdHis', strtotime(substr($sellers[0]->delivered_date, 0, 8)) + 86400 * ($claim_days + 1));
			if (self::now() > $deadline)
			{
				return new \BaseObject(-1, 'msg_shop_claim_deadline');
			}
		}

		// 대상: 전체 품목 (품목 선택 취소는 관리자 승인 화면에서 조정)
		$targets = [];
		foreach (OrderModel::getItems((int)$order->order_srl) as $oi)
		{
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

		// 품목을 신청 상태로 표시 (중복 신청 방지)
		foreach ($targets as $t)
		{
			\Rhymix\Framework\DB::getInstance()->query(
				'UPDATE commerce_order_item SET claim_status = ? WHERE order_item_srl = ?',
				'requested', (int)$t['order_item_srl']
			);
		}

		$this->setMessage('msg_shop_claim_requested');
		$this->setRedirectUrl($result_url);
	}

	/**
	 * 성인 인증 여부 — member/identity 본인인증 기록의 생년월일로 판정.
	 *
	 * @param int $member_srl
	 * @return bool
	 */
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
