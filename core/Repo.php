<?php
/**
 * The plugin's tables: invoices of orders and a small key-value store. Plain SQL that runs on
 * MySQL 5.x–8.x and MariaDB; times are Unix seconds, so no zero dates for strict SQL modes.
 */
class CoinsPay_Repo {

	/** Seconds to wait for the order lock. */
	const LOCK_WAIT = 10;

	/** @var CoinsPay_Store */
	private $store;

	public function __construct(CoinsPay_Store $store) {
		$this->store = $store;
	}

	public function install() {
		$p = $this->store->prefix();
		$this->store->query('CREATE TABLE IF NOT EXISTS `' . $p . 'coinspay_invoice` (
			`invoice_id` CHAR(36) NOT NULL,
			`order_id` INT(11) NOT NULL,
			`number` INT(11) NOT NULL DEFAULT 0,
			`attempt` INT(11) NOT NULL DEFAULT 1,
			`amount` VARCHAR(40) NOT NULL DEFAULT \'\',
			`currency` VARCHAR(10) NOT NULL DEFAULT \'\',
			`test` TINYINT(1) NOT NULL DEFAULT 0,
			`test_buyer` TINYINT(1) NOT NULL DEFAULT 0,
			`status` VARCHAR(16) NOT NULL DEFAULT \'open\',
			`pay_url` VARCHAR(500) NOT NULL DEFAULT \'\',
			`snapshot` TEXT NULL,
			`fingerprint` CHAR(32) NOT NULL DEFAULT \'\',
			`paid_applied` TINYINT(1) NOT NULL DEFAULT 0,
			`plugin_status` INT(11) NOT NULL DEFAULT 0,
			`plugin_event` VARCHAR(32) NOT NULL DEFAULT \'\',
			`next_poll` INT(11) NULL,
			`poll_until` INT(11) NOT NULL DEFAULT 0,
			`created` INT(11) NOT NULL DEFAULT 0,
			`updated` INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (`invoice_id`),
			KEY `order_id` (`order_id`),
			KEY `next_poll` (`next_poll`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8');
		$this->store->query('CREATE TABLE IF NOT EXISTS `' . $p . 'coinspay_kv` (
			`k` VARCHAR(64) NOT NULL,
			`v` TEXT NULL,
			`updated` INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (`k`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8');
	}

	/** Uninstall keeps the invoices: orders refer to them. Only the cache goes. */
	public function uninstall() {
		$this->store->query('DROP TABLE IF EXISTS `' . $this->store->prefix() . 'coinspay_kv`');
	}

	/**
	 * Remembers a new invoice of an order.
	 */
	public function add($orderId, $invoice, $attempt, $testBuyer, $pollUntil) {
		$now = time();
		$this->store->query('INSERT INTO `' . $this->table() . '` SET
			invoice_id = \'' . $this->e(strtolower(CoinsPay_Util::value($invoice, 'id'))) . '\',
			order_id = ' . (int)$orderId . ',
			number = ' . (int)CoinsPay_Util::value($invoice, 'number') . ',
			attempt = ' . (int)$attempt . ',
			amount = \'' . $this->e(CoinsPay_Util::normalize(CoinsPay_Util::value($invoice, 'amount'))) . '\',
			currency = \'' . $this->e(strtoupper(CoinsPay_Util::value($invoice, 'currency'))) . '\',
			test = ' . (empty($invoice['test']) ? 0 : 1) . ',
			test_buyer = ' . ($testBuyer ? 1 : 0) . ',
			status = \'' . $this->e(CoinsPay_Util::value($invoice, 'status')) . '\',
			pay_url = \'' . $this->e(CoinsPay_Util::value($invoice, 'pay_url')) . '\',
			snapshot = \'' . $this->e(CoinsPay_Util::json(CoinsPay_Decision::snapshot($invoice))) . '\',
			plugin_status = ' . (int)$this->pluginStatus($orderId) . ',
			plugin_event = \'' . $this->e($this->pluginEvent($orderId)) . '\',
			next_poll = ' . ($now + CoinsPay_Poller::INTERVAL_OPEN) . ',
			poll_until = ' . (int)$pollUntil . ',
			created = ' . $now . ',
			updated = ' . $now);
	}

	/**
	 * @param string $invoiceId
	 * @return array|null
	 */
	public function find($invoiceId) {
		$rows = $this->store->query('SELECT * FROM `' . $this->table() . '` WHERE invoice_id = \'' . $this->e(strtolower((string)$invoiceId)) . '\' LIMIT 1');
		return $rows ? $rows[0] : null;
	}

	/**
	 * Invoices of an order, newest first.
	 *
	 * @return array
	 */
	public function forOrder($orderId) {
		return $this->store->query('SELECT * FROM `' . $this->table() . '` WHERE order_id = ' . (int)$orderId . ' ORDER BY attempt DESC, created DESC');
	}

	/** The current (latest) invoice of an order, or null. */
	public function current($orderId) {
		$rows = $this->store->query('SELECT * FROM `' . $this->table() . '` WHERE order_id = ' . (int)$orderId . ' ORDER BY attempt DESC, created DESC LIMIT 1');
		return $rows ? $rows[0] : null;
	}

	/** Id of the invoice that paid the order, or ''. */
	public function paidInvoice($orderId) {
		$rows = $this->store->query('SELECT invoice_id FROM `' . $this->table() . '` WHERE order_id = ' . (int)$orderId . ' AND paid_applied = 1 LIMIT 1');
		return $rows ? (string)$rows[0]['invoice_id'] : '';
	}

	/** The last order status the plugin set for the order; 0 before the first one. */
	public function pluginStatus($orderId) {
		$row = $this->current($orderId);
		return $row ? (int)$row['plugin_status'] : 0;
	}

	public function pluginEvent($orderId) {
		$row = $this->current($orderId);
		return $row ? (string)$row['plugin_event'] : '';
	}

	/** Remembers the status the plugin set, on every invoice of the order. */
	public function setPluginStatus($orderId, $statusId, $event) {
		$this->store->query('UPDATE `' . $this->table() . '` SET plugin_status = ' . (int)$statusId . ', plugin_event = \'' . $this->e($event) . '\' WHERE order_id = ' . (int)$orderId);
	}

	/** Keeps the last seen state of an invoice. */
	public function saveState($invoice) {
		$this->store->query('UPDATE `' . $this->table() . '` SET
			status = \'' . $this->e(CoinsPay_Util::value($invoice, 'status')) . '\',
			snapshot = \'' . $this->e(CoinsPay_Util::json(CoinsPay_Decision::snapshot($invoice))) . '\',
			updated = ' . time() . '
			WHERE invoice_id = \'' . $this->e(strtolower(CoinsPay_Util::value($invoice, 'id'))) . '\'');
	}

	public function setFingerprint($invoiceId, $fingerprint) {
		$this->store->query('UPDATE `' . $this->table() . '` SET fingerprint = \'' . $this->e($fingerprint) . '\' WHERE invoice_id = \'' . $this->e(strtolower($invoiceId)) . '\'');
	}

	public function markPaid($invoiceId) {
		$this->store->query('UPDATE `' . $this->table() . '` SET paid_applied = 1 WHERE invoice_id = \'' . $this->e(strtolower($invoiceId)) . '\'');
	}

	/**
	 * When to ask CoinsPay about the invoice again; null — never.
	 */
	public function setNextPoll($invoiceId, $at) {
		$this->store->query('UPDATE `' . $this->table() . '` SET next_poll = ' . ($at === null ? 'NULL' : (int)$at) . ' WHERE invoice_id = \'' . $this->e(strtolower($invoiceId)) . '\'');
	}

	/**
	 * Invoices due for polling, longest unchecked first.
	 */
	public function due($limit) {
		$now = time();
		return $this->store->query('SELECT * FROM `' . $this->table() . '` WHERE next_poll IS NOT NULL AND next_poll <= ' . $now . ' AND poll_until > ' . $now . ' ORDER BY next_poll ASC LIMIT ' . (int)$limit);
	}

	/** Stops polling invoices past their time. */
	public function expirePolling() {
		$this->store->query('UPDATE `' . $this->table() . '` SET next_poll = NULL WHERE next_poll IS NOT NULL AND poll_until <= ' . time());
	}

	/**
	 * Recent invoices for the invoices tab of the settings screen.
	 */
	public function recent($limit) {
		return $this->store->query('SELECT * FROM `' . $this->table() . '` ORDER BY created DESC LIMIT ' . (int)$limit);
	}

	public function kvGet($key, $maxAge = 0) {
		$rows = $this->store->query('SELECT v, updated FROM `' . $this->store->prefix() . 'coinspay_kv` WHERE k = \'' . $this->e($key) . '\' LIMIT 1');
		if (!$rows || ($maxAge > 0 && (int)$rows[0]['updated'] < time() - $maxAge)) {
			return null;
		}
		return $rows[0]['v'];
	}

	public function kvSet($key, $value) {
		$this->store->query('REPLACE INTO `' . $this->store->prefix() . 'coinspay_kv` SET k = \'' . $this->e($key) . '\', v = \'' . $this->e((string)$value) . '\', updated = ' . time());
	}

	public function kvDelete($key) {
		$this->store->query('DELETE FROM `' . $this->store->prefix() . 'coinspay_kv` WHERE k = \'' . $this->e($key) . '\'');
	}

	/**
	 * Takes a turn: true for exactly one caller per $every seconds (the lazy poll).
	 */
	public function takeTurn($key, $every) {
		$now = time();
		$token = CoinsPay_Util::randomHex(8);
		$p = $this->store->prefix();
		$this->store->query('INSERT IGNORE INTO `' . $p . 'coinspay_kv` SET k = \'' . $this->e($key) . '\', v = \'\', updated = 0');
		// Only the first caller matches "updated <= now - every"; the token tells it apart from callers of the same second.
		$this->store->query('UPDATE `' . $p . 'coinspay_kv` SET v = \'' . $token . '\', updated = ' . $now . ' WHERE k = \'' . $this->e($key) . '\' AND updated <= ' . ($now - (int)$every));
		$rows = $this->store->query('SELECT v FROM `' . $p . 'coinspay_kv` WHERE k = \'' . $this->e($key) . '\' LIMIT 1');
		return $rows && (string)$rows[0]['v'] === $token;
	}

	/**
	 * MySQL named lock on the order: a webhook burst, the poller and the buyer's return apply one at a time.
	 */
	public function lock($orderId) {
		$rows = $this->store->query('SELECT GET_LOCK(\'' . $this->e($this->lockName($orderId)) . '\', ' . self::LOCK_WAIT . ') AS l');
		return $rows && (string)$rows[0]['l'] === '1';
	}

	public function unlock($orderId) {
		$this->store->query('SELECT RELEASE_LOCK(\'' . $this->e($this->lockName($orderId)) . '\') AS l');
	}

	private function lockName($orderId) {
		return 'coinspay_' . substr(md5($this->store->prefix() . '|' . $this->store->siteUrl()), 0, 12) . '_' . (int)$orderId;
	}

	private function table() {
		return $this->store->prefix() . 'coinspay_invoice';
	}

	private function e($value) {
		return $this->store->escape((string)$value);
	}
}
