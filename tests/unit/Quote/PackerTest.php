<?php
declare(strict_types=1);

namespace Frenet\Tests\Quote;

use Frenet\Quote\Exception;
use Frenet\Quote\Item;
use Frenet\Quote\Packer;
use PHPUnit\Framework\TestCase;

final class PackerTest extends TestCase {
	public function testFillsEachPackageInCartOrderBeforeOpeningTheNextOne(): void {
		$packages = Packer::pack([$this->item('A', 15.0, 3), $this->item('B', 10.0, 2)], 30.0, 10);

		self::assertSame([['A' => 2], ['A' => 1, 'B' => 1], ['B' => 1]], $this->describe($packages));
	}

	public function testSplitsA45KgCartInTwoPackagesOfUpTo30Kg(): void {
		$packages = Packer::pack([$this->item('SACO-15', 15.0, 3)], 30.0, 10);

		self::assertSame([['SACO-15' => 2], ['SACO-15' => 1]], $this->describe($packages));
	}

	public function testComparesTheWeightWithoutFloatingPointLeftovers(): void {
		$packages = Packer::pack([$this->item('A', 0.1, 300)], 30.0, 10);

		self::assertSame([['A' => 300]], $this->describe($packages));
	}

	public function testKeepsTheUnitValueOfEachLine(): void {
		$packages = Packer::pack([$this->item('A', 20.0, 2, 90.0)], 30.0, 10);

		self::assertSame(90.0, $packages[1][0]->getValue());
		self::assertSame(1, $packages[1][0]->getQuantity());
	}

	public function testRefusesAUnitHeavierThanThePackage(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('one unit of GELADEIRA weighs 45 kg, more than the package limit of 30 kg');

		Packer::pack([$this->item('A', 1.0, 1), $this->item('GELADEIRA', 45.0, 1)], 30.0, 10);
	}

	public function testRefusesACartThatNeedsMorePackagesThanTheLimit(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('the cart needs more than 10 packages of up to 30 kg');

		Packer::pack([$this->item('A', 25.0, 11)], 30.0, 10);
	}

	public function testPacksExactlyTheLimitOfPackages(): void {
		self::assertCount(10, Packer::pack([$this->item('A', 25.0, 10)], 30.0, 10));
	}

	/**
	 * @param string $sku
	 * @param float $weight
	 * @param int $quantity
	 * @param float $value
	 * @return Item
	 */
	private function item(string $sku, float $weight, int $quantity, float $value = 10.0): Item {
		return new Item($weight, 16.0, 2.0, 11.0, $quantity, $sku, '', $value);
	}

	/**
	 * @param array<int, Item[]> $packages
	 * @return array<int, array<string, int>>
	 */
	private function describe(array $packages): array {
		return array_map(static function (array $items): array {
			$lines = [];

			foreach ($items as $item) {
				$lines[$item->toArray()['SKU']] = $item->getQuantity();
			}

			return $lines;
		}, $packages);
	}
}
