<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Config;
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

	public function testParsesPositiveNumbersTypedInTheAdmin(): void {
		static::assertSame(12.5, Config::parsePositive(' 12,5 '));
		static::assertSame(0.0004, Config::parsePositive('0.0004'));
		static::assertNull(Config::parsePositive('0'));
		static::assertNull(Config::parsePositive('-3'));
		static::assertNull(Config::parsePositive('abc'));
		static::assertNull(Config::parsePositive(null));
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
