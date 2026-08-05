@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminClaims" />
		<select name="f_status">
			<option value="">전체</option>
			<option value="requested" @if($filters->status === 'requested') selected @endif>신청</option>
			<option value="done" @if($filters->status === 'done') selected @endif>처리 완료</option>
			<option value="rejected" @if($filters->status === 'rejected') selected @endif>거절</option>
		</select>
		<button type="submit" class="rsva-btn">검색</button>
	</form>

	@if (empty($claims))
	<p class="rsva-empty">클레임이 없습니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>주문번호</th><th>구분</th><th>사유</th><th>상태</th><th>처리</th></tr></thead>
		<tbody>
			@foreach ($claims as $c)
			@php $co = $order_map[(int)$c->order_srl] ?? null; @endphp
			<tr>
				<td>
					@if ($co)<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $co->order_srl) }}" style="font-weight:700">{{ $co->order_code }}</a>
					<br /><small style="color:#9aa1ab">{{ number_format($co->payment_price) }}원</small>@endif
				</td>
				<td>{{ ['cancel'=>'취소','return'=>'반품','exchange'=>'교환'][$c->claim_type] ?? $c->claim_type }}</td>
				<td style="max-width:260px">{{ $c->reason ?: '-' }}</td>
				<td><span class="rsva-st {{ $c->status === 'requested' ? 'rsva-st-hold' : '' }}">{{ ['requested'=>'신청','rejected'=>'거절','done'=>'완료'][$c->status] ?? $c->status }}</span></td>
				<td>
					@if ($c->status === 'requested')
					<form action="{{ getUrl('') }}" method="post" class="rsva-inline" onsubmit="return confirm('처리하시겠습니까?')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminUpdateClaim" />
						<input type="hidden" name="claim_srl" value="{{ $c->claim_srl }}" />
						<div><label>환불액 (반품배송비 차감 반영)</label><input type="number" name="refund_amount" min="0" value="{{ $co ? $co->payment_price : 0 }}" style="width:110px" /></div>
						<div><label>재입고</label><select name="restock" style="width:80px"><option value="Y">예</option><option value="N">아니요</option></select></div>
						<div><button type="submit" name="claim_action" value="approve" class="rsva-btn rsva-btn-sm rsva-btn-primary">승인·환불</button></div>
						<div><button type="submit" name="claim_action" value="reject" class="rsva-btn rsva-btn-sm rsva-btn-danger">거절</button></div>
					</form>
					@else
					{{ $c->refund_amount > 0 ? '환불 ' . number_format($c->refund_amount) . '원' : '-' }}
					@endif
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
