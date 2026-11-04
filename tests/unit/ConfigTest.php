<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase {
	public function testReadsTheShippingFrenetSettings(): void {
		$config = $this->createConfig([
			'shipping_frenet_status'     => '1',
			'shipping_frenet_token'      => 'abc123',
			'shipping_frenet_postcode'   => '01310100',
			'shipping_frenet_msg_prazo'  => " %s - em até %d dia(s) ",
			'shipping_frenet_weight_class_id' => '1',
			'shipping_frenet_length_class_id' => '2',
			'shipping_frenet_sort_order' => '3'
		]);

		static::assertTrue($config->isEnabled());
		static::assertSame('abc123', $config->getToken());
		static::assertSame('01310100', $config->getOriginPostcode());
		static::assertSame('%s - em até %d dia(s)', $config->getDeliveryTimeMessage());
		static::assertSame(1, $config->getWeightClassId());
		static::assertSame(2, $config->getLengthClassId());
		static::assertSame(3, $config->getSortOrder());
	}

	public function testReturnsEmptyValuesWhenNothingIsSaved(): void {
		$config = $this->createConfig([]);

		static::assertFalse($config->isEnabled());
		static::assertSame('', $config->getToken());
		static::assertSame('', $config->getOriginPostcode());
		static::assertSame('', $config->getDeliveryTimeMessage());
		static::assertSame(0, $config->getWeightClassId());
		static::assertSame(0, $config->getLengthClassId());
		static::assertSame(0, $config->getSortOrder());
	}

	public function testRemovesThePostcodeMaskAndTokenSpaces(): void {
		$config = $this->createConfig([
			'shipping_frenet_token'    => "  abc123 \n",
			'shipping_frenet_postcode' => '01310-100'
		]);

		static::assertSame('abc123', $config->getToken());
		static::assertSame('01310100', $config->getOriginPostcode());
	}

	public function testReadsTheTimeoutTheAttemptsAndTheDebugSwitch(): void {
		$config = $this->createConfig(['shipping_frenet_timeout' => '8', 'shipping_frenet_attempts' => '2', 'shipping_frenet_debug' => '1']);

		static::assertSame(8, $config->getTimeout());
		static::assertSame(2, $config->getAttempts());
		static::assertTrue($config->isDebug());
	}

	public function testUsesTheDefaultTimeoutAndAttemptsWhenNothingIsSaved(): void {
		$config = $this->createConfig(['shipping_frenet_timeout' => '']);

		static::assertSame(5, $config->getTimeout());
		static::assertSame(3, $config->getAttempts());
		static::assertFalse($config->isDebug());
	}

	public function testKeepsTheTimeoutAndAttemptsWithinTheSupportedRange(): void {
		$low = $this->createConfig(['shipping_frenet_timeout' => '0', 'shipping_frenet_attempts' => '-1']);
		$high = $this->createConfig(['shipping_frenet_timeout' => '60', 'shipping_frenet_attempts' => '9']);

		static::assertSame(2, $low->getTimeout());
		static::assertSame(1, $low->getAttempts());
		static::assertSame(10, $high->getTimeout());
		static::assertSame(3, $high->getAttempts());
	}

	public function testReadsTheDeliveryTimeTheTaxClassAndTheGeoZone(): void {
		$config = $this->createConfig([
			'shipping_frenet_delivery_time'   => '0',
			'shipping_frenet_additional_days' => '2',
			'shipping_frenet_tax_class_id'    => '9',
			'shipping_frenet_geo_zone_id'     => '4'
		]);

		static::assertFalse($config->isDeliveryTimeShown());
		static::assertSame(2, $config->getAdditionalDays());
		static::assertSame(9, $config->getTaxClassId());
		static::assertSame(4, $config->getGeoZoneId());
	}

	public function testShowsTheDeliveryTimeEverywhereWithoutTaxWhenNothingIsSaved(): void {
		$config = $this->createConfig(['shipping_frenet_delivery_time' => '']);

		static::assertTrue($config->isDeliveryTimeShown());
		static::assertSame(0, $config->getAdditionalDays());
		static::assertSame(0, $config->getTaxClassId());
		static::assertSame(0, $config->getGeoZoneId());
	}

	public function testNeverSubtractsDaysFromTheDeliveryTime(): void {
		static::assertSame(0, $this->createConfig(['shipping_frenet_additional_days' => '-3'])->getAdditionalDays());
	}

	public function testReadsTheMinimumMeasuresAndTheDefaultWeight(): void {
		$config = $this->createConfig([
			'shipping_frenet_minimum_height' => '3',
			'shipping_frenet_minimum_width'  => '12,5',
			'shipping_frenet_minimum_length' => '18',
			'shipping_frenet_default_weight' => '0.3'
		]);

		static::assertSame(3.0, $config->getMinimumHeight());
		static::assertSame(12.5, $config->getMinimumWidth());
		static::assertSame(18.0, $config->getMinimumLength());
		static::assertSame(0.3, $config->getDefaultWeight());
	}

	public function testFallsBackToTheCorreiosMinimumsWhenTheValueIsEmptyOrNotPositive(): void {
		$config = $this->createConfig(['shipping_frenet_minimum_height' => '0', 'shipping_frenet_minimum_width' => '-4', 'shipping_frenet_minimum_length' => 'abc']);

		static::assertSame(2.0, $config->getMinimumHeight());
		static::assertSame(11.0, $config->getMinimumWidth());
		static::assertSame(16.0, $config->getMinimumLength());
		static::assertSame(1.0, $config->getDefaultWeight());
	}

	#[TestWith(['01310100'], 'digits only')]
	#[TestWith([' 01310-100 '], 'hyphen and spaces around')]
	#[TestWith(['01.310-100'], 'dot and hyphen')]
	#[TestWith(['01310 100'], 'space')]
	public function testFormatsThePostcodeTypedInTheAdmin(string $postcode): void {
		static::assertSame('01310-100', Config::parsePostcode($postcode));
	}

	#[TestWith(['0131010'], 'seven digits')]
	#[TestWith(['013101000'], 'nine digits')]
	#[TestWith(['01310-10A'], 'letter')]
	#[TestWith(['CEP 01310100'], 'label')]
	#[TestWith([''], 'empty')]
	#[TestWith([null], 'null')]
	public function testRefusesAPostcodeWithoutEightDigits(?string $postcode): void {
		static::assertNull(Config::parsePostcode($postcode));
	}

	#[DataProvider('provideParsesPositiveNumbersTypedInTheAdminCases')]
	public function testParsesPositiveNumbersTypedInTheAdmin(?string $value, ?float $expected): void {
		static::assertSame($expected, Config::parsePositive($value));
	}

	/**
	 * @return iterable<string, array{0: string|null, 1: float|null}>
	 */
	public static function provideParsesPositiveNumbersTypedInTheAdminCases(): iterable {
		yield 'comma decimal' => [' 12,5 ', 12.5];
		yield 'light weight' => ['0.0004', 0.0004];
		yield 'zero' => ['0', null];
		yield 'negative' => ['-3', null];
		yield 'text' => ['abc', null];
		yield 'null' => [null, null];
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return Config
	 */
	private function createConfig(array $settings): Config {
		$opencart_config = new \Config();

		foreach ($settings as $key => $value) {
			$opencart_config->set($key, $value);
		}

		return new Config($opencart_config);
	}
}
