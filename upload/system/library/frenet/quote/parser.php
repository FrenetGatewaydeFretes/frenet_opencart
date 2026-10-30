<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Reads a quote response, splitting the services that can be offered at checkout from the ones reported with an error.
 */
class Parser {
	/**
	 * @param string $body
	 * @return Result
	 * @throws Exception
	 */
	public function parse(string $body): Result {
		$data = json_decode($body, true);

		if (!is_array($data)) {
			throw new Exception('The Frenet API answered with invalid JSON');
		}

		$message = is_scalar($data['Message'] ?? null) ? (string)$data['Message'] : '';
		$entries = $data['ShippingSevicesArray'] ?? [];

		if (!is_array($entries)) {
			return new Result([], [], $message);
		}

		if (isset($entries['ServiceCode'])) {
			$entries = [$entries];
		}

		$services = [];
		$errors = [];

		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			if (!empty($entry['Error'])) {
				$errors[] = [
					'code'        => (string)($entry['ServiceCode'] ?? ''),
					'description' => (string)($entry['ServiceDescription'] ?? ''),
					'message'     => (string)($entry['Msg'] ?? '')
				];

				continue;
			}

			if (!isset($entry['ServiceCode'], $entry['ShippingPrice']) || (string)$entry['ServiceCode'] === '') {
				continue;
			}

			$services[] = new Service(
				(string)$entry['ServiceCode'],
				(string)($entry['ServiceDescription'] ?? ''),
				(float)str_replace(',', '.', (string)$entry['ShippingPrice']),
				(int)($entry['DeliveryTime'] ?? 0)
			);
		}

		return new Result($services, $errors, $message);
	}
}
