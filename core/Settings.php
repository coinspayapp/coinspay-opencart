<?php
/**
 * Plugin settings and the map "invoice event → order status" that the store owner sets up:
 * stores have their own order statuses, and the standard ones may be renamed or deleted.
 */
class CoinsPay_Settings {

	const MIN_LIFETIME = 15;
	const MAX_LIFETIME = 1440;
	const DEFAULT_LIFETIME = 60;

	/** The order status is left as it is; a comment is still written. */
	const NO_CHANGE = 0;

	/** The status of the event is not set or no longer exists. */
	const INVALID = -1;

	/** Stored value "same as Paid". */
	const SAME_AS_PAID = 'paid';

	/** @var array Invoice events in the order of the settings screen. */
	public static $events = array(
		'created', 'pending', 'partial', 'paid', 'paid_late', 'paid_over', 'paid_manual',
		'mismatch', 'paid_cancelled', 'test_foreign', 'expired', 'expired_partial', 'cancelled', 'cancelled_partial',
	);

	/** @var array Events that must change the status ("no change" is not offered). */
	public static $required = array('created', 'paid');

	/** @var array Events that may be "same as Paid". */
	public static $paidLike = array('paid_late', 'paid_over', 'paid_manual');

	/**
	 * @var array Default status of each event: standard OpenCart status ids (Pending 1,
	 *            Processing 2, Canceled 7), 0 — no change, "paid" — same as Paid.
	 */
	public static $defaults = array(
		'created' => 1,
		'pending' => 0,
		'partial' => 0,
		'paid' => 2,
		'paid_late' => 'paid',
		'paid_over' => 'paid',
		'paid_manual' => 'paid',
		'mismatch' => 1,
		'paid_cancelled' => 1,
		'test_foreign' => 1,
		'expired' => 7,
		'expired_partial' => 1,
		'cancelled' => 7,
		'cancelled_partial' => 1,
	);

	/** @var array Default order statuses that mean "the store cancelled the order": Canceled, Denied, Voided. */
	public static $defaultCancelStatuses = array(7, 8, 16);

	/** @var CoinsPay_Store */
	private $store;

	/** @var array status id → exists, per request */
	private $existing = array();

	public function __construct(CoinsPay_Store $store) {
		$this->store = $store;
	}

	/**
	 * Settings to save on install: the defaults, with statuses the store does not have left empty.
	 *
	 * @param array $statusIds Order status ids of the store.
	 * @return array setting key → value
	 */
	public static function installDefaults($statusIds) {
		$have = array();
		foreach ($statusIds as $id) {
			$have[(int)$id] = true;
		}
		$values = array(
			'lifetime' => self::DEFAULT_LIFETIME,
			'auto_return' => 1,
			'test_buyer_only' => 1,
			'stablecoins_only' => 0,
			'buyer_pays_fee' => 0,
			'buyer_pays_network' => 0,
			'debug' => 0,
			'secret' => CoinsPay_Util::randomHex(32),
		);
		foreach (self::$defaults as $event => $value) {
			if (is_int($value) && $value > 0 && !isset($have[$value])) {
				$value = '';
			}
			$values['status_' . $event] = $value;
		}
		$cancel = array();
		foreach (self::$defaultCancelStatuses as $id) {
			if (isset($have[$id])) {
				$cancel[] = $id;
			}
		}
		$values['cancel_statuses'] = implode(',', $cancel);
		return $values;
	}

	public function get($key, $default = '') {
		$value = $this->store->setting($key);
		return $value === null || $value === '' ? $default : $value;
	}

	public function flag($key) {
		$value = $this->store->setting($key);
		return !empty($value) && $value !== '0' && $value !== 'off';
	}

	/** The API address when the admin leaves the field empty. */
	const DEFAULT_API_URL = 'https://coinspay.app/api/v1';

	public function apiUrl() {
		$url = rtrim(trim((string)$this->get('api_url')), '/');
		return $url === '' ? self::DEFAULT_API_URL : $url;
	}

	public function apiKey() {
		return trim((string)$this->get('api_key'));
	}

	public function webhookSecret() {
		return trim((string)$this->get('webhook_secret'));
	}

	/** A cpk_test_ key: invoices are paid with a button on the payment page, no coins move. */
	public function isTestMode() {
		return strpos($this->apiKey(), 'cpk_test_') === 0;
	}

	public function configured() {
		return $this->apiUrl() !== '' && $this->apiKey() !== '';
	}

	public function lifetime() {
		return self::clampLifetime($this->get('lifetime', self::DEFAULT_LIFETIME));
	}

	public static function clampLifetime($minutes) {
		$minutes = (int)$minutes;
		if ($minutes <= 0) {
			return self::DEFAULT_LIFETIME;
		}
		return max(self::MIN_LIFETIME, min(self::MAX_LIFETIME, $minutes));
	}

	/** Secret of the plugin's own links (return, cron, test buyer); made on install. */
	public function secret() {
		return (string)$this->get('secret');
	}

	/**
	 * Order status of an invoice event.
	 *
	 * @param string $event
	 * @return int Status id, NO_CHANGE, or INVALID when it is not set or was deleted.
	 */
	public function statusFor($event) {
		$value = (string)$this->get('status_' . $event, '');
		if ($value === self::SAME_AS_PAID && in_array($event, self::$paidLike, true)) {
			return $this->statusFor('paid');
		}
		if ($value === '' || !ctype_digit($value)) {
			return self::INVALID;
		}
		$id = (int)$value;
		if ($id === self::NO_CHANGE) {
			return in_array($event, self::$required, true) ? self::INVALID : self::NO_CHANGE;
		}
		return $this->statusExists($id) ? $id : self::INVALID;
	}

	/**
	 * Order statuses that mean "the store cancelled the order".
	 *
	 * @return array
	 */
	public function cancelStatuses() {
		$ids = array();
		foreach (explode(',', (string)$this->get('cancel_statuses', '')) as $id) {
			if (ctype_digit(trim($id)) && (int)$id > 0) {
				$ids[] = (int)$id;
			}
		}
		return $ids;
	}

	/**
	 * Events whose status is not set or was deleted: shown on the settings screen.
	 *
	 * @return array
	 */
	public function brokenEvents() {
		$broken = array();
		foreach (self::$events as $event) {
			if ($this->statusFor($event) === self::INVALID) {
				$broken[] = $event;
			}
		}
		return $broken;
	}

	public function statusExists($id) {
		$id = (int)$id;
		if (!isset($this->existing[$id])) {
			$rows = $this->store->query('SELECT order_status_id FROM `' . $this->store->prefix() . 'order_status` WHERE order_status_id = ' . $id . ' LIMIT 1');
			$this->existing[$id] = !empty($rows);
		}
		return $this->existing[$id];
	}

	public function client() {
		return new CoinsPay_ApiClient($this->apiUrl(), $this->apiKey(), $this->store->userAgent());
	}
}
