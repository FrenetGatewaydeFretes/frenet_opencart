<?php
use Frenet\Config as FrenetConfig;
use Frenet\Log as FrenetLog;
use Frenet\Postcode;
use Frenet\Simulator\Product;
use Frenet\Simulator\RateLimit;

class ControllerExtensionShippingFrenet extends Controller {
	private const PRODUCT_MARKER = 'id="button-cart"';

	private const BLOCK_END = '</div>';

	/**
	 * Adds the shipping simulator below the add to cart button, as the catalog/view/product/product/after event.
	 *
	 * @param string $route
	 * @param array<string, mixed> $data
	 * @param string $output
	 * @return void
	 */
	public function addSimulator(&$route = '', &$data = [], &$output = ''): void {
		$product_id = (int)($data['product_id'] ?? 0);

		if (!$product_id || !$this->isSimulatorAvailable(new FrenetConfig($this->config))) {
			return;
		}

		$marker = strpos($output, self::PRODUCT_MARKER);
		$position = $marker !== false ? strpos($output, self::BLOCK_END, $marker) : false;

		if ($position === false) {
			return;
		}

		if (!$this->createProduct()->requiresShipping($product_id)) {
			return;
		}

		$this->load->language('extension/shipping/frenet');

		$template_cache = $this->config->get('template_cache');

		$this->config->set('template_cache', false);

		$simulator = $this->load->view('extension/shipping/frenet_simulator', ['autofill' => $this->customer->isLogged()]);

		$this->config->set('template_cache', $template_cache);

		$output = substr_replace($output, $simulator, $position + strlen(self::BLOCK_END), 0);
	}

	public function postcode(): void {
		$postcode = null;

		if ($this->isSimulatorAvailable(new FrenetConfig($this->config))) {
			$postcode = $this->getBuyerPostcode();
		}

		$this->response->addHeader('Cache-Control: no-store, private');

		$this->respond(['postcode' => (string)$postcode]);
	}

	public function quote(): void {
		$this->load->language('extension/shipping/frenet');

		$config = new FrenetConfig($this->config);

		if (!$this->isSimulatorAvailable($config)) {
			$this->respond(['error' => ['warning' => $this->language->get('error_simulator_disabled')]]);

			return;
		}

		$log = new FrenetLog(new \Log(FrenetLog::FILENAME), $config->isDebug());

		$retry_after = (new RateLimit($this->cache, $log, $config->getSimulatorLimit()))->hit((string)($this->request->server['REMOTE_ADDR'] ?? ''));

		if ($retry_after) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 429 Too Many Requests');
			$this->response->addHeader('Retry-After: ' . $retry_after);

			$this->respond(['error' => ['warning' => $this->language->get('error_simulator_limit')]]);

			return;
		}

		try {
			$json = $this->simulate();
		} catch (\Throwable $exception) {
			$log->error('Simulator quote failed: ' . $exception->getMessage());

			$json = ['error' => ['warning' => $this->language->get('error_unavailable')]];
		}

		$this->respond($json);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function simulate(): array {
		$postcode = Postcode::getDigits((string)($this->request->post['postcode'] ?? ''));
		$state = Postcode::getState($postcode);

		if ($state === '') {
			return ['error' => ['postcode' => $this->language->get('error_simulator_postcode')]];
		}

		$product_id = (int)($this->request->post['product_id'] ?? 0);
		$quantity = (int)($this->request->post['quantity'] ?? 1);
		$options = isset($this->request->post['option']) && is_array($this->request->post['option']) ? array_filter($this->request->post['option']) : [];

		if ($quantity < 1) {
			return ['error' => ['quantity' => $this->language->get('error_simulator_quantity')]];
		}

		$simulator = $this->createProduct();

		$missing = $simulator->getMissingOptions($product_id, $options);

		if ($missing) {
			$errors = [];

			foreach ($missing as $product_option_id => $name) {
				$errors[$product_option_id] = sprintf($this->language->get('error_simulator_option'), html_entity_decode($name, ENT_QUOTES, 'UTF-8'));
			}

			return ['error' => ['option' => $errors]];
		}

		$product = $simulator->getProduct($product_id, $quantity, $options);

		if (!$product || !$product['shipping']) {
			return ['error' => ['warning' => $this->language->get('error_simulator_product')]];
		}

		$this->load->model('extension/shipping/frenet');

		$address = $this->model_extension_shipping_frenet->getPostcodeAddress($postcode, $state);

		$this->tax->unsetRates();
		$this->tax->setShippingAddress($address['country_id'], $address['zone_id']);

		if (isset($this->session->data['payment_address']['country_id']) && isset($this->session->data['payment_address']['zone_id'])) {
			$this->tax->setPaymentAddress($this->session->data['payment_address']['country_id'], $this->session->data['payment_address']['zone_id']);
		} elseif ($this->config->get('config_tax_default') == 'payment') {
			$this->tax->setPaymentAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
		}

		$this->tax->setStoreAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));

		$method = $this->model_extension_shipping_frenet->getProductQuote($address, $product);

		if (!$method) {
			return ['error' => ['warning' => $this->language->get('error_no_services')]];
		}

		if ($method['error']) {
			return ['error' => ['warning' => $method['error']]];
		}

		$rates = [];

		foreach ($method['quote'] as $rate) {
			$rates[] = [
				'title' => html_entity_decode((string)$rate['title'], ENT_QUOTES, 'UTF-8'),
				'text'  => html_entity_decode((string)$rate['text'], ENT_QUOTES, 'UTF-8')
			];
		}

		return ['quote' => $rates];
	}

	/**
	 * @param array<string, mixed> $json
	 * @return void
	 */
	private function respond(array $json): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getBuyerPostcode(): ?string {
		$postcode = Postcode::format((string)($this->session->data['shipping_address']['postcode'] ?? ''));

		if ($postcode !== null || !$this->customer->isLogged()) {
			return $postcode;
		}

		$this->load->model('account/address');

		$address = $this->model_account_address->getAddress($this->customer->getAddressId());

		return Postcode::format((string)($address['postcode'] ?? ''));
	}

	private function isSimulatorAvailable(FrenetConfig $config): bool {
		return $config->isEnabled() && $config->isSimulatorEnabled() && $config->getToken() !== '';
	}

	private function createProduct(): Product {
		return new Product($this->db, DB_PREFIX, (int)$this->config->get('config_store_id'), (int)$this->config->get('config_language_id'), (int)$this->config->get('config_customer_group_id'));
	}
}
