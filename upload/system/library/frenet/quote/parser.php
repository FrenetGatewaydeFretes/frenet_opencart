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

		$message = self::text($data['Message'] ?? null);
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
					'code'        => self::text($entry['ServiceCode'] ?? null),
					'description' => self::text($entry['ServiceDescription'] ?? null),
					'carrier'     => self::text($entry['Carrier'] ?? null),
					'message'     => self::text($entry['Msg'] ?? null)
				];

				continue;
			}

			$code = self::text($entry['ServiceCode'] ?? null);

			if ($code === '' || !isset($entry['ShippingPrice'])) {
				continue;
			}

			$services[] = new Service(
				$code,
				self::text($entry['ServiceDescription'] ?? null),
				(float)str_replace(',', '.', self::text($entry['ShippingPrice'])),
				(int)self::text($entry['DeliveryTime'] ?? null),
				self::text($entry['Carrier'] ?? null),
				self::text($entry['Msg'] ?? null)
			);
		}

		return new Result($services, $errors, $message);
	}

	/**
	 * A field of the answer as trimmed text, empty when the API sent no text there, such as a list or an object.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private static function text($value): string {
		return is_scalar($value) ? trim((string)$value) : '';
	}
}
