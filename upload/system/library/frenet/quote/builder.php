<?php
declare(strict_types=1);

namespace Frenet\Quote;

use Cart\Length;
use Cart\Weight;
use Frenet\Config;

/**
 * Turns the OpenCart cart and shipping address into a quote request with the shipped products only, in kg and cm.
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
	 * Builds the request for the products of Cart::getProducts() that require shipping, whose weight is the line total.
	 *
	 * @param array<string, mixed> $address
	 * @param array<int|string, array<string, mixed>> $products
	 * @param array<int, array{sku: string, category: string}> $catalog
	 * @param string $coupon
	 * @return Request
	 * @throws Exception
	 */
	public function build(array $address, array $products, array $catalog, string $coupon): Request {
		$weight_class_id = $this->config->getWeightClassId();
		$length_class_id = $this->config->getLengthClassId();

		if ($this->weight->getUnit($weight_class_id) === '') {
			throw new Exception('The weight class mapped to kilograms does not exist: ' . $weight_class_id);
		}

		if ($this->length->getUnit($length_class_id) === '') {
			throw new Exception('The length class mapped to centimetres does not exist: ' . $length_class_id);
		}

		$items = [];
		$invoice_value = 0.0;

		foreach ($products as $product) {
			$quantity = (int)$product['quantity'];

			if ($quantity < 1 || !$product['shipping']) {
				continue;
			}

			$details = $catalog[(int)$product['product_id']] ?? ['sku' => (string)($product['model'] ?? ''), 'category' => ''];

			$items[] = new Item(
				$this->orDefault($this->toKilograms((float)$product['weight'] / $quantity, $product['weight_class_id'], $weight_class_id), $this->config->getDefaultWeight()),
				$this->orDefault($this->toCentimetres($product['length'], $product['length_class_id'], $length_class_id), $this->config->getMinimumLength()),
				$this->orDefault($this->toCentimetres($product['height'], $product['length_class_id'], $length_class_id), $this->config->getMinimumHeight()),
				$this->orDefault($this->toCentimetres($product['width'], $product['length_class_id'], $length_class_id), $this->config->getMinimumWidth()),
				$quantity,
				$details['sku'],
				$details['category']
			);

			$invoice_value += (float)$product['total'];
		}

		if (!$items) {
			throw new Exception('No product in the cart requires shipping');
		}

		$country = (string)($address['iso_code_2'] ?? '');

		return new Request(
			$this->config->getOriginPostcode(),
			(string)preg_replace('/\D/', '', (string)($address['postcode'] ?? '')),
			$country !== '' ? $country : self::DEFAULT_COUNTRY,
			round($invoice_value, 2),
			$coupon,
			$items,
			$this->platform_version
		);
	}

	/**
	 * @param float $value
	 * @param float $default
	 * @return float
	 */
	private function orDefault(float $value, float $default): float {
		return $value > 0 ? $value : $default;
	}

	/**
	 * @param float $value
	 * @param mixed $from
	 * @param int $to
	 * @return float
	 */
	private function toKilograms(float $value, $from, int $to): float {
		return round((float)$this->weight->convert($value, $from, $to), 3);
	}

	/**
	 * @param mixed $value
	 * @param mixed $from
	 * @param int $to
	 * @return float
	 */
	private function toCentimetres($value, $from, int $to): float {
		return round((float)$this->length->convert((float)$value, $from, $to), 2);
	}
}
