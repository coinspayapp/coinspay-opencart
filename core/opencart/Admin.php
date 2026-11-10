<?php
/**
 * The settings screen of the extension, the same for every branch; each branch's admin
 * controller only renders the result with its own template.
 */
class CoinsPay_OpenCartAdmin {

	/** @var object OpenCart registry */
	private $registry;

	/** @var CoinsPay_OpenCartBranch */
	public $branch;

	/** @var CoinsPay_OpenCartStore */
	public $store;

	/** @var CoinsPay_Plugin */
	public $plugin;

	/** @var array field → error text */
	private $errors = array();

	/** @var array Plain settings (no events): key → default. */
	private static $fields = array(
		'status' => 0,
		'sort_order' => '',
		'geo_zone_id' => 0,
		'total' => '',
		'api_url' => '',
		'api_key' => '',
		'webhook_secret' => '',
		'lifetime' => CoinsPay_Settings::DEFAULT_LIFETIME,
		'auto_return' => 1,
		'stablecoins_only' => 0,
		'buyer_pays_fee' => 0,
		'buyer_pays_network' => 0,
		'test_buyer_only' => 1,
		'debug' => 0,
	);

	public function __construct($registry) {
		$this->registry = $registry;
		$this->branch = new CoinsPay_OpenCartBranch(VERSION);
		$this->store = new CoinsPay_OpenCartStore($registry, $this->branch);
		$this->plugin = new CoinsPay_Plugin($this->store);
	}

	private function get($name) {
		return $this->registry->get($name);
	}

	/** Errors of the last save(): field → text, "warning" for the whole form. */
	public function errors() {
		return $this->errors;
	}

	/** The admin user may change the extension. */
	public function canModify() {
		return $this->get('user')->hasPermission('modify', $this->branch->route);
	}

	/**
	 * The admin user may install extensions: OpenCart checks this in its extension list before
	 * it calls install() and uninstall(), and grants the extension's own permission only then.
	 */
	public function canManageExtensions() {
		$route = $this->branch->branch === '2.3' || $this->branch->branch === '3.0' ? 'extension/extension/payment' : 'extension/payment';
		return $this->get('user')->hasPermission('modify', $route);
	}

	/** Settings that are secrets: never written back into the form. */
	private static $secrets = array('api_key', 'webhook_secret');

	/**
	 * Saves the posted settings when they are valid.
	 *
	 * @return bool
	 */
	public function save() {
		$post = $this->get('request')->post;
		if (!$this->canModify()) {
			$this->errors['warning'] = $this->plugin->text->t('admin_error_permission');
			return false;
		}
		$values = $this->posted($post);
		if (!$this->validate($values)) {
			$this->errors['warning'] = $this->plugin->text->t('admin_error_form');
			return false;
		}
		// editSetting replaces the whole group: the secret of the plugin's links goes along.
		$values['secret'] = $this->plugin->settings->secret() !== '' ? $this->plugin->settings->secret() : CoinsPay_Util::randomHex(32);
		$this->editSettings($values);
		$this->plugin->repo->kvDelete('status_problem');
		$this->plugin->currencies->flush();
		return true;
	}

	/**
	 * Settings from the form, without prefixes.
	 */
	private function posted($post) {
		$p = $this->branch->prefix;
		$values = array();
		foreach (self::$fields as $key => $default) {
			$values[$key] = isset($post[$p . $key]) ? trim((string)$post[$p . $key]) : '';
		}
		foreach (array('auto_return', 'stablecoins_only', 'buyer_pays_fee', 'buyer_pays_network', 'test_buyer_only', 'debug') as $flag) {
			$values[$flag] = empty($post[$p . $flag]) ? 0 : 1;
		}
		$values['status'] = empty($post[$p . 'status']) ? 0 : 1;
		$values['api_url'] = rtrim($values['api_url'], '/');
		// The form never shows saved secrets: an empty field keeps the saved one.
		foreach (self::$secrets as $key) {
			if ($values[$key] === '') {
				$values[$key] = (string)$this->get('config')->get($p . $key);
			}
		}
		foreach (CoinsPay_Settings::$events as $event) {
			$values['status_' . $event] = isset($post[$p . 'status_' . $event]) ? trim((string)$post[$p . 'status_' . $event]) : '';
		}
		$cancel = array();
		if (isset($post[$p . 'cancel_statuses']) && is_array($post[$p . 'cancel_statuses'])) {
			foreach ($post[$p . 'cancel_statuses'] as $id) {
				if (ctype_digit((string)$id) && (int)$id > 0) {
					$cancel[] = (int)$id;
				}
			}
		}
		$values['cancel_statuses'] = implode(',', array_unique($cancel));
		return $values;
	}

