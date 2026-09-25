@include('_tabs')

@php
$st_perm_labels = [];
foreach ($staff_perms as $st_p) { $st_perm_labels[$st_p] = lang('commerce.st_perm_' . $st_p); }
$st_audit_url = getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'audit');
@endphp

<style>
.st-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
.st-roles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 0 0 16px; }
.st-roles div { padding: 12px 14px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); line-height: 1.55; }
.st-roles b { display: block; margin-bottom: 2px; font-size: 13.5px; color: var(--zmc-ink, #232a3b); }
.st-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.st-table th { padding: 9px 10px; border-bottom: 1px solid var(--zmc-line-strong, #d6d2c8); background: var(--zmc-side, #efede8); font-size: 12px; font-weight: 600; color: var(--zmc-sub, #7a7f8c); text-align: left; }
.st-table td { padding: 11px 10px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); vertical-align: top; }
.st-table tr.is-off td { opacity: .55; }
.st-name b { display: block; font-weight: 600; }
.st-sub { display: block; margin-top: 2px; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
.st-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; background: var(--zmc-side, #efede8); color: var(--zmc-ink, #232a3b); }
.st-badge.is-sub { background: var(--zmc-brand, #26345c); color: var(--zmc-on-brand, #fff8e6); }
.st-badge.is-off { background: #fdecea; color: #b3261e; }
.st-chips { display: flex; flex-wrap: wrap; gap: 4px; max-width: 360px; }
.st-chips span { padding: 2px 7px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: 4px; font-size: 11.5px; color: var(--zmc-ink-2, #3c4458); }
.st-acts { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.st-acts a { text-decoration: none !important; }
.st-form { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 12px; }
.st-form h3 { margin: 0; font-size: 14.5px; }
.st-form label.st-l { display: block; margin-bottom: 5px; font-size: 12.5px; font-weight: 600; color: var(--zmc-ink, #232a3b); }
.st-form input[type=text] { width: 100%; box-sizing: border-box; }
.st-seg { display: inline-flex; }
.st-seg button { padding: 6px 12px; border: 1px solid var(--zmc-line-strong, #d6d2c8); margin-left: -1px; background: var(--zmc-surface, #fff); font: inherit; font-size: 13px; color: var(--zmc-sub, #7a7f8c); cursor: pointer; }
.st-seg button:first-child { margin-left: 0; border-radius: 5px 0 0 5px; }
.st-seg button:last-child { border-radius: 0 5px 5px 0; }
.st-seg button.is-on { position: relative; background: var(--zmc-brand, #26345c); border-color: var(--zmc-brand, #26345c); color: var(--zmc-on-brand, #fff8e6); }
.st-perms { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; }
.st-perms label { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; }
.st-perms label.is-key { grid-column: 1 / -1; padding-top: 6px; border-top: 1px dashed var(--zmc-line, #e6e3dc); }
.st-hint { margin: 0; font-size: 12px; line-height: 1.55; color: var(--zmc-sub, #7a7f8c); }
.st-empty { padding: 40px 20px; text-align: center; color: var(--zmc-sub, #7a7f8c); }
@media (max-width: 1100px) { .st-wrap { grid-template-columns: 1fr; } .st-form { position: static; } .st-roles { grid-template-columns: 1fr; } }
</style>

<div class="rsva">
	<div class="st-roles">
		<div><b>{{ lang('commerce.au_role_owner') }}</b>{{ lang('commerce.st_role_owner_desc') }}</div>
		<div><b>{{ lang('commerce.au_role_sub') }}</b>{{ lang('commerce.st_role_sub_desc') }}</div>
		<div><b>{{ lang('commerce.au_role_manager') }}</b>{{ lang('commerce.st_role_manager_desc') }}</div>
	</div>

	<div class="st-wrap">
		<div class="rsva-panel" style="padding:0;overflow:hidden">
			@if (empty($staff_list))
			<p class="st-empty">{{ lang('commerce.st_empty') }}</p>
			@else
			<table class="st-table">
				<thead><tr><th>{{ lang('commerce.st_col_who') }}</th><th>{{ lang('commerce.st_col_role') }}</th><th>{{ lang('commerce.st_col_perms') }}</th><th>{{ lang('commerce.st_col_seen') }}</th><th></th></tr></thead>
				<tbody>
				@foreach ($staff_list as $s)
				@php
				$st_can = $staff_role === 'owner' || ($staff_role === 'sub' && $s->role !== 'sub' && (int)$s->member_srl !== $staff_me);
				$st_data = json_encode(['member_srl' => (int)$s->member_srl, 'name' => $s->nick_name . ' (' . ($s->user_id ?: $s->email_address) . ')', 'role' => $s->role, 'perms' => $s->perm_list, 'memo' => (string)$s->memo], JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_APOS + JSON_HEX_QUOT + JSON_HEX_AMP);
				@endphp
				<tr class="{{ $s->status !== 'active' ? 'is-off' : '' }}">
					<td class="st-name"><b>{{ $s->nick_name ?: '#' . $s->member_srl }}</b><span class="st-sub">{{ $s->user_id ?: $s->email_address }}@if ($s->memo) · {{ $s->memo }}@endif</span></td>
					<td>
						<span class="st-badge {{ $s->role === 'sub' ? 'is-sub' : '' }}">{{ lang('commerce.au_role_' . $s->role) }}</span>
						@if ($s->status !== 'active')<span class="st-badge is-off">{{ lang('commerce.st_suspended') }}</span>@endif
					</td>
					<td>
						@if ($s->role === 'sub')
						<span class="st-sub" style="margin:0">{{ lang('commerce.st_all_perms') }}</span>
						@else
						<div class="st-chips">@foreach ($s->perm_list as $st_p)<span>{{ $st_perm_labels[$st_p] ?? $st_p }}</span>@endforeach @if (empty($s->perm_list))<span class="st-sub" style="margin:0">{{ lang('commerce.st_no_perms') }}</span>@endif</div>
						@endif
					</td>
					<td class="st-sub" style="white-space:nowrap">{{ $s->last_seen ? zdate($s->last_seen, 'm.d H:i') : '-' }}</td>
					<td>
						<div class="st-acts">
							<a class="rsva-btn rsva-btn-sm" href="{{ $st_audit_url }}{{ strpos($st_audit_url, '?') === false ? '?' : '&' }}f_member={{ $s->member_srl }}">{{ lang('commerce.st_see_log') }}</a>
							@if ($st_can)
							<button type="button" class="rsva-btn rsva-btn-sm" data-edit='{!! $st_data !!}'>{{ lang('commerce.st_edit') }}</button>
							<button type="button" class="rsva-btn rsva-btn-sm" data-status="{{ $s->status === 'active' ? 'suspended' : 'active' }}" data-srl="{{ $s->member_srl }}">{{ $s->status === 'active' ? lang('commerce.st_suspend') : lang('commerce.st_resume') }}</button>
							<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger" data-remove="{{ $s->member_srl }}">{{ lang('commerce.st_remove') }}</button>
							@endif
						</div>
					</td>
				</tr>
				@endforeach
				</tbody>
			</table>
			@endif
		</div>

		<form class="rsva-panel st-form" id="stForm">
			<h3 id="stFormTitle">{{ lang('commerce.st_add') }}</h3>
			<input type="hidden" name="target_member_srl" id="stSrl" value="" />
			<div id="stFindRow">
				<label class="st-l" for="stFind">{{ lang('commerce.st_find') }}</label>
				<input type="text" id="stFind" name="find" placeholder="{{ lang('commerce.st_find_ph') }}" autocomplete="off" />
				<p class="st-hint" style="margin-top:5px">{{ lang('commerce.st_find_hint') }}</p>
			</div>
			<div>
				<label class="st-l">{{ lang('commerce.st_col_role') }}</label>
				<input type="hidden" name="role" id="stRole" value="manager" />
				<div class="st-seg" id="stRoleSeg">
					<button type="button" data-v="manager" class="is-on">{{ lang('commerce.au_role_manager') }}</button>
					@if ($staff_role === 'owner')<button type="button" data-v="sub">{{ lang('commerce.au_role_sub') }}</button>@endif
				</div>
			</div>
			<div id="stPermBox">
				<label class="st-l">{{ lang('commerce.st_col_perms') }}</label>
				<div class="st-perms">
					@foreach ($staff_perms as $st_p)
					<label class="{{ $st_p === 'export' ? 'is-key' : '' }}"><input type="checkbox" name="perms[]" value="{{ $st_p }}" /> {{ $st_perm_labels[$st_p] }}</label>
					@endforeach
				</div>
			</div>
			<div>
				<label class="st-l" for="stMemo">{{ lang('commerce.st_memo') }}</label>
				<input type="text" id="stMemo" name="memo" maxlength="250" placeholder="{{ lang('commerce.st_memo_ph') }}" />
			</div>
			<p class="st-hint">{{ lang('commerce.st_form_hint') }}</p>
			<div style="display:flex;gap:8px">
				<button type="submit" class="rsva-btn rsva-btn-primary" id="stSubmit">{{ lang('commerce.st_add') }}</button>
				<button type="button" class="rsva-btn" id="stCancel" hidden>{{ lang('commerce.admin_item_edit_68') }}</button>
			</div>
		</form>
	</div>
</div>

<script>
(function () {
	var T = {
		add: {!! json_encode(lang('commerce.st_add')) !!},
		edit: {!! json_encode(lang('commerce.st_edit_title')) !!},
		save: {!! json_encode(lang('commerce.admin_item_edit_159')) !!},
		askRemove: {!! json_encode(lang('commerce.st_ask_remove')) !!},
		askSuspend: {!! json_encode(lang('commerce.st_ask_suspend')) !!},
		needFind: {!! json_encode(lang('commerce.st_need_find')) !!}
	};
	var form = document.getElementById('stForm'), role = document.getElementById('stRole');
	function setRole(v) {
		role.value = v;
		document.querySelectorAll('#stRoleSeg button').forEach(function (b) { b.classList.toggle('is-on', b.dataset.v === v); });
		document.getElementById('stPermBox').hidden = v === 'sub';
	}
	document.getElementById('stRoleSeg').addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) setRole(b.dataset.v); });
	function reset() {
		form.reset(); document.getElementById('stSrl').value = ''; setRole('manager');
		document.getElementById('stFindRow').hidden = false;
		document.getElementById('stFormTitle').textContent = T.add;
		document.getElementById('stSubmit').textContent = T.add;
		document.getElementById('stCancel').hidden = true;
	}
	document.getElementById('stCancel').addEventListener('click', reset);
	document.querySelectorAll('[data-edit]').forEach(function (b) {
		b.addEventListener('click', function () {
			var d = JSON.parse(b.getAttribute('data-edit'));
			reset();
			document.getElementById('stSrl').value = d.member_srl;
			document.getElementById('stFindRow').hidden = true;
			document.getElementById('stFormTitle').textContent = T.edit.replace('%s', d.name);
			document.getElementById('stSubmit').textContent = T.save;
			document.getElementById('stCancel').hidden = false;
			document.getElementById('stMemo').value = d.memo || '';
			form.querySelectorAll('input[name="perms[]"]').forEach(function (c) { c.checked = d.perms.indexOf(c.value) !== -1; });
			setRole(d.role);
			form.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		});
	});
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var srl = document.getElementById('stSrl').value, find = document.getElementById('stFind').value.trim();
		if (!srl && !find) { alert(T.needFind); return; }
		var perms = [].map.call(form.querySelectorAll('input[name="perms[]"]:checked'), function (c) { return c.value; });
		exec_json('commerce.procCommerceAdminSaveStaff', { target_member_srl: srl, find: find, role: role.value, perms: perms.join(','), memo: document.getElementById('stMemo').value }, function () { location.reload(); });
	});
	document.querySelectorAll('[data-status]').forEach(function (b) {
		b.addEventListener('click', function () {
			if (b.dataset.status === 'suspended' && !confirm(T.askSuspend)) return;
			exec_json('commerce.procCommerceAdminStaffStatus', { target_member_srl: b.dataset.srl, status: b.dataset.status }, function () { location.reload(); });
		});
	});
	document.querySelectorAll('[data-remove]').forEach(function (b) {
		b.addEventListener('click', function () {
			if (!confirm(T.askRemove)) return;
			exec_json('commerce.procCommerceAdminDeleteStaff', { target_member_srl: b.dataset.remove }, function () { location.reload(); });
		});
	});
})();
</script>
