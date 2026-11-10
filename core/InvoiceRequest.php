<?php
/**
 * Body of POST /invoices from plain order data; no OpenCart calls, so it runs in unit tests.
 */
class CoinsPay_InvoiceRequest {

	const MAX_DESCRIPTION = 1000;
	const MAX_REFERENCE = 190;
	const MAX_RETURN_URL = 500;

	/**
	 * @param array $order   {currency, amount, number, items: [{name, quantity}], return_url}.
	 * @param array $options {lifetime, stablecoins_only, buyer_pays_fee, buyer_pays_network, auto_return}.
	 * @return array
	 */
	public static function body($order, $options) {
		$lines = array();
		foreach ($order['items'] as $item) {
			$name = trim(html_entity_decode(strip_tags((string)$item['name']), ENT_QUOTES, 'UTF-8'));
			if ($name !== '') {
				$lines[] = $name . " \xC3\x97 " . CoinsPay_Util::normalize((string)$item['quantity']);
			}
		}

		// No title: the payment page then names the invoice "Order <reference>" in the buyer's language.
		$body = array(
			'currency' => strtoupper((string)$order['currency']),
			'amount' => (string)$order['amount'],
			'reference' => CoinsPay_Util::truncate((string)$order['number'], self::MAX_REFERENCE),
			'lifetime_minutes' => CoinsPay_Settings::clampLifetime($options['lifetime']),
		);
		if ($lines) {
			$body['description'] = CoinsPay_Util::truncate(implode(', ', $lines), self::MAX_DESCRIPTION);
		}

		// A link longer than the API takes would fail the whole invoice; without it the buyer still pays.
		$returnUrl = (string)$order['return_url'];
		if ($returnUrl !== '' && strlen($returnUrl) <= self::MAX_RETURN_URL) {
			$body['return_url'] = $returnUrl;
			if (!empty($options['auto_return'])) {
				$body['auto_return'] = true;
			}
		}

		foreach (array('stablecoins_only', 'buyer_pays_fee', 'buyer_pays_network') as $flag) {
			if (!empty($options[$flag])) {
				$body[$flag] = true;
			}
		}
		return $body;
	}

	/**
	 * Idempotency key: the same order, attempt and body give the same key, so a repeated
	 * "Confirm order" gets the invoice already created. The body is part of the key, so a
	 * changed total never meets the "same key, other body" error; the site part keeps a
	 * staging copy on the same CoinsPay shop from getting invoices of the live store.
	 *
	 * @param string $site
	 * @param int    $orderId
	 * @param int    $attempt Invoice number within the order, from 1.
	 * @param array  $body
	 * @return string
	 */
	public static function idempotencyKey($site, $orderId, $attempt, $body) {
		return sprintf('oc-%s-%d-%d-%s', substr(md5((string)$site), 0, 8), (int)$orderId, (int)$attempt, substr(sha1(CoinsPay_Util::json($body)), 0, 12));
	}
}