	private function validate($values) {
		$t = $this->plugin->text;
		if ($values['api_url'] !== '' && !preg_match('#^https?://[^\s/]+#i', $values['api_url'])) {
			$this->errors['api_url'] = $t->t('admin_error_api_url');
		}
		if ($values['api_key'] !== '' && !preg_match('/^cpk_(test_)?[A-Za-z0-9]{40}$/', $values['api_key'])) {
			$this->errors['api_key'] = $t->t('admin_error_api_key');
		}
		if ($values['webhook_secret'] !== '' && strpos($values['webhook_secret'], 'whsec_') !== 0) {
			$this->errors['webhook_secret'] = $t->t('admin_error_secret');
		}
		$lifetime = (int)$values['lifetime'];
		if (!ctype_digit($values['lifetime']) || $lifetime < CoinsPay_Settings::MIN_LIFETIME || $lifetime > CoinsPay_Settings::MAX_LIFETIME) {
			$this->errors['lifetime'] = $t->t('admin_error_lifetime');
		}
		foreach (CoinsPay_Settings::$events as $event) {
			$value = $values['status_' . $event];
			$ok = (ctype_digit($value) && (int)$value > 0 && $this->plugin->settings->statusExists((int)$value))
				|| ($value === '0' && !in_array($event, CoinsPay_Settings::$required, true))
				|| ($value === CoinsPay_Settings::SAME_AS_PAID && in_array($event, CoinsPay_Settings::$paidLike, true));
			if (!$ok) {
				$this->errors['status_' . $event] = $t->t('admin_error_status');
			}
		}
		return !$this->errors;
	}

	private function editSettings($values) {
		$prefixed = array();
		foreach ($values as $key => $value) {
			$prefixed[$this->branch->prefix . $key] = $value;
		}
		$this->get('load')->model('setting/setting');
		$this->get('model_setting_setting')->editSetting($this->branch->code, $prefixed);
	}

	/**
	 * Everything the settings template needs.
	 */
	public function data() {
		$t = $this->plugin->text;
		$p = $this->branch->prefix;
		$post = $this->get('request')->post;
		$config = $this->get('config');
		$posting = $this->get('request')->server['REQUEST_METHOD'] === 'POST';

		$values = array();
		foreach (self::$fields as $key => $default) {
			if ($posting) {
				$values[$key] = isset($post[$p . $key]) ? $post[$p . $key] : (is_int($default) && $default === 1 && $key !== 'status' ? 0 : '');
			} else {
				$saved = $config->get($p . $key);
				$values[$key] = $saved === null ? $default : $saved;
			}
		}
		// Secrets stay on the server: the page only says whether one is saved. Whoever can open
		// the settings with access rights alone must not read a key that can move money.
		$secretSaved = array();
		foreach (self::$secrets as $key) {
			$secretSaved[$key] = (string)$config->get($p . $key) !== '';
			$values[$key] = '';
		}

		$statuses = $this->orderStatuses();
		$events = array();
		foreach (CoinsPay_Settings::$events as $event) {
			if ($posting) {
				$value = isset($post[$p . 'status_' . $event]) ? (string)$post[$p . 'status_' . $event] : '';
			} else {
				$saved = $config->get($p . 'status_' . $event);
				$value = $saved === null ? (string)CoinsPay_Settings::$defaults[$event] : (string)$saved;
			}
			$events[] = array(
				'key' => $event,
				'name' => $p . 'status_' . $event,
				'label' => $t->t('admin_event_' . $event),
				'value' => $value,
				'no_change' => !in_array($event, CoinsPay_Settings::$required, true),
				'same_as_paid' => in_array($event, CoinsPay_Settings::$paidLike, true),
				'error' => isset($this->errors['status_' . $event]) ? $this->errors['status_' . $event] : '',
			);
		}

		if ($posting) {
			$cancel = isset($post[$p . 'cancel_statuses']) && is_array($post[$p . 'cancel_statuses']) ? array_map('intval', $post[$p . 'cancel_statuses']) : array();
		} else {
			$cancel = $config->get($p . 'cancel_statuses') === null ? CoinsPay_Settings::$defaultCancelStatuses : $this->plugin->settings->cancelStatuses();
		}

		$warnings = array();
		$broken = array();
		foreach ($this->plugin->settings->brokenEvents() as $event) {
			$broken[] = $t->t('admin_event_' . $event);
		}
		if ($broken && $config->get($p . 'status_paid') !== null) {
			$warnings[] = $t->t('admin_warning_broken', implode('; ', $broken));
		}
		// OpenCart 1.5 subtracts the stock when the order is confirmed, whatever the status: no hints there.
		if ($this->branch->branch !== '1.5') {
			$stock = array_map('intval', array_merge((array)$config->get('config_processing_status'), (array)$config->get('config_complete_status')));
			$paidStatus = $this->plugin->settings->statusFor('paid');
			if ($paidStatus > 0 && !in_array($paidStatus, $stock, true)) {
				$warnings[] = $t->t('admin_hint_stock_paid');
			}
			$createdStatus = $this->plugin->settings->statusFor('created');
			if ($createdStatus > 0 && !in_array($createdStatus, $stock, true)) {
				$warnings[] = $t->t('admin_hint_stock_created');
			}
		}

		$lastTest = $this->plugin->repo->kvGet('last_webhook_test');
		return array(
			'branch' => $this->branch->branch,
			'prefix' => $p,
			'texts' => $this->texts(),
			'values' => $values,
			'errors' => $this->errors,
			'events' => $events,
			'statuses' => $statuses,
			'cancel_statuses' => $cancel,
			'geo_zones' => $this->geoZones(),
			'warnings' => $warnings,
			'test_mode' => $this->plugin->settings->isTestMode(),
			'saved' => $secretSaved,
			'webhook_url' => $this->store->webhookUrl(),
			'webhook_last' => $lastTest ? $t->t('admin_webhook_last', date('Y-m-d H:i:s', (int)$lastTest)) : '',
			// Signed links act on the store: only for those who may change the extension.
			'cron_url' => $this->canModify() && $this->plugin->settings->secret() !== '' ? $this->store->cronUrl($this->plugin->tokens->cronKey()) : '',
			'test_url' => $this->canModify() && $this->plugin->settings->secret() !== '' ? $this->store->catalogUrl('test', 't=' . $this->plugin->tokens->testBuyer()) : '',
			'invoices' => $this->invoices(),
			'version' => $t->t('admin_version', CoinsPay_Plugin::VERSION, $this->branch->version),
		);
	}

