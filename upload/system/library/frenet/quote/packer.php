<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Splits the cart lines into packages up to a weight, filling each package in cart order before opening the next one, as the
 * Multi Quote of the Frenet module for Magento 2.
 */
final class Packer {
	/**
	 * @var int
	 */
	private const SCALE = 10000;

	/**
	 * @var int
	 */
	private const PRECISION = 6;

	/**
	 * Packages of the lines, splitting the quantity of a line between packages when it does not fit the current one.
	 *
	 * @param Item[] $items
	 * @param float $max_weight
	 * @return array<int, Item[]>
	 * @throws Exception
	 */
	public static function pack(array $items, float $max_weight): array {
		foreach ($items as $item) {
			if ($item->getWeight() > $max_weight) {
				throw new Exception('one unit of ' . $item->getSku() . ' weighs ' . $item->getWeight() . ' kg, more than the package limit of ' . $max_weight . ' kg');
			}
		}

		$capacity = (int)floor(self::scale($max_weight));
		$packages = [];

		foreach ($items as $item) {
			if (!$packages) {
				$packages[] = [];
			}

			$quantity = $item->getQuantity();

			if ($item->getWeight() <= 0) {
				$packages[count($packages) - 1][] = $item;

				continue;
			}

			$unit = (int)ceil(self::scale($item->getWeight()));
			$per_package = intdiv($capacity, max($unit, 1));

			if ($per_package < 1) {
				throw new Exception('one unit of ' . $item->getSku() . ' does not fit a package of ' . $max_weight . ' kg');
			}

			$remaining = max(0, (int)floor(self::scale($max_weight - self::weigh($packages[count($packages) - 1]))));
			$first = min($quantity, intdiv($remaining, max($unit, 1)));
			$left = $quantity - $first;

			if ($first > 0) {
				$packages[count($packages) - 1][] = $item->withQuantity($first);
			}

			for ($full = intdiv($left, $per_package); $full > 0; $full--) {
				$packages[] = [$item->withQuantity($per_package)];
			}

			if ($left % $per_package > 0) {
				$packages[] = [$item->withQuantity($left % $per_package)];
			}
		}

		return $packages;
	}

	/**
	 * @param float $weight
	 * @return float
	 */
	private static function scale(float $weight): float {
		return round($weight * self::SCALE, self::PRECISION);
	}

	/**
	 * @param Item[] $package
	 * @return float
	 */
	private static function weigh(array $package): float {
		$total = 0.0;

		foreach ($package as $item) {
			$total += $item->getWeight() * $item->getQuantity();
		}

		return $total;
	}
}
