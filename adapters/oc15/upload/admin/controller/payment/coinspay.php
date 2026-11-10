<?php
/**
 * CoinsPay for OpenCart 1.5: the settings screen. The work is done by the shared core in
 * system/library/coinspay; this controller only renders it.
 */
class ControllerPaymentCoinspay extends Controller {

	private function admin() {
		require_once DIR_SYSTEM . 'library/coinspay/loader.php';
		return new CoinsPay_OpenCartAdmin($this->registry);
	}

	public function index() {
		$this->language->load('payment/coinspay');
		$admin = $this->admin();
		$data = $admin->data();
		$t = $data['texts'];
		$this->document->setTitle($t['heading']);
		$token = 'token=' . $this->session->data['token'];

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $admin->save()) {
			$this->session->data['success'] = $t['success'];
			$this->redirect($this->url->link('payment/coinspay', $token, 'SSL'));
		}
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$data = $admin->data();
		}

		$this->data = array_merge($this->data, $data);
		$this->data['t'] = $t;
		$this->data['success'] = '';
		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}
		$this->data['breadcrumbs'] = array(
			array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home', $token, 'SSL'), 'separator' => false),
			array('text' => $this->language->get('text_payment'), 'href' => $this->url->link('extension/payment', $token, 'SSL'), 'separator' => ' :: '),
			array('text' => $t['heading'], 'href' => $this->url->link('payment/coinspay', $token, 'SSL'), 'separator' => ' :: '),
		);
		$this->data['action'] = $this->url->link('payment/coinspay', $token, 'SSL');
		$this->data['cancel'] = $this->url->link('extension/payment', $token, 'SSL');
		$this->data['check_url'] = $this->url->link('payment/coinspay/check', $token, 'SSL');
		$this->data['refresh_url'] = $this->url->link('payment/coinspay/refresh', $token, 'SSL');
		$this->data['order_url'] = $this->url->link('sale/order/info', $token, 'SSL');
		$this->data['script'] = file_get_contents(DIR_SYSTEM . 'library/coinspay/opencart/admin.js');

		$this->template = 'payment/coinspay.tpl';
		$this->children = array('common/header', 'common/footer');
		$this->response->setOutput($this->render());
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
