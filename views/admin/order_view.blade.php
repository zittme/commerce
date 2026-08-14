@include('_tabs')

<div class="rsva">
	@php $rsva_return = getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $order->order_srl); @endphp

	<div class="rsva-panel">
		<h3>{{ sprintf(lang('commerce.adm_order_of'), $order->order_code) }}
			<span class="rsva-st {{ $order->status === 'paid' ? 'rsva-st-confirmed' : ($order->status === 'pending' ? 'rsva-st-hold' : 'rsva-st-cancelled') }}">{{ ['pending'=>lang('commerce.st_order_pending'),'paid'=>lang('commerce.st_order_paid'),'cancelled'=>lang('commerce.st_order_cancelled'),'failed'=>lang('commerce.st_order_failed'),'expired'=>lang('commerce.st_order_expired')][$order->status] ?? $order->status }}</span>
		</h3>
		<div class="rsva-form-grid">
			<div><label>{{ lang('commerce.admin_order_view_1') }}</label><div>{{ $order->orderer_name }} / {{ $order->orderer_phone }} @if($order->orderer_email)/ {{ $order->orderer_email }}@endif</div></div>
			<div><label>{{ lang('commerce.admin_order_view_2') }}</label><div><strong>{{ shop_money_in($order->payment_price, $order->currency ?? 'KRW') }}</strong> ({{ sprintf(lang('commerce.adm_amount_breakdown'), shop_money_in($order->item_total, $order->currency ?? 'KRW'), shop_money_in($order->delivery_fee_total, $order->currency ?? 'KRW')) }})</div></div>
			@if ($pay_order)
			<div style="grid-column:1/-1">
				<label>{{ lang('commerce.admin_order_view_3') }}</label>
				<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispZittme_payAdminOrderView', 'order_srl', $pay_order->order_srl) }}" target="_blank"><strong>{{ $pay_order->order_code }}</strong></a>
					<span class="rsva-st {{ $pay_order->status === 'paid' ? 'rsva-st-confirmed' : ($pay_order->status === 'pending' ? 'rsva-st-hold' : '') }}">{{ ['ready'=>lang('commerce.st_pay_ready'),'pending'=>lang('commerce.st_pay_pending'),'paid'=>lang('commerce.st_order_paid'),'cancelled'=>lang('commerce.st_order_cancelled'),'partial_cancelled'=>lang('commerce.st_pay_partial'),'failed'=>lang('commerce.st_order_failed'),'expired'=>lang('commerce.st_order_expired')][$pay_order->status] ?? $pay_order->status }}</span>
					<small style="color:#8b95a1">{{ $pay_order->gateway }}</small>
					@if (in_array($pay_order->status, ['ready', 'pending'], true))
					<form action="./" method="post" style="display:inline" onsubmit="return confirm('{{ lang('commerce.adm_deposit_confirm_ask') }}')">
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
			<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_order_view_5') }}</label><div>{{ $order_address->receiver_name }} / {{ $order_phone_text }} / {{ $order_address_text }} @if($order_address->delivery_memo)<small style="color:#6b7684">({{ $order_address->delivery_memo }})</small>@endif</div></div>
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
					<td>{{ ['none'=>'-','requested'=>lang('commerce.st_claim_requested'),'done'=>lang('commerce.st_claim_handled')][$oi->claim_status] ?? $oi->claim_status }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>

	@foreach ($order_sellers as $os)
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_order_view_13') }}
			<span class="rsva-st {{ in_array($os->status, ['shipping','delivered']) ? 'rsva-st-confirmed' : '' }}">{{ ['pending'=>lang('commerce.st_order_pending'),'paid'=>lang('commerce.st_sel_paid'),'preparing'=>lang('commerce.st_sel_preparing'),'shipping'=>lang('commerce.st_sel_shipping'),'delivered'=>lang('commerce.st_sel_delivered'),'cancelled'=>lang('commerce.st_order_cancelled'),'refunded'=>lang('commerce.st_sel_refunded')][$os->status] ?? $os->status }}</span>
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
			<div><label style="display:inline-flex;align-items:center;gap:5px;font-weight:400"><input type="checkbox" name="direct_ship" value="Y" onchange="var f=this.form; f.shipping_company.disabled=this.checked; f.shipping_invoice.disabled=this.checked;" /> {{ lang('commerce.shop_ship_direct') }}</label></div>
			<div><button type="submit" name="order_action" value="ship" class="rsva-btn">{{ lang('commerce.admin_order_view_17') }}</button></div>
			@endif
			@if ($os->status === 'shipping')
			<div><button type="submit" name="order_action" value="deliver" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_order_view_18') }}</button></div>
			@endif
			@if (in_array($order->status, ['pending', 'paid']))
			<div><button type="submit" name="order_action" value="cancel" class="rsva-btn rsva-btn-danger" onclick="return confirm('{{ lang('commerce.adm_cancel_all_ask') }}')">{{ lang('commerce.admin_order_view_19') }}</button></div>
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
					<td>{{ ['cancel'=>lang('commerce.st_claim_cancel'),'return'=>lang('commerce.st_claim_return'),'exchange'=>lang('commerce.st_claim_exchange')][$c->claim_type] ?? $c->claim_type }}</td>
					<td>{{ $c->reason ?: '-' }}</td>
					<td><span class="rsva-st">{{ ['requested'=>lang('commerce.st_claim_request'),'approved'=>lang('commerce.st_claim_approve_short'),'rejected'=>lang('commerce.st_claim_rejected'),'done'=>lang('commerce.st_claim_done')][$c->status] ?? $c->status }}</span></td>
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
				$zmc_act_labels = ['create' => lang('commerce.st_act_create'), 'pay' => lang('commerce.st_act_pay'), 'confirm' => lang('commerce.st_act_confirm'), 'ship' => lang('commerce.st_act_ship'), 'deliver' => lang('commerce.st_act_deliver'), 'purchase_confirm' => lang('commerce.st_act_purchase_confirm'), 'cancel' => lang('commerce.st_act_cancel'), 'claim' => lang('commerce.st_act_claim'), 'refund' => lang('commerce.st_act_refund'), 'expire' => lang('commerce.st_act_expire'), 'stock' => lang('commerce.st_act_stock')];
				$zmc_st_labels = ['pending' => lang('commerce.st_log_pending'), 'paid' => lang('commerce.st_log_paid'), 'preparing' => lang('commerce.st_log_preparing'), 'shipping' => lang('commerce.st_log_shipping'), 'delivered' => lang('commerce.st_act_deliver'), 'confirmed' => lang('commerce.st_log_confirmed'), 'cancelled' => lang('commerce.st_log_cancelled'), 'failed' => lang('commerce.st_log_failed'), 'expired' => lang('commerce.st_act_expire'), 'requested' => lang('commerce.st_claim_requested'), 'approved' => lang('commerce.st_claim_approved'), 'rejected' => lang('commerce.st_claim_rejected'), 'completed' => lang('commerce.st_log_completed')];
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
