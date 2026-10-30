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
			[$this->product(['quantity' => 2, 'weight' => 3, 'length' => 16, 'height' => 2, 'width' => 11])],
			199.9,
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
			'SKU'       => '',
			'Category'  => '',
			'isFragile' => false,
			'Quantity'  => 2
		]], $request['ShippingItemArray']);
	}

	public function testConvertsEveryUnitToKilogramsAndCentimetres(): void {
		$items = $this->createBuilder()->build(['postcode' => '20040020', 'iso_code_2' => 'BR'], [
			$this->product(['weight' => 500, 'weight_class_id' => self::G, 'length' => 160, 'height' => 20, 'width' => 110, 'length_class_id' => self::MM]),
			$this->product(['weight' => 2.20462, 'weight_class_id' => self::LB, 'length' => 10, 'height' => 1, 'width' => 5, 'length_class_id' => self::INCH])
		], 0.0, '')->toArray()['ShippingItemArray'];

		static::assertEqualsWithDelta(0.5, $items[0]['Weight'], 0.0001);
		static::assertEqualsWithDelta(16.0, $items[0]['Length'], 0.0001);
		static::assertEqualsWithDelta(2.0, $items[0]['Height'], 0.0001);
		static::assertEqualsWithDelta(11.0, $items[0]['Width'], 0.0001);
		static::assertEqualsWithDelta(1.0, $items[1]['Weight'], 0.0001);
		static::assertEqualsWithDelta(25.4, $items[1]['Length'], 0.0001);
		static::assertEqualsWithDelta(2.54, $items[1]['Height'], 0.0001);
		static::assertEqualsWithDelta(12.7, $items[1]['Width'], 0.0001);
	}

	public function testUsesTheClassesTheStoreOwnerMappedEvenWhenGramsAreTheStoreDefault(): void {
		$weight = new Weight([self::KG => ['unit' => 'kg', 'value' => 0.001], self::G => ['unit' => 'g', 'value' => 1.0]]);

		$items = $this->createBuilder([], $weight)->build(['postcode' => '20040020', 'iso_code_2' => 'BR'], [
			$this->product(['weight' => 1500, 'weight_class_id' => self::G])
		], 0.0, '')->toArray()['ShippingItemArray'];

		static::assertEqualsWithDelta(1.5, $items[0]['Weight'], 0.0001);
	}

	public function testFallsBackToBrazilWhenTheAddressHasNoCountry(): void {
		$request = $this->createBuilder()->build(['postcode' => '20040020', 'iso_code_2' => ''], [$this->product([])], 0.0, '')->toArray();

		static::assertSame('BR', $request['RecipientCountry']);
	}

	public function testRefusesAWeightClassThatNoLongerExists(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('weight class');

		$this->createBuilder(['shipping_frenet_weight_class_id' => 99])->build(['postcode' => '20040020'], [$this->product([])], 0.0, '');
	}

	public function testRefusesALengthClassThatNoLongerExists(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('length class');

		$this->createBuilder(['shipping_frenet_length_class_id' => 0])->build(['postcode' => '20040020'], [$this->product([])], 0.0, '');
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
	 * Cart::getProducts() line, where weight is the total of the line.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private function product(array $values): array {
		return $values + [
			'quantity'        => 1,
			'weight'          => 1,
			'weight_class_id' => self::KG,
			'length'          => 16,
			'height'          => 2,
			'width'           => 11,
			'length_class_id' => self::CM
		];
	}
}
