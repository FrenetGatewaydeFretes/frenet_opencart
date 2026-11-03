<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Cart\Length;
use Cart\Weight;
use Frenet\Config;
use Frenet\Quote\Builder;
use Frenet\Quote\Exception;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase {
	private const KG = 1;
	private const G = 2;
	private const LB = 5;
	private const CM = 1;
	private const MM = 2;
	private const INCH = 3;

	public function testBuildsThePayloadTheFrenetApiExpects(): void {
		$request = $this->createBuilder()->build(
			['postcode' => '20040-020', 'iso_code_2' => 'BR'],
			[$this->product(['product_id' => 41, 'quantity' => 2, 'weight' => 3, 'length' => 16, 'height' => 2, 'width' => 11, 'total' => 199.9])],
			[41 => ['sku' => 'CAM-AZ-M', 'category' => 'Moda|Camisetas']],
			'FRETE10'
		)->toArray();

		static::assertSame('01310100', $request['SellerCEP']);
		static::assertSame('20040020', $request['RecipientCEP']);
		static::assertSame('', $request['RecipientDocument']);
		static::assertSame('BR', $request['RecipientCountry']);
		static::assertSame(199.9, $request['ShipmentInvoiceValue']);
		static::assertSame('FRETE10', $request['Coupom']);
		static::assertSame('OpenCart3', $request['PlatformName']);
		static::assertSame('3.0.5.1', $request['PlatformVersion']);
		static::assertSame([[
			'Weight'    => 1.5,
			'Length'    => 16.0,
			'Height'    => 2.0,
			'Width'     => 11.0,
			'Diameter'  => 0,
			'SKU'       => 'CAM-AZ-M',
			'Category'  => 'Moda|Camisetas',
			'isFragile' => false,
			'Quantity'  => 2
		]], $request['ShippingItemArray']);
	}

	public function testConvertsEveryUnitToKilogramsAndCentimetres(): void {
		$items = $this->items([
			$this->product(['weight' => 500, 'weight_class_id' => self::G, 'length' => 160, 'height' => 20, 'width' => 110, 'length_class_id' => self::MM]),
			$this->product(['weight' => 2.20462, 'weight_class_id' => self::LB, 'length' => 10, 'height' => 1, 'width' => 5, 'length_class_id' => self::INCH]),
			$this->product(['weight' => 2, 'weight_class_id' => self::KG, 'length' => 30, 'height' => 4, 'width' => 20, 'length_class_id' => self::CM])
		]);

		static::assertEqualsWithDelta(0.5, $items[0]['Weight'], 0.0001);
		static::assertEqualsWithDelta(16.0, $items[0]['Length'], 0.0001);
		static::assertEqualsWithDelta(2.0, $items[0]['Height'], 0.0001);
		static::assertEqualsWithDelta(11.0, $items[0]['Width'], 0.0001);
		static::assertEqualsWithDelta(1.0, $items[1]['Weight'], 0.0001);
		static::assertEqualsWithDelta(25.4, $items[1]['Length'], 0.0001);
		static::assertEqualsWithDelta(2.54, $items[1]['Height'], 0.0001);
		static::assertEqualsWithDelta(12.7, $items[1]['Width'], 0.0001);
		static::assertEqualsWithDelta(2.0, $items[2]['Weight'], 0.0001);
		static::assertEqualsWithDelta(30.0, $items[2]['Length'], 0.0001);
	}

	public function testKeepsLightWeightsAndRoundsOnlyTheConversionNoise(): void {
		$items = $this->items([
			$this->product(['weight' => 2.20462, 'weight_class_id' => self::LB, 'length' => 10, 'height' => 1, 'width' => 5, 'length_class_id' => self::INCH]),
			$this->product(['weight' => 0.4, 'weight_class_id' => self::G]),
			$this->product(['weight' => 1500, 'quantity' => 1000, 'weight_class_id' => self::G])
		]);

		static::assertSame([1.0, 25.4, 2.54, 12.7], [$items[0]['Weight'], $items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
		static::assertSame(0.0004, $items[1]['Weight']);
		static::assertSame(0.0015, $items[2]['Weight']);
		static::assertSame(1000, $items[2]['Quantity']);
	}

	public function testRaisesMeasuresBelowTheMinimumToTheMinimum(): void {
		$items = $this->items([
			$this->product(['length' => 10, 'height' => 0.5, 'width' => 5]),
			$this->product(['length' => 1, 'height' => 1, 'width' => 1, 'length_class_id' => self::MM])
		]);

		static::assertSame([16.0, 2.0, 11.0], [$items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
		static::assertSame([16.0, 2.0, 11.0], [$items[1]['Length'], $items[1]['Height'], $items[1]['Width']]);
	}

	public function testUsesTheClassesTheStoreOwnerMappedEvenWhenGramsAreTheStoreDefault(): void {
		$weight = new Weight([self::KG => ['unit' => 'kg', 'value' => 0.001], self::G => ['unit' => 'g', 'value' => 1.0]]);

		$items = $this->createBuilder([], $weight)->build(['postcode' => '20040020', 'iso_code_2' => 'BR'], [
			$this->product(['weight' => 1500, 'weight_class_id' => self::G])
		], [], '')->toArray()['ShippingItemArray'];

		static::assertEqualsWithDelta(1.5, $items[0]['Weight'], 0.0001);
	}

	public function testSendsTheConfiguredMinimumsForAProductWithoutWeightOrMeasures(): void {
		$items = $this->createBuilder([
			'shipping_frenet_minimum_height' => '3',
			'shipping_frenet_minimum_width'  => '12',
			'shipping_frenet_minimum_length' => '18',
			'shipping_frenet_default_weight' => '0.4'
		])->build(['postcode' => '20040020'], [
			$this->product(['weight' => 0, 'length' => 0, 'height' => '', 'width' => '0.00000000']),
			$this->product(['weight' => 1, 'length' => 0, 'height' => 5, 'width' => 0])
		], [], '')->toArray()['ShippingItemArray'];

		static::assertSame([0.4, 18.0, 3.0, 12.0], [$items[0]['Weight'], $items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
		static::assertSame([1.0, 18.0, 5.0, 12.0], [$items[1]['Weight'], $items[1]['Length'], $items[1]['Height'], $items[1]['Width']]);
	}

	public function testUsesTheCorreiosMinimumsWhenNothingIsConfigured(): void {
		$items = $this->items([$this->product(['weight' => 0, 'length' => 0, 'height' => 0, 'width' => 0])]);

		static::assertSame([1.0, 16.0, 2.0, 11.0], [$items[0]['Weight'], $items[0]['Length'], $items[0]['Height'], $items[0]['Width']]);
	}

	public function testLeavesProductsWithoutShippingOutOfTheItemsAndTheDeclaredValue(): void {
		$request = $this->createBuilder()->build(['postcode' => '20040020'], [
			$this->product(['product_id' => 41, 'quantity' => 2, 'price' => 90, 'total' => 180, 'weight' => 0.6]),
			$this->product(['product_id' => 50, 'quantity' => 1, 'price' => 50, 'total' => 50, 'shipping' => '0', 'weight' => 0])
		], [41 => ['sku' => 'CAM-AZ-M', 'category' => 'Camisetas'], 50 => ['sku' => 'EBOOK', 'category' => 'Livros']], 'DESCONTO10')->toArray();

		static::assertCount(1, $request['ShippingItemArray']);
		static::assertSame('CAM-AZ-M', $request['ShippingItemArray'][0]['SKU']);
		static::assertEqualsWithDelta(0.3, $request['ShippingItemArray'][0]['Weight'], 0.0001);
		static::assertSame(180.0, $request['ShipmentInvoiceValue']);
		static::assertSame('DESCONTO10', $request['Coupom']);
	}

	public function testSendsTheModelWhenTheCatalogHasNoDetailsForTheProduct(): void {
		$items = $this->items([$this->product(['product_id' => 99, 'model' => 'MOD-99'])]);

		static::assertSame('MOD-99', $items[0]['SKU']);
		static::assertSame('', $items[0]['Category']);
	}

	public function testRefusesACartWithoutProductsToShip(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('No product in the cart requires shipping');

		$this->createBuilder()->build(['postcode' => '20040020'], [$this->product(['shipping' => '0'])], [], '');
	}

	public function testFallsBackToBrazilWhenTheAddressHasNoCountry(): void {
		$request = $this->createBuilder()->build(['postcode' => '20040020', 'iso_code_2' => ''], [$this->product([])], [], '')->toArray();

		static::assertSame('BR', $request['RecipientCountry']);
	}

	public function testRefusesAWeightClassThatNoLongerExists(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('weight class');

		$this->createBuilder(['shipping_frenet_weight_class_id' => 99])->build(['postcode' => '20040020'], [$this->product([])], [], '');
	}

	public function testRefusesALengthClassThatNoLongerExists(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('length class');

		$this->createBuilder(['shipping_frenet_length_class_id' => 0])->build(['postcode' => '20040020'], [$this->product([])], [], '');
	}

	/**
	 * @param array<int, array<string, mixed>> $products
	 * @return array<int, array<string, mixed>>
	 */
	private function items(array $products): array {
		return $this->createBuilder()->build(['postcode' => '20040020', 'iso_code_2' => 'BR'], $products, [], '')->toArray()['ShippingItemArray'];
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param Weight|null $weight
	 * @return Builder
	 */
	private function createBuilder(array $settings = [], ?Weight $weight = null): Builder {
		$opencart_config = new \Config();

		foreach ($settings + ['shipping_frenet_postcode' => '01310-100', 'shipping_frenet_weight_class_id' => self::KG, 'shipping_frenet_length_class_id' => self::CM] as $key => $value) {
			$opencart_config->set($key, $value);
		}

		return new Builder(
			new Config($opencart_config),
			$weight ?? new Weight([self::KG => ['unit' => 'kg', 'value' => 1.0], self::G => ['unit' => 'g', 'value' => 1000.0], self::LB => ['unit' => 'lb', 'value' => 2.20462]]),
			new Length([self::CM => ['unit' => 'cm', 'value' => 1.0], self::MM => ['unit' => 'mm', 'value' => 10.0], self::INCH => ['unit' => 'in', 'value' => 0.3937]]),
			'3.0.5.1'
		);
	}

	/**
	 * Cart::getProducts() line, where weight and total are the totals of the line.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private function product(array $values): array {
		return $values + [
			'product_id'      => 40,
			'model'           => 'product 11',
			'shipping'        => '1',
			'quantity'        => 1,
			'price'           => 101,
			'total'           => 101,
			'weight'          => 1,
			'weight_class_id' => self::KG,
			'length'          => 16,
			'height'          => 2,
			'width'           => 11,
			'length_class_id' => self::CM
		];
	}
}
