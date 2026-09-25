<?php

namespace Zittme\Modules\Commerce\Models;

class Brand
{
	protected static $cache = null;

	public static function getList(bool $visible_only = false): array
	{
		if (self::$cache === null)
		{
			$st = \Zittme\Framework\DB::getInstance()->query('SELECT * FROM commerce_brand ORDER BY list_order ASC, brand_srl ASC');
			self::$cache = $st ? $st->fetchAll() : [];
			Lang::textAll(self::$cache, ['name', 'description']);
		}
		if (!$visible_only)
		{
			return self::$cache;
		}
		return array_values(array_filter(self::$cache, function ($b) { return ($b->is_visible ?? 'Y') === 'Y'; }));
	}

	public static function getMap(bool $visible_only = false): array
	{
		$map = [];
		foreach (self::getList($visible_only) as $b)
		{
			$map[(int)$b->brand_srl] = $b;
		}
		return $map;
	}

	public static function get(int $brand_srl): ?object
	{
		return self::getMap()[$brand_srl] ?? null;
	}

	public static function find(string $key): ?object
	{
		$key = trim($key);
		if ($key === '')
		{
			return null;
		}
		foreach (self::getList() as $b)
		{
			if ((string)$b->slug !== '' && strcasecmp((string)$b->slug, $key) === 0)
			{
				return $b;
			}
		}
		return ctype_digit($key) ? self::get((int)$key) : null;
	}

	public static function searchSrls(string $keyword, bool $visible_only = false): string
	{
		$keyword = trim($keyword);
		if ($keyword === '')
		{
			return '';
		}
		$srls = [];
		foreach (self::getList($visible_only) as $b)
		{
			foreach ([$b->name ?? '', $b->name_en ?? '', $b->slug ?? ''] as $text)
			{
				if ((string)$text !== '' && mb_stripos((string)$text, $keyword) !== false)
				{
					$srls[] = (int)$b->brand_srl;
					break;
				}
			}
		}
		return implode(',', $srls);
	}

	public static function itemCounts(bool $on_sale_only = true): array
	{
		$sql = 'SELECT brand_srl, COUNT(*) AS cnt FROM commerce_item WHERE brand_srl > 0'
			. ($on_sale_only ? " AND status IN ('sale', 'soldout')" : '')
			. ' GROUP BY brand_srl';
		$st = \Zittme\Framework\DB::getInstance()->query($sql);
		$counts = [];
		foreach ($st ? $st->fetchAll() : [] as $row)
		{
			$counts[(int)$row->brand_srl] = (int)$row->cnt;
		}
		return $counts;
	}

	public static function makeSlug(string $text): string
	{
		$slug = strtolower(trim($text));
		$slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
		return trim((string)$slug, '-');
	}

