<?php
use Frenet\Api;
use Frenet\Config as FrenetConfig;
use Frenet\Http\Client;
use Frenet\Log as FrenetLog;
use Frenet\Quote\Builder;

class ModelExtensionShippingFrenet extends Model {
	public function getQuote($address) {
		$config = new FrenetConfig($this->config);
		$log = new FrenetLog(new \Log(FrenetLog::FILENAME), false);

		if ($config->getToken() === '') {
			$log->error('Quote skipped: the token is not configured');

			return [];
		}

		$builder = new Builder($config, $this->weight, $this->length, VERSION);
		$api = new Api(new Client(), $config->getToken(), VERSION);

		try {
			$request = $builder->build($address, $this->cart->getProducts(), (float)$this->cart->getSubTotal(), (string)($this->session->data['coupon'] ?? ''));

			$services = $api->quote($request);
		} catch (\RuntimeException $exception) {
			$log->error('Quote failed: ' . $exception->getMessage(), ['postcode' => $address['postcode'] ?? '']);

			return [];
		}

		if (!$services) {
			return [];
		}

		$this->load->language('extension/shipping/frenet');

		$quote_data = [];

		foreach ($services as $service) {
			$quote_data[$service->getCode()] = [
				'code'         => 'frenet.' . $service->getCode(),
				'title'        => $service->getTitle($config->getDeliveryTimeMessage()),
				'cost'         => $service->getPrice(),
				'tax_class_id' => 0,
				'text'         => $this->currency->format($this->tax->calculate($service->getPrice(), 0, $this->config->get('config_tax')), $this->session->data['currency'])
			];
		}

		return [
			'code'       => 'frenet',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $config->getSortOrder(),
			'error'      => false
		];
	}
}
