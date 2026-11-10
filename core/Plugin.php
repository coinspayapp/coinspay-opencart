<?php
/**
 * The core assembled for one OpenCart adapter.
 */
class CoinsPay_Plugin {

	const VERSION = '0.1.0';

	/** @var CoinsPay_Store */
	public $store;

	/** @var CoinsPay_Settings */
	public $settings;

	/** @var CoinsPay_Repo */
	public $repo;

	/** @var CoinsPay_Text Language of order comments and the admin. */
	public $text;

	/** @var CoinsPay_Currencies */
	public $currencies;

	/** @var CoinsPay_Tokens */
	public $tokens;

	/** @var CoinsPay_Sync */
	public $sync;

	/** @var CoinsPay_Payment */
	public $payment;

	/** @var CoinsPay_Poller */
	public $poller;

	/** @var CoinsPay_Webhook */
	public $webhook;

	public function __construct(CoinsPay_Store $store) {
		$this->store = $store;
		$this->settings = new CoinsPay_Settings($store);
		$this->repo = new CoinsPay_Repo($store);
		$this->text = new CoinsPay_Text($store->adminLanguage());
		$this->currencies = new CoinsPay_Currencies($this->settings, $this->repo, $store);
		$this->tokens = new CoinsPay_Tokens($this->settings->secret());
		$this->sync = new CoinsPay_Sync($store, $this->settings, $this->repo, $this->currencies, $this->text);
		$this->payment = new CoinsPay_Payment($store, $this->settings, $this->repo, $this->currencies, $this->sync, $this->tokens, $this->text);
		$this->poller = new CoinsPay_Poller($store, $this->settings, $this->repo, $this->sync);
		$this->webhook = new CoinsPay_Webhook($store, $this->settings, $this->repo, $this->sync);
	}

	/** Texts in the buyer's language. */
	public function buyerText() {
		return new CoinsPay_Text($this->store->buyerLanguage());
	}

	/** Payment page of an invoice in the buyer's language. */
	public function payUrl($invoice) {
		$url = CoinsPay_Util::value($invoice, 'pay_url');
		return $url . (strpos($url, '?') === false ? '?' : '&') . 'lang=' . $this->store->buyerLanguage();
	}

	/**
	 * Whether the payment method is offered at checkout.
	 *
	 * @param string $currency  Currency of the session.
	 * @param bool   $testBuyer The session opened the test buyer link.
	 * @return bool
	 */
	public function available($currency, $testBuyer) {
		if (!$this->settings->configured() || $this->settings->secret() === '') {
			return false;
		}
		if ($this->settings->isTestMode() && $this->settings->flag('test_buyer_only') && !$testBuyer) {
			return false;
		}
		// Unknown (CoinsPay not answering) counts as accepted: the confirm step says so if it is not.
		$session = $this->currencies->supported($currency);
		return $session !== false || $this->currencies->supported($this->store->defaultCurrency()) !== false;
	}

	/**
	 * "Check connection" on the settings screen: the shop of the key, whether the store currency
	 * is accepted and whether the key has more access than invoices.
	 *
	 * @param string $apiUrl Entered, saved or not.
	 * @param string $apiKey
	 * @return array {ok: bool, lines: [text]}
	 */
	public function checkConnection($apiUrl, $apiKey) {
		$t = $this->text;
		if (trim($apiKey) === '') {
			return array('ok' => false, 'lines' => array($t->t('check_enter')));
		}
		$client = new CoinsPay_ApiClient($apiUrl, $apiKey, $this->store->userAgent());
		try {
			$answer = $client->shop();
		} catch (CoinsPay_ApiError $e) {
			return array('ok' => false, 'lines' => array(self::connectionError($e, $t)));
		}
		$lines = array($t->t('check_connected', CoinsPay_Util::value($answer['shop'], 'name'), $t->t(strpos(trim($apiKey), 'cpk_test_') === 0 ? 'check_test_mode' : 'check_live_mode')));
		// A full-access key can withdraw the shop's money, and the settings screen is open to every admin user with access.
		$fullAccess = isset($answer['key']['access']) && $answer['key']['access'] === 'full';
		if ($fullAccess) {
			$lines[] = $t->t('check_full_access');
		}
		try {
			$data = $client->currencies();
			$codes = array();
			foreach (isset($data['fiat']) && is_array($data['fiat']) ? $data['fiat'] : array() as $currency) {
				if (isset($currency['code'])) {
					$codes[] = strtoupper((string)$currency['code']);
				}
			}
			$default = strtoupper((string)$this->store->defaultCurrency());
			$lines[] = $t->t(in_array($default, $codes, true) ? 'check_currency_ok' : 'check_currency_no', $default);
		} catch (CoinsPay_ApiError $e) {
			$lines[] = self::connectionError($e, $t);
		}
		$this->currencies->flush();
		return array('ok' => !$fullAccess, 'lines' => $lines);
	}

	private static function connectionError(CoinsPay_ApiError $e, CoinsPay_Text $t) {
		switch ($e->errorCode()) {
			case 'UNAUTHORIZED':
				return $t->t('check_bad_key');
			case 'IP_NOT_ALLOWED':
				$details = $e->details();
				return $t->t('check_ip', isset($details['ip']) ? (string)$details['ip'] : '?');
			case 'SHOP_DISABLED':
				return $t->t('check_blocked');
			case 'NETWORK':
				return $t->t('check_network', $e->getMessage());
		}
		return $t->t('check_http', $e->status(), $e->errorCode());
	}

	/**
	 * Fires a request to the polling route without waiting for it: the page of the buyer who
	 * happened to get the turn does not wait for CoinsPay.
	 */
	public function loopback() {
		if (!function_exists('curl_init')) {
			return;
		}
		$curl = curl_init($this->store->cronUrl($this->tokens->cronKey()));
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT_MS => 800,
			CURLOPT_CONNECTTIMEOUT_MS => 800,
			CURLOPT_NOSIGNAL => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_USERAGENT => $this->store->userAgent(),
		));
		curl_exec($curl);
		if (PHP_VERSION_ID < 80000) {
			curl_close($curl);
		}
	}
}
