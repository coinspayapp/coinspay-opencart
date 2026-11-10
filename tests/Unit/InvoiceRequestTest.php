<?php
/**
 * Body of the invoice request and its idempotency key.
 */

use PHPUnit\Framework\TestCase;

final class InvoiceRequestTest extends TestCase {

	private function order(array $overrides = array()) {
		return $overrides + array(
			'currency' => 'usd',
			'amount' => '49.9',
			'number' => '1042',
			'items' => array(
				array('name' => 'Espresso beans &amp; grinder', 'quantity' => 2),
				array('name' => '<b>Delivery</b>', 'quantity' => '1.000'),
			),
			'return_url' => 'https://shop.example/index.php?route=extension/payment/coinspay/back&order_id=1042&token=abc',
		);
	}

	private function options(array $overrides = array()) {
		return $overrides + array('lifetime' => 60, 'stablecoins_only' => false, 'buyer_pays_fee' => false, 'buyer_pays_network' => false, 'auto_return' => true);
	}

	public function test_amount_follows_the_currency_precision() {
		$this->assertSame('49.9', CoinsPay_Util::formatAmount('49.90', 2));
		$this->assertSame('10', CoinsPay_Util::formatAmount('10.00', 2));
		$this->assertSame('1235', CoinsPay_Util::formatAmount('1234.5', 0));
		$this->assertSame('19.99', CoinsPay_Util::formatAmount('19.989', 2));
		$this->assertSame('0.00012345', CoinsPay_Util::formatAmount('0.00012345', 8));
		$this->assertSame('0', CoinsPay_Util::normalize('0.000'));
	}

	public function test_body_of_an_order() {
		$this->assertSame(
			array(
				'currency' => 'USD',
				'amount' => '49.9',
				'reference' => '1042',
				'lifetime_minutes' => 60,
				'description' => "Espresso beans & grinder \xC3\x97 2, Delivery \xC3\x97 1",
				'return_url' => 'https://shop.example/index.php?route=extension/payment/coinspay/back&order_id=1042&token=abc',
				'auto_return' => true,
			),
			CoinsPay_InvoiceRequest::body($this->order(), $this->options())
		);
	}

	public function test_flags_and_limits() {
		$body = CoinsPay_InvoiceRequest::body(
			$this->order(array('items' => array())),
			$this->options(array('lifetime' => 5, 'stablecoins_only' => true, 'buyer_pays_fee' => true, 'buyer_pays_network' => true, 'auto_return' => false))
		);
		$this->assertSame(15, $body['lifetime_minutes']);
		$this->assertTrue($body['stablecoins_only']);
		$this->assertTrue($body['buyer_pays_fee']);
		$this->assertTrue($body['buyer_pays_network']);
		$this->assertArrayNotHasKey('auto_return', $body);
		$this->assertArrayNotHasKey('description', $body);
		$this->assertSame(1440, CoinsPay_InvoiceRequest::body($this->order(), $this->options(array('lifetime' => 100000)))['lifetime_minutes']);
	}

	public function test_a_too_long_return_link_is_left_out_with_auto_return() {
		$body = CoinsPay_InvoiceRequest::body($this->order(array('return_url' => 'https://shop.example/?token=' . str_repeat('a', 500))), $this->options());
		$this->assertArrayNotHasKey('return_url', $body);
		$this->assertArrayNotHasKey('auto_return', $body);
	}

	public function test_long_texts_are_cut_by_characters() {
		$items = array();
		for ($i = 0; $i < 80; $i++) {
			$items[] = array('name' => 'Кофейные зёрна сорта №' . $i, 'quantity' => 1);
		}
		$body = CoinsPay_InvoiceRequest::body($this->order(array('items' => $items, 'number' => str_repeat('9', 300))), $this->options());
		$this->assertSame(1000, mb_strlen($body['description'], 'UTF-8'));
		$this->assertSame("\xE2\x80\xA6", mb_substr($body['description'], -1, 1, 'UTF-8'));
		$this->assertSame(190, mb_strlen($body['reference'], 'UTF-8'));
		$this->assertTrue(mb_check_encoding($body['description'], 'UTF-8'));
	}

	public function test_idempotency_key_is_stable_per_body_and_attempt() {
		$body = CoinsPay_InvoiceRequest::body($this->order(), $this->options());
		$key = CoinsPay_InvoiceRequest::idempotencyKey('https://shop.example/', 1042, 1, $body);
		$this->assertMatchesRegularExpression('/^[\x21-\x7e]{8,128}$/', $key);
		$this->assertSame($key, CoinsPay_InvoiceRequest::idempotencyKey('https://shop.example/', 1042, 1, $body));
		$this->assertNotSame($key, CoinsPay_InvoiceRequest::idempotencyKey('https://shop.example/', 1042, 2, $body));
		$this->assertNotSame($key, CoinsPay_InvoiceRequest::idempotencyKey('https://staging.shop.example/', 1042, 1, $body));
		$body['amount'] = '59.9';
		$this->assertNotSame($key, CoinsPay_InvoiceRequest::idempotencyKey('https://shop.example/', 1042, 1, $body));
	}
}