	/** Texts of the admin screens: the admin_ keys of the core dictionary, without the prefix. */
	public function texts() {
		$file = dirname(dirname(__FILE__)) . '/lang/' . $this->store->adminLanguage() . '.php';
		$all = include $file;
		$texts = array();
		foreach ($all as $key => $text) {
			if (strpos($key, 'admin_') === 0) {
				$texts[substr($key, 6)] = $text;
			}
		}
		return $texts;
	}

	private function orderStatuses() {
		$rows = $this->store->query('SELECT order_status_id, name FROM `' . DB_PREFIX . 'order_status` WHERE language_id = ' . (int)$this->get('config')->get('config_language_id') . ' ORDER BY name');
		$statuses = array();
		foreach ($rows as $row) {
			$statuses[] = array('id' => (int)$row['order_status_id'], 'name' => $row['name']);
		}
		return $statuses;
	}

	private function geoZones() {
		$zones = array();
		foreach ($this->store->query('SELECT geo_zone_id, name FROM `' . DB_PREFIX . 'geo_zone` ORDER BY name') as $row) {
			$zones[] = array('id' => (int)$row['geo_zone_id'], 'name' => $row['name']);
		}
		return $zones;
	}

	private function invoices() {
		$rows = array();
		$t = $this->plugin->text;
		foreach ($this->plugin->repo->recent(50) as $row) {
			$snapshot = json_decode((string)$row['snapshot'], true);
			$paid = is_array($snapshot) ? CoinsPay_Util::normalize(CoinsPay_Util::value($snapshot, 'amount_paid')) : '0';
			$rows[] = array(
				'invoice_id' => $row['invoice_id'],
				'order_id' => (int)$row['order_id'],
				'number' => (int)$row['number'],
				'amount' => $row['amount'] . ' ' . $row['currency'],
				'paid' => $paid . ' ' . $row['currency'],
				'status' => $row['status'],
				'status_text' => $t->t('admin_invoice_' . preg_replace('/[^a-z]/', '', $row['status'])),
				'test' => (bool)$row['test'],
				'created' => date('Y-m-d H:i', (int)$row['created']),
				'pay_url' => $row['pay_url'],
			);
		}
		return $rows;
	}

	/**
	 * "Check connection": the address and key entered in the form, saved or not.
	 *
	 * @return array JSON answer.
	 */
	public function check() {
		if (!$this->canModify()) {
			return array('ok' => false, 'lines' => array($this->plugin->text->t('admin_error_permission')));
		}
		$post = $this->get('request')->post;
		$url = isset($post['api_url']) ? rtrim(trim((string)$post['api_url']), '/') : '';
		$key = isset($post['api_key']) ? trim((string)$post['api_key']) : '';
		// The key field is empty unless a new key is typed: the saved one is checked then.
		if ($key === '') {
			$key = $this->plugin->settings->apiKey();
		}
		if ($url === '') {
			$url = $this->plugin->settings->apiUrl();
		}
		return $this->plugin->checkConnection($url, $key);
	}

