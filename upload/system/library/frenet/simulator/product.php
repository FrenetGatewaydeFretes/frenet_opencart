<?php
declare(strict_types=1);

namespace Frenet\Simulator;

/**
 * Reads the product of the page as Cart::getProducts() would return it alone in the cart, with the same price and option rules.
 */
final class Product {
	/**
	 * @var string[]
	 */
	private const SINGLE_VALUE_TYPES = ['select', 'radio'];

	/**
	 * @var string
	 */
	private const MULTIPLE_VALUE_TYPE = 'checkbox';

	/**
	 * @var \DB
	 */
	private $db;

	/**
	 * @var string
	 */
	private $prefix;

	/**
	 * @var int
	 */
	private $store_id;

	/**
	 * @var int
	 */
	private $language_id;

	/**
	 * @var int
	 */
	private $customer_group_id;

	/**
	 * @param \DB $db
	 * @param string $prefix
	 * @param int $store_id
	 * @param int $language_id
	 * @param int $customer_group_id
	 */
	public function __construct(\DB $db, string $prefix, int $store_id, int $language_id, int $customer_group_id) {
		$this->db = $db;
		$this->prefix = $prefix;
		$this->store_id = $store_id;
		$this->language_id = $language_id;
		$this->customer_group_id = $customer_group_id;
	}

	/**
	 * Whether the store sells the product and it needs shipping, with the product rules of the cart.
	 *
	 * @param int $product_id
	 * @return bool
	 */
	public function requiresShipping(int $product_id): bool {
		return !empty($this->getSoldProduct($product_id, 'p.`shipping`')['shipping']);
	}

	/**
	 * Names of the required options left empty, keyed by product_option_id, as the cart refuses the product without them.
	 *
	 * @param int $product_id
	 * @param array<int|string, mixed> $options
	 * @return array<int, string>
	 */
	public function getMissingOptions(int $product_id, array $options): array {
		$options = array_filter($options);

		$query = $this->db->query("SELECT po.`product_option_id`, od.`name` FROM `" . $this->prefix . "product_option` po LEFT JOIN `" . $this->prefix . "option` o ON (po.`option_id` = o.`option_id`) LEFT JOIN `" . $this->prefix . "option_description` od ON (o.`option_id` = od.`option_id`) WHERE po.`product_id` = '" . $product_id . "' AND po.`required` = '1' AND od.`language_id` = '" . $this->language_id . "' ORDER BY o.`sort_order` ASC");

		$missing = [];

		foreach ($query->rows as $option) {
			if (empty($options[$option['product_option_id']])) {
				$missing[(int)$option['product_option_id']] = (string)$option['name'];
			}
		}

		return $missing;
	}

	/**
	 * The product as a line of Cart::getProducts() with the quantity and options chosen, or null when the store does not sell it.
	 *
	 * @param int $product_id
	 * @param int $quantity
	 * @param array<int|string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	public function getProduct(int $product_id, int $quantity, array $options): ?array {
		$product = $this->getSoldProduct($product_id, 'p.`product_id`, p.`model`, p.`shipping`, p.`price`, p.`weight`, p.`weight_class_id`, p.`length`, p.`width`, p.`height`, p.`length_class_id`');

		if (!$product) {
			return null;
		}

		[$option_price, $option_weight] = $this->getOptionTotals($product_id, $options);

		$price = $this->getPrice($product_id, $quantity, (float)$product['price']) + $option_price;

		return [
			'product_id'      => (int)$product['product_id'],
			'model'           => $product['model'],
			'shipping'        => $product['shipping'],
			'quantity'        => $quantity,
			'price'           => $price,
			'total'           => $price * $quantity,
			'weight'          => ((float)$product['weight'] + $option_weight) * $quantity,
			'weight_class_id' => $product['weight_class_id'],
			'length'          => $product['length'],
			'width'           => $product['width'],
			'height'          => $product['height'],
			'length_class_id' => $product['length_class_id']
		];
	}

	/**
	 * Columns of the product when the store sells it, with the product rules of Cart::getProducts(), or an empty array.
	 *
	 * @param int $product_id
	 * @param string $columns
	 * @return array<string, mixed>
	 */
	private function getSoldProduct(int $product_id, string $columns): array {
		return $this->db->query("SELECT " . $columns . " FROM `" . $this->prefix . "product_to_store` p2s LEFT JOIN `" . $this->prefix . "product` p ON (p2s.`product_id` = p.`product_id`) LEFT JOIN `" . $this->prefix . "product_description` pd ON (p.`product_id` = pd.`product_id`) WHERE p2s.`store_id` = '" . $this->store_id . "' AND p2s.`product_id` = '" . $product_id . "' AND pd.`language_id` = '" . $this->language_id . "' AND p.`date_available` <= NOW() AND p.`status` = '1'")->row;
	}

