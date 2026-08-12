@include('_tabs')

<div class="rsva">
	@php $rsva_return = getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $order->order_srl); @endphp

	<div class="rsva-panel">
		<h3>주문 {{ $order->order_code }}
			<span class="rsva-st {{ $order->status === 'paid' ? 'rsva-st-confirmed' : ($order->status === 'pending' ? 'rsva-st-hold' : 'rsva-st-cancelled') }}">{{ ['pending'=>'결제대기','paid'=>'결제완료','cancelled'=>'취소','failed'=>'실패','expired'=>'만료'][$order->status] ?? $order->status }}</span>
		</h3>
		<div class="rsva-form-grid">
			<div><label>{{ lang('commerce.admin_order_view_1') }}</label><div>{{ $order->orderer_name }} / {{ $order->orderer_phone }} @if($order->orderer_email)/ {{ $order->orderer_email }}@endif</div></div>
			<div><label>{{ lang('commerce.admin_order_view_2') }}</label><div><strong>{{ shop_money_in($order->payment_price, $order->currency ?? 'KRW') }}</strong> (상품 {{ shop_money_in($order->item_total, $order->currency ?? 'KRW') }} + 배송 {{ shop_money_in($order->delivery_fee_total, $order->currency ?? 'KRW') }})</div></div>
			@if ($pay_order)
			<div style="grid-column:1/-1">
				<label>{{ lang('commerce.admin_order_view_3') }}</label>
				<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispZittme_payAdminOrderView', 'order_srl', $pay_order->order_srl) }}" target="_blank"><strong>{{ $pay_order->order_code }}</strong></a>
					<span class="rsva-st {{ $pay_order->status === 'paid' ? 'rsva-st-confirmed' : ($pay_order->status === 'pending' ? 'rsva-st-hold' : '') }}">{{ ['ready'=>'준비','pending'=>'입금대기','paid'=>'결제완료','cancelled'=>'취소','partial_cancelled'=>'부분취소','failed'=>'실패','expired'=>'만료'][$pay_order->status] ?? $pay_order->status }}</span>
					<small style="color:#8b95a1">{{ $pay_order->gateway }}</small>
					@if (in_array($pay_order->status, ['ready', 'pending'], true))
					<form action="./" method="post" style="display:inline" onsubmit="return confirm('입금을 확인하셨습니까? 확인 처리하면 주문이 결제완료로 바뀝니다.')">
						<input type="hidden" name="module" value="zittme_pay" />
						<input type="hidden" name="act" value="procZittme_payAdminConfirmDeposit" />
						<input type="hidden" name="order_srl" value="{{ $pay_order->order_srl }}" />
						<input type="hidden" name="success_return_url" value="{{ $rsva_return }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_order_view_4') }}</button>
					</form>
					@endif
				</div>
			</div>
			@endif
			@if ($order_address)
			<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_order_view_5') }}</label><div>{{ $order_address->receiver_name }} / {{ $order_phone_text }} — {{ $order_address_text }} @if($order_address->delivery_memo)<small style="color:#6b7684">({{ $order_address->delivery_memo }})</small>@endif</div></div>
			@endif
		</div>
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_order_view_6') }}</h3>
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_order_view_7') }}</th><th>{{ lang('commerce.admin_order_view_8') }}</th><th>{{ lang('commerce.admin_order_view_9') }}</th><th>{{ lang('commerce.admin_order_view_10') }}</th><th>{{ lang('commerce.admin_order_view_11') }}</th></tr></thead>
			<tbody>
				@foreach ($order_items as $oi)
				<tr>
					<td>{{ $oi->item_name }}@if(($oi->tax_type ?? 'taxable') === 'free') <span class="rsva-st">{{ lang('commerce.admin_order_view_12') }}</span>@endif@if($oi->option_name)<br /><small style="color:#6b7684">{{ $oi->option_name }}</small>@endif</td>
					<td>{{ shop_money_in($oi->price, $order->currency ?? 'KRW') }}</td>
					<td>{{ $oi->qty }}</td>
					<td>{{ shop_money_in($oi->subtotal, $order->currency ?? 'KRW') }}</td>
					<td>{{ ['none'=>'-','requested'=>'신청됨','done'=>'처리완료'][$oi->claim_status] ?? $oi->claim_status }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>

	@foreach ($order_sellers as $os)
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_order_view_13') }}
			<span class="rsva-st {{ in_array($os->status, ['shipping','delivered']) ? 'rsva-st-confirmed' : '' }}">{{ ['pending'=>'결제대기','paid'=>'발주 대기','preparing'=>'배송준비','shipping'=>'배송중','delivered'=>'배송완료','cancelled'=>'취소','refunded'=>'환불'][$os->status] ?? $os->status }}</span>
			@if ($os->shipping_invoice)<small style="font-weight:500;color:#6b7684">{{ $os->shipping_company }} {{ $os->shipping_invoice }}</small>@endif
		</h3>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminUpdateOrder" />
			<input type="hidden" name="order_srl" value="{{ $order->order_srl }}" />
			<input type="hidden" name="success_return_url" value="{{ $rsva_return }}" />
			@if ($os->status === 'paid')
			<div><button type="submit" name="order_action" value="confirm" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_order_view_14') }}</button></div>
			@endif
			@if (in_array($os->status, ['paid', 'preparing']))
			<div><label>{{ lang('commerce.admin_order_view_15') }}</label><input type="text" name="shipping_company" placeholder="{{ lang('commerce.admin_order_view_31') }}" style="width:130px" /></div>
			<div><label>{{ lang('commerce.admin_order_view_16') }}</label><input type="text" name="shipping_invoice" style="width:160px" /></div>
			<div><button type="submit" name="order_action" value="ship" class="rsva-btn">{{ lang('commerce.admin_order_view_17') }}</button></div>
			@endif
			@if ($os->status === 'shipping')
			<div><button type="submit" name="order_action" value="deliver" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_order_view_18') }}</button></div>
			@endif
			@if (in_array($order->status, ['pending', 'paid']))
			<div><button type="submit" name="order_action" value="cancel" class="rsva-btn rsva-btn-danger" onclick="return confirm('주문을 전체 취소하고 환불·재입고 처리합니다. 계속할까요?')">{{ lang('commerce.admin_order_view_19') }}</button></div>
			@endif
		</form>
	</div>
	@endforeach

	@if (!empty($order_claims))
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_order_view_11') }}</h3>
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_order_view_20') }}</th><th>{{ lang('commerce.admin_order_view_21') }}</th><th>{{ lang('commerce.admin_order_view_22') }}</th><th>{{ lang('commerce.admin_order_view_23') }}</th><th>{{ lang('commerce.admin_order_view_24') }}</th></tr></thead>
			<tbody>
				@foreach ($order_claims as $c)
				<tr>
					<td>{{ ['cancel'=>'취소','return'=>'반품','exchange'=>'교환'][$c->claim_type] ?? $c->claim_type }}</td>
					<td>{{ $c->reason ?: '-' }}</td>
					<td><span class="rsva-st">{{ ['requested'=>'신청','approved'=>'승인','rejected'=>'거절','done'=>'완료'][$c->status] ?? $c->status }}</span></td>
					<td>{{ $c->refund_amount > 0 ? shop_money_in($c->refund_amount, $order->currency ?? 'KRW') : '-' }}</td>
					<td><small>{{ zdate($c->regdate, 'm.d H:i') }}</small></td>
				</tr>
				@endforeach
			</tbody>
		</table>
		<p style="margin:8px 0 0;font-size:13px"><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminClaims') }}" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_order_view_25') }}</a></p>
	</div>
	@endif

	@if (!empty($order_logs))
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_order_view_26') }}</h3>
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_order_view_24') }}</th><th>{{ lang('commerce.admin_order_view_27') }}</th><th>{{ lang('commerce.admin_order_view_22') }}</th><th>{{ lang('commerce.admin_order_view_28') }}</th></tr></thead>
			<tbody>
				@php
				$zmc_act_labels = ['create' => '주문 접수', 'pay' => '결제', 'confirm' => '주문 확인', 'ship' => '발송', 'deliver' => '배송 완료', 'purchase_confirm' => '구매확정', 'cancel' => '취소', 'claim' => '클레임 접수', 'refund' => '환불', 'expire' => '기한 만료', 'stock' => '재고 조정'];
				$zmc_st_labels = ['pending' => '결제 대기', 'paid' => '결제 완료', 'preparing' => '배송 준비', 'shipping' => '배송 중', 'delivered' => '배송 완료', 'confirmed' => '구매확정', 'cancelled' => '취소됨', 'failed' => '결제 실패', 'expired' => '기한 만료', 'requested' => '신청됨', 'approved' => '승인됨', 'rejected' => '반려됨', 'completed' => '처리 완료'];
				@endphp
				@foreach ($order_logs as $lg)
				<tr>
					<td><small>{{ zdate($lg->regdate, 'm.d H:i:s') }}</small></td>
					<td>{{ $zmc_act_labels[$lg->action] ?? $lg->action }}</td>
					<td>@if($lg->before_status){{ $zmc_st_labels[$lg->before_status] ?? $lg->before_status }}@endif @if($lg->after_status)→ {{ $zmc_st_labels[$lg->after_status] ?? $lg->after_status }}@endif</td>
					<td>{{ $lg->memo ?: '-' }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>
	@endif

	<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrders') }}" class="rsva-btn">{{ lang('commerce.admin_order_view_29') }}</a>
	<a href="{{ getUrl('', 'mid', '', 'module', 'commerce', 'act', 'dispCommerceAdminOrderInvoice', 'order_srl', $order->order_srl) }}" class="rsva-btn rsva-btn-primary" target="_blank" rel="noopener" data-zmc-keep>{{ lang('commerce.admin_order_view_30') }}</a>
</div>
