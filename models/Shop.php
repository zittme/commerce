<?php

namespace Zittme\Modules\Commerce\Models;

class Shop
{
	public const RESERVED = [
		'admin', 'api', 'brand', 'brands', 'cart', 'category', 'center', 'checkout', 'console', 'delete', 'edit', 'help',
		'item', 'items', 'list', 'login', 'logout', 'mall', 'manage', 'manager', 'market', 'member', 'my', 'new', 'notice',
		'official', 'operator', 'order', 'orders', 'pay', 'promo', 'root', 'search', 'seller', 'sellers', 'settings',
		'shop', 'store', 'stores', 'support', 'system', 'www', 'zittme',
	];

	public const SECTIONS = ['banner', 'featured', 'new', 'cats', 'notice'];
	public const HEADS = ['banner', 'simple'];
	public const IMAGE_SIZES = ['S', 'M', 'L'];
	public const MAX_BANNERS = 5;
	public const MAX_FEATURED = 12;
	public const MAX_FILES = 50;
	public const MAX_BYTES = 52428800;

	public static function validFormat(string $id): bool
	{
		return (bool)preg_match('/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/', $id) && strpos($id, '--') === false;
	}

	public static function idError(string $id, int $except_seller_srl = 0): string
	{
		if (!self::validFormat($id))
		{
			return 'sc_msg_id_format';
		}
		if (in_array($id, self::RESERVED, true))
		{
			return 'sc_msg_id_reserved';
		}
		$db = \Zittme\Framework\DB::getInstance();
		$rows = $db->query('SELECT seller_srl FROM commerce_seller WHERE shop_id = ? AND seller_srl <> ?', [$id, $except_seller_srl])->fetchAll();
		$used = $db->query('SELECT seller_srl FROM commerce_seller_shopid WHERE shop_id = ? AND seller_srl <> ?', [$id, $except_seller_srl])->fetchAll();
		return (count($rows) || count($used)) ? 'sc_msg_id_taken' : '';
	}

	public static function reserveId(int $seller_srl, string $id): void
	{
		if ($seller_srl <= 0 || !self::validFormat($id))
		{
			return;
		}
		$db = \Zittme\Framework\DB::getInstance();
		if (!count($db->query('SELECT shop_id FROM commerce_seller_shopid WHERE shop_id = ?', [$id])->fetchAll()))
		{
			try
			{
				$db->query('INSERT INTO commerce_seller_shopid (shop_id, seller_srl, regdate) VALUES (?, ?, ?)', [$id, $seller_srl, date('YmdHis')]);
			}
			catch (\Throwable $e)
			{
			}
		}
	}

	public static function find(string $id): ?object
	{
		$id = strtolower(trim($id));
		if (!self::validFormat($id))
		{
			return null;
		}
		$db = \Zittme\Framework\DB::getInstance();
		$rows = $db->query('SELECT seller_srl FROM commerce_seller WHERE shop_id = ?', [$id])->fetchAll();
		$moved = false;
		if (!count($rows))
		{
			$rows = $db->query('SELECT seller_srl FROM commerce_seller_shopid WHERE shop_id = ?', [$id])->fetchAll();
			$moved = true;
		}
		$seller = count($rows) ? Seller::get((int)$rows[0]->seller_srl) : null;
		if (!$seller || Seller::isOperator((int)$seller->seller_srl) || (string)($seller->shop_id ?? '') === '')
		{
			return null;
		}
		return (object)['seller' => $seller, 'moved' => $moved];
	}

	public static function changeId(int $seller_srl, string $id): bool
	{
		$seller = Seller::get($seller_srl);
		if (!$seller || self::idError($id, $seller_srl) !== '')
		{
			return false;
		}
		$old = (string)($seller->shop_id ?? '');
		if ($old === $id)
		{
			return true;
		}
		try
		{
			\Zittme\Framework\DB::getInstance()->query(
				'UPDATE commerce_seller SET shop_id = ?, shop_prev_id = ?, last_update = ? WHERE seller_srl = ?',
				[$id, $old !== '' ? $old : null, date('YmdHis'), $seller_srl]
			);
		}
		catch (\Throwable $e)
		{
			return false;
		}
		Seller::forget($seller_srl);
		$row = Seller::get($seller_srl);
		if ($row && (string)$row->shop_id === $id)
		{
			self::reserveId($seller_srl, $old);
			self::reserveId($seller_srl, $id);
			return true;
		}
		return false;
	}

