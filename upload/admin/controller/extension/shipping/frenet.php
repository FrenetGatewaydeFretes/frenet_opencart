<?php
use Frenet\Config as FrenetConfig;
use Frenet\Module;
use Frenet\Postcode;

class ControllerExtensionShippingFrenet extends Controller {
	private const PANEL_URL = 'https://painel.frenet.com.br/';

	private const EVENT_CODE = 'frenet_simulator';

	private const EVENT_TRIGGER = 'catalog/view/product/product/after';

	private const EVENT_ACTION = 'extension/shipping/frenet/addSimulator';

	private const TOTAL_CODE = 'frenet';

	private const LEGACY_KEYS = [
		'shipping_frenet_contrato_codigo',
		'shipping_frenet_contrato_senha',
		'shipping_frenet_contrato_token'
	];

	private const FIELDS = [
		'shipping_frenet_status',
		'shipping_frenet_postcode',
		'shipping_frenet_delivery_time',
		'shipping_frenet_msg_prazo',
		'shipping_frenet_additional_days',
		'shipping_frenet_weight_class_id',
		'shipping_frenet_length_class_id',
		'shipping_frenet_minimum_height',
		'shipping_frenet_minimum_width',
		'shipping_frenet_minimum_length',
		'shipping_frenet_default_weight',
		'shipping_frenet_timeout',
		'shipping_frenet_attempts',
		'shipping_frenet_simulator',
		'shipping_frenet_simulator_limit',
		'shipping_frenet_virtual_fee',
		'shipping_frenet_virtual_fee_amount',
		'shipping_frenet_virtual_fee_name',
		'shipping_frenet_debug',
		'shipping_frenet_tax_class_id',
		'shipping_frenet_geo_zone_id',
		'shipping_frenet_sort_order'
	];

	private const RANGES = [
		'shipping_frenet_timeout'  => ['entry_timeout', FrenetConfig::TIMEOUT_MIN, FrenetConfig::TIMEOUT_MAX],
		'shipping_frenet_attempts' => ['entry_attempts', FrenetConfig::ATTEMPTS_MIN, FrenetConfig::ATTEMPTS_MAX]
	];

	private const MINIMUMS = [
		'shipping_frenet_minimum_height' => ['entry_minimum_height', FrenetConfig::DEFAULT_HEIGHT],
		'shipping_frenet_minimum_width'  => ['entry_minimum_width', FrenetConfig::DEFAULT_WIDTH],
		'shipping_frenet_minimum_length' => ['entry_minimum_length', FrenetConfig::DEFAULT_LENGTH],
		'shipping_frenet_default_weight' => ['entry_default_weight', FrenetConfig::DEFAULT_WEIGHT]
	];

	/**
	 * @var array<string, string>
	 */
	private $error = [];

