<?php
declare(strict_types=1);

namespace Frenet;

/**
 * Reads Brazilian postcodes typed by people and finds their state by the Correios ranges.
 */
final class Postcode {
	/**
	 * @var array<int, array{int, int, string}>
	 */
	private const RANGES = [
		[1000, 19999, 'SP'],
		[20000, 28999, 'RJ'],
		[29000, 29999, 'ES'],
		[30000, 39999, 'MG'],
		[40000, 48999, 'BA'],
		[49000, 49999, 'SE'],
		[50000, 56999, 'PE'],
		[57000, 57999, 'AL'],
		[58000, 58999, 'PB'],
		[59000, 59999, 'RN'],
		[60000, 63999, 'CE'],
		[64000, 64999, 'PI'],
		[65000, 65999, 'MA'],
		[66000, 68899, 'PA'],
		[68900, 68999, 'AP'],
		[69000, 69299, 'AM'],
		[69300, 69399, 'RR'],
		[69400, 69899, 'AM'],
		[69900, 69999, 'AC'],
		[70000, 72799, 'DF'],
		[72800, 72999, 'GO'],
		[73000, 73699, 'DF'],
		[73700, 76799, 'GO'],
		[76800, 76999, 'RO'],
		[77000, 77999, 'TO'],
		[78000, 78999, 'MT'],
		[79000, 79999, 'MS'],
		[80000, 87999, 'PR'],
		[88000, 89999, 'SC'],
		[90000, 99999, 'RS']
	];

	/**
	 * Postcode with the mask removed, or an empty string when it does not have 8 digits.
	 *
	 * @param string $postcode
	 * @return string
	 */
	public static function getDigits(string $postcode): string {
		$postcode = trim($postcode);

		if (!preg_match('/^[0-9.\s-]+$/', $postcode)) {
			return '';
		}

		$digits = (string)preg_replace('/\D/', '', $postcode);

		return strlen($digits) === 8 ? $digits : '';
	}

	/**
	 * Code of the state the postcode belongs to, as in the zone table of OpenCart, or an empty string when none.
	 *
	 * @param string $postcode
	 * @return string
	 */
	public static function getState(string $postcode): string {
		$digits = self::getDigits($postcode);

		if ($digits === '') {
			return '';
		}

		$prefix = (int)substr($digits, 0, 5);

		foreach (self::RANGES as [$from, $to, $state]) {
			if ($prefix >= $from && $prefix <= $to) {
				return $state;
			}
		}

		return '';
	}
}
