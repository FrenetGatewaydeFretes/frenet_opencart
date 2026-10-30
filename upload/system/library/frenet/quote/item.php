<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * One cart line in the units the Frenet API expects: kilograms per unit and centimetres.
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
	 * @param float $weight
	 * @param float $length
	 * @param float $height
	 * @param float $width
	 * @param int $quantity
	 */
	public function __construct(float $weight, float $length, float $height, float $width, int $quantity) {
		$this->weight = $weight;
		$this->length = $length;
		$this->height = $height;
		$this->width = $width;
		$this->quantity = $quantity;
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
			'SKU'       => '',
			'Category'  => '',
			'isFragile' => false,
			'Quantity'  => $this->quantity
		];
	}
}
