<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

/**
 * 기획전(특별전) — 상품 묶음 + 전용 페이지.
 */
class Promotion
{
	/**
	 * 전체 목록 (콘솔용, 숨김 포함).
	 */
	public static function listAll(): array
	{
		$output = executeQueryArray('commerce.getPromotionList', (object)['sort_index' => 'promo_srl', 'order_type' => 'desc']);
		$rows = ($output->toBool() && !empty($output->data)) ? $output->data : [];
		// 다국어 문구를 연결한 이름·소개문은 미리 바꿔 둔다 (원본은 title_raw / description_raw)
		return Lang::textAll($rows, ['title', 'description']);
	}

	/**
	 * 진행 중 목록 (노출 Y + 기간 내).
	 */
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

	/**
	 * 단건 (srl 또는 slug).
	 */
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

	/**
	 * 기획전에 담긴 상품 (순서대로, 상품 정보 조인).
	 */
	public static function itemsOf(int $promo_srl, bool $only_visible = true): array
	{
		$prefix = (string)(\Rhymix\Framework\Config::get('db.master.prefix') ?? '');
		$sql = 'SELECT i.*, pi.list_order AS promo_order'
			. ' FROM `' . $prefix . 'commerce_promotion_item` pi'
			. ' JOIN `' . $prefix . 'commerce_item` i ON i.item_srl = pi.item_srl'
			. ' WHERE pi.promo_srl = ?';
		if ($only_visible)
		{
			$sql .= " AND i.status IN ('sale', 'soldout')";
		}
		$sql .= ' ORDER BY pi.list_order ASC, pi.promo_item_srl ASC';
		$stmt = \Rhymix\Framework\DB::getInstance()->getHandle()->prepare($sql);
		return ($stmt && $stmt->execute([$promo_srl])) ? $stmt->fetchAll(\PDO::FETCH_OBJ) : [];
	}

	/**
	 * 기획전에 담긴 상품 srl 목록.
	 */
	public static function itemSrlsOf(int $promo_srl): array
	{
		return array_map(function($it) { return (int)$it->item_srl; }, self::itemsOf($promo_srl, false));
	}

	/**
	 * 매핑 동기화 — 주어진 순서대로 전체 교체.
	 */
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

	/**
	 * 상품이 속한 기획전 srl 목록.
	 */
	public static function promoSrlsOfItem(int $item_srl): array
	{
		$prefix = (string)(\Rhymix\Framework\Config::get('db.master.prefix') ?? '');
		$stmt = \Rhymix\Framework\DB::getInstance()->getHandle()->prepare(
			'SELECT promo_srl FROM `' . $prefix . 'commerce_promotion_item` WHERE item_srl = ?'
		);
		if (!$stmt || !$stmt->execute([$item_srl]))
		{
			return [];
		}
		return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
	}

	/**
	 * 상품 기준 담기/빼기 (상품 편집 화면의 체크박스용). 담으면 맨 뒤 순서.
	 */
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

	/**
	 * 배너 JSON 파싱 + 배경 스타일 계산 (홈 배너와 같은 규칙).
	 */
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
		$bn['text_html'] = \Zittme\Modules\Commerce\Controllers\Front::escapeAllowBr((string)($bn['text'] ?? ''));
		return $bn;
	}
}
