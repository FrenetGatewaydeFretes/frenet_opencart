<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * One shipping option the Frenet API offered for the cart.
 */
class Service {
	/**
	 * @var string
	 */
	private $code;

	/**
	 * @var string
	 */
	private $description;

	/**
	 * @var float
	 */
	private $price;

	/**
	 * @var int
	 */
	private $delivery_time;

	/**
	 * @param string $code
	 * @param string $description
	 * @param float $price
	 * @param int $delivery_time
	 */
	public function __construct(string $code, string $description, float $price, int $delivery_time) {
		$this->code = $code;
		$this->description = $description;
		$this->price = $price;
		$this->delivery_time = $delivery_time;
	}

	/**
	 * @return string
	 */
	public function getCode(): string {
		return $this->code;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * @return float
	 */
	public function getPrice(): float {
		return $this->price;
	}

	/**
	 * Delivery days, 0 when the carrier did not inform it.
	 *
	 * @return int
	 */
	public function getDeliveryTime(): int {
		return $this->delivery_time;
	}

	/**
	 * Rate title from the store owner's template, where %s is the service name and %d the delivery days.
	 *
	 * @param string $template
	 * @return string
	 */
	public function getTitle(string $template): string {
		if ($template === '' || $this->delivery_time < 1) {
			return $this->description;
		}

		return strtr($template, ['%s' => $this->description, '%d' => (string)$this->delivery_time]);
	}
}
