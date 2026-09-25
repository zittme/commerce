<?php

namespace Zittme\Modules\Commerce\Models;

class Combo
{
	public const MAX_AXES = 3;

	public const MAX_ROWS = 100;

	public const SEPARATOR = ' / ';

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
					$values[] = mb_substr($value, 0, 120);
				}
			}
			if ($name === '' || !count($values))
			{
				continue;
			}
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

	public static function axesFromOptions(array $options): string
	{
		$axes = [];
		foreach ($options as $option)
		{
			$combo = $option->combo ?? '';
			$combo = is_string($combo) ? json_decode($combo, true) : $combo;
			if (!is_array($combo))
			{
				continue;
			}
			foreach ($combo as $name => $value)
			{
				$name = trim((string)$name);
				$value = trim((string)$value);
				if ($name === '' || $value === '')
				{
					continue;
				}
				if (!isset($axes[$name]))
				{
					$axes[$name] = [];
				}
				if (!in_array($value, $axes[$name], true))
				{
					$axes[$name][] = $value;
				}
			}
		}

		if (!count($axes))
		{
			return '';
		}

		$out = [];
		foreach ($axes as $name => $values)
		{
			$out[] = ['name' => $name, 'values' => $values, 'style' => 'select'];
		}
		return self::encodeAxes($out);
	}

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

	public static function label(array $combo): string
	{
		return mb_substr(implode(self::SEPARATOR, array_values($combo)), 0, 250);
	}

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
