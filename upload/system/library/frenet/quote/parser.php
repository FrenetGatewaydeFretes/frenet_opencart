<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Reads the services of a quote response, keeping only the ones that can be offered at checkout.
 */
class Parser {
	/**
	 * @param string $body
	 * @return Service[]
	 * @throws Exception
	 */
	public function parse(string $body): array {
		$data = json_decode($body, true);

		if (!is_array($data)) {
			throw new Exception('The Frenet API answered with invalid JSON');
		}

		$entries = $data['ShippingSevicesArray'] ?? [];

		if (!is_array($entries)) {
			return [];
		}

		if (isset($entries['ServiceCode'])) {
			$entries = [$entries];
		}

		$services = [];

		foreach ($entries as $entry) {
			if (!is_array($entry) || !empty($entry['Error']) || !isset($entry['ServiceCode'], $entry['ShippingPrice']) || (string)$entry['ServiceCode'] === '') {
				continue;
			}

			$services[] = new Service(
				(string)$entry['ServiceCode'],
				(string)($entry['ServiceDescription'] ?? ''),
				(float)str_replace(',', '.', (string)$entry['ShippingPrice']),
				(int)($entry['DeliveryTime'] ?? 0)
			);
		}

		return $services;
	}
}
