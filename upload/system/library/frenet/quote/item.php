<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * One cart line in the units the Frenet API expects, kilograms per unit and centimetres, with its code and categories.
 */
class Item {
	/**
	 * @var float
	 */
	private $weight;

	/**
	 * @var float
	 */
	private $length;

	/**
	 * @var float
	 */
	private $height;

	/**
	 * @var float
	 */
	private $width;

	/**
	 * @var int
	 */
	private $quantity;

	/**
	 * @var string
	 */
	private $sku;

	/**
	 * @var string
	 */
	private $category;

	/**
	 * @param float $weight
	 * @param float $length
	 * @param float $height
	 * @param float $width
	 * @param int $quantity
	 * @param string $sku
	 * @param string $category
	 */
	public function __construct(float $weight, float $length, float $height, float $width, int $quantity, string $sku, string $category) {
		$this->weight = $weight;
		$this->length = $length;
		$this->height = $height;
		$this->width = $width;
		$this->quantity = $quantity;
		$this->sku = $sku;
		$this->category = $category;
	}

	/**
	 * Entry of ShippingItemArray in the quote payload.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'Weight'    => $this->weight,
			'Length'    => $this->length,
			'Height'    => $this->height,
			'Width'     => $this->width,
			'Diameter'  => 0,
			'SKU'       => $this->sku,
			'Category'  => $this->category,
			'isFragile' => false,
			'Quantity'  => $this->quantity
		];
	}
}
