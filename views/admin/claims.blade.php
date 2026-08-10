@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminClaims" />
		<select name="f_status">
			<option value="">{{ lang('commerce.admin_claims_1') }}</option>
			<option value="requested" @if($filters->status === 'requested') selected @endif>{{ lang('commerce.admin_claims_2') }}</option>
			<option value="done" @if($filters->status === 'done') selected @endif>{{ lang('commerce.admin_claims_3') }}</option>
			<option value="rejected" @if($filters->status === 'rejected') selected @endif>{{ lang('commerce.admin_claims_4') }}</option>
		</select>
		<button type="submit" class="rsva-btn">{{ lang('commerce.admin_claims_5') }}</button>
	</form>

	@if (empty($claims))
	<p class="rsva-empty">{{ lang('commerce.admin_claims_6') }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ lang('commerce.admin_claims_7') }}</th><th>{{ lang('commerce.admin_claims_8') }}</th><th>{{ lang('commerce.admin_claims_9') }}</th><th>{{ lang('commerce.admin_claims_10') }}</th><th>{{ lang('commerce.admin_claims_11') }}</th></tr></thead>
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
						<div><label>{{ lang('commerce.admin_claims_12') }}</label><input type="number" name="refund_amount" min="0" value="{{ $co ? $co->payment_price : 0 }}" style="width:110px" /></div>
						<div><label>{{ lang('commerce.admin_claims_13') }}</label><select name="restock" style="width:80px"><option value="Y">{{ lang('commerce.admin_claims_14') }}</option><option value="N">{{ lang('commerce.admin_claims_15') }}</option></select></div>
						<div><button type="submit" name="claim_action" value="approve" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_claims_16') }}</button></div>
						<div><button type="submit" name="claim_action" value="reject" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_claims_4') }}</button></div>
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
