<?php

namespace Zittme\Modules\Commerce\Models;

class Lang
{
	public const PREFIX = '$user_lang->';

	public static function languages(): array
	{
		$langs = \Context::loadLangSelected();
		return is_array($langs) ? $langs : [];
	}

	public static function codeOf($value): string
	{
		$value = trim((string)$value);
		if (strpos($value, self::PREFIX) !== 0)
		{
			return '';
		}
		$code = substr($value, strlen(self::PREFIX));
		return preg_match('/^[a-zA-Z0-9_]+$/', $code) ? $code : '';
	}

	public static function toValue(string $code): string
	{
		$code = self::filterCode($code);
		return $code === '' ? '' : self::PREFIX . $code;
	}

	public static function filterCode(string $code): string
	{
		$code = preg_replace('/[^a-zA-Z0-9_]/', '', trim($code));
		return substr((string)$code, 0, 100);
	}

	public static function values(string $code): array
	{
		$code = self::filterCode($code);
		if ($code === '')
		{
			return [];
		}
		$output = executeQueryArray('module.getLang', (object)['name' => $code]);
		$values = [];
		foreach (($output->toBool() ? ($output->data ?: []) : []) as $row)
		{
			$values[(string)$row->lang_code] = (string)$row->value;
		}
		return $values;
	}

	public static function display(string $code): string
	{
		$values = self::values($code);
		$lang = \Context::getLangType();
		if (trim((string)($values[$lang] ?? '')) !== '')
		{
			return $values[$lang];
		}
		foreach ($values as $value)
		{
			if (trim($value) !== '')
			{
				return $value;
			}
		}
		return $code;
	}

	public static function text($value): string
	{
		return \Context::replaceUserLang((string)$value);
	}

	public static function textAll(array $rows, array $fields): array
	{
		foreach ($rows as $row)
		{
			foreach ($fields as $field)
			{
				if (isset($row->{$field}))
				{
					$row->{$field . '_raw'} = $row->{$field};
					$row->{$field} = self::text($row->{$field});
				}
			}
		}
		return $rows;
	}

	public static function expand(array $rows, array $fields): array
	{
		foreach ($rows as &$row)
		{
			if (!is_array($row))
			{
				continue;
			}
			foreach ($fields as $field)
			{
				$code = self::codeOf($row[$field] ?? '');
				$row[$field . '_langcode'] = $code;
				$row[$field . '_display'] = $code !== '' ? self::display($code) : (string)($row[$field] ?? '');
			}
		}
		unset($row);
		return $rows;
	}

	public static function search(string $keyword = '', int $limit = 30): array
	{
		$output = executeQueryArray('module.getLang', new \stdClass);
		$rows = $output->toBool() ? ($output->data ?: []) : [];

		$lang = \Context::getLangType();
		$map = [];
		foreach ($rows as $row)
		{
			$code = (string)$row->name;
			if (!isset($map[$code]))
			{
				$map[$code] = ['code' => $code, 'value' => '', 'fallback' => ''];
			}
			$value = (string)$row->value;
			if ((string)$row->lang_code === $lang)
			{
				$map[$code]['value'] = $value;
			}
			elseif ($map[$code]['fallback'] === '')
			{
				$map[$code]['fallback'] = $value;
			}
		}

		$keyword = trim($keyword);
		$result = [];
		foreach ($map as $row)
		{
			$value = $row['value'] !== '' ? $row['value'] : $row['fallback'];
			if ($keyword !== '' && mb_stripos($row['code'], $keyword) === false && mb_stripos($value, $keyword) === false)
			{
				continue;
			}
			$result[] = (object)['code' => $row['code'], 'value' => $value];
			if (count($result) >= $limit)
			{
				break;
			}
		}
		return $result;
	}

	public static function save(string $code, array $values): string
	{
		$allowed = self::languages();
		$clean = [];
		foreach ($values as $lang => $value)
		{
			$value = trim((string)$value);
			if (isset($allowed[$lang]) && $value !== '')
			{
				$clean[$lang] = $value;
			}
		}
		if (!count($clean))
		{
			return '';
		}

		$code = self::filterCode($code);
		if ($code === '')
		{
			$code = 'shop_' . date('YmdHis') . sprintf('%03d', mt_rand(0, 999));
		}

		executeQuery('module.deleteLang', (object)['name' => $code]);
		foreach ($clean as $lang => $value)
		{
			executeQuery('module.insertLang', (object)[
				'name' => $code,
				'lang_code' => $lang,
				'value' => $value,
			]);
		}

		\ModuleAdminController::getInstance()->makeCacheDefinedLangCode();

		return $code;
	}
}
