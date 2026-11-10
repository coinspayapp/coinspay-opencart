<?php
/**
 * Applies the state of an invoice to its order: lock → fresh order → decision → order history.
 */
class CoinsPay_Sync {

	/** @var CoinsPay_Store */
	private $store;

	/** @var CoinsPay_Settings */
	private $settings;

	/** @var CoinsPay_Repo */
	private $repo;

	/** @var CoinsPay_Currencies */
	private $currencies;

	/** @var CoinsPay_Text */
	private $text;

	public function __construct(CoinsPay_Store $store, CoinsPay_Settings $settings, CoinsPay_Repo $repo, CoinsPay_Currencies $currencies, CoinsPay_Text $text) {
		$this->store = $store;
		$this->settings = $settings;
		$this->repo = $repo;
		$this->currencies = $currencies;
		$this->text = $text;
	}

	/**
	 * @param array $invoice Invoice from the API.
	 * @return string What happened, for the log and the webhook answer.
	 * @throws RuntimeException Another request holds the order too long; the caller retries later.
	 */
	public function apply($invoice) {
		$row = $this->repo->find(CoinsPay_Util::value($invoice, 'id'));
		if (!$row) {
			return 'not an invoice of this store';
		}
		$orderId = (int)$row['order_id'];
		if (!$this->repo->lock($orderId)) {
			throw new RuntimeException('the order is busy');
		}
		// No finally in PHP 5.3–5.4: unlock on both paths.
		try {
			$result = $this->applyLocked($orderId, $invoice);
		} catch (Exception $e) {
			$this->repo->unlock($orderId);
			throw $e;
		}
		$this->repo->unlock($orderId);
		return $result;
	}

	private function applyLocked($orderId, $invoice) {
		$invoiceId = strtolower(CoinsPay_Util::value($invoice, 'id'));
		$order = $this->store->order($orderId);
		$row = $this->repo->find($invoiceId);
		if (!$order || !$row) {
			return 'the order is gone';
		}

		$this->repo->saveState($invoice);
		$fingerprint = CoinsPay_Decision::fingerprint($invoice);
		if ($row['fingerprint'] === $fingerprint) {
			$this->settle($invoiceId, $invoice);
			return 'no change';
		}

		$current = $this->repo->current($orderId);
		$paidInvoice = $this->repo->paidInvoice($orderId);
		$pluginStatus = $this->repo->pluginStatus($orderId);
		$statusNow = (int)$order['status_id'];
		$decision = CoinsPay_Decision::decide(
			$invoice,
			array(
				'is_paid' => $paidInvoice !== '',
				'paid_invoice' => $paidInvoice,
				// A cancel status the plugin did not set itself: the store cancelled the order.
				'is_store_cancelled' => in_array($statusNow, $this->settings->cancelStatuses(), true) && $statusNow !== $pluginStatus,
				'expected_amount' => $this->expectedAmount($order, CoinsPay_Util::value($invoice, 'currency')),
				'is_current' => $current && $current['invoice_id'] === $invoiceId,
				'test_allowed' => !empty($row['test_buyer']),
			),
			$this->text
		);

		$event = $decision['event'];
		$note = $decision['note'];
		$action = $this->applyEvent($order, $row, $event, $note, $invoice, $pluginStatus);
		// Only after the order took the event: if writing it failed, the next webhook or poll
		// must decide again, not see "no change" and lose the payment.
		$this->repo->setFingerprint($invoiceId, $fingerprint);

		$this->settle($invoiceId, $invoice);
		$this->store->log(sprintf('order %d, invoice %s (%s): %s → %s', $orderId, $invoiceId, CoinsPay_Util::value($invoice, 'status'), $event, $action), false);
		return $event . ': ' . $action;
	}

