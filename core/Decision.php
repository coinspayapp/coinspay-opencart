<?php
/**
 * Invoice state → event of the order. A pure function on plain arrays (unit-tested); which
 * order status an event sets is the store owner's choice (CoinsPay_Settings::statusFor).
 *
 * The rules are those of the WooCommerce plugin, with the fixes of its security review:
 * a paid invoice is checked against the order total now, money for an order the store
 * cancelled does not ship it, a test payment completes only orders of the test buyer.
 */
class CoinsPay_Decision {

	/** Nothing to do. */
	const NONE = 'none';

	/** A comment only, whatever the settings: a second invoice of a paid order was paid. */
	const COMMENT = 'comment';

	/** Events that pay the order. */
	public static $paid = array('paid', 'paid_late', 'paid_over', 'paid_manual');

	/**
	 * @param array         $invoice Invoice from the API.
	 * @param array         $order   {is_paid, paid_invoice, is_store_cancelled, expected_amount: the order total
	 *                               now in the invoice currency, or null when the currency is not the order's,
	 *                               order_currency, is_current, test_allowed}.
	 * @param CoinsPay_Text $text
	 * @return array {event, note, final}: final — the invoice will not change any more except by a late payment.
	 */
	public static function decide($invoice, $order, CoinsPay_Text $text) {
		$status = CoinsPay_Util::value($invoice, 'status');
		$paid = CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_paid'));
		$pending = CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_pending'));
		$number = CoinsPay_Util::value($invoice, 'number');

		if ($status === 'paid') {
			if (!empty($order['is_paid'])) {
				if (CoinsPay_Util::value($invoice, 'id') !== (string)$order['paid_invoice']) {
					return self::result(self::COMMENT, $text->t('note_second_paid', $number), true);
				}
				return self::result(self::NONE, '', true);
			}

			// Money that came after the store cancelled the order does not ship it: the store decides.
			if (!empty($order['is_store_cancelled'])) {
				return self::result('paid_cancelled', $text->t('note_paid_cancelled', $number, self::payments($invoice, $text)), true);
			}

			// A test payment moves no money: it completes only orders of the test buyer.
			if (!empty($invoice['test']) && empty($order['test_allowed'])) {
				return self::result('test_foreign', $text->t('note_test_foreign', $number), true);
			}

			$amount = CoinsPay_Util::normalize(CoinsPay_Util::value($invoice, 'amount'));
			$currency = strtoupper(CoinsPay_Util::value($invoice, 'currency'));
			if ($order['expected_amount'] === null || (string)$order['expected_amount'] !== $amount) {
				$expected = $order['expected_amount'] === null ? '—' : $order['expected_amount'] . ' ' . $currency;
				return self::result('mismatch', $text->t('note_mismatch', $number, $amount . ' ' . $currency, $expected), true);
			}

			$note = $text->t('note_paid', $number, self::payments($invoice, $text));
			$event = 'paid';
			if (CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_overpaid'))) {
				$note .= ' ' . $text->t('note_overpaid', self::money($invoice, 'amount_overpaid'));
				$event = 'paid_over';
			}
			if (!empty($invoice['paid_late'])) {
				$note .= ' ' . $text->t('note_paid_late');
				$event = 'paid_late';
			}
			if (!empty($invoice['paid_manually'])) {
				$note .= ' ' . $text->t('note_paid_manually');
				$event = 'paid_manual';
			}
			return self::result($event, $note, true);
		}

		if (!empty($order['is_paid'])) {
			return self::result(self::NONE, '', $status !== 'open');
		}

		if ($status === 'open') {
			// Part is paid and the rest is on its way: nothing is left to pay.
			if ($paid && $pending && !CoinsPay_Util::positive(CoinsPay_Util::value($invoice, 'amount_due'))) {
				return self::result('pending', $text->t('note_rest_pending', $number, self::money($invoice, 'amount_paid'), self::payments($invoice, $text)), false);
			}
			if ($paid) {
				return self::result('partial', $text->t('note_partial', $number, self::money($invoice, 'amount_paid'), self::money($invoice, 'amount_due')), false);
			}
			if ($pending) {
				return self::result('pending', $text->t('note_pending', $number, self::payments($invoice, $text)), false);
			}
			return self::result(self::NONE, '', false);
		}

		if ($status === 'expired' || $status === 'cancelled') {
			if ($paid || $pending) {
				$received = self::money($invoice, $paid ? 'amount_paid' : 'amount_pending');
				if ($status === 'expired') {
					return self::result('expired_partial', $text->t('note_expired_partial', $number, $received, self::money($invoice, 'amount')), true);
				}
				return self::result('cancelled_partial', $text->t('note_cancelled_partial', $number, $received, self::money($invoice, 'amount')), true);
			}
			// An older invoice of the order ran out: the buyer is paying a newer one.
			if (empty($order['is_current'])) {
				return self::result(self::NONE, '', true);
			}
			if ($status === 'cancelled') {
				return self::result('cancelled', $text->t('note_cancelled', $number), true);
			}
			return self::result('expired', $text->t('note_expired', $number), true);
		}

		return self::result(self::NONE, '', false);
	}

