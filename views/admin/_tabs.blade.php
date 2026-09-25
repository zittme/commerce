<style>
.rsva { font-family: 'Pretendard Variable', Pretendard, -apple-system, BlinkMacSystemFont, system-ui, sans-serif; word-break: keep-all; color: #1c2330; }
.rsva-table td { color: #1c2330; }
.rsva-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
.rsva-card { padding: 18px 20px; border: 1px solid #e5e8ee; border-radius: 14px; background: #fff; }
.rsva-card b { display: block; font-size: 26px; font-weight: 800; color: #2677e3; }
.rsva-card span { font-size: 13px; color: #6b7684; }
.rsva-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e8ee; border-radius: 12px; overflow: hidden; }
.rsva-table th { padding: 10px 12px; background: #f7f8fa; font-size: 13px; font-weight: 600; color: #6b7684; text-align: left; border-bottom: 1px solid #e5e8ee; }
.rsva-table td { padding: 11px 12px; font-size: 13px; border-bottom: 1px solid #f0f2f5; vertical-align: middle; }
.rsva-table tr:last-child td { border-bottom: 0; }
.rsva-st { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #f2f3f5; color: #6b7684; }
.rsva-st-confirmed { background: rgba(38,119,227,.1); color: #2677e3; }
.rsva-st-hold, .rsva-st-pending { background: #fdf3e2; color: #b97a17; }
.rsva-st-cancelled, .rsva-st-expired { background: #fdeaea; color: #c0392b; }
.rsva-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
.rsva-filter select, .rsva-filter input { padding: 7px 10px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 13px; font-family: inherit; }
/* 관리자 전역 a/버튼 색 규칙이 특이도로 덮으므로 색은 !important 로 고정한다 */
.rsva-btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 13px; border: 1px solid #e5e8ee; border-radius: 9px; background: #fff !important; font-size: 13px; font-weight: 600; font-family: inherit; cursor: pointer; color: #1c2330 !important; text-decoration: none !important; }
.rsva-btn:hover { border-color: #2677e3; color: #2677e3 !important; }
.rsva-btn-primary { background: #2677e3 !important; border-color: #2677e3; color: #fff !important; }
.rsva-btn-primary:hover { filter: brightness(1.06); color: #fff !important; }
.rsva-btn-sm { padding: 4px 9px; font-size: 12px; border-radius: 7px; }
.rsva-btn-danger:hover { border-color: #e5484d; color: #e5484d !important; }
.rsva-panel { padding: 18px 20px; border: 1px solid #e5e8ee; border-radius: 14px; background: #fff; margin-bottom: 16px; }
.rsva-panel h3 { margin: 0 0 12px; font-size: 15px; font-weight: 700; }
.rsva-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.rsva-form-grid label, .rsva-field label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; }
.rsva-form-grid input, .rsva-form-grid select, .rsva-form-grid textarea,
.rsva-field input, .rsva-field select, .rsva-field textarea { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 13px; font-family: inherit; }
.rsva-field { margin-bottom: 12px; }
.rsva-inline { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
.rsva-inline > div { min-width: 90px; }
.rsva-empty { padding: 32px 0; text-align: center; color: #6b7684; font-size: 13px; }
.rsva-pagenav { display: flex; justify-content: center; align-items: center; gap: 4px; margin: 16px 0 4px; }
.rsva-pagenav a { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 6px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #4e5968 !important; text-decoration: none !important; }
.rsva-pagenav a:hover { background: #f4f6f9; color: #2677e3 !important; }
.rsva-pagenav a.is-active { background: #2677e3; color: #fff !important; }
.rsva-weekdays { display: flex; gap: 6px; flex-wrap: wrap; }
.rsva-weekdays label { display: inline-flex; align-items: center; gap: 4px; padding: 5px 9px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 12px; cursor: pointer; margin: 0; font-weight: 500; }
@media (max-width: 768px) { .rsva-table { display: block; overflow-x: auto; } }
</style>

@if (!empty($zmc_console))
<style>
@font-face { font-family: 'Pretendard'; src: url('{{ \RX_BASEURL }}common/fonts/PretendardVariable.woff2') format('woff2-variations'); font-weight: 45 920; font-display: swap; }
:root {
	--zmc-bg: #f7f6f3; --zmc-side: #efede8; --zmc-surface: #ffffff; --zmc-field: #ffffff; --zmc-hover: #e9e6df;
	--zmc-ink: #232a3b; --zmc-ink-2: #3c4458; --zmc-sub: #7a7f8c; --zmc-line: #e6e3dc; --zmc-line-strong: #d6d2c8;
	--zmc-brand: #26345c; --zmc-brand-ink: #26345c; --zmc-on-brand: #fff8e6; --zmc-brand-soft: #fbf1d6; --zmc-mark: #e3a92f;
	--zmc-ok: #3f8a54; --zmc-ok-soft: #e7f2e8; --zmc-warn: #a3690c; --zmc-warn-soft: #fbefd6; --zmc-bad: #b33a2e; --zmc-bad-soft: #fbe6e2;
	--zmc-r: 6px; --zmc-r-sm: 5px;
	--zmc-font: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif;
}
html, body { margin: 0; padding: 0; background: var(--zmc-bg); }
body, body * { font-family: var(--zmc-font); font-style: normal; }
body { -webkit-font-smoothing: antialiased; color: var(--zmc-ink); }
.zmc-side { position: fixed; top: 0; left: 0; bottom: 0; width: 220px; box-sizing: border-box; padding: 20px 12px 14px; background: var(--zmc-side); border-right: 1px solid var(--zmc-line); z-index: 100; overflow-y: auto; display: flex; flex-direction: column; gap: 18px; }
.zmc-shop { display: flex; align-items: center; gap: 10px; padding: 0 8px; }
.zmc-shop i { flex: none; width: 34px; height: 34px; border-radius: var(--zmc-r-sm); background: var(--zmc-brand); color: var(--zmc-on-brand); display: grid; place-items: center; font-size: 15px; font-weight: 800; font-style: normal; }
.zmc-shop div { min-width: 0; }
.zmc-shop b { display: block; font-size: 15px; font-weight: 800; line-height: 1.25; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.zmc-shop small { display: block; font-size: 12px; color: var(--zmc-sub); }
.zmc-nav { flex: 1; display: flex; flex-direction: column; gap: 18px; }
.zmc-nav > div { border-top: 1px solid var(--zmc-line-strong); background: var(--zmc-surface); }
.zmc-nav > div:has(> p) { border-top: 0; background: transparent; }
.zmc-nav > div:has(> p) > a:first-of-type { border-top: 1px solid var(--zmc-line-strong); }
.zmc-nav p { margin: 0 0 6px; padding: 0 10px; font-size: 11.5px; font-weight: 700; color: var(--zmc-sub); letter-spacing: .06em; }
.zmc-nav a { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; border-radius: 0; border-bottom: 1px solid var(--zmc-line-strong); background: var(--zmc-surface); font-size: 14px; font-weight: 500; color: var(--zmc-ink) !important; text-decoration: none !important; }
.zmc-nav a:hover { background: var(--zmc-hover); }
.zmc-nav a.is-active { background: var(--zmc-brand-soft); font-weight: 700; box-shadow: inset 3px 0 0 var(--zmc-mark); }
.zmc-nav a em { font-style: normal; min-width: 20px; padding: 0 6px; border-radius: 10px; background: var(--zmc-brand); color: var(--zmc-on-brand); font-size: 11.5px; font-weight: 700; line-height: 19px; text-align: center; }
.zmc-side-foot { display: flex; flex-direction: column; padding-top: 12px; border-top: 1px solid var(--zmc-line); }
.zmc-side-foot a { padding: 6px 10px; border-radius: var(--zmc-r-sm); font-size: 12.5px; color: var(--zmc-sub) !important; text-decoration: none !important; }
.zmc-side-foot a:hover { color: var(--zmc-ink) !important; background: var(--zmc-hover); }
.zmc-top { position: sticky; top: 0; z-index: 90; margin-left: 220px; padding: 14px 32px; background: var(--zmc-bg); border-bottom: 1px solid var(--zmc-line); display: flex; align-items: center; gap: 12px; }
.zmc-top-title { flex: 1; min-width: 0; }
.zmc-top-title small { display: block; font-size: 12.5px; color: var(--zmc-sub); }
.zmc-top h2 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -0.01em; }
.zmc-tabs { margin-left: 220px; padding: 0 32px; display: flex; gap: 22px; border-bottom: 1px solid var(--zmc-line); background: var(--zmc-bg); overflow-x: auto; }
.zmc-tabs a { flex: none; padding: 11px 0 10px; border-bottom: 2px solid transparent; font-size: 14px; font-weight: 600; color: var(--zmc-sub) !important; text-decoration: none !important; }
.zmc-tabs a:hover { color: var(--zmc-ink) !important; }
.zmc-tabs a.is-active { color: var(--zmc-ink) !important; border-color: var(--zmc-brand); }
.rsva { margin-left: 220px; padding: 24px 32px 100px; box-sizing: border-box; min-height: calc(100vh - 60px); }
.rsva { font-size: 14px; color: var(--zmc-ink); }
.rsva .rsva-panel { padding: 22px 24px; border: 1px solid var(--zmc-line); border-radius: var(--zmc-r); background: var(--zmc-surface); margin-bottom: 16px; box-shadow: none; }
.rsva .rsva-panel > h3 { margin: 0 0 16px; padding: 0; border: 0; font-size: 16px; font-weight: 800; }
.rsva .rsva-form-grid { gap: 14px 16px; }
.rsva label { display: block; font-size: 13px; font-weight: 600; color: var(--zmc-ink-2); margin-bottom: 6px; }
.rsva .rsva-inline { gap: 12px 16px; }
.rsva input[type="text"], .rsva input[type="number"], .rsva input[type="date"], .rsva input[type="datetime-local"], .rsva input[type="time"], .rsva input[type="email"], .rsva input[type="tel"], .rsva input[type="password"], .rsva input[type="url"], .rsva input[type="search"], .rsva select, .rsva textarea { box-sizing: border-box; padding: 9px 11px; border: 1px solid var(--zmc-line-strong); border-radius: var(--zmc-r-sm); font-size: 14px; background: var(--zmc-field); color: var(--zmc-ink); transition: border-color .12s, box-shadow .12s; }
.rsva input:focus, .rsva select:focus, .rsva textarea:focus { outline: none; border-color: var(--zmc-brand); box-shadow: 0 0 0 3px rgba(38,52,92,.12); }
.rsva input[type="checkbox"], .rsva input[type="radio"] { accent-color: var(--zmc-brand); }
.rsva .rsva-btn { padding: 8px 15px; border-radius: var(--zmc-r-sm); font-size: 14px; border: 1px solid var(--zmc-line-strong); background: var(--zmc-surface) !important; color: var(--zmc-ink) !important; box-shadow: none; }
.rsva .rsva-btn:hover { border-color: var(--zmc-brand); color: var(--zmc-brand-ink) !important; }
.rsva .rsva-btn-primary { background: var(--zmc-brand) !important; border-color: var(--zmc-brand); color: var(--zmc-on-brand) !important; box-shadow: none; }
.rsva .rsva-btn-primary:hover { filter: brightness(1.12); color: var(--zmc-on-brand) !important; }
.rsva .rsva-btn-danger:hover { border-color: var(--zmc-bad); color: var(--zmc-bad) !important; }
.rsva .rsva-btn-sm { padding: 5px 10px; font-size: 12.5px; border-radius: var(--zmc-r-sm); }
.rsva .rsva-table { border: 1px solid var(--zmc-line); border-radius: var(--zmc-r); background: var(--zmc-surface); }
.rsva .rsva-table th { padding: 10px 14px; background: transparent; border-bottom: 1px solid var(--zmc-line); font-size: 12.5px; font-weight: 700; color: var(--zmc-sub); }
.rsva .rsva-table td { padding: 11px 14px; font-size: 13.5px; border-bottom: 1px solid var(--zmc-line); color: var(--zmc-ink); }
.rsva .rsva-table tbody tr:hover td { background: #fbfaf7; }
.rsva .rsva-filter { padding: 0; background: transparent; border: 0; border-radius: 0; margin-bottom: 14px; }
.rsva .rsva-card { border: 1px solid var(--zmc-line); border-radius: var(--zmc-r); box-shadow: none; background: var(--zmc-surface); }
.rsva .rsva-card b { color: var(--zmc-ink); }
.rsva .rsva-st { border-radius: 4px; background: #efede8; color: var(--zmc-ink-2); }
.rsva .rsva-st-confirmed, .rsva .rsva-st-paid, .rsva .rsva-st-on { background: var(--zmc-ok-soft); color: var(--zmc-ok); }
.rsva .rsva-st-hold, .rsva .rsva-st-pending { background: var(--zmc-warn-soft); color: var(--zmc-warn); }
.rsva .rsva-st-cancelled, .rsva .rsva-st-expired { background: var(--zmc-bad-soft); color: var(--zmc-bad); }
.rsva .rsva-pagenav a.is-active { background: var(--zmc-brand); color: var(--zmc-on-brand) !important; }
.rsva .rsva-pagenav a:hover { background: var(--zmc-hover); color: var(--zmc-ink) !important; }
.rsva a { color: var(--zmc-brand-ink); }
.rsva small { color: var(--zmc-sub); }
.zmc-menu-btn { display: none; align-items: center; justify-content: center; width: 38px; height: 38px; padding: 0; border: 1px solid var(--zmc-line-strong); border-radius: var(--zmc-r-sm); background: var(--zmc-surface); color: var(--zmc-ink); cursor: pointer; }
.zmc-menu-btn svg { display: block; }
.zmc-side-dim { display: none; position: fixed; inset: 0; z-index: 99; background: rgba(28,24,18,.4); }
.zmc-side-dim.is-open { display: block; }
.zmc-side-close { display: none; margin-left: auto; padding: 4px; border: 0; background: none; color: var(--zmc-sub); cursor: pointer; }
@media (max-width: 900px) {
	.zmc-side { width: 264px; transform: translateX(-100%); transition: transform .18s ease; }
	.zmc-side.is-open { transform: translateX(0); box-shadow: 0 0 40px rgba(28,24,18,.25); }
	.zmc-side-close { display: block; }
	.zmc-top { margin-left: 0; padding: 12px 14px; }
	.zmc-tabs { margin-left: 0; padding: 0 14px; }
	.zmc-menu-btn { display: inline-flex; }
	.rsva { margin-left: 0; padding: 16px 14px 70px; }
}
@media (prefers-reduced-motion: reduce) { .zmc-side { transition: none; } }
</style>
@php
$zmc_menu = [];
foreach (['pins', 'timesale', 'staff', 'audit', 'dashboard', 'orders', 'shipping', 'items', 'brands', 'stock', 'categories', 'badges', 'promotions', 'qna', 'claims', 'coupons', 'credits', 'grades', 'stats', 'config', 'sellers', 'settlements', 'seller_profile'] as $zmc_key)
{
	$zmc_menu[$zmc_key] = lang('commerce.admin_menu_' . $zmc_key);
}
$zmc_cfg_tabs = ['config' => lang('commerce.cfg_tab_general')];
foreach (['shipping', 'display', 'rewards', 'notify', 'policy'] as $zmc_key)
{
	$zmc_cfg_tabs['config_' . $zmc_key] = lang('commerce.cfg_tab_' . $zmc_key);
}
$zmc_tree = [
	['label' => '', 'items' => ['dashboard']],
	['label' => lang('commerce.admin_menu_group_orders'), 'items' => ['orders', 'shipping', 'claims', 'qna']],
	['label' => lang('commerce.admin_menu_group_items'), 'items' => ['items', 'brands', 'categories', 'badges', 'stock', 'pins']],
	['label' => lang('commerce.admin_menu_group_benefits'), 'items' => ['promotions', 'timesale', 'coupons', 'credits', 'grades']],
	['label' => lang('commerce.admin_menu_group_shop'), 'items' => ['stats', 'config_display', 'config']],
	['label' => lang('commerce.admin_menu_group_market'), 'items' => ['sellers', 'settlements']],
	['label' => lang('commerce.admin_menu_group_team'), 'items' => ['staff', 'audit']],
];
$zmc_entry = $zmc_entry ?? 'dispCommerceConsole';
$zmc_is_seller = !empty($zmc_seller_center);
if ($zmc_is_seller)
{
	$zmc_menu['dashboard'] = lang('commerce.sc_menu_dashboard');
	$zmc_menu['shipping'] = lang('commerce.sc_menu_shipping');
	$zmc_menu['shop_cats'] = lang('commerce.sc_menu_shop_cats');
	$zmc_menu['shop_design'] = lang('commerce.sc_menu_shop_design');
	$zmc_menu['seller_profile'] = lang('commerce.sc_menu_shop_info');
	$zmc_tree = [
		['label' => '', 'items' => ['dashboard']],
		['label' => lang('commerce.sc_group_items'), 'items' => ['items', 'shop_cats']],
		['label' => lang('commerce.sc_group_orders'), 'items' => ['shipping', 'settlements']],
		['label' => lang('commerce.sc_group_shop'), 'items' => ['shop_design', 'seller_profile']],
	];
	$zmc_cfg_tabs = [];
}
foreach ($zmc_tree as $zmc_gi => $zmc_g)
{
	$zmc_keep = [];
	foreach ($zmc_g['items'] as $zmc_key)
	{
		if (Zittme\Modules\Commerce\Models\Staff::canPage($zmc_key)) { $zmc_keep[] = $zmc_key; }
	}
	if (count($zmc_keep)) { $zmc_tree[$zmc_gi]['items'] = $zmc_keep; } else { unset($zmc_tree[$zmc_gi]); }
}
foreach (array_keys($zmc_cfg_tabs) as $zmc_key)
{
	if (!Zittme\Modules\Commerce\Models\Staff::canPage($zmc_key)) { unset($zmc_cfg_tabs[$zmc_key]); }
}
$zmc_role = Zittme\Modules\Commerce\Models\Staff::role();
$zmc_active_alias = ['order_view' => 'orders', 'item_edit' => 'items'];
$zmc_current = $zmc_active_alias[$zmc_page] ?? $zmc_page;
$zmc_is_cfg = isset($zmc_cfg_tabs[$zmc_current]);
$zmc_nav_current = $zmc_is_cfg ? ($zmc_current === 'config_display' ? 'config_display' : 'config') : $zmc_current;
$zmc_menu['config_display'] = lang('commerce.cfg_menu_home');
$zmc_counts = $zmc_counts ?? [];
$zmc_badge = ['sellers' => $zmc_counts['sellers'] ?? 0, 'shipping' => $zmc_counts['to_ship'] ?? 0, 'claims' => $zmc_counts['claims'] ?? 0, 'qna' => $zmc_counts['unanswered'] ?? 0];
$zmc_shop_name = $zmc_is_seller ? (string)($zmc_seller->shop_name ?? '') : (trim((string)Context::getSiteTitle()) ?: lang('commerce.admin_console_title'));
$zmc_shell_title = $zmc_is_seller ? lang('commerce.sc_title') : lang('commerce.admin_console_title');
$zmc_title = $zmc_is_cfg ? ($zmc_current === 'config_display' ? lang('commerce.cfg_menu_home') : $zmc_menu['config']) : ($zmc_menu[$zmc_current] ?? '');
$zmc_group_of = '';
foreach ($zmc_tree as $zmc_g) { if (in_array($zmc_nav_current, $zmc_g['items'], true)) { $zmc_group_of = $zmc_g['label']; } }
@endphp
<aside class="zmc-side">
	<div class="zmc-shop">
		<i aria-hidden="true">{{ mb_substr($zmc_shop_name, 0, 1) }}</i>
		<div><b>{{ $zmc_shop_name }}</b>@if ($zmc_shop_name !== $zmc_shell_title)<small>{{ $zmc_shell_title }}</small>@endif @if ($zmc_role !== 'owner' && !$zmc_is_seller)<small class="zmc-role">{{ lang('commerce.au_role_' . $zmc_role) }}</small>@endif</div>
		<button type="button" class="zmc-side-close" id="zmcSideClose" aria-label="{{ lang('commerce.admin_menu_close') }}"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button>
	</div>
	<nav class="zmc-nav">
		@foreach ($zmc_tree as $zmc_g)
		<div>
			@if ($zmc_g['label'] !== '')<p>{{ $zmc_g['label'] }}</p>@endif
			@foreach ($zmc_g['items'] as $zmc_key)
			<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', $zmc_entry, 'p', $zmc_key) }}" class="{{ $zmc_nav_current === $zmc_key ? 'is-active' : '' }}"><span>{{ $zmc_menu[$zmc_key] }}</span>@if (!empty($zmc_badge[$zmc_key]))<em>{{ $zmc_badge[$zmc_key] > 99 ? '99+' : $zmc_badge[$zmc_key] }}</em>@endif</a>
			@endforeach
		</div>
		@endforeach
	</nav>
	<div class="zmc-side-foot">
		@if ($zmc_is_seller && !empty($zmc_store_url))<a href="{{ $zmc_store_url }}" target="_blank">{{ lang('commerce.sc_view_store') }} ↗</a>@endif
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', '') }}" target="_blank">{{ lang('commerce.admin_view_site') }} ↗</a>
		@if ($zmc_role === 'owner')<a href="{{ getUrl('', 'mid', '', 'module', 'admin', 'act', '') }}" target="_blank">{{ lang('commerce.admin_go_admin') }} ↗</a>@endif
	</div>
</aside>
<div class="zmc-side-dim" id="zmcSideDim"></div>
<div class="zmc-top">
	<button type="button" class="zmc-menu-btn" id="zmcMenuBtn" aria-label="{{ lang('commerce.admin_menu_open') }}"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M2 4.5h14M2 9h14M2 13.5h14"/></svg></button>
	<div class="zmc-top-title">@if ($zmc_group_of !== '')<small>{{ $zmc_group_of }}</small>@endif<h2>{{ $zmc_title }}</h2></div>
</div>
@if ($zmc_is_cfg)
<nav class="zmc-tabs" aria-label="{{ $zmc_menu['config'] }}">
	@foreach ($zmc_cfg_tabs as $zmc_key => $zmc_tab)
	<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', $zmc_entry, 'p', $zmc_key) }}" class="{{ $zmc_current === $zmc_key ? 'is-active' : '' }}">{{ $zmc_tab }}</a>
	@endforeach
</nav>
@endif
<script>
(function () {
	var side = document.querySelector('.zmc-side');
	var dim = document.getElementById('zmcSideDim');
	var btn = document.getElementById('zmcMenuBtn');
	if (!side || !dim || !btn) return;
	function open() { side.classList.add('is-open'); dim.classList.add('is-open'); }
	function close() { side.classList.remove('is-open'); dim.classList.remove('is-open'); }
	btn.addEventListener('click', open);
	dim.addEventListener('click', close);
	var closeBtn = document.getElementById('zmcSideClose');
	if (closeBtn) closeBtn.addEventListener('click', close);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
	document.querySelectorAll('.zmc-group-btn').forEach(function (b) {
		b.addEventListener('click', function () { b.parentElement.classList.toggle('is-open'); });
	});
})();
</script>

<script>
(function () {
	var ENTRY = {!! json_encode($zmc_entry) !!};
	function toP(n) { return n.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase(); }
	function rewrite() {
		document.querySelectorAll('a[href*="dispCommerceAdmin"]').forEach(function (a) {
			if (a.hasAttribute('data-zmc-keep')) {
				var k = new URL(a.href, location.href);
				if (k.searchParams.get('module') === 'admin') { k.searchParams.set('module', 'commerce'); a.href = k.toString(); }
				return;
			}
			var m = a.getAttribute('href').match(/dispCommerceAdmin([A-Za-z]+)/);
			if (!m) return;
			var u = new URL(a.href, location.href);
			u.searchParams.delete('module');
			u.searchParams.set('act', ENTRY);
			u.searchParams.set('p', toP(m[1]));
			a.href = u.toString();
		});
		document.querySelectorAll('form').forEach(function (f) {
			var act = f.querySelector('input[name="act"]');
			if (!act) return;
			var m = act.value.match(/^dispCommerceAdmin([A-Za-z]+)$/);
			if (m) {
				act.value = ENTRY;
				var mod = f.querySelector('input[name="module"]');
				if (mod) mod.remove();
				var p = f.querySelector('input[name="p"]');
				if (!p) { p = document.createElement('input'); p.type = 'hidden'; p.name = 'p'; f.appendChild(p); }
				p.value = toP(m[1]);
			} else if (/^proc/.test(act.value)) {
				var md = f.querySelector('input[name="module"]');
				if (md && md.value === 'admin') md.value = 'commerce';
				var s = f.querySelector('input[name="success_return_url"]');
				if (!s) { s = document.createElement('input'); s.type = 'hidden'; s.name = 'success_return_url'; f.appendChild(s); }
				s.value = location.href;
			}
		});
	}
	document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', rewrite) : rewrite();
})();
</script>
@else
<div class="x_page-header rsva">
	<h1>{{ $lang->commerce }}</h1>
</div>

<div class="rsva" style="margin:0 0 14px;padding:16px 20px;border:1px solid rgba(38,119,227,.35);border-radius:12px;background:#f2f6fd;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
	<div style="font-size:13.5px;color:#1c2330">{!! lang('commerce.admin_console_notice') !!}</div>
	<a href="{{ getUrl('', 'module', '', 'act', 'dispCommerceConsole') }}" target="_blank" class="rsva-btn rsva-btn-primary" id="zmcOpenConsole">{{ lang('commerce.admin_open_console') }}</a>
</div>
@if ($shop_tab === 'dashboard')
<script>
(function () {
	try {
		if (!sessionStorage.getItem('zmcConsoleOpened')) {
			sessionStorage.setItem('zmcConsoleOpened', '1');
			var btn = document.getElementById('zmcOpenConsole');
			if (btn) window.open(btn.href, '_blank');
		}
	} catch (e) {}
})();
</script>
@endif

@endif