	/**
	 * "Refresh" of an invoice: the catalog applies it, because order history is written by the
	 * catalog's order model (stock, e-mails, other extensions' events).
	 *
	 * @return array JSON answer.
	 */
	public function refresh() {
		$t = $this->plugin->text;
		if (!$this->canModify()) {
			return array('ok' => false, 'message' => $t->t('admin_error_permission'));
		}
		$invoiceId = isset($this->get('request')->post['invoice_id']) ? (string)$this->get('request')->post['invoice_id'] : '';
		if (!preg_match(CoinsPay_ApiClient::UUID, $invoiceId)) {
			return array('ok' => false, 'message' => $t->t('admin_refresh_failed'));
		}
		$url = $this->store->catalogUrl('refresh', 'key=' . $this->plugin->tokens->cronKey() . '&invoice_id=' . strtolower($invoiceId));
		$curl = curl_init($url);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_USERAGENT => $this->store->userAgent(),
		));
		$raw = curl_exec($curl);
		if (PHP_VERSION_ID < 80000) {
			curl_close($curl);
		}
		$answer = json_decode((string)$raw, true);
		if (!is_array($answer)) {
			return array('ok' => false, 'message' => $t->t('admin_refresh_failed'));
		}
		return $answer;
	}

	/**
	 * Install: tables, default settings with the store's statuses, events of the branch.
	 */
	public function install() {
		if (!$this->canManageExtensions()) {
			return;
		}
		$this->plugin->repo->install();
		$ids = array();
		foreach ($this->store->query('SELECT DISTINCT order_status_id FROM `' . DB_PREFIX . 'order_status`') as $row) {
			$ids[] = (int)$row['order_status_id'];
		}
		$values = array();
		foreach (self::$fields as $key => $default) {
			$values[$key] = $default;
		}
		$values = array_merge($values, CoinsPay_Settings::installDefaults($ids));
		// A reinstall keeps the settings the owner made.
		$this->get('load')->model('setting/setting');
		$saved = $this->get('model_setting_setting')->getSetting($this->branch->code);
		foreach ($saved as $key => $value) {
			$plain = substr($key, strlen($this->branch->prefix));
			if ($plain !== false && $plain !== '' && array_key_exists($plain, $values)) {
				$values[$plain] = $value;
			}
		}
		$this->editSettings($values);
		$this->events(true);
	}

	public function uninstall() {
		if (!$this->canManageExtensions()) {
			return;
		}
		$this->events(false);
		$this->plugin->repo->uninstall();
	}

	/**
	 * Events of the branch: the order history hook (the store cancels an order → its invoice is
	 * cancelled) and the page-load hook of the lazy poll. OpenCart 1.5 has no events.
	 */
	private function events($add) {
		$branch = $this->branch;
		if ($branch->branch === '1.5') {
			return;
		}
		$events = array();
		// OpenCart 4.1 names model events with a dot before the method: model/checkout/order.addHistory/after.
		$dot = version_compare($branch->version, '4.1', '>=') ? '.' : '/';
		if (version_compare($branch->version, '2.2', '<')) {
			$events['coinspay_history'] = array('post.order.history.add', $branch->action('eventHistory'));
		} else {
			$model = $branch->branch === '4' ? 'addHistory' : 'addOrderHistory';
			$events['coinspay_history'] = array('catalog/model/checkout/order' . $dot . $model . '/after', $branch->action('eventHistory'));
			$events['coinspay_poll'] = array('catalog/controller/common/footer/after', $branch->action('eventPoll'));
		}
		if ($branch->branch === '4') {
			$events['coinspay_comments'] = array('catalog/model/account/order' . $dot . 'getHistories/after', $branch->action('eventHistories'));
		}

		if (version_compare($branch->version, '3.0', '<')) {
			$this->get('load')->model('extension/event');
			$model = $this->get('model_extension_event');
		} else {
			$this->get('load')->model('setting/event');
			$model = $this->get('model_setting_event');
		}
		foreach ($events as $code => $event) {
			$this->deleteEvent($model, $code);
			if (!$add) {
				continue;
			}
			if ($branch->branch !== '4') {
				$model->addEvent($code, $event[0], $event[1]);
			} elseif (version_compare($branch->version, '4.0.1.0', '<')) {
				$model->addEvent($code, 'CoinsPay', $event[0], $event[1], 1, 0);
			} else {
				$model->addEvent(array('code' => $code, 'description' => 'CoinsPay', 'trigger' => $event[0], 'action' => $event[1], 'status' => 1, 'sort_order' => 0));
			}
		}
	}

	/**
	 * Removes an event by its code. Plain SQL: the models differ (deleteEvent takes the code in
	 * 2.x, the id in 3.0–4.x; deleteEventByCode is missing in 4.0.0.0), the table is the same.
	 */
	private function deleteEvent($model, $code) {
		$this->store->query('DELETE FROM `' . DB_PREFIX . 'event` WHERE `code` = \'' . $this->store->escape($code) . '\'');
	}
}
