<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Catalog;
use PHPUnit\Framework\TestCase;

class CatalogTest extends TestCase {
	public function testSendsTheSkuOrTheModelAndTheNamesOfTheEnabledCategoriesOfTheStore(): void {
		$db = new \DB([
			[
				['product_id' => '40', 'sku' => '', 'model' => 'product 11'],
				['product_id' => '41', 'sku' => 'CAM-AZ-M', 'model' => 'Camiseta azul']
			],
			[
				['product_id' => '40', 'name' => 'Desktops'],
				['product_id' => '40', 'name' => 'Phones &amp; PDAs'],
				['product_id' => '41', 'name' => 'Moda | Verão']
			]
		]);

		$products = (new Catalog($db, 'oc_', 2, 3))->getProducts(['40', 41, 41]);

		static::assertSame([
			40 => ['sku' => 'product 11', 'category' => 'Desktops|Phones & PDAs'],
			41 => ['sku' => 'CAM-AZ-M', 'category' => 'Moda / Verão']
		], $products);
		static::assertStringContainsString('FROM `oc_product` WHERE `product_id` IN (40,41)', $db->queries[0]);
		static::assertStringContainsString("cd.`language_id` = '2'", $db->queries[1]);
		static::assertStringContainsString("c.`status` = '1'", $db->queries[1]);
		static::assertStringContainsString("c2s.`store_id` = '3'", $db->queries[1]);
	}

	public function testDoesNotQueryWithoutProducts(): void {
		$db = new \DB([]);

		static::assertSame([], (new Catalog($db, 'oc_', 1, 0))->getProducts([]));
		static::assertSame([], $db->queries);
	}
}
