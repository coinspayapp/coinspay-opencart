<?php
/**
 * Asks CoinsPay about open invoices when webhooks do not arrive: they are not set up, the
 * store was in maintenance mode, a firewall dropped them.
 */
class CoinsPay_Poller {

	const INTERVAL_OPEN = 300;
	const INTERVAL_CLOSED = 1800;
	const BATCH = 25;

	/** The lazy poll from page loads runs at most this often. */
	const LAZY_EVERY = 300;

	/** @var CoinsPay_Store */
	private $store;

	/** @var CoinsPay_Settings */
	private $settings;

	/** @var CoinsPay_Repo */
	private $repo;

	/** @var CoinsPay_Sync */
	private $sync;

	public function __construct(CoinsPay_Store $store, CoinsPay_Settings $settings, CoinsPay_Repo $repo, CoinsPay_Sync $sync) {
		$this->store = $store;
		$this->settings = $settings;
		$this->repo = $repo;
		$this->sync = $sync;
	}

	/**
	 * One batch of due invoices.
	 *
	 * @return int Invoices checked.
	 */
	public function run() {
		if (!$this->settings->configured()) {
			return 0;
		}
		$this->repo->expirePolling();
		$client = $this->settings->client();
		$failures = 0;
		$checked = 0;
		foreach ($this->repo->due(self::BATCH) as $row) {
			// Moved forward first: a fatal error on one invoice must not stop the next runs.
			$this->repo->setNextPoll($row['invoice_id'], time() + self::INTERVAL_OPEN);
			$order = $this->store->order((int)$row['order_id']);
			if (!$order) {
				$this->repo->setNextPoll($row['invoice_id'], null);
				continue;
			}
			try {
				// The store cancelled the order: its open invoice is cancelled too. On OpenCart 1.5,
				// which has no events, this is the only way; elsewhere it backs up the event.
				if ($row['status'] === 'open' && $this->storeCancelled($order)) {
					$invoice = $this->cancel($client, $row['invoice_id']);
				} else {
					$invoice = $client->invoice($row['invoice_id']);
				}
				$this->sync->apply($invoice);
				$checked++;
			} catch (CoinsPay_ApiError $e) {
				if ($e->isNotFound()) {
					$this->repo->setNextPoll($row['invoice_id'], null);
					continue;
				}
				$this->store->log(sprintf('poll of invoice %s failed: %s', $row['invoice_id'], $e->summary()), true);
				// CoinsPay is not answering: the next run tries again.
				if (++$failures >= 3) {
					break;
				}
			} catch (Exception $e) {
				$this->store->log(sprintf('poll of invoice %s failed: %s', $row['invoice_id'], $e->getMessage()), true);
			}
		}
		return $checked;
	}

	/**
	 * The store put the order into a cancel status: its open invoice is cancelled at once.
	 * Called from the order history event.
	 */
	public function cancelForOrder($orderId) {
		$row = $this->repo->current($orderId);
		$order = $this->store->order($orderId);
		if (!$row || !$order || $row['status'] !== 'open' || !$this->settings->configured() || !$this->storeCancelled($order)) {
			return;
		}
		try {
			$this->sync->apply($this->cancel($this->settings->client(), $row['invoice_id']));
			$this->store->log(sprintf('order %d cancelled: invoice %s cancelled too', $orderId, $row['invoice_id']), false);
		} catch (Exception $e) {
			$this->store->log(sprintf('order %d cancelled, but its invoice %s was not: %s', $orderId, $row['invoice_id'], $e->getMessage()), true);
		}
	}

	/** Whether the lazy poll should run now: one page load in five minutes gets the turn. */
	public function lazyTurn() {
		return $this->settings->configured() && $this->repo->takeTurn('lazy_poll', self::LAZY_EVERY);
	}

	private function storeCancelled($order) {
		$status = (int)$order['status_id'];
		return in_array($status, $this->settings->cancelStatuses(), true) && $status !== $this->repo->pluginStatus((int)$order['id']);
	}

	private function cancel($client, $invoiceId) {
		try {
			return $client->cancelInvoice($invoiceId);
		} catch (CoinsPay_ApiError $e) {
			// Not open any more (paid or expired meanwhile): its real state is applied instead.
			return $client->invoice($invoiceId);
		}
	}
}
