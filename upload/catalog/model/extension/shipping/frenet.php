<?php
use Frenet\Api;
use Frenet\Config as FrenetConfig;
use Frenet\Http\Client;
use Frenet\Log as FrenetLog;
use Frenet\Quote\Builder;
use Frenet\Quote\Catalog;

class ModelExtensionShippingFrenet extends Model {
	public function getQuote($address) {
		$config = new FrenetConfig($this->config);
		$log = new FrenetLog(new \Log(FrenetLog::FILENAME), $config->isDebug());

		if (!$this->inGeoZone($config->getGeoZoneId(), $address)) {
			$log->debug('Quote skipped: the address is outside the Frenet geo zone', ['country_id' => $address['country_id'] ?? 0, 'zone_id' => $address['zone_id'] ?? 0]);

			return [];
		}

		if ($config->getToken() === '') {
			$log->error('Quote skipped: the token is not configured', ['postcode' => $address['postcode'] ?? '']);

			return [];
		}

		$builder = new Builder($config, $this->weight, $this->length, VERSION);
		$products = $this->cart->getProducts();

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
			$result = (new Api(new Client(), $log, $config->getToken(), VERSION, $config->getTimeout(), $config->getAttempts()))->quote($request);
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

	private function inGeoZone(int $geo_zone_id, array $address): bool {
		if (!$geo_zone_id) {
			return true;
		}

		$query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' AND `country_id` = '" . (int)($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . (int)($address['zone_id'] ?? 0) . "' OR `zone_id` = '0') LIMIT 1");

		return (bool)$query->num_rows;
	}

	private function method(FrenetConfig $config, array $quote_data, $error) {
		return [
			'code'       => 'frenet',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $config->getSortOrder(),
			'error'      => $error
		];
	}
}
