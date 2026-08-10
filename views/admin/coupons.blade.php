@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_coupons_1') }}</h3>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminInsertCoupon" />
			<div style="min-width:180px"><label>{{ lang('commerce.admin_coupons_2') }}</label><span class="zlf-row-wrap"><input type="text" name="title" required />@include('_langfield', ['lf_name' => 'title', 'lf_value' => '', 'lf_key' => 'coupon'])</span></div>
			<div><label>{{ lang('commerce.admin_coupons_3') }}</label><input type="text" name="code" placeholder="WELCOME10" style="width:130px" /></div>
			<div><label>{{ lang('commerce.admin_coupons_4') }}</label><select name="discount_type" style="width:90px"><option value="fixed">{{ lang('commerce.admin_coupons_5') }}</option><option value="percent">{{ lang('commerce.admin_coupons_6') }}</option></select></div>
			<div><label>{{ lang('commerce.admin_coupons_7') }}</label><input type="number" name="discount_value" min="0" required style="width:90px" /></div>
			<div><label>{{ lang('commerce.admin_coupons_8') }}</label><input type="number" name="max_discount" min="0" value="0" style="width:100px" /></div>
			<div><label>{{ lang('commerce.admin_coupons_9') }}</label><input type="number" name="min_order" min="0" value="0" style="width:100px" /></div>
			<div><label>{{ lang('commerce.admin_coupons_10') }}</label><input type="date" name="use_start" /></div>
			<div><label>{{ lang('commerce.admin_coupons_11') }}</label><input type="date" name="use_end" /></div>
			<div><label>{{ lang('commerce.admin_coupons_12') }}</label><input type="number" name="per_member" min="1" value="1" style="width:70px" /></div>
			<div><label>{{ lang('commerce.admin_coupons_13') }}</label><input type="number" name="total_limit" min="0" value="0" style="width:90px" /></div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_coupons_14') }}</button></div>
		</form>
		<small style="display:block;margin-top:8px;color:#6b7684;font-size:12px">{{ lang('commerce.admin_coupons_15') }}</small>
	</div>

	@if (empty($coupons))
	<p class="rsva-empty">{{ lang('commerce.admin_coupons_16') }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ lang('commerce.admin_coupons_2') }}</th><th>{{ lang('commerce.admin_coupons_17') }}</th><th>{{ lang('commerce.admin_coupons_18') }}</th><th>{{ lang('commerce.admin_coupons_19') }}</th><th>{{ lang('commerce.admin_coupons_20') }}</th><th>{{ lang('commerce.admin_coupons_21') }}</th><th>{{ lang('commerce.admin_coupons_22') }}</th><th>{{ lang('commerce.admin_coupons_23') }}</th></tr></thead>
		<tbody>
			@foreach ($coupons as $c)
			<tr>
				<td style="font-weight:700">{{ $c->title }}</td>
				<td>{{ $c->code ?: '-' }}</td>
				<td>
					{{ $c->discount_type === 'percent' ? $c->discount_value . '%' : number_format($c->discount_value) . '원' }}
					@if ($c->discount_type === 'percent' && $c->max_discount > 0)<br /><small style="color:#9aa1ab">최대 {{ number_format($c->max_discount) }}원</small>@endif
				</td>
				<td>{{ $c->min_order > 0 ? number_format($c->min_order) . '원 이상' : '-' }}<br /><small style="color:#9aa1ab">1인 {{ $c->per_member }}회</small></td>
				<td><small>{{ $c->use_start ? zdate($c->use_start, 'Y.m.d') : '' }} ~ {{ $c->use_end ? zdate($c->use_end, 'Y.m.d') : '' }}</small></td>
				<td>{{ number_format($c->used_count) }} / {{ $c->total_limit > 0 ? number_format($c->total_limit) : '∞' }}
					<br /><small style="color:#9aa1ab">발급 {{ number_format($issue_counts[(int)$c->coupon_srl] ?? 0) }}</small></td>
				<td><span class="rsva-st {{ $c->status === 'Y' ? 'rsva-st-confirmed' : '' }}">{{ $c->status === 'Y' ? '활성' : '중지' }}</span></td>
				<td>
					<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminIssueCoupon" />
						<input type="hidden" name="coupon_srl" value="{{ $c->coupon_srl }}" />
						<div><input type="text" name="target" placeholder="{{ lang('commerce.admin_coupons_26') }}" style="width:140px" /></div>
						<div><button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_coupons_24') }}</button></div>
					</form>
					<form action="{{ getUrl('') }}" method="post" style="display:inline-block;margin-top:4px" onsubmit="return confirm('쿠폰을 삭제할까요? 발급 이력은 남습니다.')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminDeleteCoupon" />
						<input type="hidden" name="coupon_srl" value="{{ $c->coupon_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_coupons_25') }}</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
