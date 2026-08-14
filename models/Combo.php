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
					// 다국어를 연결한 값은 '$user_lang->코드' 로 저장되므로 접두어만큼 여유를 둔다
					$values[] = mb_substr($value, 0, 120);
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
	 * 조합 옵션 하나의 표시 이름. 축 값에서 현재 언어로 만든다.
	 *
	 * 축이 없거나 조합이 축에서 빠졌으면 저장해 둔 이름을 그대로 쓴다.
	 *
	 * @param mixed $item 상품 (option_axes 를 가진 객체)
	 * @param mixed $option 옵션 (combo, option_label)
	 * @return string
	 */
	public static function optionLabel($item, $option): string
	{
		$saved = trim((string)($option->option_label ?? $option->option_name ?? ''));
		if (empty($option->combo))
		{
			return $saved;
		}
		$axes = self::axes($item->option_axes ?? '');
		if (!count($axes))
		{
			return $saved;
		}
		foreach ($axes as $axis)
		{
			foreach ($axis->items as $axis_item)
			{
				$axis_item->value = Lang::text($axis_item->value);
			}
		}
		$label = self::labelFromKey($axes, self::indexKey($axes, $option->combo));
		return $label !== '' ? $label : $saved;
	}

	/**
	 * 자리 번호 열쇠(indexKey)로 지금 축 값에서 이름을 다시 만든다.
	 *
	 * 저장된 이름은 조합을 만든 시점의 글자로 굳으므로, 그 뒤에 축 값에
	 * 다국어 코드를 연결해도 따라오지 않는다. 화면에 낼 때마다 다시 만든다.
	 *
	 * @param array $axes 축 정의 (값이 이미 풀린 상태여도 된다)
	 * @param string $key '0=0|1=3' 형태
	 * @return string 만들 수 없으면 빈 문자열
	 */
	public static function labelFromKey(array $axes, string $key): string
	{
		if ($key === '')
		{
			return '';
		}
		$parts = [];
		foreach (explode('|', $key) as $pair)
		{
			$pos = strpos($pair, '=');
			if ($pos === false)
			{
				return '';
			}
			$ai = (int)substr($pair, 0, $pos);
			$vi = (int)substr($pair, $pos + 1);
			if (!isset($axes[$ai]->items[$vi]))
			{
				return '';
			}
			$parts[] = (string)$axes[$ai]->items[$vi]->value;
		}
		return mb_substr(implode(self::SEPARATOR, $parts), 0, 250);
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

	/**
	 * 구매 화면 대조용 열쇠. 축·값의 자리 번호만 쓴다. ("0=1|1=0")
	 *
	 * 화면에 나가는 글자는 코어가 다국어 코드를 치환하므로 값이 언어마다 달라진다.
	 * 자리 번호는 치환 대상이 아니므로 어떤 언어에서도 같은 열쇠가 나온다.
	 *
	 * @param array<int, object> $axes axes() 결과
	 * @param mixed $combo 배열 또는 JSON
	 * @return string 축을 하나라도 못 찾으면 빈 문자열
	 */
	public static function indexKey(array $axes, $combo): string
	{
		if (is_string($combo))
		{
			$combo = json_decode($combo, true);
		}
		if (!is_array($combo) || !count($axes))
		{
			return '';
		}

		// 축 이름·값에 다국어 문구를 연결하면 저장된 조합은 옛 표기를 들고 있을 수 있다.
		// 원문이 어긋나면 현재 언어로 푼 글자끼리 한 번 더 맞춰 본다.
		$same = function($a, $b) {
			if ((string)$a === (string)$b)
			{
				return true;
			}
			return Lang::text((string)$a) === Lang::text((string)$b);
		};

		$combo_values = [];
		foreach ($combo as $combo_name => $combo_value)
		{
			$combo_values[] = [$combo_name, $combo_value];
		}

		$parts = [];
		foreach ($axes as $ai => $axis)
		{
			$value = null;
			foreach ($combo_values as $pair)
			{
				if ($same($pair[0], $axis->name))
				{
					$value = $pair[1];
					break;
				}
			}
			// 이름조차 못 찾으면 자리 순서로 본다 (조합은 축 차례대로 만들어진다)
			if ($value === null && isset($combo_values[$ai]))
			{
				$value = $combo_values[$ai][1];
			}
			if ($value === null)
			{
				return '';
			}

			$vi = -1;
			foreach ($axis->items as $index => $item)
			{
				if ($same($item->value, $value))
				{
					$vi = $index;
					break;
				}
			}
			if ($vi < 0)
			{
				return '';
			}
			$parts[] = $ai . '=' . $vi;
		}
		return implode('|', $parts);
	}
}