	public static function url(string $shop_id, string $mid = '', array $extra = []): string
	{
		if ($shop_id === '')
		{
			return '';
		}
		if ($mid === '')
		{
			$instance = \Zittme\Modules\Commerce\Controllers\Base::getDefaultInstance();
			$mid = $instance ? (string)$instance->mid : \Zittme\Modules\Commerce\Controllers\Base::DEFAULT_MID;
		}
		$params = ['', 'mid', $mid, 'act', 'dispCommerceStore', 'shop', $shop_id];
		foreach ($extra as $k => $v)
		{
			$params[] = (string)$k;
			$params[] = $v;
		}
		return (string)call_user_func_array('getUrl', $params);
	}

	public static function itemUrl(string $shop_id, int $item_srl, string $mid = '', bool $full = false): string
	{
		if ($shop_id === '' || $item_srl <= 0)
		{
			return '';
		}
		if ($mid === '')
		{
			$instance = \Zittme\Modules\Commerce\Controllers\Base::getDefaultInstance();
			$mid = $instance ? (string)$instance->mid : \Zittme\Modules\Commerce\Controllers\Base::DEFAULT_MID;
		}
		return (string)call_user_func($full ? 'getNotEncodedFullUrl' : 'getUrl', '', 'mid', $mid, 'act', 'dispCommerceStore', 'shop', $shop_id, 'item', $item_srl);
	}

	public static function itemsInStore(): bool
	{
		return (\Zittme\Modules\Commerce\Controllers\Base::config()->seller_item_in_store ?? 'Y') !== 'N' && Seller::isOpen();
	}

	public static function sellerForItem(object $item): ?object
	{
		$srl = (int)($item->seller_srl ?? 0);
		if (Seller::isOperator($srl) || !Seller::isOpen())
		{
			return null;
		}
		$seller = Seller::get($srl);
		return ($seller && $seller->status === 'approved' && (string)($seller->shop_id ?? '') !== '') ? $seller : null;
	}

	public static function defaults(): array
	{
		return [
			'color' => '#26345c',
			'head' => 'banner',
			'banners' => [],
			'sections' => array_map(function ($k) { return ['key' => $k, 'on' => true]; }, self::SECTIONS),
			'image_size' => 'M',
			'featured' => [],
			'notice' => '',
			'count' => 8,
		];
	}

	public static function design(object $seller): array
	{
		$saved = json_decode((string)($seller->shop_design ?? ''), true);
		$design = self::normalize(is_array($saved) ? $saved : [], (int)$seller->seller_srl);
		$design['logo'] = self::ownsUpload((string)($seller->shop_logo ?? ''), (int)$seller->seller_srl) ? (string)$seller->shop_logo : '';
		$design['cover'] = self::ownsUpload((string)($seller->shop_cover ?? ''), (int)$seller->seller_srl) ? (string)$seller->shop_cover : '';
		return $design;
	}

