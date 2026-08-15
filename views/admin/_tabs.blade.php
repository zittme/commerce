<style>
/* 운영 화면 공통 스타일 */
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
.rsva-weekdays { display: flex; gap: 6px; flex-wrap: wrap; }
.rsva-weekdays label { display: inline-flex; align-items: center; gap: 4px; padding: 5px 9px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 12px; cursor: pointer; margin: 0; font-weight: 500; }
@media (max-width: 768px) { .rsva-table { display: block; overflow-x: auto; } }
</style>

@if (!empty($zmc_console))
<style>
@font-face { font-family: 'Pretendard'; src: url('{{ \RX_BASEURL }}common/fonts/PretendardVariable.woff2') format('woff2-variations'); font-weight: 45 920; font-display: swap; }
:root { --zmc-brand: #2677e3; --zmc-brand-soft: #eef4fd; --zmc-ink: #191f28; --zmc-sub: #6b7684; --zmc-line: #e5e8eb; --zmc-bg: #f5f7fa; --zmc-font: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif; }
html, body { margin: 0; padding: 0; background: var(--zmc-bg); }
body, body * { font-family: var(--zmc-font); font-style: normal; }
body { -webkit-font-smoothing: antialiased; color: var(--zmc-ink); }
/* ── 사이드바 ── */
.zmc-side { position: fixed; top: 0; left: 0; bottom: 0; width: 232px; box-sizing: border-box; padding: 24px 16px 16px; background: #fff; border-right: 1px solid var(--zmc-line); z-index: 100; overflow-y: auto; display: flex; flex-direction: column; }
.zmc-logo { display: flex; align-items: baseline; gap: 7px; padding: 0 10px 22px; font-size: 18px; font-weight: 800; color: var(--zmc-ink); letter-spacing: -0.02em; }
.zmc-logo b { color: var(--zmc-brand); font-weight: 800; }
.zmc-logo span { font-size: 14px; font-weight: 700; color: var(--zmc-sub); }
.zmc-nav { flex: 1; }
.zmc-nav a { position: relative; display: flex; align-items: center; gap: 10px; padding: 10px 14px; margin-bottom: 3px; border-radius: 10px; font-size: 14.5px; font-weight: 600; font-style: normal; color: #4e5968 !important; text-decoration: none !important; transition: background .12s, color .12s; }
.zmc-nav a span { font-style: normal; }
.zmc-nav a:hover { background: #f4f6f9; color: var(--zmc-ink) !important; }
.zmc-nav a.is-active { background: var(--zmc-brand-soft); color: var(--zmc-brand) !important; font-weight: 700; }
.zmc-nav a.is-active::before { content: ''; position: absolute; left: 0; top: 9px; bottom: 9px; width: 3px; border-radius: 3px; background: var(--zmc-brand); }
.zmc-side-foot { padding-top: 14px; border-top: 1px solid var(--zmc-line); }
.zmc-side-foot a { display: block; padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: #8b95a1 !important; text-decoration: none !important; }
.zmc-side-foot a:hover { color: var(--zmc-brand) !important; background: #f4f6f9; }
/* ── 상단 바 + 콘텐츠 ── */
.zmc-top { position: sticky; top: 0; z-index: 90; margin-left: 232px; padding: 16px 36px; background: rgba(255,255,255,.92); backdrop-filter: blur(6px); border-bottom: 1px solid var(--zmc-line); display: flex; align-items: center; justify-content: space-between; }
.zmc-top h2 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -0.02em; }
.rsva { margin-left: 232px; padding: 28px 36px 100px; box-sizing: border-box; min-height: calc(100vh - 60px); }
/* ── 콘솔 전용 컴포넌트 재정의 (스마트스토어풍: 카드 섹션 + 넉넉한 입력) ── */
.rsva { font-size: 14px; color: var(--zmc-ink); }
.rsva .rsva-panel { padding: 26px 28px; border: 1px solid var(--zmc-line); border-radius: 16px; background: #fff; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(25,31,40,.03); }
.rsva .rsva-panel > h3 { margin: 0 0 18px; padding-bottom: 14px; border-bottom: 1px solid #f0f2f5; font-size: 16px; font-weight: 800; letter-spacing: -0.01em; }
.rsva .rsva-form-grid { gap: 16px 18px; }
.rsva label { display: block; font-size: 13px; font-weight: 700; color: #333d4b; margin-bottom: 7px; }
.rsva .rsva-inline { gap: 14px 18px; }
.rsva input[type="text"], .rsva input[type="number"], .rsva input[type="date"], .rsva input[type="datetime-local"], .rsva input[type="time"], .rsva input[type="email"], .rsva input[type="tel"], .rsva input[type="password"], .rsva select, .rsva textarea { box-sizing: border-box; padding: 10px 12px; border: 1px solid #dde3ec; border-radius: 10px; font-size: 14px; background: #fff; color: var(--zmc-ink); transition: border-color .12s, box-shadow .12s; }
.rsva input:focus, .rsva select:focus, .rsva textarea:focus { outline: none; border-color: var(--zmc-brand); box-shadow: 0 0 0 3px rgba(38,119,227,.12); }
.rsva .rsva-btn { padding: 9px 16px; border-radius: 10px; font-size: 14px; border-color: #dde3ec; }
.rsva .rsva-btn:hover { border-color: var(--zmc-brand); }
.rsva .rsva-btn-primary { box-shadow: 0 1px 3px rgba(38,119,227,.3); }
.rsva .rsva-btn-sm { padding: 6px 11px; font-size: 12.5px; border-radius: 8px; }
.rsva .rsva-table { border-radius: 14px; border-color: var(--zmc-line); }
.rsva .rsva-table th { padding: 12px 14px; font-size: 12.5px; letter-spacing: .01em; }
.rsva .rsva-table td { padding: 13px 14px; font-size: 13.5px; }
.rsva .rsva-table tbody tr:hover td { background: #fafbfc; }
.rsva .rsva-filter { padding: 14px 16px; background: #fff; border: 1px solid var(--zmc-line); border-radius: 14px; margin-bottom: 16px; }
.rsva .rsva-card { border-radius: 16px; box-shadow: 0 1px 2px rgba(25,31,40,.03); }
.rsva small { color: var(--zmc-sub); }
/* ── 좁은 화면: 메뉴 버튼으로 여는 서랍 ── */
.zmc-menu-btn { display: none; align-items: center; justify-content: center; width: 38px; height: 38px; padding: 0; border: 1px solid var(--zmc-line); border-radius: 10px; background: #fff; color: var(--zmc-ink); cursor: pointer; }
.zmc-menu-btn svg { display: block; }
.zmc-side-dim { display: none; position: fixed; inset: 0; z-index: 99; background: rgba(16,20,28,.45); }
.zmc-side-dim.is-open { display: block; }
.zmc-side-close { display: none; margin-left: auto; padding: 4px; border: 0; background: none; color: var(--zmc-sub); cursor: pointer; }

@media (max-width: 900px) {
	/* 아이콘이 없어 글자만 숨기면 빈 막대가 된다. 아예 밀어 두고 버튼으로 연다 */
	.zmc-side { width: 264px; transform: translateX(-100%); transition: transform .18s ease; }
	.zmc-side.is-open { transform: translateX(0); box-shadow: 0 0 40px rgba(16,20,28,.25); }
	.zmc-side-close { display: block; }
	.zmc-top { margin-left: 0; padding: 12px 14px; display: flex; align-items: center; gap: 12px; }
	.zmc-menu-btn { display: inline-flex; }
	.rsva { margin-left: 0; padding: 16px 14px 70px; }
}
</style>
@php
$zmc_menu = [];
foreach (['dashboard', 'orders', 'items', 'stock', 'categories', 'badges', 'promotions', 'qna', 'claims', 'coupons', 'credits', 'grades', 'stats', 'config'] as $zmc_key)
{
	$zmc_menu[$zmc_key] = lang('commerce.admin_menu_' . $zmc_key);
}
$zmc_active_alias = ['order_view' => 'orders', 'item_edit' => 'items'];
$zmc_current = $zmc_active_alias[$zmc_page] ?? $zmc_page;
@endphp
<aside class="zmc-side">
	<div class="zmc-logo">
		<b>zittme</b> <span>{{ lang('commerce.admin_console_title') }}</span>
		<button type="button" class="zmc-side-close" id="zmcSideClose" aria-label="{{ lang('commerce.admin_menu_close') }}"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button>
	</div>
	<nav class="zmc-nav">
		@foreach ($zmc_menu as $key => $label)
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', $key) }}" class="{{ $zmc_current === $key ? 'is-active' : '' }}"><span>{{ $label }}</span></a>
		@endforeach
	</nav>
	<div class="zmc-side-foot">
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', '') }}" target="_blank">{{ lang('commerce.admin_view_site') }}</a>
		<a href="{{ getUrl('', 'mid', '', 'module', 'admin', 'act', '') }}" target="_blank">{{ lang('commerce.admin_go_admin') }}</a>
	</div>
</aside>
<div class="zmc-side-dim" id="zmcSideDim"></div>
<div class="zmc-top">
	<button type="button" class="zmc-menu-btn" id="zmcMenuBtn" aria-label="{{ lang('commerce.admin_menu_open') }}"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M2 4.5h14M2 9h14M2 13.5h14"/></svg></button>
	<h2>{{ $zmc_menu[$zmc_current] ?? '' }}</h2>
</div>
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
})();
</script>

<script>
(function () {
	function toP(n) { return n.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase(); }
	function rewrite() {
		document.querySelectorAll('a[href*="dispCommerceAdmin"]').forEach(function (a) {
			// 콘솔 페이지가 아닌 독립 화면(주문서 인쇄 등)은 그대로 둔다
			if (a.hasAttribute('data-zmc-keep')) return;
			var m = a.getAttribute('href').match(/dispCommerceAdmin([A-Za-z]+)/);
			if (!m) return;
			var u = new URL(a.href, location.href);
			u.searchParams.delete('module');
			u.searchParams.set('act', 'dispCommerceConsole');
			u.searchParams.set('p', toP(m[1]));
			a.href = u.toString();
		});
		document.querySelectorAll('form').forEach(function (f) {
			var act = f.querySelector('input[name="act"]');
			if (!act) return;
			var m = act.value.match(/^dispCommerceAdmin([A-Za-z]+)$/);
			if (m) {
				act.value = 'dispCommerceConsole';
				var mod = f.querySelector('input[name="module"]');
				if (mod) mod.remove();
				var p = f.querySelector('input[name="p"]');
				if (!p) { p = document.createElement('input'); p.type = 'hidden'; p.name = 'p'; f.appendChild(p); }
				p.value = toP(m[1]);
			} else if (/^proc/.test(act.value)) {
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

{{-- 관리자 탭 제거: 운영 화면은 전용 콘솔에서만 제공한다 --}}
@endif
