<?php
/**
 * What differs between OpenCart branches, by the VERSION constant of the store.
 */
class CoinsPay_OpenCartBranch {

	/** @var string 1.5, 2.0 (2.0–2.2), 2.3, 3.0, 4 */
	public $branch;

	/** @var string Controller route of the extension, without the method. */
	public $route;

	/** @var string Separator of the method in a route: "/" up to 3.0, "|" in 4.0.0–4.0.1, "." from 4.0.2. */
	public $separator;

	/** @var string Setting group code. */
	public $code;

	/** @var string Prefix of setting keys. */
	public $prefix;

	/** @var string Name of the admin session token parameter. */
	public $token;

	/** @var string */
	public $version;

	public function __construct($version) {
		$this->version = (string)$version;
		if (version_compare($this->version, '2.0', '<')) {
			$this->branch = '1.5';
		} elseif (version_compare($this->version, '2.3', '<')) {
			$this->branch = '2.0';
		} elseif (version_compare($this->version, '3.0', '<')) {
			$this->branch = '2.3';
		} elseif (version_compare($this->version, '4.0', '<')) {
			$this->branch = '3.0';
		} else {
			$this->branch = '4';
		}

		if ($this->branch === '1.5' || $this->branch === '2.0') {
			$this->route = 'payment/coinspay';
		} elseif ($this->branch === '4') {
			$this->route = 'extension/coinspay/payment/coinspay';
		} else {
			$this->route = 'extension/payment/coinspay';
		}

		if ($this->branch !== '4') {
			$this->separator = '/';
		} elseif (version_compare($this->version, '4.0.2.0', '<')) {
			$this->separator = '|';
		} else {
			$this->separator = '.';
		}

		$this->code = version_compare($this->version, '3.0', '<') ? 'coinspay' : 'payment_coinspay';
		$this->prefix = $this->code . '_';
		$this->token = version_compare($this->version, '3.0', '<') ? 'token' : 'user_token';
	}

	/** Route of a method of the extension's controller. */
	public function action($method) {
		return $this->route . $this->separator . $method;
	}

	/** OpenCart 4.0.2+ stores the payment method as JSON with the code "coinspay.coinspay". */
	public function paymentMethodJson() {
		return $this->branch === '4' && version_compare($this->version, '4.0.2.0', '>=');
	}

	/** OpenCart 4.0.2.2–4.1.0.3 show the customer every order history comment, notify or not. */
	public function showsAllComments() {
		return $this->branch === '4' && version_compare($this->version, '4.0.2.2', '>=') && version_compare($this->version, '4.1.0.4', '<');
	}
}
