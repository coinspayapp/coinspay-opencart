<?php
/**
 * CoinsPay for OpenCart 3.0: the confirm step, the buyer's return, the webhook, polling and
 * the test buyer link. The work is done by the shared core in system/library/coinspay.
 */
class ControllerExtensionPaymentCoinspay extends Controller {

	private function catalog() {
		require_once DIR_SYSTEM . 'library/coinspay/loader.php';
		return new CoinsPay_OpenCartCatalog($this->registry);
	}

	public function index() {
		$catalog = $this->catalog();
		return $this->load->view('extension/payment/coinspay', $catalog->confirmView());
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
		$this->response->redirect($answer['redirect']);
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
		$this->load->language('checkout/success');
		$data = array(
			'breadcrumbs' => array(),
			'heading_title' => 'CoinsPay',
			'text_message' => '<p>' . htmlspecialchars($answer['message'], ENT_QUOTES, 'UTF-8') . '</p>',
			'button_continue' => $this->language->get('button_continue'),
			'continue' => $answer['redirect'],
			'column_left' => $this->load->controller('common/column_left'),
			'column_right' => $this->load->controller('common/column_right'),
			'content_top' => $this->load->controller('common/content_top'),
			'content_bottom' => $this->load->controller('common/content_bottom'),
			'footer' => $this->load->controller('common/footer'),
			'header' => $this->load->controller('common/header'),
		);
		$this->document->setTitle('CoinsPay');
		$this->response->setOutput($this->load->view('common/success', $data));
	}

	/** Event catalog/model/checkout/order/addOrderHistory/after. Returns nothing: a value would replace the model's output. */
	public function eventHistory(&$route, &$args, &$output) {
		$catalog = $this->catalog();
		$catalog->onHistory(isset($args[0]) ? (int)$args[0] : 0);
	}

	/** Event catalog/controller/common/footer/after: the lazy poll. */
	public function eventPoll(&$route, &$args, &$output) {
		if (!$this->config->get('payment_coinspay_status')) {
			return;
		}
		$catalog = $this->catalog();
		$catalog->onPageLoad();
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
