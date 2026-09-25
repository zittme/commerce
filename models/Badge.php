<?php

namespace Zittme\Modules\Commerce\Models;

class Badge
{
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
		return Lang::textAll($rows, ['title']);
	}

	public static function getMap(bool $active_only = false): array
	{
		$map = [];
		foreach (self::getList($active_only) as $badge)
		{
			$map[(int)$badge->badge_srl] = $badge;
		}
		return $map;
	}

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
