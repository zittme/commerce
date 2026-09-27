@include('_tabs')
@php
$ss_role_labels = [];
foreach ($ss_roles as $ss_r) { $ss_role_labels[$ss_r] = lang('commerce.ss_role_' . $ss_r); }
@endphp
<style>
.ss-roles { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin: 0; }
.ss-roles div { padding: 12px 14px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r-sm, 5px); }
.ss-roles dt { margin: 0 0 4px; font-size: 13.5px; font-weight: 700; }
.ss-roles dd { margin: 0; font-size: 12.5px; line-height: 1.6; color: var(--zmc-sub, #7a7f8c); }
.ss-inline { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; }
.ss-inline > div { min-width: 180px; }
.ss-inline > div.ss-grow { flex: 1; min-width: 220px; }
.ss-inline input[type="text"], .ss-inline select { width: 100%; }
.ss-table td form { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
.ss-table td.ss-acts { text-align: right; white-space: nowrap; }
.ss-sub { display: block; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
.ss-note { margin: 12px 0 0; font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); }
</style>

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post" class="rsva-panel">
		<input type="hidden" name="module" value="commerce" />
		<input type="hidden" name="act" value="procCommerceSellerCenterInviteMember" />
		<h3>{{ lang('commerce.ss_invite_title') }}</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.ss_invite_desc') }}</p>
		<div class="ss-inline">
			<div class="ss-grow"><label>{{ lang('commerce.ss_find') }}</label><input type="text" name="find" maxlength="120" required placeholder="{{ lang('commerce.ss_find_ph') }}" /></div>
			<div><label>{{ lang('commerce.ss_col_role') }}</label><select name="role">@foreach ($ss_role_labels as $ss_k => $ss_l)<option value="{{ $ss_k }}" @if ($ss_k === 'items') selected @endif>{{ $ss_l }}</option>@endforeach</select></div>
			<div style="min-width:0"><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.ss_do_invite') }}</button></div>
		</div>
	</form>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.ss_list_title') }}</h3>
		@if (count($ss_list))
		<table class="rsva-table ss-table">
			<thead><tr><th>{{ lang('commerce.ss_col_member') }}</th><th>{{ lang('commerce.ss_col_role') }}</th><th>{{ lang('commerce.mk_col_status') }}</th><th>{{ lang('commerce.ss_col_date') }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($ss_list as $ss_row)
				<tr>
					<td><b>{{ $ss_row->nick_name ?: '#' . $ss_row->member_srl }}</b>@if ($ss_row->user_id !== '')<span class="ss-sub">{{ $ss_row->user_id }}</span>@endif</td>
					<td>
						<form action="{{ getUrl('') }}" method="post">
							<input type="hidden" name="module" value="commerce" />
							<input type="hidden" name="act" value="procCommerceSellerCenterUpdateMember" />
							<input type="hidden" name="target_member_srl" value="{{ $ss_row->member_srl }}" />
							<select name="role" onchange="this.form.submit()" aria-label="{{ lang('commerce.ss_col_role') }}">@foreach ($ss_role_labels as $ss_k => $ss_l)<option value="{{ $ss_k }}" @if ($ss_row->role === $ss_k) selected @endif>{{ $ss_l }}</option>@endforeach</select>
						</form>
					</td>
					<td><span class="rsva-st {{ $ss_row->status === 'active' ? 'rsva-st-on' : 'rsva-st-pending' }}">{{ lang('commerce.ss_st_' . $ss_row->status) }}</span></td>
					<td>{{ $ss_row->status === 'active' ? $ss_row->accepted_text : $ss_row->regdate_text }}</td>
					<td class="ss-acts">
						<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm({{ json_encode(lang('commerce.ss_ask_remove')) }})">
							<input type="hidden" name="module" value="commerce" />
							<input type="hidden" name="act" value="procCommerceSellerCenterRemoveMember" />
							<input type="hidden" name="target_member_srl" value="{{ $ss_row->member_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $ss_row->status === 'active' ? lang('commerce.ss_do_remove') : lang('commerce.ss_do_cancel') }}</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@else
		<div class="rsva-empty">{{ lang('commerce.ss_empty') }}</div>
		@endif
		<p class="ss-note">{{ lang('commerce.ss_note') }}</p>
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.ss_roles_title') }}</h3>
		<dl class="ss-roles">
			@foreach ($ss_role_labels as $ss_k => $ss_l)
			<div><dt>{{ $ss_l }}</dt><dd>{{ lang('commerce.ss_role_desc_' . $ss_k) }}</dd></div>
			@endforeach
		</dl>
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.ss_log_title') }}</h3>
		@if (count($ss_logs))
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.au_col_when') }}</th><th>{{ lang('commerce.au_col_who') }}</th><th>{{ lang('commerce.au_col_what') }}</th></tr></thead>
			<tbody>
				@foreach ($ss_logs as $ss_log)
				<tr>
					<td style="white-space:nowrap">{{ $ss_log->regdate_text }}</td>
					<td>{{ $ss_log->actor }}</td>
					<td>{{ $ss_log->summary }}@if ($ss_log->result !== 'ok') <span class="rsva-st rsva-st-cancelled">{{ $ss_log->result }}</span>@endif</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@else
		<div class="rsva-empty">{{ lang('commerce.ss_log_empty') }}</div>
		@endif
	</div>
</div>
