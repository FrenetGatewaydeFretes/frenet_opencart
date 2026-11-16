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
	 * @var string
	 */
	private $carrier;

	/**
	 * @var string
	 */
	private $message;

	/**
	 * @param string $code
	 * @param string $description
	 * @param float $price
	 * @param int $delivery_time
	 * @param string $carrier
	 * @param string $message
	 */
	public function __construct(string $code, string $description, float $price, int $delivery_time, string $carrier = '', string $message = '') {
		$this->code = $code;
		$this->description = $description;
		$this->price = $price;
		$this->delivery_time = $delivery_time;
		$this->carrier = $carrier;
		$this->message = $message;
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
	 * Name of the carrier of the service, such as Correios or Jadlog.
	 *
	 * @return string
	 */
	public function getCarrier(): string {
		return $this->carrier;
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
	 * Note the Frenet API sent with the service, such as a delivery restriction, empty when there is none.
	 *
	 * @return string
	 */
	public function getMessage(): string {
		return $this->message;
	}

	/**
	 * Rate title from the store owner's template, where %s is the service name and %d the delivery days plus the additional ones,
	 * followed by the message of the Frenet API in parentheses.
	 *
	 * @param string $template
	 * @param int $additional_days
	 * @return string
	 */
	public function getTitle(string $template, int $additional_days): string {
		$days = $this->delivery_time + max(0, $additional_days);
		$title = $template === '' || $days < 1 ? $this->description : strtr($template, ['%s' => $this->description, '%d' => (string)$days]);

		return $this->message !== '' ? $title . ' (' . $this->message . ')' : $title;
	}
}
