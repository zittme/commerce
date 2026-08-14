<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;

/**
 * 트리거 수신.
 *
 * 결제 핸들러는 PG 콜백 요청 안에서 실행되므로 세션에 접근하지 않는다.
 * eventHandler 는 모듈 설치·업데이트 때 DB 에 등록되어야 동작한다.
 */
class Trigger extends Base
{
	/**
	 * 사이트맵 "모듈 연결" 목록에서 커머스를 제외한다 (단일 인스턴스 모델).
	 * 설치 시 shop mid 가 자동 생성되므로 추가 인스턴스를 만들면 안 된다.
	 *
	 * @param array $moduleList (참조)
	 * @return void
	 */
	/**
	 * 로그인하면 비회원으로 담아 둔 장바구니를 회원에게 넘긴다.
	 *
	 * 장바구니 소유자가 쿠키 키에서 회원 번호로 바뀌므로, 옮기지 않으면
	 * 담아 둔 것이 사라진 것처럼 보인다.
	 *
	 * @param object $member_info
	 * @return \BaseObject
	 */
	public function triggerAfterLogin($member_info)
	{
		$member_srl = (int)($member_info->member_srl ?? 0);
		if ($member_srl > 0)
		{
			// 장바구니 이관이 실패해도 로그인 자체는 막지 않는다
			try
			{
				CartModel::mergeGuestCart($member_srl);
			}
			catch (\Throwable $e)
			{
			}
		}
		return new \BaseObject();
	}

	public function triggerModuleListInSitemap(&$moduleList)
	{
		if (is_array($moduleList))
		{
			$moduleList = array_values(array_diff($moduleList, ['commerce']));
		}
	}

	/**
	 * 결제 승인 → 주문 paid 전이(멱등) + 재고 확정.
	 *
	 * @param object $pay_order zittme_pay 주문
	 * @return void
	 */
	public function triggerPayApproved($pay_order)
	{
		if (!is_object($pay_order) || ($pay_order->source_module ?? '') !== 'commerce')
		{
			return;
		}
		$order_srl = (int)($pay_order->source_srl ?? 0);
		if ($order_srl <= 0)
		{
			return;
		}
		OrderModel::markPaid($order_srl);
	}

	/**
	 * 결제 취소/환불 → 주문 취소 + 재고 반환.
	 *
	 * @param object $pay_order
	 * @return void
	 */
	public function triggerPayCancelled($pay_order)
	{
		if (!is_object($pay_order) || ($pay_order->source_module ?? '') !== 'commerce')
		{
			return;
		}
		$order_srl = (int)($pay_order->source_srl ?? 0);
		if ($order_srl <= 0)
		{
			return;
		}
		// 전액 취소일 때만 주문 전체 취소. 부분 환불은 클레임 흐름이 처리한다.
		$cancelled = (int)($pay_order->cancelled_amount ?? 0);
		$amount = (int)($pay_order->amount ?? 0);
		if ($amount > 0 && $cancelled >= $amount)
		{
			OrderModel::cancelAndRestock($order_srl, 0, 'pay cancelled');
		}
	}
}