	public static function normalize(array $in, int $seller_srl): array
	{
		$out = self::defaults();
		if (isset($in['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$in['color']))
		{
			$out['color'] = strtolower((string)$in['color']);
		}
		if (in_array($in['head'] ?? '', self::HEADS, true))
		{
			$out['head'] = $in['head'];
		}
		if (in_array($in['image_size'] ?? '', self::IMAGE_SIZES, true))
		{
			$out['image_size'] = $in['image_size'];
		}
		$out['count'] = max(4, min(24, (int)($in['count'] ?? 8)));
		$out['notice'] = mb_substr(trim(strip_tags((string)($in['notice'] ?? ''))), 0, 1000);

		$banners = [];
		foreach (is_array($in['banners'] ?? null) ? $in['banners'] : [] as $url)
		{
			if (is_string($url) && self::ownsUpload($url, $seller_srl) && !in_array($url, $banners, true))
			{
				$banners[] = $url;
			}
		}
		$out['banners'] = array_slice($banners, 0, self::MAX_BANNERS);

		if (is_array($in['sections'] ?? null))
		{
			$seen = [];
			$sections = [];
			foreach ($in['sections'] as $sec)
			{
				$key = is_array($sec) ? (string)($sec['key'] ?? '') : '';
				if (in_array($key, self::SECTIONS, true) && !isset($seen[$key]))
				{
					$seen[$key] = true;
					$on = $sec['on'] ?? false;
					$sections[] = ['key' => $key, 'on' => $on === true || $on === 1 || $on === '1' || $on === 'Y'];
				}
			}
			foreach (self::SECTIONS as $key)
			{
				if (!isset($seen[$key]))
				{
					$sections[] = ['key' => $key, 'on' => false];
				}
			}
			$out['sections'] = $sections;
		}

		$featured = [];
		foreach (is_array($in['featured'] ?? null) ? $in['featured'] : [] as $srl)
		{
			$srl = (int)$srl;
			$item = $srl > 0 ? Item::get($srl) : null;
			if ($item && (int)$item->seller_srl === $seller_srl && !in_array($srl, $featured, true))
			{
				$featured[] = $srl;
			}
		}
		$out['featured'] = array_slice($featured, 0, self::MAX_FEATURED);
		return $out;
	}

	public static function fromRequest(int $seller_srl): array
	{
		$sections = json_decode((string)\Context::get('sections_json'), true);
		$banners = json_decode((string)\Context::get('banners_json'), true);
		$design = self::normalize([
			'color' => (string)\Context::get('color'),
			'head' => (string)\Context::get('head'),
			'image_size' => (string)\Context::get('image_size'),
			'count' => (int)\Context::get('count'),
			'notice' => (string)\Context::get('notice'),
			'banners' => is_array($banners) ? $banners : [],
			'sections' => is_array($sections) ? $sections : null,
			'featured' => (array)\Context::get('featured'),
		], $seller_srl);
		$logo = (string)\Context::get('logo');
		$cover = (string)\Context::get('cover');
		$design['logo'] = self::ownsUpload($logo, $seller_srl) ? $logo : '';
		$design['cover'] = self::ownsUpload($cover, $seller_srl) ? $cover : '';
		return $design;
	}

	public static function saveDesign(int $seller_srl, array $design): bool
	{
		$logo = (string)($design['logo'] ?? '');
		$cover = (string)($design['cover'] ?? '');
		unset($design['logo'], $design['cover']);
		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_seller SET shop_design = ?, shop_logo = ?, shop_cover = ?, last_update = ? WHERE seller_srl = ?',
			[json_encode($design, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES), $logo, $cover, date('YmdHis'), $seller_srl]
		);
		Seller::forget($seller_srl);
		return true;
	}

	public static function setDraft(int $seller_srl, array $design): void
	{
		$_SESSION['commerce_shop_preview'] = ['seller_srl' => $seller_srl, 'time' => time(), 'design' => $design];
	}

	public static function draft(int $seller_srl): ?array
	{
		$d = $_SESSION['commerce_shop_preview'] ?? null;
		if (!is_array($d) || (int)($d['seller_srl'] ?? 0) !== $seller_srl || time() - (int)($d['time'] ?? 0) > 3600)
		{
			return null;
		}
		return is_array($d['design'] ?? null) ? $d['design'] : null;
	}

	public static function uploadPath(int $seller_srl): string
	{
		return 'files/attach/images/commerce/seller/' . $seller_srl . '/';
	}

	public static function ownsUpload(string $url, int $seller_srl): bool
	{
		if ($url === '' || $seller_srl <= 0)
		{
			return false;
		}
		$prefix = \RX_BASEURL . self::uploadPath($seller_srl);
		if (strpos($url, $prefix) !== 0)
		{
			return false;
		}
		$name = substr($url, strlen($prefix));
		return (bool)preg_match('/^[a-z0-9_]+\.(jpg|png|gif|webp)$/', $name) && is_file(\RX_BASEDIR . self::uploadPath($seller_srl) . $name);
	}

	public static function saveUpload(array $file, int $seller_srl): array
	{
		if (!is_uploaded_file($file['tmp_name'] ?? ''))
		{
			return ['error' => 'sc_msg_upload_none'];
		}
		if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024)
		{
			return ['error' => 'sc_msg_upload_size'];
		}
		$info = @getimagesize($file['tmp_name']);
		$types = [\IMAGETYPE_JPEG => 'jpg', \IMAGETYPE_PNG => 'png', \IMAGETYPE_GIF => 'gif', \IMAGETYPE_WEBP => 'webp'];
		if (!$info || !isset($types[$info[2]]) || $info[0] > 6000 || $info[1] > 6000)
		{
			return ['error' => 'sc_msg_upload_type'];
		}
		$dir = \RX_BASEDIR . self::uploadPath($seller_srl);
		[$count, $bytes] = self::folderUsage($dir);
		if ($count >= self::MAX_FILES || $bytes + (int)$file['size'] > self::MAX_BYTES)
		{
			return ['error' => 'sc_msg_upload_full'];
		}
		\Zittme\Framework\Storage::createDirectory($dir);
		$ext = $types[$info[2]];
		$name = 'img_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
		if (!self::reencode($file['tmp_name'], $dir . $name, $info[2]))
		{
			return ['error' => 'sc_msg_upload_fail'];
		}
		return ['url' => \RX_BASEURL . self::uploadPath($seller_srl) . $name];
	}

	protected static function folderUsage(string $dir): array
	{
		$count = 0;
		$bytes = 0;
		foreach (is_dir($dir) ? (glob($dir . '*') ?: []) : [] as $path)
		{
			if (is_file($path))
			{
				$count++;
				$bytes += (int)filesize($path);
			}
		}
		return [$count, $bytes];
	}

	protected static function reencode(string $src, string $dest, int $type): bool
	{
		if (!function_exists('imagecreatetruecolor'))
		{
			return false;
		}
		switch ($type)
		{
			case \IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
			case \IMAGETYPE_PNG: $img = @imagecreatefrompng($src); break;
			case \IMAGETYPE_GIF: $img = @imagecreatefromgif($src); break;
			case \IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
			default: $img = false;
		}
		if (!$img)
		{
			return false;
		}
		if ($type === \IMAGETYPE_PNG || $type === \IMAGETYPE_WEBP)
		{
			imagealphablending($img, false);
			imagesavealpha($img, true);
		}
		switch ($type)
		{
			case \IMAGETYPE_JPEG: $ok = imagejpeg($img, $dest, 88); break;
			case \IMAGETYPE_PNG: $ok = imagepng($img, $dest, 6); break;
			case \IMAGETYPE_GIF: $ok = imagegif($img, $dest); break;
			default: $ok = imagewebp($img, $dest, 88);
		}
		imagedestroy($img);
		return (bool)$ok;
	}

	public static function cleanUploads(int $seller_srl, array $keep_urls): void
	{
		$dir = \RX_BASEDIR . self::uploadPath($seller_srl);
		if (!is_dir($dir))
		{
			return;
		}
		$keep = [];
		foreach ($keep_urls as $url)
		{
			if (is_string($url) && $url !== '')
			{
				$keep[basename($url)] = true;
			}
		}
		foreach (glob($dir . 'img_*') ?: [] as $path)
		{
			if (is_file($path) && !isset($keep[basename($path)]) && filemtime($path) < time() - 86400)
			{
				@unlink($path);
			}
		}
	}

	public static function referencedUrls(array $design): array
	{
		return array_merge([(string)($design['logo'] ?? ''), (string)($design['cover'] ?? '')], (array)($design['banners'] ?? []));
	}

	public static function categories(int $seller_srl): array
	{
		if ($seller_srl <= 0)
		{
			return [];
		}
		return \Zittme\Framework\DB::getInstance()->query(
			'SELECT category_srl, seller_srl, title, list_order FROM commerce_seller_category WHERE seller_srl = ? ORDER BY list_order ASC, category_srl ASC',
			[$seller_srl]
		)->fetchAll();
	}

	public static function ownsCategory(int $seller_srl, int $category_srl): bool
	{
		if ($category_srl <= 0)
		{
			return false;
		}
		$rows = \Zittme\Framework\DB::getInstance()->query(
			'SELECT category_srl FROM commerce_seller_category WHERE category_srl = ? AND seller_srl = ?',
			[$category_srl, $seller_srl]
		)->fetchAll();
		return count($rows) > 0;
	}

	public static function saveCategory(int $seller_srl, int $category_srl, string $title, int $list_order): int
	{
		$title = mb_substr(trim(strip_tags($title)), 0, 80);
		if ($title === '')
		{
			return 0;
		}
		$db = \Zittme\Framework\DB::getInstance();
		if ($category_srl > 0)
		{
			if (!self::ownsCategory($seller_srl, $category_srl))
			{
				return 0;
			}
			$db->query('UPDATE commerce_seller_category SET title = ?, list_order = ? WHERE category_srl = ? AND seller_srl = ?', [$title, $list_order, $category_srl, $seller_srl]);
			return $category_srl;
		}
		if (count(self::categories($seller_srl)) >= 50)
		{
			return 0;
		}
		$category_srl = getNextSequence();
		$db->query(
			'INSERT INTO commerce_seller_category (category_srl, seller_srl, title, list_order, regdate) VALUES (?, ?, ?, ?, ?)',
			[$category_srl, $seller_srl, $title, $list_order, date('YmdHis')]
		);
		return $category_srl;
	}

	public static function deleteCategory(int $seller_srl, int $category_srl): bool
	{
		if (!self::ownsCategory($seller_srl, $category_srl))
		{
			return false;
		}
		$db = \Zittme\Framework\DB::getInstance();
		$db->query('DELETE FROM commerce_seller_category WHERE category_srl = ? AND seller_srl = ?', [$category_srl, $seller_srl]);
		$db->query('UPDATE commerce_item SET seller_category_srl = 0 WHERE seller_category_srl = ? AND seller_srl = ?', [$category_srl, $seller_srl]);
		return true;
	}

	public static function setItemCategory(int $seller_srl, int $item_srl, int $category_srl): bool
	{
		$item = Item::get($item_srl);
		if (!$item || (int)$item->seller_srl !== $seller_srl)
		{
			return false;
		}
		if ($category_srl > 0 && !self::ownsCategory($seller_srl, $category_srl))
		{
			return false;
		}
		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE commerce_item SET seller_category_srl = ? WHERE item_srl = ? AND seller_srl = ?',
			[$category_srl, $item_srl, $seller_srl]
		);
		return true;
	}
}
