<style>
.zqr-dim { position: fixed; inset: 0; z-index: 400; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(28,24,18,.45); }
.zqr-dim.is-open { display: flex; }
.zqr-box { width: 100%; max-width: 360px; box-sizing: border-box; padding: 22px 24px; border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); box-shadow: 0 20px 50px -20px rgba(28,24,18,.5); }
.zqr-box h3 { margin: 0 0 4px; font-size: 16px; font-weight: 800; }
.zqr-url { margin: 0 0 14px; font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); overflow-wrap: anywhere; }
.zqr-img { display: flex; justify-content: center; padding: 12px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r-sm, 5px); background: #fff; }
.zqr-img svg { display: block; width: 220px; height: 220px; }
.zqr-acts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.zqr-acts .zqr-close { margin-left: auto; }
.zqr-box .rsva-btn { border: 1px solid var(--zmc-line-strong, #d6d2c8); background: var(--zmc-surface, #fff) !important; color: var(--zmc-ink, #232a3b) !important; }
.zqr-box .rsva-btn:hover, .zqr-box .rsva-btn:focus-visible { border-color: var(--zmc-brand, #26345c); color: var(--zmc-brand-ink, #26345c) !important; }
.zqr-box .rsva-btn-primary { border-color: var(--zmc-brand, #26345c); background: var(--zmc-brand, #26345c) !important; color: var(--zmc-on-brand, #fff8e6) !important; }
.zqr-box .rsva-btn-primary:hover, .zqr-box .rsva-btn-primary:focus-visible { filter: brightness(1.12); color: var(--zmc-on-brand, #fff8e6) !important; }
</style>
<div class="zqr-dim" id="zqrDim" role="dialog" aria-modal="true" aria-labelledby="zqrTitle">
	<div class="zqr-box">
		<h3 id="zqrTitle"></h3>
		<p class="zqr-url" id="zqrUrl"></p>
		<div class="zqr-img" id="zqrImg"></div>
		<div class="zqr-acts">
			<a class="rsva-btn rsva-btn-sm rsva-btn-primary" id="zqrPng" href="#" download>{{ lang('commerce.qr_png') }}</a>
			<a class="rsva-btn rsva-btn-sm" id="zqrSvg" href="#" download>{{ lang('commerce.qr_svg') }}</a>
			<button type="button" class="rsva-btn rsva-btn-sm zqr-close" id="zqrClose">{{ lang('commerce.qr_close') }}</button>
		</div>
	</div>
</div>
<script src="{{ \RX_BASEURL }}modules/commerce/tpl/js/qr.js?v=1.1.1"></script>
<script>
(function () {
	var dim = document.getElementById('zqrDim');
	if (!dim || !window.ZmcQr) return;
	var svgUrl = '';
	function close() { dim.classList.remove('is-open'); if (svgUrl) { URL.revokeObjectURL(svgUrl); svgUrl = ''; } }
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-qr-url]');
		if (!btn) return;
		e.preventDefault();
		var url = btn.getAttribute('data-qr-url');
		var name = (btn.getAttribute('data-qr-name') || 'shop').replace(/[^a-z0-9_-]/gi, '') || 'shop';
		var svg = window.ZmcQr.svg(url);
		document.getElementById('zqrTitle').textContent = btn.getAttribute('data-qr-title') || name;
		document.getElementById('zqrUrl').textContent = url;
		document.getElementById('zqrImg').innerHTML = svg;
		var png = document.getElementById('zqrPng');
		png.href = window.ZmcQr.png(url, 16);
		png.setAttribute('download', name + '-qr.png');
		if (svgUrl) URL.revokeObjectURL(svgUrl);
		svgUrl = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
		var sv = document.getElementById('zqrSvg');
		sv.href = svgUrl;
		sv.setAttribute('download', name + '-qr.svg');
		dim.classList.add('is-open');
	});
	document.getElementById('zqrClose').addEventListener('click', close);
	dim.addEventListener('click', function (e) { if (e.target === dim) close(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
