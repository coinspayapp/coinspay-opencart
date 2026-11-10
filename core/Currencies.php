<?php
/**
 * Currencies CoinsPay accepts prices in, with their decimals; cached for 12 hours.
 */
class CoinsPay_Currencies {

	const TTL = 43200;

	/** After a failure, the next try comes in 5 minutes. */
	const RETRY = 300;

	/** @var CoinsPay_Settings */
	private $settings;

	/** @var CoinsPay_Repo */
	private $repo;

	/** @var CoinsPay_Store */
	private $store;

	/** @var array|null per request */
	private $list;

	public function __construct(CoinsPay_Settings $settings, CoinsPay_Repo $repo, CoinsPay_Store $store) {
		$this->settings = $settings;
		$this->repo = $repo;
		$this->store = $store;
	}

	/**
	 * Decimals of a currency.
	 *
	 * @param string $code
	 * @return int
	 * @throws CoinsPay_ApiError CURRENCY_NOT_SUPPORTED or the list is not available.
	 */
	public function scale($code) {
		$list = $this->load(true);
		$code = strtoupper((string)$code);
		if (!isset($list[$code])) {
			throw new CoinsPay_ApiError(400, 'CURRENCY_NOT_SUPPORTED', 'CoinsPay does not accept prices in ' . $code);
		}
		return $list[$code];
	}

	/**
	 * Whether CoinsPay accepts the currency; null when the list is not available.
	 *
	 * @param string $code
	 * @return bool|null
	 */
	public function supported($code) {
		try {
			$list = $this->load(false);
		} catch (CoinsPay_ApiError $e) {
			return null;
		}
		return isset($list[strtoupper((string)$code)]);
	}

	public function flush() {
		$this->list = null;
		$this->repo->kvDelete($this->key());
		$this->repo->kvDelete($this->key() . '_failed');
	}

	private function load($force) {
		if ($this->list !== null) {
			return $this->list;
		}
		$cached = $this->repo->kvGet($this->key(), self::TTL);
		if ($cached !== null) {
			$list = json_decode($cached, true);
			if (is_array($list)) {
				return $this->list = $list;
			}
		}
		if (!$force && $this->repo->kvGet($this->key() . '_failed', self::RETRY) !== null) {
			throw new CoinsPay_ApiError(0, 'NETWORK', 'The currency list is not available');
		}

		try {
			$data = $this->settings->client()->currencies();
		} catch (CoinsPay_ApiError $e) {
			$this->repo->kvSet($this->key() . '_failed', '1');
			$this->store->log('currency list not loaded: ' . $e->summary(), true);
			throw $e;
		}

		$list = array();
		foreach (array('fiat', 'crypto') as $kind) {
			if (empty($data[$kind]) || !is_array($data[$kind])) {
				continue;
			}
			foreach ($data[$kind] as $currency) {
				if (isset($currency['code'], $currency['scale'])) {
					$list[strtoupper((string)$currency['code'])] = (int)$currency['scale'];
				}
			}
		}
		$this->repo->kvSet($this->key(), CoinsPay_Util::json($list));
		$this->repo->kvDelete($this->key() . '_failed');
		return $this->list = $list;
	}

	private function key() {
		return 'currencies_' . substr(md5($this->settings->apiUrl() . '|' . ($this->settings->isTestMode() ? 'test' : 'live')), 0, 12);
	}
}
