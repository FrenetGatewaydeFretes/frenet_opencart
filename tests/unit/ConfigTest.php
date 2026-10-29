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
			'shipping_frenet_msg_prazo'  => '%s - em até %d dia(s)',
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
