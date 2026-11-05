<?php
use Frenet\Api;
use Frenet\Config as FrenetConfig;
use Frenet\Http\Client;
use Frenet\Log as FrenetLog;
use Frenet\Quote\Builder;
use Frenet\Quote\Cache as QuoteCache;
use Frenet\Quote\Catalog;

class ModelExtensionShippingFrenet extends Model {
	/**
	 * @param array<string, mixed> $address
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		return $this->quote($address, $this->cart->getProducts());
	}

	/**
	 * Quotes the product of the page alone, as Frenet\Simulator\Product builds it, the way the cart would quote it.
	 *
	 * @param array<string, mixed> $address
	 * @param array<string, mixed> $product
	 * @return array<string, mixed>
	 */
	public function getProductQuote(array $address, array $product): array {
		return $this->quote($address, [$product]);
	}

	/**
	 * Shipping address of a Brazilian postcode with the country and state of the store, as the cart shipping estimate builds it.
	 *
	 * @param string $postcode
	 * @param string $state
	 * @return array<string, mixed>
	 */
	public function getPostcodeAddress(string $postcode, string $state): array {
		$this->load->model('localisation/country');
		$this->load->model('localisation/zone');

		$country = $this->findRow($this->model_localisation_country->getCountries(), 'iso_code_2', 'BR');
		$zone = $country ? $this->findRow($this->model_localisation_zone->getZonesByCountryId($country['country_id']), 'code', $state) : [];

		return [
			'firstname'      => '',
			'lastname'       => '',
			'company'        => '',
			'address_1'      => '',
			'address_2'      => '',
			'postcode'       => substr($postcode, 0, 5) . '-' . substr($postcode, 5),
			'city'           => '',
			'zone_id'        => (int)($zone['zone_id'] ?? 0),
			'zone'           => (string)($zone['name'] ?? ''),
			'zone_code'      => (string)($zone['code'] ?? $state),
			'country_id'     => (int)($country['country_id'] ?? 0),
			'country'        => (string)($country['name'] ?? ''),
			'iso_code_2'     => 'BR',
			'iso_code_3'     => (string)($country['iso_code_3'] ?? 'BRA'),
			'address_format' => (string)($country['address_format'] ?? '')
		];
	}

	/**
	 * @param array<string, mixed> $address
	 * @param array<int|string, array<string, mixed>> $products
	 * @return array<string, mixed>
	 */
	private function quote(array $address, array $products): array {
		$config = new FrenetConfig($this->config);
		$log = new FrenetLog(new \Log(FrenetLog::FILENAME), $config->isDebug());

		$geo_zone_id = $config->getGeoZoneId();

		if ($geo_zone_id && !$this->inGeoZone($geo_zone_id, $address)) {
			if ($this->geoZoneExists($geo_zone_id)) {
				$log->debug('Quote skipped: the address is outside the Frenet geo zone', ['country_id' => $address['country_id'] ?? 0, 'zone_id' => $address['zone_id'] ?? 0]);
			} else {
				$log->error('Quote skipped: the geo zone ' . $geo_zone_id . ' chosen for Frenet no longer exists, choose another one in the settings');
			}

			return [];
		}

		if ($config->getToken() === '') {
			$log->error('Quote skipped: the token is not configured', ['postcode' => $address['postcode'] ?? '']);

			return [];
		}

		$builder = new Builder($config, $this->weight, $this->length, VERSION);

		try {
			$catalog = (new Catalog($this->db, DB_PREFIX, (int)$this->config->get('config_language_id'), (int)$this->config->get('config_store_id')))->getProducts(array_column($builder->filterShipped($products), 'product_id'));
		} catch (\Throwable $exception) {
			$log->error('SKU and categories could not be read, quoting with the model only: ' . $exception->getMessage());
			$catalog = [];
		}

		try {
			$request = $builder->build($address, $products, $catalog, (string)($this->session->data['coupon'] ?? ''));
		} catch (\Throwable $exception) {
			$log->error('Quote skipped: ' . $exception->getMessage(), ['postcode' => $address['postcode'] ?? '']);

			return [];
		}

		$this->load->language('extension/shipping/frenet');

		try {
			$result = (new Api(new Client(), new QuoteCache($this->cache), $log, $config->getToken(), VERSION, $config->getTimeout(), $config->getAttempts()))->quote($request);
		} catch (\Throwable $exception) {
			$log->error('Quote failed: ' . $exception->getMessage(), ['request' => $request->toArray()]);

			return $this->method($config, [], $this->language->get('error_unavailable'));
		}

		if ($result->getErrors()) {
			$log->error('Services with an error were left out of the quote', ['services' => $result->getErrors(), 'request' => $request->toArray()]);
		}

		if (!$result->getServices()) {
			$log->error('Quote returned no service' . ($result->getMessage() !== '' ? ': ' . $result->getMessage() : ''), ['request' => $request->toArray()]);

			return $this->method($config, [], $this->language->get('error_no_services'));
		}

		$template = $config->isDeliveryTimeShown() ? ($config->getDeliveryTimeMessage() ?: $this->language->get('text_delivery_time')) : '';

		$quote_data = [];

		foreach ($result->getServices() as $service) {
			$quote_data[$service->getCode()] = [
				'code'         => 'frenet.' . $service->getCode(),
				'title'        => $service->getTitle($template, $config->getAdditionalDays()),
				'cost'         => $service->getPrice(),
				'tax_class_id' => $config->getTaxClassId(),
				'text'         => $this->currency->format($this->tax->calculate($service->getPrice(), $config->getTaxClassId(), $this->config->get('config_tax')), $this->session->data['currency'])
			];
		}

		return $this->method($config, $quote_data, false);
	}

	/**
	 * @param int $geo_zone_id
	 * @param array<string, mixed> $address
	 * @return bool
	 */
	private function inGeoZone(int $geo_zone_id, array $address): bool {
		$query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' AND `country_id` = '" . (int)($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . (int)($address['zone_id'] ?? 0) . "' OR `zone_id` = '0') LIMIT 1");

		return (bool)$query->num_rows;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @param string $field
	 * @param string $value
	 * @return array<string, mixed>
	 */
	private function findRow(array $rows, string $field, string $value): array {
		foreach ($rows as $row) {
			if ((string)($row[$field] ?? '') === $value) {
				return $row;
			}
		}

		return [];
	}

	private function geoZoneExists(int $geo_zone_id): bool {
		return (bool)$this->db->query("SELECT 1 FROM `" . DB_PREFIX . "geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' LIMIT 1")->num_rows;
	}

	/**
	 * @param FrenetConfig $config
	 * @param array<string, array<string, mixed>> $quote_data
	 * @param false|string $error
	 * @return array<string, mixed>
	 */
	private function method(FrenetConfig $config, array $quote_data, $error): array {
		return [
			'code'       => 'frenet',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $config->getSortOrder(),
			'error'      => $error
		];
	}
}
