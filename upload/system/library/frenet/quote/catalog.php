<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Reads what the cart does not carry for each product: the code sent as SKU and the category names.
 */
class Catalog {
	/**
	 * @var string
	 */
	public const CATEGORY_SEPARATOR = '|';

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
	private $language_id;

	/**
	 * @param \DB $db
	 * @param string $prefix
	 * @param int $language_id
	 */
	public function __construct(\DB $db, string $prefix, int $language_id) {
		$this->db = $db;
		$this->prefix = $prefix;
		$this->language_id = $language_id;
	}

	/**
	 * SKU, or the model when the SKU is empty, and the category names joined by |, per product id.
	 *
	 * @param int[] $product_ids
	 * @return array<int, array{sku: string, category: string}>
	 */
	public function getProducts(array $product_ids): array {
		$ids = implode(',', array_unique(array_map('intval', $product_ids)));

		if ($ids === '') {
			return [];
		}

		$products = [];

		foreach ($this->db->query("SELECT `product_id`, `sku`, `model` FROM `" . $this->prefix . "product` WHERE `product_id` IN (" . $ids . ")")->rows as $row) {
			$sku = $this->decode($row['sku']);

			$products[(int)$row['product_id']] = ['sku' => $sku !== '' ? $sku : $this->decode($row['model']), 'categories' => []];
		}

		foreach ($this->db->query("SELECT p2c.`product_id`, cd.`name` FROM `" . $this->prefix . "product_to_category` p2c INNER JOIN `" . $this->prefix . "category_description` cd ON (cd.`category_id` = p2c.`category_id` AND cd.`language_id` = '" . $this->language_id . "') WHERE p2c.`product_id` IN (" . $ids . ") ORDER BY cd.`name`")->rows as $row) {
			if (isset($products[(int)$row['product_id']])) {
				$products[(int)$row['product_id']]['categories'][] = str_replace(self::CATEGORY_SEPARATOR, '/', $this->decode($row['name']));
			}
		}

		$details = [];

		foreach ($products as $product_id => $product) {
			$details[$product_id] = ['sku' => $product['sku'], 'category' => implode(self::CATEGORY_SEPARATOR, $product['categories'])];
		}

		return $details;
	}

	/**
	 * OpenCart stores catalog text HTML-escaped; the Frenet panel compares the plain text.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function decode($value): string {
		return trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
	}
}
