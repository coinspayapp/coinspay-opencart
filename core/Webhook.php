<?php
/**
 * The webhook receiver. The event only says which invoice changed: its state is fetched from
 * CoinsPay with the store's own key, so a forged or replayed event changes nothing.
 */
class CoinsPay_Webhook {

	/** @var CoinsPay_Settings */
	private $settings;

	/** @var CoinsPay_Repo */
	private $repo;

	/** @var CoinsPay_Sync */
	private $sync;

	/** @var CoinsPay_Store */
	private $store;

	public function __construct(CoinsPay_Store $store, CoinsPay_Settings $settings, CoinsPay_Repo $repo, CoinsPay_Sync $sync) {
		$this->store = $store;
		$this->settings = $settings;
		$this->repo = $repo;
		$this->sync = $sync;
	}

	/**
	 * @param string $body      Raw request body.
	 * @param string $signature Value of x-cpay-signature.
	 * @return array {0: HTTP status, 1: short answer}
	 */
	public function handle($body, $signature) {
		$secret = $this->settings->webhookSecret();
		if ($secret === '') {
			return array(401, 'webhook secret is not set in the plugin');
		}
		if (!CoinsPay_Signature::verify($body, $signature, $secret)) {
			$this->store->log('webhook rejected: bad or stale signature', true);
			return array(401, 'bad signature');
		}

		$event = json_decode((string)$body, true);
		if (!is_array($event) || empty($event['type'])) {
			return array(400, 'not an event');
		}
		$type = (string)$event['type'];
		if ($type === 'webhook.test') {
			$this->repo->kvSet('last_webhook_test', (string)time());
			return array(200, 'test event received');
		}

		// Test and live events share the webhook of the shop; a test key handles only test ones.
		if (!empty($event['test']) !== $this->settings->isTestMode()) {
			return array(200, 'ignored: other mode');
		}

		$invoiceId = self::invoiceId($event);
		if ($invoiceId === '' || !$this->repo->find($invoiceId)) {
			return array(200, 'ignored: unknown invoice');
		}

		try {
			$result = $this->sync->apply($this->settings->client()->invoice($invoiceId));
		} catch (CoinsPay_ApiError $e) {
			if ($e->isNotFound()) {
				return array(200, 'ignored: unknown invoice');
			}
			$this->store->log(sprintf('webhook %s for invoice %s: %s', $type, $invoiceId, $e->summary()), true);
			return array(503, 'CoinsPay API is not available, retry later');
		} catch (Exception $e) {
			$this->store->log(sprintf('webhook %s for invoice %s failed: %s', $type, $invoiceId, $e->getMessage()), true);
			return array(500, 'processing failed, retry later');
		}
		return array(200, $result);
	}

	/**
	 * Invoice id of an event: invoice.* carry data.invoice.id, payment.* carry data.deposit.invoice_id.
	 */
	public static function invoiceId($event) {
		$data = isset($event['data']) && is_array($event['data']) ? $event['data'] : array();
		if (isset($data['invoice']['id']) && is_string($data['invoice']['id'])) {
			return strtolower($data['invoice']['id']);
		}
		if (isset($data['deposit']['invoice_id']) && is_string($data['deposit']['invoice_id'])) {
			return strtolower($data['deposit']['invoice_id']);
		}
		return '';
	}
}
