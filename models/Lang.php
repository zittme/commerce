<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 다국어 코드 — 코어의 사용자 정의 언어(lang 테이블)를 그대로 쓴다.
 *
 * 값 자체는 '$user_lang->코드' 로 저장하고, 화면 출력은 코어가
 * HTMLDisplayHandler 마지막 단계에서 통째로 치환한다. 커머스가 따로 들고 있는
 * 언어 데이터는 없다 — 한 번 만든 코드는 다른 카테고리·상품에서도 골라 쓴다.
 */
class Lang
{
	/**
	 * 코어 규약의 접두어.
	 */
	public const PREFIX = '$user_lang->';

	/**
	 * 사이트에 켜둔 언어 목록 (코드 => 이름).
	 *
	 * @return array<string, string>
	 */
	public static function languages(): array
	{
		$langs = \Context::loadLangSelected();
		return is_array($langs) ? $langs : [];
	}

	/**
	 * 값이 다국어 코드면 코드 이름만, 아니면 빈 문자열.
	 *
	 * @param mixed $value
	 * @return string
	 */
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

	/**
	 * 코드 이름을 저장값으로.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function toValue(string $code): string
	{
		$code = self::filterCode($code);
		return $code === '' ? '' : self::PREFIX . $code;
	}

	/**
	 * 코드 이름 정리 (영문·숫자·밑줄만).
	 *
	 * @param string $code
	 * @return string
	 */
	public static function filterCode(string $code): string
	{
		$code = preg_replace('/[^a-zA-Z0-9_]/', '', trim($code));
		return substr((string)$code, 0, 100);
	}

	/**
	 * 코드 하나의 언어별 값.
	 *
	 * @param string $code
	 * @return array<string, string>
	 */
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

	/**
	 * 현재 언어로 읽은 값. 없으면 코드 이름.
	 *
	 * @param string $code
	 * @return string
	 */
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

	/**
	 * 화면에 내보내기 전 치환.
	 *
	 * 코어는 최종 HTML 을 한 번 훑어 치환하지만, 템플릿이 escape 로 출력하면
	 * '->' 가 '&gt;' 가 되어 그 정규식에 걸리지 않는다. 그래서 값을 넘기기 전에 미리 바꾼다.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function text($value): string
	{
		return \Context::replaceUserLang((string)$value);
	}

	/**
	 * 목록의 특정 필드들을 미리 치환.
	 *
	 * @param array<int, object> $rows
	 * @param array<int, string> $fields
	 * @return array<int, object>
	 */
	public static function textAll(array $rows, array $fields): array
	{
		foreach ($rows as $row)
		{
			foreach ($fields as $field)
			{
				if (isset($row->{$field}))
				{
					// 편집 화면은 연결된 코드를 알아야 하므로 원본을 남겨 둔다
					$row->{$field . '_raw'} = $row->{$field};
					$row->{$field} = self::text($row->{$field});
				}
			}
		}
		return $rows;
	}

	/**
	 * 배너처럼 JSON 으로 담긴 항목의 다국어 필드를 편집 화면용으로 풀어 준다.
	 * <필드>_langcode(코드 이름)와 <필드>_display(현재 언어 값)를 덧붙인다.
	 *
	 * @param array $rows
	 * @param array<int, string> $fields
	 * @return array
	 */
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

	/**
	 * 등록된 코드 목록 (검색어로 좁힘). 코드마다 현재 언어 값을 함께 준다.
	 *
	 * @param string $keyword
	 * @param int $limit
	 * @return array<int, object> [{code, value}]
	 */
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

	/**
	 * 코드를 만들거나 고친다. 코어의 lang 테이블에 그대로 쓴다.
	 *
	 * @param string $code 비우면 자동 생성
	 * @param array<string, string> $values 언어 코드 => 값
	 * @return string 저장된 코드 이름 ('' = 실패)
	 */
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

		// 코어와 같은 순서: 기존 값을 지우고 다시 넣는다
		executeQuery('module.deleteLang', (object)['name' => $code]);
		foreach ($clean as $lang => $value)
		{
			executeQuery('module.insertLang', (object)[
				'name' => $code,
				'lang_code' => $lang,
				'value' => $value,
			]);
		}

		// 치환용 캐시를 다시 만들어야 바로 반영된다
		\ModuleAdminController::getInstance()->makeCacheDefinedLangCode();

		return $code;
	}
}
