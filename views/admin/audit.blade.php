@include('_tabs')

@php
$au_kinds = ['' => lang('commerce.au_kind_all'), 'change' => lang('commerce.au_kind_change'), 'view' => lang('commerce.au_kind_view'), 'export' => lang('commerce.au_kind_export'), 'denied' => lang('commerce.au_kind_denied'), 'staff' => lang('commerce.au_kind_staff')];
$au_base = getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'audit');
$au_qs = ['f_member' => $audit_filters->member ?: '', 'f_kind' => $audit_filters->kind, 'f_alert' => $audit_filters->alert, 'f_q' => $audit_filters->q, 'f_from' => $audit_filters->from, 'f_to' => $audit_filters->to];
@endphp

<style>
.au-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0 0 12px; padding: 10px 12px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); }
.au-bar select, .au-bar input { height: 34px !important; padding: 0 10px !important; line-height: normal !important; font-size: 13px !important; }
.au-bar input[type=search] { width: 200px; }
.au-bar label { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: var(--zmc-ink, #232a3b); }
.au-bar .au-grow { flex: 1; }
.au-bar .au-count { font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); }
.au-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.au-table th { padding: 9px 10px; border-bottom: 1px solid var(--zmc-line-strong, #d6d2c8); background: var(--zmc-side, #efede8); font-size: 12px; font-weight: 600; color: var(--zmc-sub, #7a7f8c); text-align: left; white-space: nowrap; }
.au-table td { padding: 9px 10px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); vertical-align: top; }
.au-table tr.is-alert td { background: #fff6f4; }
.au-table tr.au-detail td { background: var(--zmc-bg, #f7f6f3); }
.au-time { white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--zmc-sub, #7a7f8c); }
.au-who b { font-weight: 600; }
.au-sub { display: block; margin-top: 2px; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
.au-tag { display: inline-block; margin-right: 4px; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; background: var(--zmc-side, #efede8); color: var(--zmc-ink-2, #3c4458); }
.au-tag.k-denied, .au-tag.is-fail { background: #fdecea; color: #b3261e; }
.au-tag.k-export { background: #fff4d6; color: #7a5a10; }
.au-tag.k-staff { background: var(--zmc-brand, #26345c); color: var(--zmc-on-brand, #fff8e6); }
.au-tag.is-alert { background: #b3261e; color: #fff; }
.au-more { border: 0; background: none; padding: 0; font: inherit; font-size: 12px; color: var(--zmc-brand, #26345c); text-decoration: underline; cursor: pointer; }
.au-diff { width: 100%; border-collapse: collapse; margin: 0 0 8px; font-size: 12px; }
.au-diff th, .au-diff td { padding: 4px 8px; border: 1px solid var(--zmc-line, #e6e3dc); text-align: left; word-break: break-all; }
.au-diff th { width: 150px; background: var(--zmc-surface, #fff); color: var(--zmc-sub, #7a7f8c); font-weight: 600; }
.au-diff .b { color: #b3261e; text-decoration: line-through; }
.au-diff .a { color: #1d7a45; }
.au-raw { max-height: 220px; overflow: auto; margin: 0; padding: 8px 10px; border: 1px solid var(--zmc-line, #e6e3dc); background: var(--zmc-surface, #fff); font-size: 11.5px; line-height: 1.5; white-space: pre-wrap; word-break: break-all; }
.au-pager { display: flex; justify-content: center; gap: 4px; margin: 14px 0; }
.au-pager a, .au-pager span { min-width: 30px; padding: 5px 8px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: 4px; text-align: center; font-size: 12.5px; text-decoration: none !important; color: var(--zmc-ink, #232a3b) !important; background: var(--zmc-surface, #fff); }
.au-pager span { background: var(--zmc-brand, #26345c); color: #fff !important; border-color: var(--zmc-brand, #26345c); }
.au-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); }
.au-foot input { width: 80px; height: 30px !important; padding: 0 8px !important; }
.au-empty { padding: 40px 20px; text-align: center; color: var(--zmc-sub, #7a7f8c); }
</style>

<div class="rsva">
	<form class="au-bar" method="get" action="{{ getUrl('') }}">
		<input type="hidden" name="act" value="dispCommerceConsole" /><input type="hidden" name="p" value="audit" />
		<select name="f_member" aria-label="{{ lang('commerce.au_col_who') }}">
			<option value="">{{ lang('commerce.au_all_people') }}</option>
			@foreach ($audit_actors as $au_a)
			<option value="{{ $au_a->member_srl }}" @if ((int)$audit_filters->member === (int)$au_a->member_srl) selected @endif>{{ $au_a->actor ?: '#' . $au_a->member_srl }}</option>
			@endforeach
		</select>
		<select name="f_kind" aria-label="{{ lang('commerce.au_col_kind') }}">
			@foreach ($au_kinds as $au_k => $au_l)<option value="{{ $au_k }}" @if ($audit_filters->kind === $au_k) selected @endif>{{ $au_l }}</option>@endforeach
		</select>
		<input type="date" name="f_from" value="{{ $audit_filters->from }}" aria-label="{{ lang('commerce.au_from') }}" /> ~
		<input type="date" name="f_to" value="{{ $audit_filters->to }}" aria-label="{{ lang('commerce.au_to') }}" />
		<input type="search" name="f_q" value="{{ $audit_filters->q }}" placeholder="{{ lang('commerce.au_search_ph') }}" />
		<label><input type="checkbox" name="f_alert" value="Y" @if ($audit_filters->alert === 'Y') checked @endif /> {{ lang('commerce.au_only_alert') }}</label>
		<button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.au_filter') }}</button>
		<span class="au-grow"></span>
		<span class="au-count">{{ sprintf(lang('commerce.au_total'), number_format($audit_total)) }}</span>
	</form>

	<div class="rsva-panel" style="padding:0;overflow-x:auto">
		@if (empty($audit_rows))
		<p class="au-empty">{{ lang('commerce.au_empty') }}</p>
		@else
		<table class="au-table">
			<thead><tr><th>{{ lang('commerce.au_col_when') }}</th><th>{{ lang('commerce.au_col_who') }}</th><th>{{ lang('commerce.au_col_what') }}</th><th>{{ lang('commerce.au_col_ip') }}</th><th></th></tr></thead>
			<tbody>
			@foreach ($audit_rows as $au)
			@php
			$au_detail = json_decode((string)$au->detail, true);
			$au_detail = is_array($au_detail) ? $au_detail : [];
			$au_changes = $au_detail['changes'] ?? [];
			@endphp
			<tr class="{{ $au->alert ? 'is-alert' : '' }}">
				<td class="au-time">{{ zdate($au->regdate, 'Y.m.d H:i:s') }}</td>
				<td class="au-who"><b>{{ $au->actor ?: '#' . $au->member_srl }}</b><span class="au-sub">{{ lang('commerce.au_role_' . ($au->role ?: 'none')) }}</span></td>
				<td>
					@if ($au->alert)<span class="au-tag is-alert">{{ lang('commerce.au_alert_' . $au->alert) }}</span>@endif
					@if ($au->kind !== 'change')<span class="au-tag k-{{ $au->kind }}">{{ $au_kinds[$au->kind] ?? $au->kind }}</span>@endif
					@if ($au->result === 'fail')<span class="au-tag is-fail">{{ lang('commerce.au_failed') }}</span>@endif
					{{ $au->summary }}
					@if (!empty($au_detail['error']))<span class="au-sub">{{ $au_detail['error'] }}</span>@endif
				</td>
				<td class="au-time">{{ $au->ipaddress }}</td>
				<td><button type="button" class="au-more" data-toggle="au{{ $au->log_srl }}">{{ lang('commerce.au_detail') }}</button></td>
			</tr>
			<tr class="au-detail" id="au{{ $au->log_srl }}" hidden>
				<td colspan="5">
					@if (count($au_changes))
					<table class="au-diff">
						@foreach ($au_changes as $au_k => $au_v)
						<tr><th>{{ $au_k }}</th><td><span class="b">{{ is_scalar($au_v[0] ?? null) ? $au_v[0] : json_encode($au_v[0] ?? null, JSON_UNESCAPED_UNICODE) }}</span> → <span class="a">{{ is_scalar($au_v[1] ?? null) ? $au_v[1] : json_encode($au_v[1] ?? null, JSON_UNESCAPED_UNICODE) }}</span></td></tr>
						@endforeach
					</table>
					@endif
					<pre class="au-raw">{{ json_encode($au_detail['params'] ?? [], JSON_UNESCAPED_UNICODE + JSON_PRETTY_PRINT) }}</pre>
					<span class="au-sub">{{ $au->user_agent }}</span>
				</td>
			</tr>
			@endforeach
			</tbody>
		</table>
		@endif
	</div>

	@if ($audit_pages > 1)
	<nav class="au-pager">
		@for ($au_i = max(1, $audit_page - 5); $au_i <= min($audit_pages, $audit_page + 5); $au_i++)
		@if ($au_i === $audit_page)<span>{{ $au_i }}</span>@else<a href="{{ $au_base }}&amp;{{ http_build_query(array_filter($au_qs)) }}&amp;page={{ $au_i }}">{{ $au_i }}</a>@endif
		@endfor
	</nav>
	@endif

	<div class="au-foot">
		<span>{{ sprintf(lang('commerce.au_keep_note'), $audit_days) }}</span>
		@if ($audit_is_owner)
		<form id="auKeep" style="display:flex;gap:6px;align-items:center;margin:0">
			<input type="number" name="audit_days" min="30" max="3650" value="{{ $audit_days }}" aria-label="{{ lang('commerce.au_keep_days') }}" /> {{ lang('commerce.au_days') }}
			<button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.au_keep_save') }}</button>
		</form>
		@endif
	</div>
</div>

<script>
(function () {
	document.querySelectorAll('[data-toggle]').forEach(function (b) {
		b.addEventListener('click', function () { var r = document.getElementById(b.dataset.toggle); r.hidden = !r.hidden; });
	});
	var keep = document.getElementById('auKeep');
	if (keep) keep.addEventListener('submit', function (e) {
		e.preventDefault();
		exec_json('commerce.procCommerceAdminAuditConfig', { audit_days: keep.audit_days.value }, function () { location.reload(); });
	});
})();
</script>