	/**
	 * @param int $product_id
	 * @param int $quantity
	 * @param float $price
	 * @return float
	 */
	private function getPrice(int $product_id, int $quantity, float $price): float {
		$dates = "((`date_start` = '0000-00-00' OR `date_start` < NOW()) AND (`date_end` = '0000-00-00' OR `date_end` > NOW()))";

		$discount = $this->db->query("SELECT `price` FROM `" . $this->prefix . "product_discount` WHERE `product_id` = '" . $product_id . "' AND `customer_group_id` = '" . $this->customer_group_id . "' AND `quantity` <= '" . $quantity . "' AND " . $dates . " ORDER BY `quantity` DESC, `priority` ASC, `price` ASC LIMIT 1");

		if ($discount->num_rows) {
			$price = (float)$discount->row['price'];
		}

		$special = $this->db->query("SELECT `price` FROM `" . $this->prefix . "product_special` WHERE `product_id` = '" . $product_id . "' AND `customer_group_id` = '" . $this->customer_group_id . "' AND " . $dates . " ORDER BY `priority` ASC, `price` ASC LIMIT 1");

		if ($special->num_rows) {
			$price = (float)$special->row['price'];
		}

		return $price;
	}

	/**
	 * @param int $product_id
	 * @param array<int|string, mixed> $options
	 * @return array{float, float}
	 */
	private function getOptionTotals(int $product_id, array $options): array {
		$value_ids = [];

		foreach ($options as $value) {
			foreach ((array)$value as $product_option_value_id) {
				$value_ids[] = (int)$product_option_value_id;
			}
		}

		$value_ids = array_filter(array_unique($value_ids));

		if (!$value_ids) {
			return [0.0, 0.0];
		}

		$query = $this->db->query("SELECT pov.`product_option_id`, pov.`product_option_value_id`, pov.`price`, pov.`price_prefix`, pov.`weight`, pov.`weight_prefix`, o.`type` FROM `" . $this->prefix . "product_option_value` pov LEFT JOIN `" . $this->prefix . "product_option` po ON (pov.`product_option_id` = po.`product_option_id`) LEFT JOIN `" . $this->prefix . "option` o ON (po.`option_id` = o.`option_id`) WHERE pov.`product_id` = '" . $product_id . "' AND pov.`product_option_value_id` IN (" . implode(',', $value_ids) . ")");

		$price = 0.0;
		$weight = 0.0;

		foreach ($query->rows as $value) {
			if (!$this->isChosen($value, $options[$value['product_option_id']] ?? null)) {
				continue;
			}

			$price += $this->getSigned($value['price'], $value['price_prefix']);
			$weight += $this->getSigned($value['weight'], $value['weight_prefix']);
		}

		return [$price, $weight];
	}

	/**
	 * @param array<string, mixed> $value
	 * @param mixed $chosen
	 * @return bool
	 */
	private function isChosen(array $value, $chosen): bool {
		$product_option_value_id = (int)$value['product_option_value_id'];

		if (in_array($value['type'], self::SINGLE_VALUE_TYPES, true)) {
			return !is_array($chosen) && (int)$chosen === $product_option_value_id;
		}

		if ($value['type'] === self::MULTIPLE_VALUE_TYPE) {
			return is_array($chosen) && in_array($product_option_value_id, array_map('intval', $chosen), true);
		}

		return false;
	}

	/**
	 * @param mixed $value
	 * @param mixed $prefix
	 * @return float
	 */
	private function getSigned($value, $prefix): float {
		if ($prefix === '+') {
			return (float)$value;
		}

		if ($prefix === '-') {
			return -(float)$value;
		}

		return 0.0;
	}
}
