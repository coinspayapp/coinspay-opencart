<?php
/**
 * CoinsPay_Store on the OpenCart registry: the same calls exist from 1.5 to 4.x, the
 * differences are in CoinsPay_OpenCartBranch.
 */
class CoinsPay_OpenCartStore implements CoinsPay_Store {

	/** @var bool The plugin is writing order history: its own history event must not react. */
	public static $writing = false;

	/** @var object OpenCart registry */
	private $registry;

	/** @var CoinsPay_OpenCartBranch */
	private $branch;

	/** @var object|null */
	private $logger;

	public function __construct($registry, CoinsPay_OpenCartBranch $branch) {
		$this->registry = $registry;
		$this->branch = $branch;
	}

	public function branch() {
		return $this->branch;
	}

	public function query($sql) {
		$result = $this->registry->get('db')->query($sql);
		return is_object($result) && isset($result->rows) && is_array($result->rows) ? $result->rows : array();
	}

	public function escape($value) {
		return $this->registry->get('db')->escape((string)$value);
	}

	public function prefix() {
		return DB_PREFIX;
	}

	public function setting($key) {
		return $this->registry->get('config')->get($this->branch->prefix . $key);
	}

	public function config($key) {
		return $this->registry->get('config')->get($key);
	}

	public function defaultCurrency() {
		return (string)$this->config('config_currency');
	}

	public function defaultOrderStatus() {
		return (int)$this->config('config_order_status_id');
	}

	public function order($orderId) {
		$p = DB_PREFIX;
		$code = $this->branch->paymentMethodJson() ? 'payment_method' : 'payment_code';
		$rows = $this->query('SELECT order_id, order_status_id, total, currency_code, currency_value, ' . $code . ' AS payment FROM `' . $p . 'order` WHERE order_id = ' . (int)$orderId . ' LIMIT 1');
		if (!$rows) {
			return null;
		}
		$row = $rows[0];
		$payment = (string)$row['payment'];
		if ($this->branch->paymentMethodJson()) {
			$method = json_decode($payment, true);
			$payment = is_array($method) && isset($method['code']) ? (string)$method['code'] : '';
		}
		// 4.0.2+ keeps "coinspay.coinspay": the extension code is the part before the dot.
		$dot = strpos($payment, '.');
		if ($dot !== false) {
			$payment = substr($payment, 0, $dot);
		}

		$items = array();
		foreach ($this->query('SELECT name, quantity FROM `' . $p . 'order_product` WHERE order_id = ' . (int)$orderId . ' ORDER BY order_product_id') as $product) {
			$items[] = array('name' => $product['name'], 'quantity' => $product['quantity']);
		}
		return array(
			'id' => (int)$row['order_id'],
			'status_id' => (int)$row['order_status_id'],
			'total' => (string)$row['total'],
			'currency_code' => (string)$row['currency_code'],
			'currency_value' => (string)$row['currency_value'],
			'payment_code' => $payment,
			'items' => $items,
		);
	}

	public function addHistory($orderId, $statusId, $comment) {
		$load = $this->registry->get('load');
		$load->model('checkout/order');
		$model = $this->registry->get('model_checkout_order');
		$current = $this->order($orderId);

		self::$writing = true;
		try {
			if ($this->branch->branch === '1.5') {
				// 1.5: confirm() gives a new order its first status (and e-mails the customer), update() the rest.
				if ($current && (int)$current['status_id'] === 0) {
					$model->confirm((int)$orderId, (int)$statusId, $comment, false);
					// confirm() keeps the comment only when the customer is notified: it goes in a history entry of its own.
					if ($comment !== '') {
						$model->update((int)$orderId, (int)$statusId, $comment, false);
					}
				} else {
					$model->update((int)$orderId, (int)$statusId, $comment, false);
				}
			} elseif ($this->branch->branch === '4') {
				$model->addHistory((int)$orderId, (int)$statusId, $comment, false);
			} else {
				$model->addOrderHistory((int)$orderId, (int)$statusId, $comment, false);
			}
		} catch (Exception $e) {
			self::$writing = false;
			throw $e;
		}
		self::$writing = false;

		$after = $this->order($orderId);
		return $after ? (int)$after['status_id'] : (int)$statusId;
	}

	/**
	 * Address of a catalog route of the extension. Plain index.php?route= links: they work with
	 * and without SEO URLs, and webhooks must not meet redirects.
	 */
	public function catalogUrl($method, $query = '') {
		$base = $this->catalogBase();
		return $base . 'index.php?route=' . $this->branch->action($method) . ($query !== '' ? '&' . $query : '');
	}

	public function catalogBase() {
		$secure = (bool)$this->config('config_secure');
		if (defined('HTTP_CATALOG')) {
			// The admin: HTTP_SERVER is the admin address there.
			$base = $secure && defined('HTTPS_CATALOG') && HTTPS_CATALOG ? HTTPS_CATALOG : HTTP_CATALOG;
		} else {
			$base = $secure && defined('HTTPS_SERVER') && HTTPS_SERVER ? HTTPS_SERVER : HTTP_SERVER;
		}
		return rtrim($base, '/') . '/';
	}

	public function returnUrl($orderId, $token) {
		$query = 'order_id=' . (int)$orderId . '&token=' . $token;
		if ($this->branch->branch === '4') {
			// OpenCart 4 sends its session cookie SameSite=Strict: a page of the extension makes the
			// last step back a same-site one, so the buyer keeps the cart and the session.
			// Only the order and its token travel in the fragment: the page builds the link itself and goes nowhere else.
			return $this->catalogBase() . 'extension/coinspay/catalog/view/javascript/coinspay-return.html#' . (int)$orderId . '.' . $token;
		}
		return $this->catalogUrl('back', $query);
	}

	public function webhookUrl() {
		return $this->catalogUrl('callback');
	}

	public function cronUrl($key) {
		return $this->catalogUrl('cron', 'key=' . $key);
	}

	public function siteUrl() {
		return $this->catalogBase();
	}

	public function adminLanguage() {
		// config_admin_language up to 3.0, config_language_admin in 4.
		$code = (string)$this->config('config_admin_language');
		return self::lang($code !== '' ? $code : (string)$this->config('config_language_admin'));
	}

	public function buyerLanguage() {
		return self::lang((string)$this->config('config_language'));
	}

	public static function lang($code) {
		return strpos(strtolower($code), 'ru') === 0 ? 'ru' : 'en';
	}

	public function userAgent() {
		return 'CoinsPay-OpenCart/' . CoinsPay_Plugin::VERSION . '; OpenCart/' . $this->branch->version . '; PHP/' . PHP_VERSION;
	}

	public function log($message, $error) {
		if (!$error && !$this->setting('debug')) {
			return;
		}
		if ($this->logger === null) {
			$class = class_exists('Opencart\System\Library\Log') ? 'Opencart\System\Library\Log' : 'Log';
			$this->logger = new $class('coinspay.log');
		}
		$this->logger->write(($error ? 'ERROR ' : '') . $message);
	}
}