	public function index(): void {
		$this->load->language('extension/shipping/frenet');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$settings = $this->model_setting_setting->getSetting('shipping_frenet');

		$can_modify = $this->user->hasPermission('modify', 'extension/shipping/frenet');

		$data['migrated'] = $can_modify && $this->removeLegacySettings($settings);

		if ($can_modify) {
			$this->installSimulator();
		}

		$saved_token = (string)($settings['shipping_frenet_token'] ?? '');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate($saved_token)) {
			$token = trim((string)($this->request->post['shipping_frenet_token'] ?? ''));

			$values = ['shipping_frenet_token' => $token !== '' ? $token : $saved_token];

			foreach (self::FIELDS as $field) {
				$values[$field] = $this->request->post[$field] ?? '';
			}

			$values['shipping_frenet_postcode'] = Postcode::format((string)$values['shipping_frenet_postcode']);

			$adjusted = [];

			foreach (self::RANGES as $field => [$label, $min, $max]) {
				$value = max($min, min($max, (int)$values[$field]));

				if ((string)$value !== trim((string)$values[$field])) {
					$adjusted[] = sprintf($this->language->get('text_adjusted'), $this->language->get($label), $value, $min, $max);
				}

				$values[$field] = $value;
			}

			$additional_days = max(0, (int)$values['shipping_frenet_additional_days']);

			if (trim((string)$values['shipping_frenet_additional_days']) !== '' && (string)$additional_days !== trim((string)$values['shipping_frenet_additional_days'])) {
				$adjusted[] = sprintf($this->language->get('text_adjusted_min'), $this->language->get('entry_additional_days'), $additional_days);
			}

			$values['shipping_frenet_additional_days'] = $additional_days;

			$simulator_limit = trim((string)$values['shipping_frenet_simulator_limit']);

			$values['shipping_frenet_simulator_limit'] = $simulator_limit !== '' ? (int)$simulator_limit : FrenetConfig::SIMULATOR_LIMIT_DEFAULT;

			foreach (self::MINIMUMS as $field => [$label, $default]) {
				$value = FrenetConfig::parsePositive($values[$field]);

				if ($value === null) {
					$adjusted[] = sprintf($this->language->get('text_default_used'), $this->language->get($label), $this->formatNumber($default));
					$value = $default;
				}

				$values[$field] = $this->formatNumber($value);
			}

			$virtual_fee = FrenetConfig::parsePositive($values['shipping_frenet_virtual_fee_amount']);

			$values['shipping_frenet_virtual_fee'] = (int)!empty($values['shipping_frenet_virtual_fee']);
			$values['shipping_frenet_virtual_fee_amount'] = $virtual_fee !== null ? $this->formatNumber($virtual_fee) : '';
			$values['shipping_frenet_virtual_fee_name'] = trim((string)$values['shipping_frenet_virtual_fee_name']);

			$this->model_setting_setting->editSetting('shipping_frenet', $values);

			if ($values['shipping_frenet_virtual_fee'] && $this->installTotal()) {
				$adjusted[] = $this->language->get('text_total_installed');
			}

			$this->session->data['success'] = implode(' ', array_merge([$this->language->get('text_success')], $adjusted));

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_token'] = $this->error['token'] ?? '';
		$data['error_postcode'] = $this->error['postcode'] ?? '';
		$data['error_simulator_limit'] = $this->error['simulator_limit'] ?? '';
		$data['error_virtual_fee_amount'] = $this->error['virtual_fee_amount'] ?? '';

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/shipping/frenet', 'user_token=' . $this->session->data['user_token'], true)
		];

		$data['action'] = $this->url->link('extension/shipping/frenet', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);

