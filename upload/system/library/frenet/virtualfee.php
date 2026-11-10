<?php
declare(strict_types=1);

namespace Frenet;

/**
 * Virtual products fee of a cart: the fixed fee of the settings, charged once when the cart has a product that is not shipped.
 */
final class VirtualFee {
	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct(Config $config) {
		$this->config = $config;
	}

	/**
	 * Fee charged on the cart products, 0 when the fee is off or every product is shipped.
	 *
	 * @param array<int|string, array<string, mixed>> $products
	 * @return float
	 */
	public function getAmount(array $products): float {
		$fee = $this->config->getVirtualFee();

		if ($fee <= 0) {
			return 0.0;
		}

		foreach ($products as $product) {
			if ((int)($product['quantity'] ?? 0) > 0 && !($product['shipping'] ?? true)) {
				return $fee;
			}
		}

		return 0.0;
	}
}
