<?php
declare(strict_types=1);

namespace Frenet\Tests;

use Frenet\Config;
use Frenet\VirtualFee;
use PHPUnit\Framework\TestCase;

final class VirtualFeeTest extends TestCase {
	private const SHIPPED = ['product_id' => 40, 'quantity' => '1', 'shipping' => '1'];

	private const NOT_SHIPPED = ['product_id' => 34, 'quantity' => '1', 'shipping' => '0'];

	public function testChargesTheFeeOnceForACartWithProductsThatAreNotShipped(): void {
		$fee = $this->createFee('1', '4,90');

		self::assertSame(4.9, $fee->getAmount([self::NOT_SHIPPED]));
		self::assertSame(4.9, $fee->getAmount([self::SHIPPED, self::NOT_SHIPPED, ['quantity' => '3'] + self::NOT_SHIPPED]));
	}

	public function testDoesNotChargeTheFeeWhenEveryProductIsShipped(): void {
		$fee = $this->createFee('1', '4.90');

		self::assertSame(0.0, $fee->getAmount([self::SHIPPED]));
		self::assertSame(0.0, $fee->getAmount([]));
		self::assertSame(0.0, $fee->getAmount([['quantity' => '0'] + self::NOT_SHIPPED]));
		self::assertSame(0.0, $fee->getAmount([['product_id' => 34, 'quantity' => '1']]));
	}

	public function testDoesNotChargeTheFeeWhenItIsOff(): void {
		self::assertSame(0.0, $this->createFee('0', '4.90')->getAmount([self::NOT_SHIPPED]));
		self::assertSame(0.0, $this->createFee('1', '0')->getAmount([self::NOT_SHIPPED]));
	}

	private function createFee(string $status, string $amount): VirtualFee {
		$config = new \Config();
		$config->set('shipping_frenet_virtual_fee', $status);
		$config->set('shipping_frenet_virtual_fee_amount', $amount);

		return new VirtualFee(new Config($config));
	}
}
