<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class ApplyForm
{
	public const SECTIONS = ['biz', 'bank', 'intro'];

	public const TYPES = ['text', 'number', 'select', 'file'];

	public const FORMATS = ['', 'clabe', 'swift', 'iban'];

	public const LOCKED = ['shop_name', 'shop_id'];

	public const BUILTIN = [
		'shop_name' => ['section' => 'biz', 'req' => true, 'max' => 120, 'input' => 'text'],
		'shop_id' => ['section' => 'biz', 'req' => true, 'max' => 30, 'input' => 'text'],
		'biz_name' => ['section' => 'biz', 'req' => true, 'max' => 120, 'input' => 'text'],
		'ceo_name' => ['section' => 'biz', 'req' => true, 'max' => 80, 'input' => 'text'],
		'biz_no' => ['section' => 'biz', 'req' => true, 'max' => 20, 'input' => 'text'],
		'mailorder_no' => ['section' => 'biz', 'req' => true, 'max' => 40, 'input' => 'text'],
		'biz_address' => ['section' => 'biz', 'req' => false, 'max' => 250, 'input' => 'text'],
		'tel' => ['section' => 'biz', 'req' => true, 'max' => 30, 'input' => 'tel'],
		'email' => ['section' => 'biz', 'req' => false, 'max' => 120, 'input' => 'email'],
		'bank_name' => ['section' => 'bank', 'req' => true, 'max' => 40, 'input' => 'text'],
		'bank_account' => ['section' => 'bank', 'req' => true, 'max' => 60, 'input' => 'text'],
		'bank_holder' => ['section' => 'bank', 'req' => true, 'max' => 60, 'input' => 'text'],
		'intro' => ['section' => 'intro', 'req' => false, 'max' => 2000, 'input' => 'textarea'],
	];

	public const PRESETS = [
		'clabe' => ['type' => 'text', 'section' => 'bank', 'format' => 'clabe', 'req' => true],
		'swift' => ['type' => 'text', 'section' => 'bank', 'format' => 'swift', 'req' => false],
		'iban' => ['type' => 'text', 'section' => 'bank', 'format' => 'iban', 'req' => false],
		'payee_address' => ['type' => 'text', 'section' => 'bank', 'format' => '', 'req' => false],
	];

	public const MAX_CUSTOM = 20;
	public const FILE_EXTS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
	public const FILE_BYTES = 10485760;

	protected static $cache = null;

	public static function config(): array
	{
		if (self::$cache !== null)
		{
			return self::$cache;
		}
		$raw = json_decode((string)(Base::config()->seller_form ?? ''), true);
		$raw = is_array($raw) ? $raw : [];
		$builtin = [];
		foreach (self::BUILTIN as $key => $def)
		{
			$saved = is_array($raw['b'][$key] ?? null) ? $raw['b'][$key] : [];
			$locked = in_array($key, self::LOCKED, true);
			$builtin[$key] = [
				'on' => $locked ? true : (bool)($saved['on'] ?? true),
				'req' => $locked ? true : (bool)($saved['req'] ?? $def['req']),
				'label' => mb_substr(trim((string)($saved['label'] ?? '')), 0, 60),
				'locked' => $locked,
			] + $def;
		}
		$custom = [];
		foreach (is_array($raw['c'] ?? null) ? $raw['c'] : [] as $row)
		{
			$one = self::normalizeCustom(is_array($row) ? $row : []);
			if ($one && !isset($custom[$one['key']]))
			{
				$custom[$one['key']] = $one;
			}
			if (count($custom) >= self::MAX_CUSTOM)
			{
				break;
			}
		}
		return self::$cache = ['builtin' => $builtin, 'custom' => $custom];
	}

	protected static function normalizeCustom(array $row): ?array
	{
		$key = strtolower(trim((string)($row['key'] ?? '')));
		$label = mb_substr(trim(strip_tags((string)($row['label'] ?? ''))), 0, 60);
		if (!preg_match('/^[a-z][a-z0-9_]{1,30}$/', $key) || $label === '')
		{
			return null;
		}
		$type = in_array($row['type'] ?? '', self::TYPES, true) ? $row['type'] : 'text';
		$options = [];
		if ($type === 'select')
		{
			$list = is_array($row['options'] ?? null) ? $row['options'] : preg_split('/[\r\n,]+/', (string)($row['options'] ?? ''));
			foreach ($list as $opt)
			{
				$opt = mb_substr(trim(strip_tags((string)$opt)), 0, 60);
				if ($opt !== '' && !in_array($opt, $options, true))
				{
					$options[] = $opt;
				}
			}
			if (!count($options))
			{
				$type = 'text';
			}
		}
		return [
			'key' => $key,
			'label' => $label,
			'type' => $type,
			'section' => in_array($row['section'] ?? '', self::SECTIONS, true) ? $row['section'] : 'biz',
			'req' => !empty($row['req']) && $row['req'] !== 'N',
			'options' => array_slice($options, 0, 50),
			'format' => $type === 'text' && in_array($row['format'] ?? '', self::FORMATS, true) ? (string)$row['format'] : '',
			'help' => mb_substr(trim(strip_tags((string)($row['help'] ?? ''))), 0, 200),
		];
	}

	public static function fromRequest(): string
	{
		$on = (array)\Context::get('af_on');
		$req = (array)\Context::get('af_req');
		$labels = (array)\Context::get('af_label');
		$b = [];
		foreach (self::BUILTIN as $key => $def)
		{
			$b[$key] = [
				'on' => in_array($key, self::LOCKED, true) || ($on[$key] ?? 'N') === 'Y',
				'req' => in_array($key, self::LOCKED, true) || ($req[$key] ?? 'N') === 'Y',
				'label' => mb_substr(trim(strip_tags((string)($labels[$key] ?? ''))), 0, 60),
			];
		}
		$c = [];
		$keys = (array)\Context::get('xc_key');
		foreach ($keys as $i => $key)
		{
			$key = strtolower(trim((string)$key));
			if ($key === '')
			{
				$key = 'f' . substr(md5(uniqid('', true) . $i), 0, 8);
			}
			$one = self::normalizeCustom([
				'key' => $key,
				'label' => ((array)\Context::get('xc_label'))[$i] ?? '',
				'type' => ((array)\Context::get('xc_type'))[$i] ?? 'text',
				'section' => ((array)\Context::get('xc_section'))[$i] ?? 'biz',
				'req' => (((array)\Context::get('xc_req'))[$i] ?? 'N') === 'Y',
				'options' => ((array)\Context::get('xc_options'))[$i] ?? '',
				'format' => ((array)\Context::get('xc_format'))[$i] ?? '',
				'help' => ((array)\Context::get('xc_help'))[$i] ?? '',
			]);
			if ($one && !isset($c[$one['key']]) && !isset(self::BUILTIN[$one['key']]))
			{
				$c[$one['key']] = $one;
			}
			if (count($c) >= self::MAX_CUSTOM)
			{
				break;
			}
		}
		self::$cache = null;
		return json_encode(['b' => $b, 'c' => array_values($c)], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
	}

	public static function builtinLabel(string $key): string
	{
		$cfg = self::config()['builtin'][$key] ?? null;
		if ($cfg && $cfg['label'] !== '')
		{
			return $cfg['label'];
		}
		if ($key === 'shop_id')
		{
			return lang('commerce.sc_shop_id');
		}
		return lang('commerce.mk_f_' . $key);
	}

	public static function sectionLabel(string $section): string
	{
		return lang($section === 'bank' ? 'commerce.mk_f_bank' : ($section === 'intro' ? 'commerce.mk_f_intro' : 'commerce.mk_profile_biz'));
	}

	public static function extraOf(?object $seller): array
	{
		$data = json_decode((string)($seller->extra_vars ?? ''), true);
		return is_array($data) ? $data : [];
	}

	public static function fields(?object $seller = null, array $sections = self::SECTIONS, bool $only_on = true): array
	{
		$cfg = self::config();
		$extra = self::extraOf($seller);
		$out = [];
		foreach ($sections as $section)
		{
			$out[$section] = [];
		}
		foreach ($cfg['builtin'] as $key => $b)
		{
			if (!isset($out[$b['section']]))
			{
				continue;
			}
			$value = (string)($seller->{$key} ?? '');
			if ($only_on && !$b['on'] && $value === '')
			{
				continue;
			}
			$out[$b['section']][] = (object)[
				'key' => $key,
				'name' => $key,
				'label' => self::builtinLabel($key),
				'type' => $b['input'] === 'textarea' ? 'textarea' : 'text',
				'input' => $b['input'],
				'required' => $b['on'] && $b['req'],
				'on' => $b['on'],
				'builtin' => true,
				'max' => $b['max'],
				'options' => [],
				'format' => '',
				'help' => $key === 'shop_id' ? lang('commerce.sc_shop_id_desc') : '',
				'value' => $value,
				'file_url' => '',
			];
		}
		foreach ($cfg['custom'] as $key => $c)
		{
			if (!isset($out[$c['section']]))
			{
				continue;
			}
			$value = (string)($extra[$key] ?? '');
			$out[$c['section']][] = (object)[
				'key' => $key,
				'name' => 'xf_' . $key,
				'label' => $c['label'],
				'type' => $c['type'],
				'input' => $c['type'] === 'number' ? 'text' : $c['type'],
				'required' => $c['req'],
				'on' => true,
				'builtin' => false,
				'max' => $c['type'] === 'number' ? 30 : 200,
				'options' => $c['options'],
				'format' => $c['format'],
				'help' => $c['help'] !== '' ? $c['help'] : ($c['format'] !== '' ? lang('commerce.af_fmt_help_' . $c['format']) : ''),
				'value' => $c['type'] === 'file' ? ($value !== '' ? basename($value) : '') : $value,
				'file_url' => $c['type'] === 'file' && $value !== '' && $seller ? self::fileUrl((int)$seller->seller_srl, $key) : '',
			];
		}
		return $out;
	}

	public static function customValues(?object $seller): array
	{
		$rows = [];
		$extra = self::extraOf($seller);
		foreach (self::config()['custom'] as $key => $c)
		{
			$value = (string)($extra[$key] ?? '');
			if ($value === '')
			{
				continue;
			}
			$rows[] = (object)[
				'key' => $key,
				'label' => $c['label'],
				'type' => $c['type'],
				'section' => $c['section'],
				'value' => $c['type'] === 'file' ? basename($value) : $value,
				'file_url' => $c['type'] === 'file' && $seller ? self::fileUrl((int)$seller->seller_srl, $key) : '',
			];
		}
		return $rows;
	}

	public static function missing(object $data, array $extra, array $sections = self::SECTIONS, array $skip = []): string
	{
		$cfg = self::config();
		foreach ($cfg['builtin'] as $key => $b)
		{
			if (in_array($key, $skip, true) || !in_array($b['section'], $sections, true))
			{
				continue;
			}
			if ($b['on'] && $b['req'] && trim((string)($data->{$key} ?? '')) === '')
			{
				return self::builtinLabel($key);
			}
		}
		foreach ($cfg['custom'] as $key => $c)
		{
			if (in_array($c['section'], $sections, true) && $c['req'] && trim((string)($extra[$key] ?? '')) === '')
			{
				return $c['label'];
			}
		}
		return '';
	}

	public static function collect(array $old_extra, int $owner_srl, array $sections = self::SECTIONS): array
	{
		$extra = $old_extra;
		foreach (self::config()['custom'] as $key => $c)
		{
			if (!in_array($c['section'], $sections, true))
			{
				continue;
			}
			if ($c['type'] === 'file')
			{
				$file = $_FILES['xf_' . $key] ?? null;
				if (is_array($file) && ($file['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_NO_FILE)
				{
					$saved = self::saveFile($file, $owner_srl);
					if (isset($saved['error']))
					{
						return ['error' => sprintf(lang('commerce.' . $saved['error']), $c['label'])];
					}
					if (!empty($old_extra[$key]))
					{
						self::removeFile((string)$old_extra[$key]);
					}
					$extra[$key] = $saved['path'];
				}
				continue;
			}
			$value = \Context::get('xf_' . $key);
			if ($value === null)
			{
				continue;
			}
			$value = mb_substr(trim(strip_tags((string)$value)), 0, $c['type'] === 'number' ? 30 : 200);
			if ($value !== '')
			{
				if ($c['type'] === 'number' && !preg_match('/^-?[0-9]+([.,][0-9]+)?$/', $value))
				{
					return ['error' => sprintf(lang('commerce.af_msg_invalid'), $c['label'])];
				}
				if ($c['type'] === 'select' && !in_array($value, $c['options'], true))
				{
					return ['error' => sprintf(lang('commerce.af_msg_invalid'), $c['label'])];
				}
				if ($c['format'] !== '')
				{
					$value = strtoupper(preg_replace('/\s+/', '', $value));
					if (!self::validFormat($c['format'], $value))
					{
						return ['error' => sprintf(lang('commerce.af_msg_invalid'), $c['label'])];
					}
				}
			}
			$extra[$key] = $value;
		}
		return ['extra' => $extra];
	}

	public static function validFormat(string $format, string $value): bool
	{
		switch ($format)
		{
			case 'clabe':
				if (!preg_match('/^[0-9]{18}$/', $value))
				{
					return false;
				}
				$weights = [3, 7, 1];
				$sum = 0;
				for ($i = 0; $i < 17; $i++)
				{
					$sum += ((int)$value[$i] * $weights[$i % 3]) % 10;
				}
				return (10 - $sum % 10) % 10 === (int)$value[17];
			case 'swift':
				return (bool)preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $value);
			case 'iban':
				if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $value))
				{
					return false;
				}
				$moved = substr($value, 4) . substr($value, 0, 4);
				$digits = '';
				foreach (str_split($moved) as $ch)
				{
					$digits .= ctype_alpha($ch) ? (string)(ord($ch) - 55) : $ch;
				}
				$rest = 0;
				foreach (str_split($digits, 7) as $chunk)
				{
					$rest = (int)(($rest . $chunk) % 97);
				}
				return $rest === 1;
		}
		return true;
	}

	public static function storeExtra(int $seller_srl, array $extra): void
	{
		$clean = [];
		foreach ($extra as $key => $value)
		{
			if (isset(self::config()['custom'][$key]) || (string)$value !== '')
			{
				$clean[(string)$key] = (string)$value;
			}
		}
		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_seller SET extra_vars = ? WHERE seller_srl = ?',
			[count($clean) ? json_encode($clean, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : null, $seller_srl]
		);
		Seller::forget($seller_srl);
	}

	public static function fileDir(): string
	{
		return \RX_BASEDIR . 'files/commerce_private/seller_docs/';
	}

	protected static function saveFile(array $file, int $owner_srl): array
	{
		if (($file['error'] ?? 1) !== \UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? ''))
		{
			return ['error' => 'af_msg_file_fail'];
		}
		if ((int)$file['size'] <= 0 || (int)$file['size'] > self::FILE_BYTES)
		{
			return ['error' => 'af_msg_file_size'];
		}
		$ext = strtolower(pathinfo((string)$file['name'], \PATHINFO_EXTENSION));
		if (!in_array($ext, self::FILE_EXTS, true))
		{
			return ['error' => 'af_msg_file_type'];
		}
		$head = (string)file_get_contents($file['tmp_name'], false, null, 0, 8);
		$ok = $ext === 'pdf' ? strpos($head, '%PDF') === 0 : @getimagesize($file['tmp_name']) !== false;
		if (!$ok)
		{
			return ['error' => 'af_msg_file_type'];
		}
		$dir = self::fileDir();
		if (!is_dir($dir))
		{
			\FileHandler::makeDir($dir);
		}
		if (!is_file(\RX_BASEDIR . 'files/commerce_private/.htaccess'))
		{
			\FileHandler::writeFile(\RX_BASEDIR . 'files/commerce_private/.htaccess', "Require all denied\nDeny from all\n");
		}
		$name = $owner_srl . '_' . bin2hex(random_bytes(16)) . '.' . $ext;
		if (!move_uploaded_file($file['tmp_name'], $dir . $name))
		{
			return ['error' => 'af_msg_file_fail'];
		}
		@chmod($dir . $name, 0644);
		return ['path' => $name];
	}

	protected static function removeFile(string $name): void
	{
		$name = basename($name);
		if (preg_match('/^[0-9]+_[a-f0-9]{32}\.[a-z]{3,4}$/', $name) && is_file(self::fileDir() . $name))
		{
			@unlink(self::fileDir() . $name);
		}
	}

	public static function fileUrl(int $seller_srl, string $key): string
	{
		return getUrl('', 'module', 'commerce', 'mid', '', 'act', 'dispCommerceSellerDoc', 'seller_srl', $seller_srl, 'field', $key);
	}

	public static function filePath(object $seller, string $key): string
	{
		$c = self::config()['custom'][$key] ?? null;
		$name = basename((string)(self::extraOf($seller)[$key] ?? ''));
		if (!$c || $c['type'] !== 'file' || !preg_match('/^[0-9]+_[a-f0-9]{32}\.[a-z]{3,4}$/', $name))
		{
			return '';
		}
		$path = self::fileDir() . $name;
		return is_file($path) ? $path : '';
	}
}
