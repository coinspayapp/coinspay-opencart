<?php
/**
 * The map "invoice event → order status" and the plugin's signed links.
 */

use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	private function settings(array $values, array $statuses = array(1, 2, 5, 7, 8, 16)) {
		$store = new FakeStore();
		$store->settings = $values;
		$store->statuses = $statuses;
		return new CoinsPay_Settings($store);
	}

	public function test_install_defaults_use_the_standard_statuses_the_store_has() {
		$values = CoinsPay_Settings::installDefaults(array(1, 2, 5, 7, 8, 16));
		$this->assertSame(1, $values['status_created']);
		$this->assertSame(0, $values['status_pending']);
		$this->assertSame(2, $values['status_paid']);
		$this->assertSame('paid', $values['status_paid_late']);
		$this->assertSame(7, $values['status_expired']);
		$this->assertSame('7,8,16', $values['cancel_statuses']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $values['secret']);

		// The store deleted Canceled: the expiry events wait for the owner's choice.
		$values = CoinsPay_Settings::installDefaults(array(1, 2, 5, 8));
		$this->assertSame('', $values['status_expired']);
		$this->assertSame('', $values['status_cancelled']);
		$this->assertSame('8', $values['cancel_statuses']);
	}

	public function test_status_of_an_event() {
		$settings = $this->settings(array(
			'status_created' => '1',
			'status_paid' => '17',
			'status_paid_late' => 'paid',
			'status_pending' => '0',
			'status_expired' => '7',
		), array(1, 7, 17));

		// A status of the store's own, chosen for "paid".
		$this->assertSame(17, $settings->statusFor('paid'));
		$this->assertSame(17, $settings->statusFor('paid_late'));
		$this->assertSame(CoinsPay_Settings::NO_CHANGE, $settings->statusFor('pending'));
		$this->assertSame(7, $settings->statusFor('expired'));
		// Not set.
		$this->assertSame(CoinsPay_Settings::INVALID, $settings->statusFor('mismatch'));
	}

	public function test_deleted_or_impossible_statuses_are_invalid() {
		$settings = $this->settings(array(
			'status_paid' => '17',
			'status_created' => '0',
			'status_mismatch' => 'paid',
			'status_expired' => 'abc',
		), array(1, 7));

		// The owner deleted the status chosen for "paid".
		$this->assertSame(CoinsPay_Settings::INVALID, $settings->statusFor('paid'));
		// "No change" is not allowed for a new order: it would stay out of the order list.
		$this->assertSame(CoinsPay_Settings::INVALID, $settings->statusFor('created'));
		// "Same as paid" only for the paid events.
		$this->assertSame(CoinsPay_Settings::INVALID, $settings->statusFor('mismatch'));
		$this->assertSame(CoinsPay_Settings::INVALID, $settings->statusFor('expired'));
		$this->assertContains('paid', $settings->brokenEvents());
	}

	public function test_cancel_statuses() {
		$this->assertSame(array(7, 8, 21), $this->settings(array('cancel_statuses' => '7, 8,x,0,21'))->cancelStatuses());
		$this->assertSame(array(), $this->settings(array())->cancelStatuses());
	}

	public function test_lifetime_and_mode() {
		$this->assertSame(60, $this->settings(array())->lifetime());
		$this->assertSame(15, $this->settings(array('lifetime' => '3'))->lifetime());
		$this->assertTrue($this->settings(array('api_key' => 'cpk_test_abc'))->isTestMode());
		$this->assertFalse($this->settings(array('api_key' => 'cpk_abc'))->isTestMode());
	}

	public function test_signed_links() {
		$tokens = new CoinsPay_Tokens('secret-one');
		$this->assertTrue($tokens->checkReturn(42, $tokens->returnToken(42)));
		$this->assertFalse($tokens->checkReturn(43, $tokens->returnToken(42)));
		$this->assertFalse((new CoinsPay_Tokens('secret-two'))->checkReturn(42, $tokens->returnToken(42)));
		$this->assertFalse((new CoinsPay_Tokens(''))->checkReturn(42, ''));

		$this->assertTrue($tokens->checkCron($tokens->cronKey()));
		$this->assertFalse($tokens->checkCron('nope'));

		$link = $tokens->testBuyer(1000);
		$this->assertTrue($tokens->checkTestBuyer($link, 2000));
		$this->assertFalse($tokens->checkTestBuyer($link, 1000 + CoinsPay_Tokens::TEST_BUYER_TTL + 1));
		$this->assertFalse($tokens->checkTestBuyer('99999999999.' . str_repeat('0', 32), 2000));
	}

	public function test_an_empty_api_address_means_coinspay_app() {
		foreach (array(array(), array('api_url' => ''), array('api_url' => '  ')) as $values) {
			$settings = $this->settings($values + array('api_key' => 'cpk_live_key'));
			$this->assertSame('https://coinspay.app/api/v1', $settings->apiUrl());
			$this->assertTrue($settings->configured());
		}
		$this->assertSame('https://pay.example/api/v1', $this->settings(array('api_url' => ' https://pay.example/api/v1/ '))->apiUrl());
		$this->assertFalse($this->settings(array())->configured());
	}
}
