<?php
/**
 * Invoice state → event of the order.
 */

use PHPUnit\Framework\TestCase;

final class DecisionTest extends TestCase {

	const INVOICE_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

	private function invoice(array $overrides = array()) {
		return $overrides + array(
			'id' => self::INVOICE_ID,
			'number' => 12,
			'status' => 'open',
			'test' => false,
			'currency' => 'USD',
			'amount' => '49.9',
			'amount_paid' => '0',
			'amount_pending' => '0',
			'amount_due' => '49.9',
			'amount_overpaid' => null,
			'paid_late' => false,
			'paid_manually' => false,
			'payments' => array(),
		);
	}

	private function order(array $overrides = array()) {
		return $overrides + array(
			'is_paid' => false,
			'paid_invoice' => '',
			'is_store_cancelled' => false,
			'expected_amount' => '49.9',
			'is_current' => true,
			'test_allowed' => false,
		);
	}

	private function payment($status = 'confirmed') {
		return array('deposit_id' => 'd1', 'status' => $status, 'chain' => 'tron', 'asset' => 'USDT', 'amount' => '49.9', 'tx_hash' => 'abc123');
	}

	private function decide(array $invoice, array $order, $lang = 'en') {
		return CoinsPay_Decision::decide($invoice, $order, new CoinsPay_Text($lang));
	}

	private function paid(array $overrides = array()) {
		return $this->invoice($overrides + array('status' => 'paid', 'amount_paid' => '49.9', 'amount_due' => '0', 'payments' => array($this->payment())));
	}

	public function test_open_unpaid_invoice_changes_nothing() {
		$decision = $this->decide($this->invoice(), $this->order());
		$this->assertSame(CoinsPay_Decision::NONE, $decision['event']);
		$this->assertFalse($decision['final']);
	}

	public function test_payment_on_its_way() {
		$decision = $this->decide($this->invoice(array('amount_pending' => '49.9', 'amount_due' => '0', 'payments' => array($this->payment('pending')))), $this->order());
		$this->assertSame('pending', $decision['event']);
		$this->assertStringContainsString('on its way', $decision['note']);
		$this->assertStringContainsString('49.9 USDT, TRON, tx abc123', $decision['note']);
		$this->assertFalse($decision['final']);
	}

	public function test_partial_payment_with_the_rest() {
		$decision = $this->decide($this->invoice(array('amount_paid' => '20', 'amount_due' => '29.9')), $this->order());
		$this->assertSame('partial', $decision['event']);
		$this->assertStringContainsString('20 USD received, 29.9 USD left', $decision['note']);
	}

	public function test_rest_on_its_way_after_a_partial_payment_is_not_called_partial() {
		$decision = $this->decide(
			$this->invoice(array('amount_paid' => '20', 'amount_pending' => '29.9', 'amount_due' => '0', 'payments' => array($this->payment('confirmed'), $this->payment('pending')))),
			$this->order()
		);
		$this->assertSame('pending', $decision['event']);
		$this->assertStringContainsString('20 USD received, the rest is on its way', $decision['note']);
		$this->assertStringNotContainsString('left', $decision['note']);

		// Part paid, part on its way, still something to pay: partially paid.
		$partial = $this->decide($this->invoice(array('amount_paid' => '10', 'amount_pending' => '10', 'amount_due' => '29.9')), $this->order());
		$this->assertSame('partial', $partial['event']);
	}

	public function test_paid_invoice_pays_the_order() {
		$decision = $this->decide($this->paid(), $this->order());
		$this->assertSame('paid', $decision['event']);
		$this->assertTrue($decision['final']);
		$this->assertStringContainsString('invoice No. 12 is paid: 49.9 USDT, TRON', $decision['note']);
	}

	public function test_late_overpaid_and_manual_payments_have_their_own_events() {
		$this->assertSame('paid_over', $this->decide($this->paid(array('amount_overpaid' => '5.1')), $this->order())['event']);
		$this->assertSame('paid_late', $this->decide($this->paid(array('amount_overpaid' => '5.1', 'paid_late' => true)), $this->order())['event']);

		$decision = $this->decide($this->paid(array('amount_overpaid' => '5.1', 'paid_late' => true, 'paid_manually' => true)), $this->order());
		$this->assertSame('paid_manual', $decision['event']);
		$this->assertStringContainsString('after the invoice expired', $decision['note']);
		$this->assertStringContainsString('Overpaid by 5.1 USD', $decision['note']);
		$this->assertStringContainsString('marked as paid manually', $decision['note']);
	}

