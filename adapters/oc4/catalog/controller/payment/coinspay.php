<?php
namespace Opencart\Catalog\Controller\Extension\Coinspay\Payment;

/**
 * CoinsPay for OpenCart 4: the confirm step, the buyer's return, the webhook, polling and the
 * test buyer link. The work is done by the shared core in extension/coinspay/system/library/coinspay.
 */
class Coinspay extends \Opencart\System\Engine\Controller {

	private function catalog(): \CoinsPay_OpenCartCatalog {
		require_once DIR_EXTENSION . 'coinspay/system/library/coinspay/loader.php';
		return new \CoinsPay_OpenCartCatalog($this->registry);
	}

	public function index(): string {
		$catalog = $this->catalog();
		return $this->load->view('extension/coinspay/payment/coinspay', $catalog->confirmView());
	}

	public function confirm(): void {
		$catalog = $this->catalog();
		$this->json($catalog->confirm());
	}

	public function back(): void {
		$catalog = $this->catalog();
		$answer = $catalog->back();
		if (!empty($answer['error'])) {
			$this->session->data['error'] = $answer['error'];
		}
		$this->response->redirect($answer['redirect']);
	}

	public function callback(): void {
		$catalog = $this->catalog();
		$this->plain($catalog->callback());
	}

	public function cron(): void {
		$catalog = $this->catalog();
		$this->plain($catalog->cron());
	}

	public function refresh(): void {
		$catalog = $this->catalog();
		$this->json($catalog->refresh());
	}

	public function test(): void {
		$catalog = $this->catalog();
		$answer = $catalog->test();
		$this->load->language('checkout/success');
		$data = [
			'breadcrumbs' => [],
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
		];
		$this->document->setTitle('CoinsPay');
		$this->response->setOutput($this->load->view('common/success', $data));
	}

	/** Event catalog/model/checkout/order/addHistory/after. Returns nothing: a value would replace the model's output. */
	public function eventHistory(string &$route, array &$args, mixed &$output): void {
		$catalog = $this->catalog();
		$catalog->onHistory(isset($args[0]) ? (int)$args[0] : 0);
	}

	/** Event catalog/controller/common/footer/after: the lazy poll. */
	public function eventPoll(string &$route, array &$args, mixed &$output): void {
		if (!$this->config->get('payment_coinspay_status')) {
			return;
		}
		$catalog = $this->catalog();
		$catalog->onPageLoad();
	}

	/** Event catalog/model/account/order/getHistories/after: OpenCart 4.0.2.2–4.1.0.3 show every comment to the customer. */
	public function eventHistories(string &$route, array &$args, mixed &$output): void {
		$catalog = $this->catalog();
		$catalog->filterHistories($output);
	}

	private function json(array $data): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($data));
	}

	private function plain(array $answer): void {
		$reasons = [200 => 'OK', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 500 => 'Internal Server Error', 503 => 'Service Unavailable'];
		$code = (int)$answer[0];
		$this->response->addHeader('HTTP/1.1 ' . $code . ' ' . ($reasons[$code] ?? 'OK'));
		$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
		$this->response->setOutput((string)$answer[1]);
	}
}