	/**
	 * Writes the event to the order: the status the store owner chose for it, or a comment only.
	 */
	private function applyEvent($order, $row, $event, $note, $invoice, $pluginStatus) {
		if ($event === CoinsPay_Decision::NONE) {
			return 'nothing';
		}
		$orderId = (int)$order['id'];
		$statusNow = (int)$order['status_id'];
		$comment = $this->comment($note, !empty($invoice['test']));
		$hasMoney = CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_paid')) || CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_pending'));

		// The buyer switched the order to another payment method: its status is not ours any
		// more, only money that came by CoinsPay is noted.
		if ($order['payment_code'] !== 'coinspay') {
			if (!$hasMoney) {
				return 'other payment method';
			}
			$this->store->addHistory($orderId, $statusNow, $comment . ' ' . $this->text->t('note_other_method'));
			return 'comment (other payment method)';
		}

		if ($event === CoinsPay_Decision::COMMENT) {
			$this->store->addHistory($orderId, $statusNow, $comment);
			return 'comment';
		}

		// The invoice counts as the payment of the order only once the order history has it:
		// marked before, a failed write would leave the order unpaid for good.
		$isPaid = in_array($event, CoinsPay_Decision::$paid, true);

		$target = $this->settings->statusFor($event);
		if ($target === CoinsPay_Settings::INVALID) {
			$this->store->log(sprintf('order %d: the order status for "%s" is not set or was deleted; only a comment is written', $orderId, $event), true);
			$this->repo->kvSet('status_problem', $event);
			$this->store->addHistory($orderId, $statusNow, $comment . ' ' . $this->text->t('note_status_missing'));
			if ($isPaid) {
				$this->repo->markPaid($row['invoice_id']);
			}
			return 'comment (status not set)';
		}

		// Payments and "needs a decision" for a paid invoice always reach the order. Money on its way,
		// a part or an expiry change only an order the plugin still holds: the store has not moved it
		// since, and an order the store cancelled stays cancelled until its invoice is paid in full.
		$always = $isPaid || in_array($event, array('mismatch', 'paid_cancelled', 'test_foreign'), true);
		$held = $pluginStatus === 0 || $statusNow === $pluginStatus;
		if ($target === CoinsPay_Settings::NO_CHANGE || $target === $statusNow || (!$always && !$held)) {
			$this->store->addHistory($orderId, $statusNow, $comment);
			if ($isPaid) {
				$this->repo->markPaid($row['invoice_id']);
			}
			return 'comment';
		}

		$after = $this->store->addHistory($orderId, $target, $comment);
		if ($isPaid) {
			$this->repo->markPaid($row['invoice_id']);
		}
		$this->repo->setPluginStatus($orderId, $after, $event);
		return 'status ' . $target . ($after !== $target ? ' (set to ' . $after . ' by another extension)' : '');
	}

	/**
	 * The order total now, in the invoice currency: the store may have changed it after the
	 * invoice was made. Null when the invoice is in neither the order nor the default currency.
	 */
	public function expectedAmount($order, $currency) {
		$currency = strtoupper((string)$currency);
		if ($currency === strtoupper((string)$order['currency_code'])) {
			$total = (float)$order['total'] * (float)$order['currency_value'];
		} elseif ($currency === strtoupper((string)$this->store->defaultCurrency())) {
			$total = (float)$order['total'];
		} else {
			return null;
		}
		try {
			$scale = $this->currencies->scale($currency);
		} catch (Exception $e) {
			$scale = 2;
		}
		return CoinsPay_Util::formatAmount($total, $scale);
	}

	/**
	 * Polling of the invoice after this state: open — every 5 minutes, paid — never, expired or
	 * cancelled — every 30 minutes until the end of the polling time (a late payment may come).
	 */
	private function settle($invoiceId, $invoice) {
		$status = CoinsPay_Util::value($invoice, 'status');
		if ($status === 'open') {
			$this->repo->setNextPoll($invoiceId, time() + CoinsPay_Poller::INTERVAL_OPEN);
		} elseif ($status === 'paid') {
			$this->repo->setNextPoll($invoiceId, null);
		} else {
			$this->repo->setNextPoll($invoiceId, time() + CoinsPay_Poller::INTERVAL_CLOSED);
		}
	}

	/**
	 * Order comments start with "CoinsPay": OpenCart 4.0.2.2–4.1.0.3 show the customer every
	 * comment, and the plugin hides its own by this mark.
	 */
	private function comment($note, $test) {
		return ($test ? $this->text->t('comment_prefix_test') : $this->text->t('comment_prefix')) . ' ' . $note;
	}
}
