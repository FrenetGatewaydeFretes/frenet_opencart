<?php
use Frenet\Module;

class ControllerExtensionShippingFrenet extends Controller {
	private const PANEL_URL = 'https://painel.frenet.com.br/';

	private const LEGACY_KEYS = [
		'shipping_frenet_contrato_codigo',
		'shipping_frenet_contrato_senha',
		'shipping_frenet_contrato_token'
	];

	private const FIELDS = [
		'shipping_frenet_status',
		'shipping_frenet_postcode',
		'shipping_frenet_msg_prazo',
		'shipping_frenet_weight_class_id',
		'shipping_frenet_length_class_id',
		'shipping_frenet_sort_order'
	];

	private $error = [];

	public function index() {
		$this->load->language('extension/shipping/frenet');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$settings = $this->model_setting_setting->getSetting('shipping_frenet');

		$data['migrated'] = $this->removeLegacySettings($settings);

		$saved_token = (string)($settings['shipping_frenet_token'] ?? '');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$token = trim((string)($this->request->post['shipping_frenet_token'] ?? ''));

			$values = ['shipping_frenet_token' => $token !== '' ? $token : $saved_token];

			foreach (self::FIELDS as $field) {
				$values[$field] = $this->request->post[$field] ?? '';
			}

			$this->model_setting_setting->editSetting('shipping_frenet', $values);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true));
		}

		$data['error_warning'] = $this->error['warning'] ?? '';

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

		foreach (self::FIELDS as $field) {
			$data[$field] = $this->request->post[$field] ?? $this->config->get($field);
		}

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

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->config->set('template_cache', false);

		$this->response->setOutput($this->load->view('extension/shipping/frenet', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/frenet')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function removeLegacySettings(array $settings): bool {
		$legacy = array_intersect_key($settings, array_flip(self::LEGACY_KEYS));

		if (!$legacy) {
			return false;
		}

		$this->model_setting_setting->editSetting('shipping_frenet', array_diff_key($settings, $legacy));

		return true;
	}

	private function findClassId(array $classes, string $id_key, string $unit): int {
		foreach ($classes as $class) {
			if (strtolower(trim($class['unit'])) === $unit) {
				return (int)$class[$id_key];
			}
		}

		return 0;
	}
}
