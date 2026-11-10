<?php
/**
 * CoinsPay for OpenCart 3.0: the settings screen. The work is done by the shared core in
 * system/library/coinspay; this controller only renders it.
 */
class ControllerExtensionPaymentCoinspay extends Controller {

	private function admin() {
		require_once DIR_SYSTEM . 'library/coinspay/loader.php';
		return new CoinsPay_OpenCartAdmin($this->registry);
	}

	public function index() {
		$this->load->language('extension/payment/coinspay');
		$admin = $this->admin();
		$data = $admin->data();
		$t = $data['texts'];
		$this->document->setTitle($t['heading']);
		$token = 'user_token=' . $this->session->data['user_token'];

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $admin->save()) {
			$this->session->data['success'] = $t['success'];
			$this->response->redirect($this->url->link('extension/payment/coinspay', $token, true));
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
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token, true)),
			array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=payment', true)),
			array('text' => $t['heading'], 'href' => $this->url->link('extension/payment/coinspay', $token, true)),
		);
		$data['action'] = $this->url->link('extension/payment/coinspay', $token, true);
		$data['cancel'] = $this->url->link('marketplace/extension', $token . '&type=payment', true);
		$data['check_url'] = $this->url->link('extension/payment/coinspay/check', $token, true);
		$data['refresh_url'] = $this->url->link('extension/payment/coinspay/refresh', $token, true);
		$data['order_url'] = $this->url->link('sale/order/info', $token, true);
		$data['script'] = file_get_contents(DIR_SYSTEM . 'library/coinspay/opencart/admin.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/payment/coinspay', $data));
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
