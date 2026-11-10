<?php
namespace Opencart\Admin\Controller\Extension\Coinspay\Payment;

/**
 * CoinsPay for OpenCart 4: the settings screen. The work is done by the shared core in
 * extension/coinspay/system/library/coinspay; this controller only renders it.
 */
class Coinspay extends \Opencart\System\Engine\Controller {

	private function admin(): \CoinsPay_OpenCartAdmin {
		require_once DIR_EXTENSION . 'coinspay/system/library/coinspay/loader.php';
		return new \CoinsPay_OpenCartAdmin($this->registry);
	}

	public function index(): void {
		$this->load->language('extension/coinspay/payment/coinspay');
		$admin = $this->admin();
		$data = $admin->data();
		$t = $data['texts'];
		$this->document->setTitle($t['heading']);
		$token = 'user_token=' . $this->session->data['user_token'];
		$route = 'extension/coinspay/payment/coinspay';

		$data['t'] = $t;
		$data['success'] = '';
		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', $token)],
			['text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', $token . '&type=payment')],
			['text' => $t['heading'], 'href' => $this->url->link($route, $token)],
		];
		$data['save'] = $this->url->link($admin->branch->action('save'), $token);
		$data['back'] = $this->url->link('marketplace/extension', $token . '&type=payment');
		$data['check_url'] = $this->url->link($admin->branch->action('check'), $token);
		$data['refresh_url'] = $this->url->link($admin->branch->action('refresh'), $token);
		$data['order_url'] = $this->url->link('sale/order.info', $token);
		$data['script'] = file_get_contents(DIR_EXTENSION . 'coinspay/system/library/coinspay/opencart/admin.js');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view($route, $data));
	}

	/** The form is saved over AJAX, as everywhere in the OpenCart 4 admin. */
	public function save(): void {
		$admin = $this->admin();
		$json = [];
		if ($admin->save()) {
			$json['success'] = $admin->plugin->text->t('admin_success');
		} else {
			$json['error'] = $admin->errors();
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function check(): void {
		$admin = $this->admin();
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($admin->check()));
	}

	public function refresh(): void {
		$admin = $this->admin();
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($admin->refresh()));
	}

	public function install(): void {
		$admin = $this->admin();
		$admin->install();
	}

	public function uninstall(): void {
		$admin = $this->admin();
		$admin->uninstall();
	}
}
