@include('_tabs')

<div class="rsva">
	@php $rsva_return = getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $order->order_srl); @endphp

	<div class="rsva-panel">
		<h3>주문 {{ $order->order_code }}
			<span class="rsva-st {{ $order->status === 'paid' ? 'rsva-st-confirmed' : ($order->status === 'pending' ? 'rsva-st-hold' : 'rsva-st-cancelled') }}">{{ ['pending'=>'결제대기','paid'=>'결제완료','cancelled'=>'취소','failed'=>'실패','expired'=>'만료'][$order->status] ?? $order->status }}</span>
		</h3>
		<div class="rsva-form-grid">
			<div><label>주문자</label><div>{{ $order->orderer_name }} / {{ $order->orderer_phone }} @if($order->orderer_email)/ {{ $order->orderer_email }}@endif</div></div>
			<div><label>결제 금액</label><div><strong>{{ number_format($order->payment_price) }}원</strong> (상품 {{ number_format($order->item_total) }} + 배송 {{ number_format($order->delivery_fee_total) }})</div></div>
			@if ($order_address)
			<div style="grid-column:1/-1"><label>배송지</label><div>{{ $order_address->receiver_name }} / {{ $order_address->receiver_phone }} — [{{ $order_address->zipcode }}] {{ $order_address->address1 }} {{ $order_address->address2 }} @if($order_address->delivery_memo)<small style="color:#6b7684">({{ $order_address->delivery_memo }})</small>@endif</div></div>
			@endif
		</div>
	</div>

	<div class="rsva-panel">
		<h3>품목</h3>
		<table class="rsva-table">
			<thead><tr><th>상품</th><th>단가</th><th>수량</th><th>금액</th><th>클레임</th></tr></thead>
			<tbody>
				@foreach ($order_items as $oi)
				<tr>
					<td>{{ $oi->item_name }}@if($oi->option_name)<br /><small style="color:#6b7684">{{ $oi->option_name }}</small>@endif</td>
					<td>{{ number_format($oi->price) }}</td>
					<td>{{ $oi->qty }}</td>
					<td>{{ number_format($oi->subtotal) }}</td>
					<td>{{ ['none'=>'-','requested'=>'신청됨','done'=>'처리완료'][$oi->claim_status] ?? $oi->claim_status }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>

	@foreach ($order_sellers as $os)
	<div class="rsva-panel">
		<h3>배송 처리
			<span class="rsva-st {{ in_array($os->status, ['shipping','delivered']) ? 'rsva-st-confirmed' : '' }}">{{ ['pending'=>'결제대기','paid'=>'발주 대기','preparing'=>'배송준비','shipping'=>'배송중','delivered'=>'배송완료','cancelled'=>'취소','refunded'=>'환불'][$os->status] ?? $os->status }}</span>
			@if ($os->shipping_invoice)<small style="font-weight:500;color:#6b7684">{{ $os->shipping_company }} {{ $os->shipping_invoice }}</small>@endif
		</h3>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminUpdateOrder" />
			<input type="hidden" name="order_srl" value="{{ $order->order_srl }}" />
			<input type="hidden" name="success_return_url" value="{{ $rsva_return }}" />
			@if ($os->status === 'paid')
			<div><button type="submit" name="order_action" value="confirm" class="rsva-btn rsva-btn-primary">발주 확인 (배송준비)</button></div>
			@endif
			@if (in_array($os->status, ['paid', 'preparing']))
			<div><label>택배사</label><input type="text" name="shipping_company" placeholder="CJ대한통운" style="width:130px" /></div>
			<div><label>송장번호</label><input type="text" name="shipping_invoice" style="width:160px" /></div>
			<div><button type="submit" name="order_action" value="ship" class="rsva-btn">송장 등록 (배송중)</button></div>
			@endif
			@if ($os->status === 'shipping')
			<div><button type="submit" name="order_action" value="deliver" class="rsva-btn rsva-btn-primary">배송 완료</button></div>
			@endif
			@if (in_array($order->status, ['pending', 'paid']))
			<div><button type="submit" name="order_action" value="cancel" class="rsva-btn rsva-btn-danger" onclick="return confirm('주문을 전체 취소하고 환불·재입고 처리합니다. 계속할까요?')">전체 취소</button></div>
			@endif
		</form>
	</div>
	@endforeach

	@if (!empty($order_claims))
	<div class="rsva-panel">
		<h3>클레임</h3>
		<table class="rsva-table">
			<thead><tr><th>구분</th><th>사유</th><th>상태</th><th>환불액</th><th>일시</th></tr></thead>
			<tbody>
				@foreach ($order_claims as $c)
				<tr>
					<td>{{ ['cancel'=>'취소','return'=>'반품','exchange'=>'교환'][$c->claim_type] ?? $c->claim_type }}</td>
					<td>{{ $c->reason ?: '-' }}</td>
					<td><span class="rsva-st">{{ ['requested'=>'신청','approved'=>'승인','rejected'=>'거절','done'=>'완료'][$c->status] ?? $c->status }}</span></td>
					<td>{{ $c->refund_amount > 0 ? number_format($c->refund_amount) : '-' }}</td>
					<td><small>{{ zdate($c->regdate, 'm.d H:i') }}</small></td>
				</tr>
				@endforeach
			</tbody>
		</table>
		<p style="margin:8px 0 0;font-size:13px"><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminClaims') }}" class="rsva-btn rsva-btn-sm">클레임 관리로 이동</a></p>
	</div>
	@endif

	@if (!empty($order_logs))
	<div class="rsva-panel">
		<h3>처리 이력</h3>
		<table class="rsva-table">
			<thead><tr><th>일시</th><th>동작</th><th>상태</th><th>메모</th></tr></thead>
			<tbody>
				@foreach ($order_logs as $lg)
				<tr>
					<td><small>{{ zdate($lg->regdate, 'm.d H:i:s') }}</small></td>
					<td>{{ $lg->action }}</td>
					<td>{{ $lg->before_status }}@if($lg->after_status) → {{ $lg->after_status }}@endif</td>
					<td>{{ $lg->memo ?: '-' }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>
	@endif

	<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrders') }}" class="rsva-btn">주문 목록</a>
</div>
