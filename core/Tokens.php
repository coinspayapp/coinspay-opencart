<?php
/**
 * Signed values of the plugin's own links, keyed by the secret made on install.
 */
class CoinsPay_Tokens {

	/** A test buyer link works for a day. */
	const TEST_BUYER_TTL = 86400;

	/** @var string */
	private $secret;

	public function __construct($secret) {
		$this->secret = (string)$secret;
	}

	/** Token of the return link of an order: nobody else can make the plugin show or sync that order. */
	public function returnToken($orderId) {
		return $this->sign('return|' . (int)$orderId);
	}

	public function checkReturn($orderId, $token) {
		return $this->secret !== '' && CoinsPay_Util::equals($this->returnToken($orderId), (string)$token);
	}

	/** Key of the polling route. */
	public function cronKey() {
		return $this->sign('cron');
	}

	public function checkCron($key) {
		return $this->secret !== '' && CoinsPay_Util::equals($this->cronKey(), (string)$key);
	}

	/**
	 * Value of a test buyer link: "<expiry>.<signature>".
	 */
	public function testBuyer($now = null) {
		$until = ($now === null ? time() : (int)$now) + self::TEST_BUYER_TTL;
		return $until . '.' . $this->sign('test|' . $until);
	}

	public function checkTestBuyer($value, $now = null) {
		$parts = explode('.', (string)$value, 2);
		if ($this->secret === '' || count($parts) !== 2 || !ctype_digit($parts[0])) {
			return false;
		}
		if ((int)$parts[0] < ($now === null ? time() : (int)$now)) {
			return false;
		}
		return CoinsPay_Util::equals($this->sign('test|' . $parts[0]), $parts[1]);
	}

	private function sign($data) {
		return substr(hash_hmac('sha256', $data, $this->secret), 0, 32);
	}
}
