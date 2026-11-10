<?php
/**
 * Storefront routes of the extension, the same for every branch: the payment method at
 * checkout, the confirm button, the buyer's return, the webhook, polling and the test buyer link.
 * Each branch's catalog controller passes the call here and renders or outputs the answer.
 */
class CoinsPay_OpenCartCatalog {

	/** Session key of the test buyer flag. */
	const TEST_BUYER = 'coinspay_test_buyer';

	/** @var object OpenCart registry */
	private $registry;

	/** @var CoinsPay_OpenCartBranch */
	public $branch;

	/** @var CoinsPay_OpenCartStore */
	public $store;

	/** @var CoinsPay_Plugin */
	public $plugin;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->branch = new CoinsPay_OpenCartBranch(VERSION);
		$this->store = new CoinsPay_OpenCartStore($registry, $this->branch);
		$this->plugin = new CoinsPay_Plugin($this->store);
	}

	private function get($name) {
		return $this->registry->get($name);
	}

	private function session() {
		return $this->get('session');
	}

	/** Texts of the storefront: the catalog_ keys of the core dictionary in the buyer's language. */
	public function texts() {
		$all = include dirname(dirname(__FILE__)) . '/lang/' . $this->store->buyerLanguage() . '.php';
		$texts = array();
		foreach ($all as $key => $text) {
			if (strpos($key, 'catalog_') === 0) {
				$texts[substr($key, 8)] = $text;
			}
		}
		return $texts;
	}

	public function isTestBuyer() {
		// A variable for empty(): PHP 5.3–5.4 take nothing else.
		$data = $this->session()->data;
		return !empty($data[self::TEST_BUYER]);
	}

	/**
	 * The payment method for the checkout, or null when it is not offered.
	 *
	 * @param array      $address Payment address (zone_id, country_id); may be empty in OpenCart 4.
	 * @param float|null $total   Cart total in the default currency; null when the branch does not pass it.
	 * @return array|null {code, title, terms, sort_order}
	 */
	public function method($address, $total) {
		$settings = $this->plugin->settings;
		if (!$settings->flag('status')) {
			return null;
		}
		if ($total === null) {
			$total = $this->get('cart')->getTotal();
		}
		$minimum = (float)$settings->get('total', 0);
		if ($minimum > 0 && (float)$total < $minimum) {
			return null;
		}
		$zone = (int)$settings->get('geo_zone_id', 0);
		if ($zone > 0) {
			$rows = $this->store->query('SELECT zone_to_geo_zone_id FROM `' . DB_PREFIX . 'zone_to_geo_zone` WHERE geo_zone_id = ' . $zone
				. ' AND country_id = ' . (int)(isset($address['country_id']) ? $address['country_id'] : 0)
				. ' AND (zone_id = ' . (int)(isset($address['zone_id']) ? $address['zone_id'] : 0) . ' OR zone_id = 0) LIMIT 1');
			if (!$rows) {
				return null;
			}
		}
		$currency = isset($this->session()->data['currency']) ? (string)$this->session()->data['currency'] : $this->store->defaultCurrency();
		if (!$this->plugin->available($currency, $this->isTestBuyer())) {
			return null;
		}
		$texts = $this->texts();
		return array(
			'code' => 'coinspay',
			'title' => $texts['title'],
			'terms' => '',
			'sort_order' => (int)$settings->get('sort_order', 0),
		);
	}

	/** Data of the confirm step view. */
	public function confirmView() {
		$texts = $this->texts();
		return array(
			'texts' => $texts,
			'button_confirm' => $texts['button'],
			'text_loading' => $texts['loading'],
			// Relative: the AJAX call goes to the scheme and host of the checkout page, with its session cookie.
			'confirm_url' => 'index.php?route=' . $this->branch->action('confirm'),
			'test_mode' => $this->plugin->settings->isTestMode(),
		);
	}

	/**
	 * The confirm button: the invoice of the order and the payment page to go to.
	 *
	 * @return array JSON answer: {redirect} or {error}.
	 */
	public function confirm() {
		$buyer = $this->plugin->buyerText();
		$data = $this->session()->data;
		$orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;
		$order = $orderId ? $this->store->order($orderId) : null;
		if (!$order || $order['payment_code'] !== 'coinspay' || $this->sessionMethod() !== 'coinspay') {
			return array('error' => $buyer->t('error_order'));
		}
		try {
			$invoice = $this->plugin->payment->invoiceFor($orderId, $this->isTestBuyer());
		} catch (CoinsPay_ApiError $e) {
			$this->store->log(sprintf('order %d: invoice not created: %s', $orderId, $e->summary()), true);
			return array('error' => CoinsPay_Payment::errorMessage($e, $buyer));
		} catch (Exception $e) {
			$this->store->log(sprintf('order %d: invoice not created: %s', $orderId, $e->getMessage()), true);
			return array('error' => $buyer->t('error_failed'));
		}
		return array('redirect' => $this->plugin->payUrl($invoice));
	}

	/** Code of the payment method chosen in the session: an array in most branches, a string in 4.0.0–4.0.1. */
	private function sessionMethod() {
		$data = $this->session()->data;
		if (!isset($data['payment_method'])) {
			return '';
		}
		$method = $data['payment_method'];
		$code = is_array($method) ? (isset($method['code']) ? (string)$method['code'] : '') : (string)$method;
		$dot = strpos($code, '.');
		return $dot === false ? $code : substr($code, 0, $dot);
	}

	/**
	 * The buyer comes back from the payment page.
	 *
	 * @return array {redirect, error?}: where to send the buyer, and a message for the cart page.
	 */
	public function back() {
		$request = $this->get('request')->get;
		$orderId = isset($request['order_id']) ? (int)$request['order_id'] : 0;
		$token = isset($request['token']) ? (string)$request['token'] : '';
		$home = $this->store->catalogBase();
		if (!$orderId || !$this->plugin->tokens->checkReturn($orderId, $token)) {
			return array('redirect' => $home);
		}

		$row = $this->plugin->repo->current($orderId);
		$invoice = null;
		if ($row) {
			try {
				$invoice = $this->plugin->settings->client()->invoice($row['invoice_id']);
				$this->plugin->sync->apply($invoice);
			} catch (Exception $e) {
				$this->store->log(sprintf('return to order %d: %s', $orderId, $e->getMessage()), true);
				$invoice = json_decode((string)$row['snapshot'], true);
			}
		}

		// Paid, or money on its way: the order is placed; the success page clears the cart.
		$money = is_array($invoice) && (CoinsPay_Util::value($invoice, 'status') === 'paid'
			|| CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_paid'))
			|| CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_pending')));
		if ($money) {
			return array('redirect' => $home . 'index.php?route=checkout/success');
		}
		$texts = $this->texts();
		return array('redirect' => $home . 'index.php?route=checkout/cart', 'error' => $texts['back_failed']);
	}

	/**
	 * The webhook.
	 *
	 * @return array {0: HTTP status, 1: text}
	 */
	public function callback() {
		$server = $this->get('request')->server;
		$signature = isset($server['HTTP_X_CPAY_SIGNATURE']) ? (string)$server['HTTP_X_CPAY_SIGNATURE'] : '';
		// The signature is over the raw bytes; php://input is the only place they are.
		$body = (string)file_get_contents('php://input');
		return $this->plugin->webhook->handle($body, $signature);
	}

	/**
	 * Polling, by system cron or by the lazy poll's own request.
	 *
	 * @return array {0: HTTP status, 1: text}
	 */
	public function cron() {
		$key = isset($this->get('request')->get['key']) ? (string)$this->get('request')->get['key'] : '';
		if (!$this->plugin->tokens->checkCron($key)) {
			return array(403, 'bad key');
		}
		// The lazy poll's request hangs up at once: the batch goes on without it.
		ignore_user_abort(true);
		if (function_exists('set_time_limit')) {
			@set_time_limit(120);
		}
		return array(200, 'checked ' . $this->plugin->poller->run());
	}

	/**
	 * "Refresh" from the invoices tab of the settings screen.
	 *
	 * @return array JSON answer.
	 */
	public function refresh() {
		$t = $this->plugin->text;
		$request = $this->get('request')->get;
		if (!$this->plugin->tokens->checkCron(isset($request['key']) ? (string)$request['key'] : '')) {
			return array('ok' => false, 'message' => 'bad key');
		}
		$invoiceId = isset($request['invoice_id']) ? (string)$request['invoice_id'] : '';
		if (!$this->plugin->repo->find($invoiceId)) {
			return array('ok' => false, 'message' => $t->t('admin_refresh_failed'));
		}
		try {
			$invoice = $this->plugin->settings->client()->invoice($invoiceId);
			$result = $this->plugin->sync->apply($invoice);
		} catch (Exception $e) {
			return array('ok' => false, 'message' => $t->t('admin_refresh_failed') . ' ' . $e->getMessage());
		}
		return array('ok' => true, 'message' => $t->t('admin_refreshed', $t->t('admin_invoice_' . preg_replace('/[^a-z]/', '', CoinsPay_Util::value($invoice, 'status')))), 'status' => CoinsPay_Util::value($invoice, 'status'), 'result' => $result);
	}

	/**
	 * The test buyer link from the settings screen.
	 *
	 * @return array {redirect, message}
	 */
	public function test() {
		$value = isset($this->get('request')->get['t']) ? (string)$this->get('request')->get['t'] : '';
		$texts = $this->texts();
		if (!$this->plugin->tokens->checkTestBuyer($value)) {
			return array('redirect' => $this->store->catalogBase(), 'message' => $texts['test_bad']);
		}
		$this->session()->data[self::TEST_BUYER] = 1;
		return array('redirect' => $this->store->catalogBase(), 'message' => $texts['test_on']);
	}

	/**
	 * After any order history entry: when the store put an order into a cancel status, its open invoice is cancelled.
	 */
	public function onHistory($orderId) {
		if (CoinsPay_OpenCartStore::$writing || !$orderId) {
			return;
		}
		try {
			$this->plugin->poller->cancelForOrder((int)$orderId);
		} catch (Exception $e) {
			$this->store->log('order history event: ' . $e->getMessage(), true);
		}
	}

	/**
	 * A storefront page was built: at most once in five minutes one page fires the polling request.
	 */
	public function onPageLoad() {
		// One cheap query on most pages; the turn itself is taken in the plugin's table.
		$rows = $this->store->query('SELECT updated FROM `' . DB_PREFIX . 'coinspay_kv` WHERE k = \'lazy_poll\' LIMIT 1');
		if ($rows && (int)$rows[0]['updated'] > time() - CoinsPay_Poller::LAZY_EVERY) {
			return;
		}
		if ($this->plugin->poller->lazyTurn()) {
			$plugin = $this->plugin;
			register_shutdown_function(array($plugin, 'loopback'));
		}
	}

	/**
	 * OpenCart 4.0.2.2–4.1.0.3 show the customer every history comment: the plugin's own
	 * (they start with "CoinsPay") are blanked when the customer was not notified.
	 */
	public function filterHistories(&$histories) {
		if (!$this->branch->showsAllComments() || !is_array($histories)) {
			return;
		}
		foreach ($histories as $i => $history) {
			if (isset($history['comment']) && strpos((string)$history['comment'], 'CoinsPay') === 0 && empty($history['notify'])) {
				$histories[$i]['comment'] = '';
			}
		}
	}
}