	public function test_paid_invoice_is_checked_against_the_order_total_now() {
		$paid = $this->paid(array('amount' => '12.5', 'amount_paid' => '12.5'));
		// The store raised the order total after the invoice was made.
		$decision = $this->decide($paid, $this->order(array('expected_amount' => '100')));
		$this->assertSame('mismatch', $decision['event']);
		$this->assertStringContainsString('12.5 USD differs from the order (100 USD)', $decision['note']);
		// The invoice is in a currency that is neither the order's nor the store's.
		$this->assertSame('mismatch', $this->decide($paid, $this->order(array('expected_amount' => null)))['event']);
	}

	public function test_payment_of_an_order_cancelled_by_the_store_needs_a_decision() {
		$decision = $this->decide($this->paid(), $this->order(array('is_store_cancelled' => true)));
		$this->assertSame('paid_cancelled', $decision['event']);
		$this->assertStringContainsString('cancelled in the store', $decision['note']);
		$this->assertTrue($decision['final']);
	}

	public function test_test_payment_pays_only_an_order_of_the_test_buyer() {
		$paid = $this->paid(array('test' => true));
		$decision = $this->decide($paid, $this->order());
		$this->assertSame('test_foreign', $decision['event']);
		$this->assertStringContainsString('no money was received', $decision['note']);

		$this->assertSame('paid', $this->decide($paid, $this->order(array('test_allowed' => true)))['event']);
		$this->assertSame('paid', $this->decide(array('test' => false) + $paid, $this->order())['event']);
	}

	public function test_second_paid_invoice_of_a_paid_order_is_only_a_comment() {
		$same = $this->decide($this->paid(), $this->order(array('is_paid' => true, 'paid_invoice' => self::INVOICE_ID)));
		$this->assertSame(CoinsPay_Decision::NONE, $same['event']);

		$other = $this->decide($this->paid(), $this->order(array('is_paid' => true, 'paid_invoice' => 'another-invoice')));
		$this->assertSame(CoinsPay_Decision::COMMENT, $other['event']);
		$this->assertStringContainsString('Refund the buyer', $other['note']);
	}

	public function test_expired_unpaid_invoice() {
		$expired = $this->invoice(array('status' => 'expired'));
		$this->assertSame('expired', $this->decide($expired, $this->order())['event']);
		// An older invoice of the order ran out while the buyer pays a newer one.
		$this->assertSame(CoinsPay_Decision::NONE, $this->decide($expired, $this->order(array('is_current' => false)))['event']);
	}

	public function test_cancelled_unpaid_invoice() {
		$decision = $this->decide($this->invoice(array('status' => 'cancelled')), $this->order());
		$this->assertSame('cancelled', $decision['event']);
		$this->assertStringContainsString('was cancelled', $decision['note']);
	}

	public function test_expired_or_cancelled_partially_paid_invoice_needs_a_decision() {
		foreach (array('expired', 'cancelled') as $status) {
			$decision = $this->decide($this->invoice(array('status' => $status, 'amount_paid' => '20')), $this->order(array('is_current' => false)));
			$this->assertSame($status . '_partial', $decision['event'], $status);
			$this->assertStringContainsString('20 USD of 49.9 USD', $decision['note']);
			$this->assertTrue($decision['final']);
		}
	}

	public function test_paid_order_is_not_touched_by_other_states() {
		foreach (array('open', 'expired', 'cancelled') as $status) {
			$this->assertSame(CoinsPay_Decision::NONE, $this->decide($this->invoice(array('status' => $status, 'amount_paid' => '20')), $this->order(array('is_paid' => true)))['event']);
		}
	}

	public function test_comments_in_russian() {
		$decision = $this->decide($this->paid(), $this->order(), 'ru');
		$this->assertStringContainsString('счёт № 12 оплачен: 49.9 USDT, TRON', $decision['note']);
	}

	public function test_fingerprint_changes_with_what_matters_only() {
		$base = CoinsPay_Decision::fingerprint($this->invoice());
		$this->assertSame($base, CoinsPay_Decision::fingerprint($this->invoice(array('amount_paid' => '0.00'))));
		$this->assertSame($base, CoinsPay_Decision::fingerprint($this->invoice(array('expires_at' => '2026-10-29T00:00:00Z'))));
		$this->assertNotSame($base, CoinsPay_Decision::fingerprint($this->invoice(array('amount_pending' => '49.9'))));
		$this->assertNotSame($base, CoinsPay_Decision::fingerprint($this->invoice(array('status' => 'expired'))));
		$this->assertNotSame(
			CoinsPay_Decision::fingerprint($this->invoice(array('payments' => array($this->payment('pending'))))),
			CoinsPay_Decision::fingerprint($this->invoice(array('payments' => array($this->payment('confirmed')))))
		);
	}
}
