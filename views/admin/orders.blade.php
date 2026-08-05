@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminOrders" />
		<select name="f_status">
			<option value="">전체 상태</option>
			@foreach (['pending'=>'결제 대기','paid'=>'결제 완료','cancelled'=>'취소','expired'=>'만료'] as $key => $label)
			<option value="{{ $key }}" @if($filters->status === $key) selected @endif>{{ $label }}</option>
			@endforeach
		</select>
		<input type="text" name="f_keyword" placeholder="주문번호·이름·연락처" value="{{ $filters->keyword }}" />
		<button type="submit" class="rsva-btn">검색</button>
	</form>

	@if (empty($orders))
	<p class="rsva-empty">조건에 맞는 주문이 없습니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>주문번호</th><th>주문자</th><th>금액</th><th>결제</th><th>배송</th><th>일시</th><th></th></tr></thead>
		<tbody>
			@foreach ($orders as $o)
			@php $os = $seller_map[(int)$o->order_srl] ?? null; @endphp
			<tr>
				<td><strong>{{ $o->order_code }}</strong></td>
				<td>{{ $o->orderer_name }}<br /><small style="color:#9aa1ab">{{ $o->orderer_phone }}</small></td>
				<td>{{ number_format($o->payment_price) }}원</td>
				<td><span class="rsva-st {{ $o->status === 'paid' ? 'rsva-st-confirmed' : ($o->status === 'pending' ? 'rsva-st-hold' : 'rsva-st-cancelled') }}">{{ ['pending'=>'결제대기','paid'=>'결제완료','cancelled'=>'취소','failed'=>'실패','expired'=>'만료'][$o->status] ?? $o->status }}</span></td>
				<td>{{ $os ? (['pending'=>'-','paid'=>'발주 대기','preparing'=>'배송준비','shipping'=>'배송중','delivered'=>'배송완료','cancelled'=>'취소','refunded'=>'환불'][$os->status] ?? $os->status) : '-' }}</td>
				<td><small>{{ zdate($o->regdate, 'm.d H:i') }}</small></td>
				<td><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $o->order_srl) }}" class="rsva-btn rsva-btn-sm">상세·처리</a></td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
