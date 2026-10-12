<?php

namespace Zittme\Modules\Commerce\Kit;

use Zittme\Modules\Autoinstall\Models\Kit\TableKitImporter;

class Importer extends TableKitImporter
{
	protected function title(): string
	{
		return lang('commerce.commerce') ?: 'Commerce';
	}

	protected function tables(): array
	{
		return [
			'commerce_category' => ['pk' => 'category_srl', 'scope' => 'all', 'refs' => ['parent_srl' => 'commerce_category'], 'where' => "is_active = 'Y'"],
			'commerce_badge' => ['pk' => 'badge_srl', 'scope' => 'all'],
			'commerce_item' => [
				'pk' => 'item_srl',
				'scope' => 'all',
				'refs' => ['category_srl' => 'commerce_category', 'seller_srl' => 'member'],
				'reset' => ['view_count' => 0, 'buy_count' => 0, 'low_stock_alerted' => 'N'],
				'where' => "status <> 'deleted'",
			],
			'commerce_item_option' => ['pk' => 'option_srl', 'parent' => ['item_srl' => 'commerce_item'], 'reset' => ['low_stock_alerted' => 'N']],
			'commerce_promotion' => ['pk' => 'promo_srl', 'scope' => 'all'],
			'commerce_promotion_item' => ['pk' => 'promo_item_srl', 'parent' => ['promo_srl' => 'commerce_promotion'], 'refs' => ['item_srl' => 'commerce_item']],
		];
	}

	protected function importRow(string $table, array $row, \Zittme\Modules\Autoinstall\Models\Kit\ImportContext $ctx): array
	{
		if ($table === 'commerce_promotion' && !empty($row['slug']))
		{
			$row['slug'] = substr((string)$row['slug'], 0, 80) . '-' . $ctx->job->job_srl;
		}
		return $row;
	}
}
