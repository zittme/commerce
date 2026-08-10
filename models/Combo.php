<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 조합형 옵션 — 축(색상·사이즈 …)을 정의하고 그 조합을 옵션 행으로 펼친다.
 *
 * 옵션 행 자체는 기존 commerce_item_option 을 그대로 쓴다. 라벨도 종전 형식
 * ("블랙 / L")이라 장바구니·주문·명세서는 손대지 않아도 된다.
 * 축 선택과 행을 잇는 열쇠만 combo(JSON) 로 따로 들고 있는다.
 */
class Combo
{
	/**
	 * 축 최대 개수.
	 */
	public const MAX_AXES = 3;

	/**
	 * 만들 수 있는 조합 최대 개수.
	 */
	public const MAX_ROWS = 100;

	/**
	 * 라벨 구분자.
	 */
	public const SEPARATOR = ' / ';

	/**
	 * 축 정의 읽기.
	 *
	 * @param mixed $raw commerce_item.option_axes
	 * @return array<int, object> [{name, values[]}]
	 */
	public static function axes($raw): array
	{
		if (!is_string($raw) || trim($raw) === '')
		{
			return [];
		}
		$data = json_decode($raw, true);
		if (!is_array($data))
		{
			return [];
		}

		$axes = [];
		foreach ($data as $axis)
		{
			if (!is_array($axis))
			{
				continue;
			}
			$name = trim((string)($axis['name'] ?? ''));
			$values = [];
			foreach ((array)($axis['values'] ?? []) as $value)
			{
				$value = trim((string)$value);
				if ($value !== '' && !in_array($value, $values, true))
				{
					$values[] = mb_substr($value, 0, 60);
				}
			}
			if ($name === '' || !count($values))
			{
				continue;
			}
			// 표시 방식: select(기본) / button / color
			$style = (string)($axis['style'] ?? 'select');
			if (!in_array($style, ['select', 'button', 'color'], true))
			{
				$style = 'select';
			}

			$axes[] = (object)[
				'name' => mb_substr($name, 0, 40),
				'values' => $values,
				'style' => $style,
				'items' => self::items($values),
			];
			if (count($axes) >= self::MAX_AXES)
			{
				break;
			}
		}
		return $axes;
	}

	/**
	 * 값 목록을 표시용으로 푼다.
	 *
	 * 색상칩 축은 "블루미스트|#7ec8e3" 처럼 색을 함께 적을 수 있다.
	 * 조합 라벨·열쇠에는 언제나 앞쪽 이름만 쓴다.
	 *
	 * @param array<int, string> $values
	 * @return array<int, object> [{value, color}]
	 */
	public static function items(array $values): array
	{
		$items = [];
		foreach ($values as $value)
		{
			$color = '';
			if (strpos($value, '|') !== false)
			{
				[$value, $color] = array_map('trim', explode('|', $value, 2));
				$color = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '';
			}
			if ($value === '')
			{
				continue;
			}
			$items[] = (object)['value' => $value, 'color' => $color];
		}
		return $items;
	}

	/**
	 * 축 정의 저장용 JSON.
	 *
	 * @param mixed $input [{name, values[]}] 또는 그 JSON
	 * @return string
	 */
	public static function encodeAxes($input): string
	{
		if (is_string($input))
		{
			$input = json_decode($input, true);
		}
		$axes = self::axes(json_encode(is_array($input) ? $input : [], \JSON_UNESCAPED_UNICODE));
		if (!count($axes))
		{
			return '';
		}
		$out = [];
		foreach ($axes as $axis)
		{
			$out[] = ['name' => $axis->name, 'values' => $axis->values, 'style' => $axis->style];
		}
		return json_encode($out, \JSON_UNESCAPED_UNICODE);
	}

	/**
	 * 축을 모두 곱해 조합 목록을 만든다.
	 *
	 * @param array<int, object> $axes
	 * @return array<int, array<string, string>> [{축이름: 값}, ...]
	 */
	public static function expand(array $axes): array
	{
		if (!count($axes))
		{
			return [];
		}
		$rows = [[]];
		foreach ($axes as $axis)
		{
			$next = [];
			foreach ($rows as $row)
			{
				foreach ($axis->items as $item)
				{
					$row[$axis->name] = $item->value;
					$next[] = $row;
					if (count($next) > self::MAX_ROWS)
					{
						return array_slice($next, 0, self::MAX_ROWS);
					}
				}
			}
			$rows = $next;
		}
		return $rows;
	}

	/**
	 * 조합을 라벨 한 줄로. ("블랙 / L")
	 *
	 * @param array<string, string> $combo
	 * @return string
	 */
	public static function label(array $combo): string
	{
		return mb_substr(implode(self::SEPARATOR, array_values($combo)), 0, 250);
	}

	/**
	 * 조합 비교용 열쇠. 축 차례를 그대로 따른다.
	 *
	 * @param mixed $combo 배열 또는 JSON
	 * @return string
	 */
	public static function key($combo): string
	{
		if (is_string($combo))
		{
			$combo = json_decode($combo, true);
		}
		if (!is_array($combo))
		{
			return '';
		}
		$parts = [];
		foreach ($combo as $name => $value)
		{
			$parts[] = $name . '=' . $value;
		}
		return implode('|', $parts);
	}
}
