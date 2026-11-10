<?php
/**
 * The invoice for an order at the checkout confirm step.
 */
class CoinsPay_Payment {

	/** After the invoice expires, late payments are looked for during a day. */
	const POLL_AFTER_EXPIRY = 86400;

	/** @var CoinsPay_Store */
	private $store;

	/** @var CoinsPay_Settings */
	private $settings;

	/** @var CoinsPay_Repo */
	private $repo;

	/** @var CoinsPay_Currencies */
	private $currencies;

	/** @var CoinsPay_Sync */
	private $sync;

	/** @var CoinsPay_Tokens */
	private $tokens;

	/** @var CoinsPay_Text */
	private $text;

	public function __construct(CoinsPay_Store $store, CoinsPay_Settings $settings, CoinsPay_Repo $repo, CoinsPay_Currencies $currencies, CoinsPay_Sync $sync, CoinsPay_Tokens $tokens, CoinsPay_Text $text) {
		$this->store = $store;
		$this->settings = $settings;
		$this->repo = $repo;
		$this->currencies = $currencies;
		$this->sync = $sync;
		$this->tokens = $tokens;
		$this->text = $text;
	}

	/**
	 * Invoice to send the buyer to: the open one of the same amount, or a new one.
	 *
	 * @param int  $orderId
	 * @param bool $testBuyer The order is placed by the test buyer: only then a test payment completes it.
	 * @return array Invoice: open, or paid when the order is already paid.
	 * @throws CoinsPay_ApiError
	 */
	public function invoiceFor($orderId, $testBuyer) {
		$order = $this->store->order($orderId);
		if (!$order) {
			throw new CoinsPay_ApiError(404, 'ORDER_NOT_FOUND', 'No order ' . (int)$orderId);
		}
		$currency = $this->currencyFor($order);
		$body = CoinsPay_InvoiceRequest::body(
			array(
				'currency' => $currency,
				'amount' => $this->sync->expectedAmount($order, $currency),
				'number' => (string)$order['id'],
				'items' => $order['items'],
				'return_url' => $this->store->returnUrl($order['id'], $this->tokens->returnToken($order['id'])),
			),
			array(
				'lifetime' => $this->settings->lifetime(),
				'stablecoins_only' => $this->settings->flag('stablecoins_only'),
				'buyer_pays_fee' => $this->settings->flag('buyer_pays_fee'),
				'buyer_pays_network' => $this->settings->flag('buyer_pays_network'),
				'auto_return' => $this->settings->flag('auto_return'),
			)
		);
		$client = $this->settings->client();

		$rows = $this->repo->forOrder($order['id']);
		$current = $rows ? $rows[0] : null;
		if ($current && $current['amount'] === $body['amount'] && $current['currency'] === $body['currency'] && (bool)$current['test'] === $this->settings->isTestMode()) {
			try {
				$invoice = $client->invoice($current['invoice_id']);
				if (in_array(CoinsPay_Util::value($invoice, 'status'), array('open', 'paid'), true)) {
					$this->sync->apply($invoice);
					return $invoice;
				}
			} catch (CoinsPay_ApiError $e) {
				if (!$e->isNotFound()) {
					throw $e;
				}
			}
		}

		// The same attempt and body give the same key: a repeated click gets the same invoice.
		$attempt = count($rows) + 1;
		$invoice = $client->createInvoice($body, CoinsPay_InvoiceRequest::idempotencyKey($this->store->siteUrl(), $order['id'], $attempt, $body));
		if (!$this->repo->find(CoinsPay_Util::value($invoice, 'id'))) {
			$this->repo->add($order['id'], $invoice, $attempt, $testBuyer, time() + $this->settings->lifetime() * 60 + self::POLL_AFTER_EXPIRY);
		}

		$note = $this->text->t(empty($invoice['test']) ? 'comment_prefix' : 'comment_prefix_test') . ' '
			. $this->text->t('note_created', CoinsPay_Util::value($invoice, 'number'), CoinsPay_Util::value($invoice, 'amount') . ' ' . CoinsPay_Util::value($invoice, 'currency'));
		$statusNow = (int)$order['status_id'];
		if ($statusNow === 0) {
			// A new order: OpenCart keeps it out of the order list until it gets a status.
			$created = $this->settings->statusFor('created');
			if ($created <= 0) {
				$created = (int)$this->store->defaultOrderStatus();
				$this->store->log('the order status for "created" is not set; the default order status of the store is used', true);
			}
			$after = $this->store->addHistory($order['id'], $created, $note);
			$this->repo->setPluginStatus($order['id'], $after, 'created');
		} else {
			$this->store->addHistory($order['id'], $statusNow, $note);
		}

		if ($current && $current['invoice_id'] !== strtolower(CoinsPay_Util::value($invoice, 'id'))) {
			$this->cancelReplaced($client, $current);
		}
		$this->store->log(sprintf('order %d: invoice %s created', $order['id'], CoinsPay_Util::value($invoice, 'id')), false);
		return $invoice;
	}

	/**
	 * Currency of the invoice: the order's when CoinsPay takes it, else the default currency of the store.
	 *
	 * @throws CoinsPay_ApiError CURRENCY_NOT_SUPPORTED when it takes neither.
	 */
	public function currencyFor($order) {
		foreach (array($order['currency_code'], $this->store->defaultCurrency()) as $code) {
			try {
				$this->currencies->scale($code);
				return strtoupper($code);
			} catch (CoinsPay_ApiError $e) {
				if ($e->errorCode() !== 'CURRENCY_NOT_SUPPORTED') {
					throw $e;
				}
			}
		}
		throw new CoinsPay_ApiError(400, 'CURRENCY_NOT_SUPPORTED', 'CoinsPay does not accept prices in ' . $order['currency_code']);
	}

	/**
	 * Cancels the invoice a new one replaced (the order total, currency or mode changed), so
	 * the buyer cannot pay the old amount. Only an open invoice without money is cancelled; a
	 * paid one is still checked against the order total when it arrives.
	 */
	private function cancelReplaced($client, $row) {
		$snapshot = json_decode((string)$row['snapshot'], true);
		if ($row['status'] !== 'open' || !is_array($snapshot)
			|| CoinsPay_Util::positive(CoinsPay_Util::value($snapshot, 'amount_paid'))
			|| CoinsPay_Util::positive(CoinsPay_Util::value($snapshot, 'amount_pending'))) {
			return;
		}
		try {
			$client->cancelInvoice($row['invoice_id']);
			$this->store->log(sprintf('invoice %s replaced and cancelled', $row['invoice_id']), false);
		} catch (Exception $e) {
			$this->store->log(sprintf('replaced invoice %s was not cancelled: %s', $row['invoice_id'], $e->getMessage()), false);
		}
	}

	/**
	 * What to tell the buyer when the invoice cannot be created.
	 */
	public static function errorMessage(CoinsPay_ApiError $error, CoinsPay_Text $buyerText) {
		switch ($error->errorCode()) {
			case 'CURRENCY_NOT_SUPPORTED':
				return $buyerText->t('error_currency');
			case 'QUOTE_UNAVAILABLE':
			case 'NETWORK':
			case 'RATE_LIMITED':
				return $buyerText->t('error_unavailable');
			default:
				return $buyerText->t('error_failed');
		}
	}
}