	/**
	 * Payments of the invoice in one line: "12.5 USDT, TRON, tx …; 0.001 BTC, Bitcoin".
	 */
	public static function payments($invoice, CoinsPay_Text $text) {
		$lines = array();
		if (!empty($invoice['payments']) && is_array($invoice['payments'])) {
			foreach ($invoice['payments'] as $payment) {
				$line = trim(CoinsPay_Util::value($payment, 'amount') . ' ' . CoinsPay_Util::value($payment, 'asset'));
				if (CoinsPay_Util::value($payment, 'chain') !== '') {
					$line .= ', ' . self::chainName(CoinsPay_Util::value($payment, 'chain'));
				}
				if (CoinsPay_Util::value($payment, 'tx_hash') !== '') {
					$line .= ', tx ' . CoinsPay_Util::value($payment, 'tx_hash');
				}
				$lines[] = $line;
			}
		}
		return $lines ? implode('; ', $lines) : $text->t('note_no_payments');
	}

	public static function chainName($chain) {
		$names = array(
			'ethereum' => 'Ethereum',
			'bsc' => 'BNB Smart Chain',
			'tron' => 'TRON',
			'bitcoin' => 'Bitcoin',
			'litecoin' => 'Litecoin',
			'solana' => 'Solana',
		);
		return isset($names[$chain]) ? $names[$chain] : $chain;
	}

	/**
	 * What of the invoice matters for the order: the same fingerprint means nothing new.
	 */
	public static function fingerprint($invoice) {
		$payments = array();
		if (!empty($invoice['payments']) && is_array($invoice['payments'])) {
			foreach ($invoice['payments'] as $payment) {
				$payments[] = CoinsPay_Util::value($payment, 'deposit_id') . ':' . CoinsPay_Util::value($payment, 'status');
			}
		}
		return md5(implode('|', array(
			CoinsPay_Util::value($invoice, 'status'),
			CoinsPay_Util::normalize(CoinsPay_Util::value($invoice, 'amount_paid')),
			CoinsPay_Util::normalize(CoinsPay_Util::value($invoice, 'amount_pending')),
			empty($invoice['paid_late']) ? '0' : '1',
			empty($invoice['paid_manually']) ? '0' : '1',
			implode(',', $payments),
		)));
	}

	/**
	 * The part of the invoice kept with the order: shown on the invoices tab.
	 */
	public static function snapshot($invoice) {
		$copy = array();
		foreach (array('id', 'number', 'status', 'test', 'currency', 'amount', 'amount_paid', 'amount_pending', 'amount_due', 'amount_overpaid', 'pay_url', 'expires_at', 'paid_late', 'paid_manually') as $field) {
			$copy[$field] = isset($invoice[$field]) ? $invoice[$field] : null;
		}
		$copy['payments'] = array();
		if (!empty($invoice['payments']) && is_array($invoice['payments'])) {
			foreach ($invoice['payments'] as $payment) {
				$copy['payments'][] = array(
					'chain' => CoinsPay_Util::value($payment, 'chain'),
					'asset' => CoinsPay_Util::value($payment, 'asset'),
					'amount' => CoinsPay_Util::value($payment, 'amount'),
					'status' => CoinsPay_Util::value($payment, 'status'),
					'tx_hash' => CoinsPay_Util::value($payment, 'tx_hash'),
				);
			}
		}
		return $copy;
	}

	private static function money($invoice, $field) {
		return CoinsPay_Util::normalize(CoinsPay_Util::value($invoice, $field)) . ' ' . CoinsPay_Util::value($invoice, 'currency');
	}

	private static function result($event, $note, $final) {
		return array('event' => $event, 'note' => $note, 'final' => (bool)$final);
	}
}
