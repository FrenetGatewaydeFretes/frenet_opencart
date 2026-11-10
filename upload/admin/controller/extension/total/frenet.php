<?php
class ControllerExtensionTotalFrenet extends Controller {
	/**
	 * @var array<string, string>
	 */
	private $error = [];

	public function index(): void {
		$this->load->language('extension/total/frenet');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('total_frenet', [
				'total_frenet_status'     => 1,
				'total_frenet_sort_order' => (int)($this->request->post['total_frenet_sort_order'] ?? 0)
			]);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true));
		}

		$data['error_warning'] = $this->error['warning'] ?? '';

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/total/frenet', 'user_token=' . $this->session->data['user_token'], true)
		];

		$data['action'] = $this->url->link('extension/total/frenet', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true);

		$data['shipping_settings'] = $this->url->link('extension/shipping/frenet', 'user_token=' . $this->session->data['user_token'], true);

		$data['total_frenet_sort_order'] = $this->request->post['total_frenet_sort_order'] ?? $this->config->get('total_frenet_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->config->set('template_cache', false);

		$this->response->setOutput($this->load->view('extension/total/frenet', $data));
	}

	public function install(): void {
		$this->load->model('setting/setting');

		$this->model_setting_setting->editSetting('total_frenet', [
			'total_frenet_status'     => 1,
			'total_frenet_sort_order' => (int)$this->config->get('total_shipping_sort_order')
		]);
	}

	private function validate(): bool {
		if (!$this->user->hasPermission('modify', 'extension/total/frenet')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