		$data['token_saved'] = $saved_token !== '';
		$data['panel_url'] = self::PANEL_URL;
		$data['text_version'] = sprintf($this->language->get('text_version'), Module::VERSION);

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			foreach (self::FIELDS as $field) {
				$data[$field] = $this->request->post[$field] ?? '';
			}
		} else {
			foreach (self::FIELDS as $field) {
				$data[$field] = $this->config->get($field);
			}

			$frenet_config = new FrenetConfig($this->config);

			$data['shipping_frenet_delivery_time'] = $frenet_config->isDeliveryTimeShown();
			$data['shipping_frenet_additional_days'] = $frenet_config->getAdditionalDays();
			$data['shipping_frenet_simulator'] = $frenet_config->isSimulatorEnabled();
			$data['shipping_frenet_simulator_limit'] = $frenet_config->getSimulatorLimit();
			$data['shipping_frenet_timeout'] = $frenet_config->getTimeout();
			$data['shipping_frenet_attempts'] = $frenet_config->getAttempts();
			$data['shipping_frenet_minimum_height'] = $this->formatNumber($frenet_config->getMinimumHeight());
			$data['shipping_frenet_minimum_width'] = $this->formatNumber($frenet_config->getMinimumWidth());
			$data['shipping_frenet_minimum_length'] = $this->formatNumber($frenet_config->getMinimumLength());
			$data['shipping_frenet_default_weight'] = $this->formatNumber($frenet_config->getDefaultWeight());
			$data['shipping_frenet_virtual_fee_name'] = $frenet_config->getVirtualFeeName();
		}

		$data['timeout_min'] = FrenetConfig::TIMEOUT_MIN;
		$data['timeout_max'] = FrenetConfig::TIMEOUT_MAX;
		$data['attempts_min'] = FrenetConfig::ATTEMPTS_MIN;
		$data['attempts_max'] = FrenetConfig::ATTEMPTS_MAX;

		$this->load->model('localisation/weight_class');

		$data['weight_classes'] = $this->model_localisation_weight_class->getWeightClasses();

		if (!$data['shipping_frenet_weight_class_id']) {
			$data['shipping_frenet_weight_class_id'] = $this->findClassId($data['weight_classes'], 'weight_class_id', 'kg');
		}

		$this->load->model('localisation/length_class');

		$data['length_classes'] = $this->model_localisation_length_class->getLengthClasses();

		if (!$data['shipping_frenet_length_class_id']) {
			$data['shipping_frenet_length_class_id'] = $this->findClassId($data['length_classes'], 'length_class_id', 'cm');
		}

		$this->load->model('localisation/tax_class');

		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$saved_geo_zone_id = (int)$this->config->get('shipping_frenet_geo_zone_id');

		$data['geo_zone_missing'] = $saved_geo_zone_id && !in_array($saved_geo_zone_id, array_map('intval', array_column($data['geo_zones'], 'geo_zone_id')), true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->config->set('template_cache', false);

		$this->response->setOutput($this->load->view('extension/shipping/frenet', $data));
	}

	public function install(): void {
		$this->installSimulator();
	}

	public function uninstall(): void {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode(self::EVENT_CODE);

		$this->load->model('setting/extension');

		$this->model_setting_extension->uninstall('total', self::TOTAL_CODE);

		$this->load->model('extension/shipping/frenet');

		$this->model_extension_shipping_frenet->dropSimulatorTable();
	}

	private function validate(string $saved_token): bool {
		if (!$this->user->hasPermission('modify', 'extension/shipping/frenet')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (Postcode::format((string)($this->request->post['shipping_frenet_postcode'] ?? '')) === null) {
			$this->error['postcode'] = $this->language->get('error_postcode');
		}

		$simulator_limit = trim((string)($this->request->post['shipping_frenet_simulator_limit'] ?? ''));

		if ($simulator_limit !== '' && !ctype_digit($simulator_limit)) {
			$this->error['simulator_limit'] = $this->language->get('error_simulator_limit_value');
		}

		if (!empty($this->request->post['shipping_frenet_virtual_fee']) && FrenetConfig::parsePositive($this->request->post['shipping_frenet_virtual_fee_amount'] ?? '') === null) {
			$this->error['virtual_fee_amount'] = $this->language->get('error_virtual_fee_amount');
		}

		if (!empty($this->request->post['shipping_frenet_status']) && $saved_token === '' && trim((string)($this->request->post['shipping_frenet_token'] ?? '')) === '') {
			$this->error['token'] = $this->language->get('error_token');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return bool
	 */
	private function removeLegacySettings(array $settings): bool {
		$legacy = array_intersect_key($settings, array_flip(self::LEGACY_KEYS));

		if (!$legacy) {
			return false;
		}

		$this->model_setting_setting->editSetting('shipping_frenet', array_diff_key($settings, $legacy));

		return true;
	}

	private function installSimulator(): void {
		$this->addEvent();

		$this->load->model('extension/shipping/frenet');

		$this->model_extension_shipping_frenet->createSimulatorTable();
	}

	/**
	 * Installs the Frenet order total, which charges the fee on carts the core sends to checkout without the shipping step.
	 *
	 * @return bool
	 */
	private function installTotal(): bool {
		$this->load->model('setting/extension');

		if (in_array(self::TOTAL_CODE, $this->model_setting_extension->getInstalled('total'), true)) {
			return false;
		}

		$this->model_setting_extension->install('total', self::TOTAL_CODE);

		$this->load->model('user/user_group');

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/total/' . self::TOTAL_CODE);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/total/' . self::TOTAL_CODE);

		$this->load->controller('extension/total/' . self::TOTAL_CODE . '/install');

		return true;
	}

	private function addEvent(): void {
		$this->load->model('setting/event');

		foreach ($this->model_setting_event->getEvents() as $event) {
			if ($event['code'] === self::EVENT_CODE) {
				return;
			}
		}

		$this->model_setting_event->addEvent(self::EVENT_CODE, self::EVENT_TRIGGER, self::EVENT_ACTION);
	}

	private function formatNumber(float $value): string {
		return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
	}

	/**
	 * @param array<int, array<string, mixed>> $classes
	 * @param string $id_key
	 * @param string $unit
	 * @return int
	 */
	private function findClassId(array $classes, string $id_key, string $unit): int {
		foreach ($classes as $class) {
			if (strtolower(trim($class['unit'])) === $unit) {
				return (int)$class[$id_key];
			}
		}

		return 0;
	}
}
