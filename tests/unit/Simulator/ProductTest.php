<?php
declare(strict_types=1);

namespace Frenet\Tests\Simulator;

use Frenet\Simulator\Product;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase {
	private const PRODUCT = [
		'product_id'      => '40',
		'model'           => 'product 11',
		'shipping'        => '1',
		'price'           => '100.0000',
		'weight'          => '1.50000000',
		'weight_class_id' => '1',
		'length'          => '20.00000000',
		'width'           => '15.00000000',
		'height'          => '10.00000000',
		'length_class_id' => '1'
	];

	public function testReturnsTheProductAsALineOfTheCart(): void {
		$db = new \DB([[self::PRODUCT], [], []]);

		$product = $this->createProduct($db)->getProduct(40, 2, []);

		self::assertSame([
			'product_id'      => 40,
			'model'           => 'product 11',
			'shipping'        => '1',
			'quantity'        => 2,
			'price'           => 100.0,
			'total'           => 200.0,
			'weight'          => 3.0,
			'weight_class_id' => '1',
			'length'          => '20.00000000',
			'width'           => '15.00000000',
			'height'          => '10.00000000',
			'length_class_id' => '1'
		], $product);
		self::assertStringContainsString("p2s.`store_id` = '3' AND p2s.`product_id` = '40' AND pd.`language_id` = '2' AND p.`date_available` <= NOW() AND p.`status` = '1'", $db->queries[0]);
	}

	public function testUsesTheSpecialOverTheQuantityDiscountOfTheCustomerGroup(): void {
		$db = new \DB([[self::PRODUCT], [['price' => '90.0000']], [['price' => '80.0000']]]);

		$product = $this->createProduct($db)->getProduct(40, 3, []);

		self::assertNotNull($product);
		self::assertSame(80.0, $product['price']);
		self::assertSame(240.0, $product['total']);
		self::assertStringContainsString("`customer_group_id` = '5' AND `quantity` <= '3'", $db->queries[1]);
		self::assertStringContainsString('ORDER BY `quantity` DESC, `priority` ASC, `price` ASC LIMIT 1', $db->queries[1]);
		self::assertStringContainsString("FROM `oc_product_special` WHERE `product_id` = '40' AND `customer_group_id` = '5'", $db->queries[2]);
	}

	public function testUsesTheQuantityDiscountWithoutSpecial(): void {
		$product = $this->createProduct(new \DB([[self::PRODUCT], [['price' => '90.0000']], []]))->getProduct(40, 3, []);

		self::assertNotNull($product);
		self::assertSame(90.0, $product['price']);
	}

	public function testAddsThePriceAndWeightOfTheChosenOptionsAsTheCart(): void {
		$db = new \DB([
			[self::PRODUCT],
			[
				['product_option_id' => '226', 'product_option_value_id' => '15', 'price' => '10.0000', 'price_prefix' => '+', 'weight' => '0.50000000', 'weight_prefix' => '+', 'type' => 'select'],
				['product_option_id' => '223', 'product_option_value_id' => '8', 'price' => '5.0000', 'price_prefix' => '-', 'weight' => '0.20000000', 'weight_prefix' => '-', 'type' => 'checkbox'],
				['product_option_id' => '223', 'product_option_value_id' => '10', 'price' => '2.0000', 'price_prefix' => '+', 'weight' => '0.10000000', 'weight_prefix' => '+', 'type' => 'checkbox'],
				['product_option_id' => '218', 'product_option_value_id' => '6', 'price' => '99.0000', 'price_prefix' => '+', 'weight' => '9.00000000', 'weight_prefix' => '+', 'type' => 'radio']
			],
			[],
			[]
		]);

		$product = $this->createProduct($db)->getProduct(40, 2, ['226' => '15', '223' => ['8', '10'], '218' => '5', '209' => 'Engraving']);

		self::assertNotNull($product);
		self::assertSame(107.0, $product['price']);
		self::assertSame(214.0, $product['total']);
		self::assertEqualsWithDelta(3.8, $product['weight'], 0.000001);
		self::assertStringContainsString("WHERE pov.`product_id` = '40' AND pov.`product_option_value_id` IN (15,8,10,5)", $db->queries[1]);
	}

	public function testIgnoresAValueSentForAnotherKindOfOption(): void {
		$db = new \DB([
			[self::PRODUCT],
			[
				['product_option_id' => '226', 'product_option_value_id' => '15', 'price' => '10.0000', 'price_prefix' => '+', 'weight' => '0.50000000', 'weight_prefix' => '+', 'type' => 'select'],
				['product_option_id' => '223', 'product_option_value_id' => '8', 'price' => '5.0000', 'price_prefix' => '+', 'weight' => '0.20000000', 'weight_prefix' => '+', 'type' => 'checkbox']
			],
			[],
			[]
		]);

		$product = $this->createProduct($db)->getProduct(40, 1, ['226' => ['15'], '223' => '8']);

		self::assertNotNull($product);
		self::assertSame(100.0, $product['price']);
		self::assertSame(1.5, $product['weight']);
	}

	public function testReturnsNullWhenTheStoreDoesNotSellTheProduct(): void {
		self::assertNull($this->createProduct(new \DB([[]]))->getProduct(40, 1, []));
	}

	public function testRequiresShippingWithOneQueryOfTheProductRules(): void {
		$db = new \DB([[['shipping' => '1']]]);

		self::assertTrue($this->createProduct($db)->requiresShipping(40));
		self::assertCount(1, $db->queries);
		self::assertStringContainsString("p2s.`store_id` = '3' AND p2s.`product_id` = '40' AND p.`date_available` <= NOW() AND p.`status` = '1'", $db->queries[0]);
	}

	public function testDoesNotRequireShippingForAProductWithoutShipping(): void {
		self::assertFalse($this->createProduct(new \DB([[['shipping' => '0']]]))->requiresShipping(40));
	}

	public function testDoesNotRequireShippingWhenTheStoreDoesNotSellTheProduct(): void {
		self::assertFalse($this->createProduct(new \DB([[]]))->requiresShipping(40));
	}

	public function testListsTheRequiredOptionsLeftEmpty(): void {
		$db = new \DB([[
			['product_option_id' => '226', 'name' => 'Select'],
			['product_option_id' => '223', 'name' => 'Checkbox'],
			['product_option_id' => '218', 'name' => 'Radio']
		]]);

		$missing = $this->createProduct($db)->getMissingOptions(40, ['226' => '15', '223' => '', '209' => 'Engraving']);

		self::assertSame([223 => 'Checkbox', 218 => 'Radio'], $missing);
		self::assertStringContainsString("WHERE po.`product_id` = '40' AND po.`required` = '1' AND od.`language_id` = '2' ORDER BY o.`sort_order` ASC", $db->queries[0]);
	}

	private function createProduct(\DB $db): Product {
		return new Product($db, 'oc_', 3, 2, 5);
	}
}
