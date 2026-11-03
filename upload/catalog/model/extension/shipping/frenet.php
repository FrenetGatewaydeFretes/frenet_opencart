<?php
use Frenet\Api;
use Frenet\Config as FrenetConfig;
use Frenet\Http\Client;
use Frenet\Log as FrenetLog;
use Frenet\Quote\Builder;

class ModelExtensionShippingFrenet extends Model {
	public function getQuote($address) {
		$config = new FrenetConfig($this->config);
		$log = new FrenetLog(new \Log(FrenetLog::FILENAME), $config->isDebug());

		if ($config->getToken() === '') {
			$log->error('Quote skipped: the token is not configured', ['postcode' => $address['postcode'] ?? '']);

			return [];
		}

		try {
			$request = (new Builder($config, $this->weight, $this->length, VERSION))->build($address, $this->cart->getProducts(), (float)$this->cart->getSubTotal(), (string)($this->session->data['coupon'] ?? ''));
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

		$quote_data = [];

		foreach ($result->getServices() as $service) {
			$quote_data[$service->getCode()] = [
				'code'         => 'frenet.' . $service->getCode(),
				'title'        => $service->getTitle($config->getDeliveryTimeMessage()),
				'cost'         => $service->getPrice(),
				'tax_class_id' => 0,
				'text'         => $this->currency->format($this->tax->calculate($service->getPrice(), 0, $this->config->get('config_tax')), $this->session->data['currency'])
			];
		}

		return $this->method($config, $quote_data, false);
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
