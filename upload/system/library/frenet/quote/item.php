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
	 * @var float
	 */
	private $value;

	/**
	 * @param float $weight
	 * @param float $length
	 * @param float $height
	 * @param float $width
	 * @param int $quantity
	 * @param string $sku
	 * @param string $category
	 * @param float $value
	 */
	public function __construct(float $weight, float $length, float $height, float $width, int $quantity, string $sku, string $category, float $value = 0.0) {
		$this->weight = $weight;
		$this->length = $length;
		$this->height = $height;
		$this->width = $width;
		$this->quantity = $quantity;
		$this->sku = $sku;
		$this->category = $category;
		$this->value = $value;
	}

	/**
	 * Weight of one unit in kilograms.
	 *
	 * @return float
	 */
	public function getWeight(): float {
		return $this->weight;
	}

	/**
	 * @return int
	 */
	public function getQuantity(): int {
		return $this->quantity;
	}

	/**
	 * Selling price of one unit, the part of the declared value each unit adds to its package.
	 *
	 * @return float
	 */
	public function getValue(): float {
		return $this->value;
	}

	/**
	 * Same line with another quantity, for the part of it that goes in one package.
	 *
	 * @param int $quantity
	 * @return self
	 */
	public function withQuantity(int $quantity): self {
		$item = clone $this;
		$item->quantity = $quantity;

		return $item;
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