	public static function save(int $brand_srl, array $in): int
	{
		$db = \Zittme\Framework\DB::getInstance();
		$now = date('YmdHis');
		$name = mb_substr(trim((string)($in['name'] ?? '')), 0, 100);
		if ($name === '')
		{
			return 0;
		}
		$slug_src = trim((string)($in['slug'] ?? ''));
		if ($slug_src === '') { $slug_src = trim((string)($in['name_en'] ?? '')) ?: $name; }
		$slug = self::makeSlug($slug_src);
		$base = $slug;
		for ($i = 2; $slug !== '' && self::slugTaken($slug, $brand_srl); $i++)
		{
			$slug = $base . '-' . $i;
		}
		$row = [
			'name' => $name,
			'name_en' => mb_substr(trim((string)($in['name_en'] ?? '')), 0, 100),
			'slug' => mb_substr($slug, 0, 100),
			'logo' => mb_substr(trim((string)($in['logo'] ?? '')), 0, 250),
			'cover' => mb_substr(trim((string)($in['cover'] ?? '')), 0, 250),
			'description' => (string)($in['description'] ?? ''),
			'is_visible' => ($in['is_visible'] ?? 'Y') === 'N' ? 'N' : 'Y',
			'last_update' => $now,
		];
		if ($brand_srl > 0 && self::get($brand_srl))
		{
			$sets = implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($row)));
			$db->query('UPDATE commerce_brand SET ' . $sets . ' WHERE brand_srl = ?', array_merge(array_values($row), [$brand_srl]));
		}
		else
		{
			$brand_srl = getNextSequence();
			$row['brand_srl'] = $brand_srl;
			$row['regdate'] = $now;
			$row['list_order'] = $brand_srl;
			$cols = array_keys($row);
			$db->query('INSERT INTO commerce_brand (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($row));
		}
		self::$cache = null;
		return $brand_srl;
	}

	protected static function slugTaken(string $slug, int $except): bool
	{
		foreach (self::getList() as $b)
		{
			if ((int)$b->brand_srl !== $except && strcasecmp((string)$b->slug, $slug) === 0)
			{
				return true;
			}
		}
		return false;
	}

	public static function setItems(int $brand_srl, array $item_srls): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		$item_srls = array_values(array_unique(array_filter(array_map('intval', $item_srls))));
		if (count($item_srls))
		{
			$marks = implode(', ', array_fill(0, count($item_srls), '?'));
			$db->query('UPDATE commerce_item SET brand_srl = 0 WHERE brand_srl = ? AND item_srl NOT IN (' . $marks . ')', array_merge([$brand_srl], $item_srls));
			$db->query('UPDATE commerce_item SET brand_srl = ? WHERE item_srl IN (' . $marks . ')', array_merge([$brand_srl], $item_srls));
		}
		else
		{
			$db->query('UPDATE commerce_item SET brand_srl = 0 WHERE brand_srl = ?', [$brand_srl]);
		}
	}

	public static function reorder(array $brand_srls): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		$order = array_values(array_unique(array_filter(array_map('intval', $brand_srls))));
		foreach (self::getList() as $b)
		{
			if (!in_array((int)$b->brand_srl, $order, true))
			{
				$order[] = (int)$b->brand_srl;
			}
		}
		foreach ($order as $i => $srl)
		{
			$db->query('UPDATE commerce_brand SET list_order = ? WHERE brand_srl = ?', [$i + 1, $srl]);
		}
		self::$cache = null;
	}

	public static function previewDraft(int $brand_srl): ?array
	{
		$draft = $_SESSION['commerce_brand_preview'] ?? null;
		$logged = \Context::get('logged_info');
		if (\Context::get('zmc_preview') !== 'Y' || !is_array($draft) || !$logged || !Staff::can('items'))
		{
			return null;
		}
		if ((int)($draft['srl'] ?? 0) !== $brand_srl || time() - (int)($draft['time'] ?? 0) >= 3600)
		{
			return null;
		}
		return $draft;
	}

	public static function delete(int $brand_srl): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		$db->query('UPDATE commerce_item SET brand_srl = 0 WHERE brand_srl = ?', [$brand_srl]);
		$db->query('DELETE FROM commerce_brand WHERE brand_srl = ?', [$brand_srl]);
		self::$cache = null;
	}

	public static function move(int $brand_srl, string $dir): void
	{
		$ids = array_map(function ($b) { return (int)$b->brand_srl; }, self::getList());
		$i = array_search($brand_srl, $ids, true);
		$j = $dir === 'up' ? $i - 1 : $i + 1;
		if ($i === false || !isset($ids[$j]))
		{
			return;
		}
		[$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
		$db = \Zittme\Framework\DB::getInstance();
		foreach ($ids as $n => $id)
		{
			$db->query('UPDATE commerce_brand SET list_order = ? WHERE brand_srl = ?', [$n + 1, $id]);
		}
		self::$cache = null;
	}

	public static function attach(array $items, string $mid = ''): void
	{
		$map = self::getMap(true);
		foreach ($items as $it)
		{
			if (!is_object($it))
			{
				continue;
			}
			$b = $map[(int)($it->brand_srl ?? 0)] ?? null;
			$it->brand_name = $b ? (string)$b->name : '';
			$it->brand_name_en = $b ? (string)$b->name_en : '';
			$it->brand_logo = $b ? (string)$b->logo : '';
			$it->brand_url = $b ? self::url($b, $mid) : '';
		}
	}

	public static function url(object $brand, string $mid = ''): string
	{
		$key = (string)$brand->slug !== '' ? (string)$brand->slug : (string)$brand->brand_srl;
		return $mid !== '' ? getNotEncodedUrl('', 'mid', $mid, 'v', 'list', 'brand', $key) : getNotEncodedUrl('', 'v', 'list', 'brand', $key);
	}

	public static function splitName(string $item_name): ?array
	{
		foreach ([' / ', '|'] as $sep)
		{
			$parts = explode($sep, $item_name, 2);
			if (count($parts) !== 2)
			{
				continue;
			}
			$brand = trim($parts[0]);
			$name = trim($parts[1]);
			if ($brand !== '' && $name !== '' && mb_strlen($brand) <= 30)
			{
				return [$brand, $name];
			}
		}
		return null;
	}

	public static function findNamedItems(): array
	{
		$st = \Zittme\Framework\DB::getInstance()->query("SELECT item_srl, item_name FROM commerce_item WHERE (brand_srl = 0 OR brand_srl IS NULL) AND (item_name LIKE '% / %' OR item_name LIKE '%|%')");
		$found = [];
		foreach ($st ? $st->fetchAll() : [] as $row)
		{
			$split = self::splitName((string)$row->item_name);
			if ($split)
			{
				$found[$split[0]][] = (int)$row->item_srl;
			}
		}
		return $found;
	}

	public static function migrateFromNames(): array
	{
		$db = \Zittme\Framework\DB::getInstance();
		$by_name = [];
		foreach (self::getList() as $b)
		{
			$by_name[mb_strtolower(trim((string)$b->name))] = (int)$b->brand_srl;
		}
		$made = 0;
		$moved = 0;
		foreach (self::findNamedItems() as $brand => $srls)
		{
			$key = mb_strtolower($brand);
			if (!isset($by_name[$key]))
			{
				$by_name[$key] = self::save(0, ['name' => $brand]);
				$made++;
			}
			if (!$by_name[$key])
			{
				continue;
			}
			foreach ($srls as $srl)
			{
				$row = $db->query('SELECT item_name FROM commerce_item WHERE item_srl = ?', [$srl])->fetchAll()[0] ?? null;
				$split = $row ? self::splitName((string)$row->item_name) : null;
				if (!$split)
				{
					continue;
				}
				$db->query('UPDATE commerce_item SET brand_srl = ?, item_name = ? WHERE item_srl = ?', [$by_name[$key], $split[1], $srl]);
				$moved++;
			}
		}
		return ['brands' => $made, 'items' => $moved];
	}
}
