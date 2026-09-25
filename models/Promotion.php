<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Promotion
{
	public static function listAll(): array
	{
		$output = executeQueryArray('commerce.getPromotionList', (object)['sort_index' => 'promo_srl', 'order_type' => 'desc']);
		$rows = ($output->toBool() && !empty($output->data)) ? $output->data : [];
		return Lang::textAll($rows, ['title', 'description']);
	}

	public static function activeList(): array
	{
		$now = Base::now();
		return array_values(array_filter(self::listAll(), function($p) use ($now) {
			if (($p->status ?? 'Y') !== 'Y') return false;
			if (!empty($p->start_date) && $now < $p->start_date) return false;
			if (!empty($p->end_date) && $now > $p->end_date) return false;
			return true;
		}));
	}

	public static function get(int $promo_srl = 0, string $slug = ''): ?object
	{
		foreach (self::listAll() as $p)
		{
			if (($promo_srl > 0 && (int)$p->promo_srl === $promo_srl) || ($slug !== '' && $p->slug === $slug))
			{
				return $p;
			}
		}
		return null;
	}

	public static function itemsOf(int $promo_srl, bool $only_visible = true): array
	{
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$sql = 'SELECT i.*, pi.list_order AS promo_order'
			. ' FROM `' . $prefix . 'commerce_promotion_item` pi'
			. ' JOIN `' . $prefix . 'commerce_item` i ON i.item_srl = pi.item_srl'
			. ' WHERE pi.promo_srl = ?';
		if ($only_visible)
		{
			$sql .= " AND i.status IN ('sale', 'soldout')";
		}
		$sql .= ' ORDER BY pi.list_order ASC, pi.promo_item_srl ASC';
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare($sql);
		return ($stmt && $stmt->execute([$promo_srl])) ? $stmt->fetchAll(\PDO::FETCH_OBJ) : [];
	}

	public static function previewDraft(int $promo_srl): ?array
	{
		$draft = $_SESSION['commerce_promo_preview'] ?? null;
		$logged = \Context::get('logged_info');
		if (\Context::get('zmc_preview') !== 'Y' || !is_array($draft) || !$logged || !Staff::can('promos'))
		{
			return null;
		}
		if ((int)($draft['srl'] ?? 0) !== $promo_srl || time() - (int)($draft['time'] ?? 0) >= 3600)
		{
			return null;
		}
		return $draft;
	}

	public static function itemsBySrls(array $item_srls): array
	{
		$item_srls = array_values(array_unique(array_filter(array_map('intval', $item_srls))));
		if (!count($item_srls))
		{
			return [];
		}
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$sql = 'SELECT * FROM `' . $prefix . 'commerce_item` WHERE item_srl IN (' . implode(',', array_fill(0, count($item_srls), '?')) . ") AND status IN ('sale', 'soldout')";
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare($sql);
		$rows = ($stmt && $stmt->execute($item_srls)) ? $stmt->fetchAll(\PDO::FETCH_OBJ) : [];
		$by = [];
		foreach ($rows as $row)
		{
			$by[(int)$row->item_srl] = $row;
		}
		$out = [];
		foreach ($item_srls as $srl)
		{
			if (isset($by[$srl]))
			{
				$out[] = $by[$srl];
			}
		}
		return $out;
	}

	public static function itemSrlsOf(int $promo_srl): array
	{
		return array_map(function($it) { return (int)$it->item_srl; }, self::itemsOf($promo_srl, false));
	}

	public static function syncItems(int $promo_srl, array $item_srls): void
	{
		executeQuery('commerce.deletePromotionItems', (object)['promo_srl' => $promo_srl]);
		$order = 0;
		foreach (array_values(array_unique(array_map('intval', $item_srls))) as $item_srl)
		{
			if ($item_srl <= 0) continue;
			executeQuery('commerce.insertPromotionItem', (object)[
				'promo_item_srl' => getNextSequence(),
				'promo_srl' => $promo_srl,
				'item_srl' => $item_srl,
				'list_order' => $order++,
			]);
		}
	}

	public static function promoSrlsOfItem(int $item_srl): array
	{
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare(
			'SELECT promo_srl FROM `' . $prefix . 'commerce_promotion_item` WHERE item_srl = ?'
		);
		if (!$stmt || !$stmt->execute([$item_srl]))
		{
			return [];
		}
		return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
	}

	public static function setItemMembership(int $item_srl, int $promo_srl, bool $on): void
	{
		$current = self::promoSrlsOfItem($item_srl);
		if ($on && !in_array($promo_srl, $current, true))
		{
			$max = 0;
			foreach (self::itemsOf($promo_srl, false) as $it)
			{
				$max = max($max, (int)$it->promo_order + 1);
			}
			executeQuery('commerce.insertPromotionItem', (object)[
				'promo_item_srl' => getNextSequence(),
				'promo_srl' => $promo_srl,
				'item_srl' => $item_srl,
				'list_order' => $max,
			]);
		}
		elseif (!$on && in_array($promo_srl, $current, true))
		{
			executeQuery('commerce.deletePromotionItems', (object)['promo_srl' => $promo_srl, 'item_srl' => $item_srl]);
		}
	}

	public static function bannerOf(?object $promo): array
	{
		$bn = json_decode((string)($promo->banner ?? ''), true);
		$bn = is_array($bn) ? $bn : [];
		$type = $bn['bg_type'] ?? (!empty($bn['image']) ? 'image' : 'gradient');
		$c1 = (isset($bn['bg_color']) && preg_match('/^#[0-9a-fA-F]+$/', (string)$bn['bg_color'])) ? $bn['bg_color'] : '#1a1f2e';
		$c2 = (isset($bn['bg_color2']) && preg_match('/^#[0-9a-fA-F]+$/', (string)$bn['bg_color2'])) ? $bn['bg_color2'] : '#0d1019';
		if ($type === 'image' && !empty($bn['image']))
		{
			$bn['bg_style'] = 'background-image:url(' . escape($bn['image']) . ')';
		}
		elseif ($type === 'color')
		{
			$bn['bg_style'] = 'background:' . $c1;
		}
		else
		{
			$bn['bg_style'] = 'background:linear-gradient(120deg,' . $c1 . ',' . $c2 . ')';
		}
		$bn['text_color'] = (isset($bn['text_color']) && preg_match('/^#[0-9a-fA-F]+$/', (string)$bn['text_color'])) ? $bn['text_color'] : '#ffffff';
		$bn['shadow'] = ($bn['shadow'] ?? 'Y') === 'N' ? 'N' : 'Y';
		$bn['title_html'] = \Zittme\Modules\Commerce\Controllers\Front::escapeAllowBr((string)($promo->title ?? ''));
		$bn['text'] = Lang::text((string)($bn['text'] ?? ''));
		$bn['text_html'] = \Zittme\Modules\Commerce\Controllers\Front::escapeAllowBr((string)$bn['text']);
		return $bn;
	}
}
