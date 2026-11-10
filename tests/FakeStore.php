<?php
/**
 * A store for the unit tests: settings in an array, order statuses in a list, SQL recorded.
 */
class FakeStore implements CoinsPay_Store {

	public $settings = array();
	public $statuses = array(1, 2, 5, 7, 8, 16);
	public $queries = array();
	public $logs = array();

	public function query($sql) {
		$this->queries[] = $sql;
		if (preg_match('/FROM `oc_order_status` WHERE order_status_id = (\d+)/', $sql, $match)) {
			return in_array((int)$match[1], $this->statuses, true) ? array(array('order_status_id' => $match[1])) : array();
		}
		return array();
	}

	public function escape($value) {
		return addslashes($value);
	}

	public function prefix() {
		return 'oc_';
	}

	public function setting($key) {
		return isset($this->settings[$key]) ? $this->settings[$key] : null;
	}

	public function defaultCurrency() {
		return 'USD';
	}

	public function defaultOrderStatus() {
		return 1;
	}

	public function order($orderId) {
		return null;
	}

	public function addHistory($orderId, $statusId, $comment) {
		return $statusId;
	}

	public function returnUrl($orderId, $token) {
		return 'https://shop.example/index.php?route=extension/payment/coinspay/back&order_id=' . $orderId . '&token=' . $token;
	}

	public function webhookUrl() {
		return 'https://shop.example/index.php?route=extension/payment/coinspay/callback';
	}

	public function cronUrl($key) {
		return 'https://shop.example/index.php?route=extension/payment/coinspay/cron&key=' . $key;
	}

	public function siteUrl() {
		return 'https://shop.example/';
	}

	public function adminLanguage() {
		return 'en';
	}

	public function buyerLanguage() {
		return 'en';
	}

	public function userAgent() {
		return 'CoinsPay-OpenCart/test';
	}

	public function log($message, $error) {
		$this->logs[] = $message;
	}
}
