<?php
declare(strict_types=1);

namespace Frenet\Quote;

/**
 * Body of POST /shipping/quote, so the payload format lives in a single place.
 */
class Request {
	/**
	 * @var string
	 */
	public const PLATFORM_NAME = 'OpenCart3';

	/**
	 * @var string
	 */
	private $seller_postcode;

	/**
	 * @var string
	 */
	private $recipient_postcode;

	/**
	 * @var string
	 */
	private $recipient_country;

	/**
	 * @var float
	 */
	private $invoice_value;

	/**
	 * @var string
	 */
	private $coupon;

	/**
	 * @var Item[]
	 */
	private $items;

	/**
	 * @var string
	 */
	private $platform_version;

	/**
	 * @param string $seller_postcode
	 * @param string $recipient_postcode
	 * @param string $recipient_country
	 * @param float $invoice_value
	 * @param string $coupon
	 * @param Item[] $items
	 * @param string $platform_version
	 */
	public function __construct(string $seller_postcode, string $recipient_postcode, string $recipient_country, float $invoice_value, string $coupon, array $items, string $platform_version) {
		$this->seller_postcode = $seller_postcode;
		$this->recipient_postcode = $recipient_postcode;
		$this->recipient_country = $recipient_country;
		$this->invoice_value = $invoice_value;
		$this->coupon = $coupon;
		$this->items = $items;
		$this->platform_version = $platform_version;
	}

	/**
	 * @return Item[]
	 */
	public function getItems(): array {
		return $this->items;
	}

	/**
	 * Same quote for one package: only its items, declaring the selling price of those items.
	 *
	 * @param Item[] $items
	 * @return self
	 */
	public function withItems(array $items): self {
		$request = clone $this;
		$request->items = $items;
		$request->invoice_value = round(array_sum(array_map(static function (Item $item): float {
			return $item->getValue() * $item->getQuantity();
		}, $items)), 2);

		return $request;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'SellerCEP'            => $this->seller_postcode,
			'RecipientCEP'         => $this->recipient_postcode,
			'RecipientDocument'    => '',
			'RecipientCountry'     => $this->recipient_country,
			'ShipmentInvoiceValue' => $this->invoice_value,
			'Coupom'               => $this->coupon,
			'PlatformName'         => self::PLATFORM_NAME,
			'PlatformVersion'      => $this->platform_version,
			'ShippingItemArray'    => array_map(static function (Item $item): array {
				return $item->toArray();
			}, $this->items)
		];
	}
}
