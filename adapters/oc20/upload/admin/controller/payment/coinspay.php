<?php
/**
 * CoinsPay for OpenCart 2.0–2.2: the settings screen. The work is done by the shared core in
 * system/library/coinspay; this controller only renders it.
 */
class ControllerPaymentCoinspay extends Controller {

	private function admin() {
		require_once DIR_SYSTEM . 'library/coinspay/loader.php';
		return new CoinsPay_OpenCartAdmin($this->registry);
	}

	public function index() {
		$this->load->language('payment/coinspay');
		$admin = $this->admin();
		$data = $admin->data();
		$t = $data['texts'];
		$this->document->setTitle($t['heading']);
		$token = 'token=' . $this->session->data['token'];

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $admin->save()) {
			$this->session->data['success'] = $t['success'];
			$this->response->redirect($this->url->link('payment/coinspay', $token, 'SSL'));
		}
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$data = $admin->data();
		}

		$data['t'] = $t;
		$data['success'] = '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}
		$data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, 'SSL')),
			array('text' => $this->language->get('text_payment'), 'href' => $this->url->link('extension/payment', $token, 'SSL')),
			array('text' => $t['heading'], 'href' => $this->url->link('payment/coinspay', $token, 'SSL')),
		);
		$data['action'] = $this->url->link('payment/coinspay', $token, 'SSL');
		$data['cancel'] = $this->url->link('extension/payment', $token, 'SSL');
		$data['check_url'] = $this->url->link('payment/coinspay/check', $token, 'SSL');
		$data['refresh_url'] = $this->url->link('payment/coinspay/refresh', $token, 'SSL');
		$data['order_url'] = $this->url->link('sale/order/info', $token, 'SSL');
		$data['script'] = file_get_contents(DIR_SYSTEM . 'library/coinspay/opencart/admin.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		// 2.0–2.1 take the template with its extension, 2.2 adds it itself.
		$view = version_compare(VERSION, '2.2', '<') ? 'payment/coinspay.tpl' : 'payment/coinspay';
		$this->response->setOutput($this->load->view($view, $data));
	}

	public function check() {
		$admin = $this->admin();
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($admin->check()));
	}

	public function refresh() {
		$admin = $this->admin();
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($admin->refresh()));
	}

	public function install() {
		$admin = $this->admin();
		$admin->install();
	}

	public function uninstall() {
		$admin = $this->admin();
		$admin->uninstall();
	}
}
