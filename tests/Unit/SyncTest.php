<?php
/**
 * Applying an invoice to its order survives a failed write: the next webhook decides again.
 */

use PHPUnit\Framework\TestCase;

/** The order store: one order, and a history write that fails as many times as asked. */
final class FailingStore extends FakeStore {

	public $failures = 0;
	public $history = array();
	public $status = 1;

	public function order($orderId) {
		return array('id' => $orderId, 'status_id' => $this->status, 'total' => '49.9', 'currency_code' => 'USD', 'currency_value' => 1, 'payment_code' => 'coinspay', 'items' => array());
	}

	public function addHistory($orderId, $statusId, $comment) {
		if ($this->failures > 0) {
			$this->failures--;
			throw new RuntimeException('the order history is not writable');
		}
		$this->history[] = array($statusId, $comment);
		$this->status = $statusId;
		return $statusId;
	}
}

/** The invoice table in memory. */
final class MemoryRepo extends CoinsPay_Repo {

	public $rows = array();

	public function __construct() {
	}

	public function find($invoiceId) {
		return isset($this->rows[$invoiceId]) ? $this->rows[$invoiceId] : null;
	}

	public function current($orderId) {
		return reset($this->rows);
	}

	public function paidInvoice($orderId) {
		foreach ($this->rows as $row) {
			if ($row['paid_applied']) {
				return $row['invoice_id'];
			}
		}
		return '';
	}

	public function pluginStatus($orderId) {
		return 0;
	}

	public function setPluginStatus($orderId, $statusId, $event) {
	}

	public function saveState($invoice) {
	}

	public function setFingerprint($invoiceId, $fingerprint) {
		$this->rows[$invoiceId]['fingerprint'] = $fingerprint;
	}

	public function markPaid($invoiceId) {
		$this->rows[$invoiceId]['paid_applied'] = 1;
	}

	public function setNextPoll($invoiceId, $at) {
	}

	public function kvSet($key, $value) {
	}

	public function lock($orderId) {
		return true;
	}

	public function unlock($orderId) {
	}
}

final class FixedCurrencies extends CoinsPay_Currencies {

	public function __construct() {
	}

	public function scale($code) {
		return 2;
	}
}

final class SyncTest extends TestCase {

	const INVOICE_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

	public function test_a_failed_order_write_does_not_lose_the_payment() {
		$store = new FailingStore();
		$store->settings = CoinsPay_Settings::installDefaults($store->statuses);
		$repo = new MemoryRepo();
		$repo->rows[self::INVOICE_ID] = array('invoice_id' => self::INVOICE_ID, 'order_id' => 7, 'fingerprint' => null, 'paid_applied' => 0, 'test_buyer' => 0);
		$sync = new CoinsPay_Sync($store, new CoinsPay_Settings($store), $repo, new FixedCurrencies(), new CoinsPay_Text('en'));
		$invoice = array(
			'id' => self::INVOICE_ID, 'status' => 'paid', 'test' => false, 'currency' => 'USD', 'amount' => '49.9',
			'amount_paid' => '49.9', 'amount_pending' => '0', 'amount_due' => '0', 'amount_overpaid' => null,
			'paid_late' => false, 'paid_manually' => false, 'payments' => array(),
		);

		$store->failures = 1;
		try {
			$sync->apply($invoice);
			$this->fail('the write did not fail');
		} catch (RuntimeException $e) {
			$this->assertSame('the order history is not writable', $e->getMessage());
		}
		$this->assertNull($repo->rows[self::INVOICE_ID]['fingerprint'], 'no fingerprint after a failed write');
		$this->assertSame(0, $repo->rows[self::INVOICE_ID]['paid_applied'], 'not marked paid after a failed write');

		$this->assertStringStartsWith('paid: status 2', $sync->apply($invoice));
		$this->assertSame(2, $store->status);
		$this->assertSame(1, $repo->rows[self::INVOICE_ID]['paid_applied']);
		$this->assertSame('no change', $sync->apply($invoice));
		$this->assertCount(1, $store->history);
	}
}
