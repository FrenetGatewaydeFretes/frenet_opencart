<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Splits the cart lines into packages up to a weight, filling each package in cart order before opening the next one.
 */
final class Packer {
	/**
	 * @var int
	 */
	private const SCALE = 1000000;

	/**
	 * Packages of the lines, splitting the quantity of a line between packages when it does not fit the current one.
	 *
	 * @param Item[] $items
	 * @param float $max_weight
	 * @param int $max_packages
	 * @return array<int, Item[]>
	 * @throws Exception
	 */
	public static function pack(array $items, float $max_weight, int $max_packages): array {
		$capacity = (int)floor(round($max_weight * self::SCALE, 3));
		$packages = [];
		$remaining = 0;

		foreach ($items as $item) {
			$unit = (int)round($item->getWeight() * self::SCALE);
			$quantity = $item->getQuantity();

			if ($unit > $capacity) {
				throw new Exception('one unit of ' . $item->toArray()['SKU'] . ' weighs ' . $item->getWeight() . ' kg, more than the package limit of ' . $max_weight . ' kg');
			}

			while ($quantity > 0) {
				$fits = $unit > 0 ? intdiv($remaining, $unit) : $quantity;

				if (!$packages || $fits < 1) {
					if (count($packages) === $max_packages) {
						throw new Exception('the cart needs more than ' . $max_packages . ' packages of up to ' . $max_weight . ' kg');
					}

					$packages[] = [];
					$remaining = $capacity;

					continue;
				}

				$batch = min($quantity, $fits);
				$packages[count($packages) - 1][] = $item->withQuantity($batch);
				$remaining -= $batch * $unit;
				$quantity -= $batch;
			}
		}

		return $packages;
	}
}
