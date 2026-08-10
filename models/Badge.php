<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 상품 뱃지 — 판매자가 직접 만들어 상품에 붙이는 표시.
 *
 * 기본 제공되는 추천·NEW 와 달리 문구와 색을 자유롭게 정한다.
 * 상품에는 badge_srl 목록을 쉼표로 이어 저장한다.
 */
class Badge
{
	/**
	 * 뱃지 목록.
	 *
	 * @param bool $active_only 노출 중인 것만
	 * @return array<object>
	 */
	public static function getList(bool $active_only = false): array
	{
		$args = new \stdClass;
		if ($active_only)
		{
			$args->is_active = 'Y';
		}
		$output = executeQueryArray('commerce.getBadgeList', $args);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$rows = is_array($output->data) ? $output->data : [$output->data];
		$rows = array_values(array_filter($rows, function($row) { return !empty($row->badge_srl); }));
		// 다국어 문구를 연결한 문구는 미리 바꿔 둔다 (원본은 title_raw)
		return Lang::textAll($rows, ['title']);
	}

	/**
	 * 번호로 찾기 쉽게 만든 목록.
	 *
	 * @param bool $active_only
	 * @return array<int, object>
	 */
	public static function getMap(bool $active_only = false): array
	{
		$map = [];
		foreach (self::getList($active_only) as $badge)
		{
			$map[(int)$badge->badge_srl] = $badge;
		}
		return $map;
	}

	/**
	 * 상품에 붙은 뱃지들. 저장된 순서를 그대로 따른다.
	 *
	 * @param object $item
	 * @param array<int, object> $map getMap() 결과 (목록 화면에서 재사용)
	 * @return array<object>
	 */
	public static function ofItem(object $item, array $map): array
	{
		$result = [];
		foreach (self::parseSrls($item->badges ?? '') as $srl)
		{
			if (isset($map[$srl]))
			{
				$result[] = $map[$srl];
			}
		}
		return $result;
	}

	/**
	 * 쉼표로 이어 둔 번호 문자열을 배열로.
	 *
	 * @param string $raw
	 * @return array<int>
	 */
	public static function parseSrls(string $raw): array
	{
		$srls = [];
		foreach (explode(',', $raw) as $srl)
		{
			$srl = (int)trim($srl);
			if ($srl > 0 && !in_array($srl, $srls, true))
			{
				$srls[] = $srl;
			}
		}
		return $srls;
	}
}
