@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_badges_1') }}</h3>
		<p class="zmc-badgehint">{{ lang('commerce.admin_badges_2') }}</p>

		@if (empty($badges))
		<p class="rsva-empty">{{ lang('commerce.admin_badges_3') }}</p>
		@else
		<table class="rsva-table zmc-badgetable" style="margin-bottom:14px">
			<thead><tr><th style="width:120px">{{ lang('commerce.admin_badges_4') }}</th><th>{{ lang('commerce.admin_badges_5') }}</th><th style="width:110px">{{ lang('commerce.admin_badges_6') }}</th><th style="width:110px">{{ lang('commerce.admin_badges_7') }}</th><th style="width:80px">{{ lang('commerce.admin_badges_8') }}</th><th style="width:100px">{{ lang('commerce.admin_badges_9') }}</th><th style="width:150px"></th></tr></thead>
			<tbody>
				@foreach ($badges as $b)
				@php
					$b_style = '';
					if (!empty($b->bg_color)) { $b_style .= 'background:' . $b->bg_color . ';'; }
					if (!empty($b->color)) { $b_style .= 'color:' . $b->color . ';'; }
				@endphp
				<tr>
					{{-- 행마다 폼을 표 안에 두면 중첩되므로 form 속성으로 잇는다 --}}
					<form action="{{ getUrl('') }}" method="post" id="zmcBadgeForm{{ $b->badge_srl }}"></form>
					<td><span class="zmc-badge-pv" style="{{ $b_style }}">{{ $b->title }}</span></td>
					<td>
						<input type="hidden" name="module" value="admin" form="zmcBadgeForm{{ $b->badge_srl }}" />
						<input type="hidden" name="act" value="procCommerceAdminInsertBadge" form="zmcBadgeForm{{ $b->badge_srl }}" />
						{{-- 저장한 뒤 보던 자리로 돌아온다. 없으면 쪽수와 검색 조건이 날아간다 --}}
						<input type="hidden" name="success_return_url" value="{{ getUrl() }}" form="zmcBadgeForm{{ $b->badge_srl }}" />
						<input type="hidden" name="badge_srl" value="{{ $b->badge_srl }}" form="zmcBadgeForm{{ $b->badge_srl }}" />
						<input type="text" name="title" value="{{ $b->title }}" maxlength="30" required form="zmcBadgeForm{{ $b->badge_srl }}" style="min-width:160px" />@include('_langfield', ['lf_name' => 'title', 'lf_value' => $b->title, 'lf_key' => $b->badge_srl, 'lf_form' => 'zmcBadgeForm' . $b->badge_srl])
					</td>
					<td><input type="color" name="color" value="{{ $b->color ?: '#ffffff' }}" form="zmcBadgeForm{{ $b->badge_srl }}" style="width:56px;height:30px;padding:2px" /></td>
					<td><input type="color" name="bg_color" value="{{ $b->bg_color ?: '#2677e3' }}" form="zmcBadgeForm{{ $b->badge_srl }}" style="width:56px;height:30px;padding:2px" /></td>
					<td><input type="number" name="list_order" value="{{ (int)$b->list_order }}" style="width:64px" form="zmcBadgeForm{{ $b->badge_srl }}" /></td>
					<td>
						<select name="is_active" form="zmcBadgeForm{{ $b->badge_srl }}">
							<option value="Y" @if ($b->is_active !== 'N') selected @endif>{{ lang('commerce.admin_badges_9') }}</option>
							<option value="N" @if ($b->is_active === 'N') selected @endif>{{ lang('commerce.admin_badges_10') }}</option>
						</select>
					</td>
					<td>
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-primary" form="zmcBadgeForm{{ $b->badge_srl }}">{{ lang('commerce.admin_badges_11') }}</button>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ lang('commerce.adm_badge_delete_ask') }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procCommerceAdminDeleteBadge" />
							<input type="hidden" name="badge_srl" value="{{ $b->badge_srl }}" />
							<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_badges_12') }}</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif

		<form action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminInsertBadge" />
			<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
			<div class="rsva-inline">
				<div><label>{{ lang('commerce.admin_badges_13') }}</label><input type="text" name="title" maxlength="30" placeholder="{{ lang('commerce.admin_badges_15') }}" required /></div>
				<div><label>{{ lang('commerce.admin_badges_6') }}</label><input type="color" name="color" value="#ffffff" style="width:56px;height:34px;padding:2px" /></div>
				<div><label>{{ lang('commerce.admin_badges_7') }}</label><input type="color" name="bg_color" value="#2677e3" style="width:56px;height:34px;padding:2px" /></div>
				<div><label>{{ lang('commerce.admin_badges_8') }}</label><input type="number" name="list_order" value="0" style="width:70px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_badges_14') }}</button></div>
			</div>
		</form>
	</div>
</div>

<style>
.zmc-badgehint { margin: 0 0 12px; font-size: 12.5px; color: #6b7684; }
.zmc-badgetable td { vertical-align: middle; }
.zmc-badge-pv { display: inline-block; padding: 3px 9px; border-radius: 6px; background: #2677e3; color: #fff; font-size: 12px; font-weight: 700; }
</style>
