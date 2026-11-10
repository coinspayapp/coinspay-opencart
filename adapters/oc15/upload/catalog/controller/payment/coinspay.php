<?php
/**
 * CoinsPay for OpenCart 1.5: the confirm step, the buyer's return, the webhook, polling and
 * the test buyer link. The work is done by the shared core in system/library/coinspay.
 * OpenCart 1.5 has no events: the poller cancels invoices of orders the store cancelled.
 */
class ControllerPaymentCoinspay extends Controller {

	private function catalog() {
		require_once DIR_SYSTEM . 'library/coinspay/loader.php';
		return new CoinsPay_OpenCartCatalog($this->registry);
	}

	private function template($template) {
		$theme = $this->config->get('config_template');
		if (file_exists(DIR_TEMPLATE . $theme . '/template/' . $template . '.tpl')) {
			return $theme . '/template/' . $template . '.tpl';
		}
		return 'default/template/' . $template . '.tpl';
	}

	protected function index() {
		$catalog = $this->catalog();
		$this->data = array_merge($this->data, $catalog->confirmView());
		$this->template = $this->template('payment/coinspay');
		$this->render();
	}

	public function confirm() {
		$catalog = $this->catalog();
		$this->json($catalog->confirm());
	}

	public function back() {
		$catalog = $this->catalog();
		$answer = $catalog->back();
		if (!empty($answer['error'])) {
			$this->session->data['error'] = $answer['error'];
		}
		$this->redirect($answer['redirect']);
	}

	public function callback() {
		$catalog = $this->catalog();
		$this->plain($catalog->callback());
	}

	public function cron() {
		$catalog = $this->catalog();
		$this->plain($catalog->cron());
	}

	public function refresh() {
		$catalog = $this->catalog();
		$this->json($catalog->refresh());
	}

	public function test() {
		$catalog = $this->catalog();
		$answer = $catalog->test();
		$this->language->load('checkout/success');
		$this->document->setTitle('CoinsPay');
		$this->data['breadcrumbs'] = array();
		$this->data['heading_title'] = 'CoinsPay';
		$this->data['text_message'] = '<p>' . htmlspecialchars($answer['message'], ENT_QUOTES, 'UTF-8') . '</p>';
		$this->data['button_continue'] = $this->language->get('button_continue');
		$this->data['continue'] = $answer['redirect'];
		$this->template = $this->template('common/success');
		$this->children = array('common/column_left', 'common/column_right', 'common/content_top', 'common/content_bottom', 'common/footer', 'common/header');
		$this->response->setOutput($this->render());
	}

	private function json($data) {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}

	private function plain($answer) {
		$reasons = array(200 => 'OK', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 500 => 'Internal Server Error', 503 => 'Service Unavailable');
		$code = (int)$answer[0];
		$this->response->addHeader('HTTP/1.1 ' . $code . ' ' . (isset($reasons[$code]) ? $reasons[$code] : 'OK'));
		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		$this->response->setOutput((string)$answer[1]);
	}
}
