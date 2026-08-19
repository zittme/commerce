@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_credits_1') }}</h3>
		<form action="{{ getUrl('') }}" method="get" class="rsva-inline" style="margin-bottom:12px">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="dispCommerceAdminCredits" />
			<div><label>{{ lang('commerce.admin_credits_2') }}</label><input type="text" name="f_target" value="{{ $f_target }}" style="width:200px" /></div>
			<div><button type="submit" class="rsva-btn">{{ lang('commerce.admin_credits_3') }}</button></div>
		</form>

		@if ($f_target !== '' && !$credit_member)
		<p class="rsva-empty">{{ lang('commerce.admin_credits_4') }}</p>
		@elseif ($credit_member)
		<p style="margin:0 0 10px;font-size:14px"><strong>{{ $credit_member->nick_name }}</strong> ({{ $credit_member->user_id ?: $credit_member->email_address }}) / <strong style="color:#2677e3">{{ sprintf(lang('commerce.adm_credit_balance'), shop_money_base($credit_balance)) }}</strong></p>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminAdjustCredit" />
			<input type="hidden" name="target" value="{{ $f_target }}" />
			<div><label>{{ lang('commerce.admin_credits_5') }}</label><input type="number" name="amount" required style="width:120px" /></div>
			<div style="min-width:200px"><label>{{ lang('commerce.admin_credits_6') }}</label><input type="text" name="memo" placeholder="{{ lang('commerce.admin_credits_17') }}" /></div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_credits_7') }}</button></div>
		</form>

		@if (!empty($credit_logs))
		<table class="rsva-table" style="margin-top:14px">
			<thead><tr><th>{{ lang('commerce.admin_credits_8') }}</th><th>{{ lang('commerce.admin_credits_9') }}</th><th>{{ lang('commerce.admin_credits_10') }}</th><th>{{ lang('commerce.admin_credits_11') }}</th><th>{{ lang('commerce.admin_credits_12') }}</th><th>{{ lang('commerce.admin_credits_6') }}</th></tr></thead>
			<tbody>
				@foreach ($credit_logs as $cl)
				<tr>
					<td><small>{{ zdate($cl->regdate, 'Y.m.d H:i') }}</small></td>
					<td style="{{ $cl->amount >= 0 ? 'color:#2677e3' : 'color:#c0392b' }};font-weight:600">{{ $cl->amount >= 0 ? '+' : '' }}{{ shop_money_base($cl->amount) }}</td>
					<td>{{ shop_money_base($cl->balance_after) }}</td>
					<td>{{ ['earn'=>lang('commerce.adm_credit_earn'),'spend'=>lang('commerce.adm_credit_spend'),'refund'=>lang('commerce.adm_credit_refund'),'earn_cancel'=>lang('commerce.adm_credit_earn_cancel'),'admin'=>lang('commerce.adm_credit_admin')][$cl->type] ?? $cl->type }}</td>
					<td>{{ $cl->order_srl > 0 ? $cl->order_srl : '-' }}</td>
					<td>{{ $cl->memo ?: '-' }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
		@endif
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_credits_13') }}</h3>
		@if (empty($recent_logs))
		<p class="rsva-empty">{{ lang('commerce.admin_credits_14') }}</p>
		@else
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_credits_8') }}</th><th>{{ lang('commerce.admin_credits_15') }}</th><th>{{ lang('commerce.admin_credits_9') }}</th><th>{{ lang('commerce.admin_credits_10') }}</th><th>{{ lang('commerce.admin_credits_11') }}</th></tr></thead>
			<tbody>
				@foreach ($recent_logs as $cl)
				<tr>
					<td><small>{{ zdate($cl->regdate, 'Y.m.d H:i') }}</small></td>
					<td>
					{{-- 닉네임 표시, 클릭 시 회원 정보(새 탭) --}}
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispMemberAdminInsert', 'member_srl', $cl->member_srl) }}" target="_blank">{{ $cl->member_name !== '' ? $cl->member_name : ('#' . $cl->member_srl) }}</a>
				</td>
					<td style="{{ $cl->amount >= 0 ? 'color:#2677e3' : 'color:#c0392b' }};font-weight:600">{{ $cl->amount >= 0 ? '+' : '' }}{{ shop_money_base($cl->amount) }}</td>
					<td>{{ shop_money_base($cl->balance_after) }}</td>
					<td>{{ ['earn'=>lang('commerce.adm_credit_earn'),'spend'=>lang('commerce.adm_credit_spend'),'refund'=>lang('commerce.adm_credit_refund'),'earn_cancel'=>lang('commerce.adm_credit_earn_cancel'),'admin'=>lang('commerce.adm_credit_admin')][$cl->type] ?? $cl->type }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
		<small style="display:block;margin-top:8px;color:#6b7684;font-size:12px">{{ lang('commerce.admin_credits_16') }}</small>
	</div>
</div>
