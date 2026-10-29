<?php
declare(strict_types=1);

namespace Frenet\Quote;

use Cart\Length;
use Cart\Weight;
use Frenet\Config;

/**
 * Turns the OpenCart cart and shipping address into a quote request, converting every product to kg and cm.
 */
class Builder {
	/**
	 * @var string
	 */
	private const DEFAULT_COUNTRY = 'BR';

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var Weight
	 */
	private $weight;

	/**
	 * @var Length
	 */
	private $length;

	/**
	 * @var string
	 */
	private $platform_version;

	/**
	 * @param Config $config
	 * @param Weight $weight
	 * @param Length $length
	 * @param string $platform_version
	 */
	public function __construct(Config $config, Weight $weight, Length $length, string $platform_version) {
		$this->config = $config;
		$this->weight = $weight;
		$this->length = $length;
		$this->platform_version = $platform_version;
	}

	/**
	 * Builds the request for the products of Cart::getProducts(), whose weight is the line total.
	 *
	 * @param array<string, mixed> $address
	 * @param array<int|string, array<string, mixed>> $products
	 * @param float $invoice_value
	 * @param string $coupon
	 * @return Request
	 * @throws Exception
	 */
	public function build(array $address, array $products, float $invoice_value, string $coupon): Request {
		$weight_class_id = $this->config->getWeightClassId();
		$length_class_id = $this->config->getLengthClassId();

		if ($this->weight->getUnit($weight_class_id) === '') {
			throw new Exception('The weight class mapped to kilograms does not exist: ' . $weight_class_id);
		}

		if ($this->length->getUnit($length_class_id) === '') {
			throw new Exception('The length class mapped to centimetres does not exist: ' . $length_class_id);
		}

		$items = [];

		foreach ($products as $product) {
			$quantity = (int)$product['quantity'];

			if ($quantity < 1) {
				continue;
			}

			$items[] = new Item(
				$this->toKilograms((float)$product['weight'] / $quantity, $product['weight_class_id'], $weight_class_id),
				$this->toCentimetres($product['length'], $product['length_class_id'], $length_class_id),
				$this->toCentimetres($product['height'], $product['length_class_id'], $length_class_id),
				$this->toCentimetres($product['width'], $product['length_class_id'], $length_class_id),
				$quantity
			);
		}

		$country = (string)($address['iso_code_2'] ?? '');

		return new Request(
			$this->config->getOriginPostcode(),
			(string)preg_replace('/\D/', '', (string)($address['postcode'] ?? '')),
			$country !== '' ? $country : self::DEFAULT_COUNTRY,
			$invoice_value,
			$coupon,
			$items,
			$this->platform_version
		);
	}

	/**
	 * @param float $value
	 * @param mixed $from
	 * @param int $to
	 * @return float
	 */
	private function toKilograms(float $value, $from, int $to): float {
		return (float)$this->weight->convert($value, $from, $to);
	}

	/**
	 * @param mixed $value
	 * @param mixed $from
	 * @param int $to
	 * @return float
	 */
	private function toCentimetres($value, $from, int $to): float {
		return (float)$this->length->convert((float)$value, $from, $to);
	}
}
