<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Merges the quote of the whole cart with the quotes of its packages: Correios summed per package, other carriers whole.
 */
final class Matcher {
	/**
	 * @var string
	 */
	public const CORREIOS = 'Correios';

	/**
	 * @var string
	 */
	public const FULL = 'full';

	/**
	 * Correios services quoted in every package, with the sum of the prices and the longest delivery time, followed by the
	 * services of the other carriers quoted for the whole cart.
	 *
	 * @param array<int|string, Result|UnavailableException> $results
	 * @return Result
	 * @throws UnavailableException
	 */
	public static function match(array $results): Result {
		$full = $results[self::FULL] ?? null;
		unset($results[self::FULL]);

		$totals = [];
		$errors = [];
		$failure = $full instanceof UnavailableException ? $full : null;

		foreach ($results as $package => $result) {
			if (!$result instanceof Result) {
				$failure = $failure ?? $result;

				continue;
			}

			foreach ($result->getErrors() as $error) {
				$error['message'] = 'Package ' . $package . ': ' . $error['message'];
				$errors[] = $error;
			}

			foreach ($result->getServices() as $service) {
				if (!self::isCorreios($service->getCarrier())) {
					continue;
				}

				$code = $service->getCode();
				$total = $totals[$code] ?? ['service' => $service, 'price' => 0.0, 'delivery_time' => 0, 'packages' => []];
				$total['price'] += $service->getPrice();
				$total['delivery_time'] = max($total['delivery_time'], $service->getDeliveryTime());
				$total['packages'][$package] = true;
				$totals[$code] = $total;
			}
		}

		$services = [];

		foreach ($totals as $code => $total) {
			if (count($total['packages']) === count($results)) {
				$services[] = new Service((string)$code, $total['service']->getDescription(), round($total['price'], 2), $total['delivery_time'], $total['service']->getCarrier());
			}
		}

		if ($full instanceof Result) {
			foreach ($full->getServices() as $service) {
				if (!self::isCorreios($service->getCarrier())) {
					$services[] = $service;
				}
			}

			foreach ($full->getErrors() as $error) {
				if (!self::isCorreios($error['carrier'])) {
					$errors[] = $error;
				}
			}
		}

		if (!$services && $failure) {
			throw $failure;
		}

		return new Result($services, $errors, $full instanceof Result ? $full->getMessage() : '');
	}

	/**
	 * @param string $carrier
	 * @return bool
	 */
	private static function isCorreios(string $carrier): bool {
		return strcasecmp($carrier, self::CORREIOS) === 0;
	}
}
