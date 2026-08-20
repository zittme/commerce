{{-- 목록 공용 페이지 네비게이션. pn(PageHandler)과 pn_param(페이지 쿼리 이름, 기본 'page')을 받는다 --}}
@php
$pn_param = $pn_param ?? 'page';
$pn_pages = [];
if (!empty($pn) && (int)$pn->total_page > 1)
{
	// getNextPage()는 현재 페이지 주변 묶음을 돌려주는 반복자다
	while ($pn_no = $pn->getNextPage())
	{
		$pn_pages[] = $pn_no;
	}
}
@endphp
@if (count($pn_pages))
<nav class="rsva-pagenav">
	@if ((int)$pn->cur_page > 1)
	<a href="{{ getUrl($pn_param, (int)$pn->cur_page - 1) }}" class="rsva-pagenav-arrow" aria-label="prev"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3L5 8l5 5"/></svg></a>
	@endif
	@foreach ($pn_pages as $pn_no)
	<a href="{{ getUrl($pn_param, $pn_no) }}" class="{{ (int)$pn->cur_page === (int)$pn_no ? 'is-active' : '' }}">{{ $pn_no }}</a>
	@endforeach
	@if ((int)$pn->cur_page < (int)$pn->total_page)
	<a href="{{ getUrl($pn_param, (int)$pn->cur_page + 1) }}" class="rsva-pagenav-arrow" aria-label="next"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3l5 5-5 5"/></svg></a>
	@endif
</nav>
@endif
