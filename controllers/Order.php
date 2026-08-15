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
 * 금액은 브라우저 값을 쓰지 않는다. 장바구니를 서버에서 다시 해석해
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

		// 해외 주소는 우편번호가 없는 국가가 있어 선택 입력이고, 대신 도시를 받는다
		if (\Zittme\Modules\Commerce\Models\Address::isOverseasInput((string)\Context::get('country'))
			&& trim((string)\Context::get('city')) === '')
		{
			return new \BaseObject(-1, 'msg_shop_need_city');
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
			$guest_password = \Zittme\Framework\Password::hashPassword($raw);
		}

		if (\Context::get('agree_privacy') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_shop_need_agreement');
		}

		// 서버 재계산 — 장바구니를 지금 시점 가격으로 다시 해석
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
		// 지역 추가 배송비. 지역별 면제 기준을 보므로 상품 금액도 함께 넘긴다
		$delivery_fee += CartModel::extraShipFee(
			(string)\Context::get('zipcode'),
			(string)\Context::get('address1'),
			self::filterCountry((string)\Context::get('country')),
			(string)\Context::get('state'),
			(string)\Context::get('city'),
			(int)$item_total
		);

		// 재고 원자 선점 — 실패 시 이미 선점한 것 전부 롤백
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

		// 주문 통화 — 표시 통화로 결제한다. 기준 통화 주문이 기본이고, 기준이 KRW 인
		// 상점의 외화 병행 판매 주문만 환율이 붙는다. 병행 판매 주문은 기준 통화 원장의
		// 쿠폰·적립금을 지원하지 않으므로 입력을 무시한다 (주문서 화면도 해당 칸을 숨긴다)
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
				return new \BaseObject(-1, 'msg_shop_fx_unavailable');
			}
			\Context::set('coupon_issue_srl', 0);
			\Context::set('coupon_code', '');
			\Context::set('use_credit', 0);
		}

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

		// 외화 병행 판매 주문 — 상품·배송비를 주문 통화(최소단위 정수)로 재계산한다.
		// 통화별 등록가가 있으면 그 값을, 없으면 설정에 따라 환산가를 쓴다.
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
					return new \BaseObject(-1, sprintf(lang('commerce.msg_shop_fx_not_sellable'), (string)$entry->item->item_name));
				}
				// 품목 스냅샷(order_item)도 주문 통화로 남아야 한다. KRW 단가가 섞이면
				// 명세서·환불 계산이 전부 어긋난다.
				$entry->unit_price = $fx_unit + $fx_add;
				$entry->subtotal = $entry->unit_price * $entry->qty;
				$fx_item_total += $entry->subtotal;
			}
			$item_total = $fx_item_total;
			$delivery_fee = max(0, \Zittme\Modules\Commerce\Models\Money::convertMinor($delivery_fee, $order_currency));
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
				// 명세서·출고용 SKU 스냅샷 — 옵션 SKU 우선, 없으면 상품 코드
				'sku' => $entry->option && trim((string)($entry->option->sku ?? '')) !== ''
					? trim((string)$entry->option->sku)
					: trim((string)($entry->item->item_code ?? '')),
				'thumb' => (string)($entry->item->thumb ?? ''),
				'price' => $entry->unit_price,
				'qty' => $entry->qty,
				'subtotal' => $entry->subtotal,
				// 상품 설정이 나중에 바뀌어도 명세서의 과세 구분은 그대로 남아야 한다
				'tax_type' => ($entry->item->tax_type ?? 'taxable') === 'free' ? 'free' : 'taxable',
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

		// 연락처 저장 (회원, 요청 시): 회원 정보의 전화번호를 주문자 연락처로 갱신한다
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

		// 배송지 저장 (회원, 요청 시): 같은 주소가 이미 있으면 중복 저장하지 않는다
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

		// 주문 알림 메일 — 관리자(신규 주문) + 구매자(접수 안내)
		$notify_order = OrderModel::get($order_srl);
		OrderModel::notifyMail('new_order', $notify_order);
		OrderModel::notifyMail('received', $notify_order);

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
			|| !\Zittme\Framework\Password::checkPassword($raw, $order->guest_password))
		{
			return new \BaseObject(-1, 'msg_shop_wrong_password');
		}

		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order->order_code, 'gp', $raw));
	}

	/**
	 * 구매확정 — 배송완료 주문을 구매자가 확정한다. 확정한 상품만 리뷰를 쓸 수 있다.
	 */
	public function procCommerceConfirmPurchase()
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
				|| !\Zittme\Framework\Password::checkPassword($raw, $order->guest_password))
			{
				return new \BaseObject(-1, 'msg_shop_wrong_password');
			}
		}

		if ($order->status !== self::ORDER_PAID)
		{
			return new \BaseObject(-1, 'msg_shop_confirm_not_allowed');
		}

		// 배송완료 상태의 하위주문만 확정으로 전이한다
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

		// 구매확정 후 리뷰 작성 유도: 단일 상품이면 상품 리뷰 폼으로, 여러 상품이면 주문 상세의 리뷰 안내로
		$confirm_items = OrderModel::getItems((int)$order->order_srl);
		$distinct = [];
		foreach ($confirm_items as $ci)
		{
			if ((int)$ci->item_srl > 0)
			{
				$distinct[(int)$ci->item_srl] = true;
			}
		}
		// 확정 후에는 주문 상세로 돌아가 확정 상태를 보여준다. 리뷰는 거기서 이어 쓴다
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceOrderResult', 'code', $order->order_code, 'gp', (string)\Context::get('guest_password'), 'review', '1'));
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
				|| !\Zittme\Framework\Password::checkPassword($raw, $order->guest_password))
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
		// 배송 중부터는 취소가 아니라 반품으로 처리한다 (회수 물류 필요)
		$seller_status = count($sellers) ? (string)$sellers[0]->status : '';
		if ($claim_type === 'cancel' && in_array($seller_status, [self::SELLER_SHIPPING, self::SELLER_DELIVERED], true))
		{
			$claim_type = 'return';
		}
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
		OrderModel::notifyMail('claim', $order, '유형: ' . $claim_type . ' / 사유: ' . mb_substr(trim((string)\Context::get('reason')), 0, 200));

		// 품목을 신청 상태로 표시 (중복 신청 방지)
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

	/**
	 * 배송 국가 코드 정리. 값이 없거나 형식이 아니면 국내(KR)로 본다.
	 *
	 * @param string $country
	 * @return string
	 */
	public static function filterCountry(string $country): string
	{
		$country = strtoupper(trim($country));
		if (strlen($country) !== 2 || !ctype_alpha($country))
		{
			return 'KR';
		}
		return $country;
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
