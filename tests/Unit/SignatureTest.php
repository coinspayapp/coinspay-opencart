<?php
/**
 * Webhook signature. The reference vector is computed by the CoinsPay server code (signPayload).
 */

use PHPUnit\Framework\TestCase;

final class SignatureTest extends TestCase {

	const BODY = '{"id":"0b0e1c1e-6f0c-4c56-9a8e-2d6c1e1f0a01","type":"invoice.paid","created_at":"2026-10-28T08:00:00.000Z","shop_id":"5a1f6b9e-1c2d-4e3f-8a9b-0c1d2e3f4a5b","test":true,"data":{"invoice":{"id":"7c9e6679-7425-40de-944b-e07fc1f90ae7","status":"paid"}}}';
	const SECRET = 'whsec_plugin_test_secret';
	const TIME = 1761638400;
	const HEADER = 't=1761638400,v1=34c05e780e334839328753e5d1b2fa20f7705b67b4489d2f29abda8695af92d9';

	public function test_accepts_the_signature_of_coinspay() {
		$this->assertTrue(CoinsPay_Signature::verify(self::BODY, self::HEADER, self::SECRET, self::TIME));
		// Clocks may differ up to five minutes either way.
		$this->assertTrue(CoinsPay_Signature::verify(self::BODY, self::HEADER, self::SECRET, self::TIME + 300));
		$this->assertTrue(CoinsPay_Signature::verify(self::BODY, self::HEADER, self::SECRET, self::TIME - 300));
		$this->assertTrue(CoinsPay_Signature::verify(self::BODY, ' t=1761638400 , v1=34c05e780e334839328753e5d1b2fa20f7705b67b4489d2f29abda8695af92d9 ', self::SECRET, self::TIME));
	}

	public function test_rejects_a_stale_timestamp() {
		$this->assertFalse(CoinsPay_Signature::verify(self::BODY, self::HEADER, self::SECRET, self::TIME + 301));
		$this->assertFalse(CoinsPay_Signature::verify(self::BODY, self::HEADER, self::SECRET, self::TIME - 301));
	}

	public function test_rejects_another_secret_or_body() {
		$this->assertFalse(CoinsPay_Signature::verify(self::BODY, self::HEADER, 'whsec_other', self::TIME));
		$this->assertFalse(CoinsPay_Signature::verify(str_replace('"paid"', '"open"', self::BODY), self::HEADER, self::SECRET, self::TIME));
		// Re-encoded JSON is other bytes: only the raw body verifies.
		$this->assertFalse(CoinsPay_Signature::verify(json_encode(json_decode(self::BODY, true), JSON_PRETTY_PRINT), self::HEADER, self::SECRET, self::TIME));
	}

	public function test_rejects_malformed_headers() {
		foreach (array('', 'v1=34c05e78', 't=abc,v1=34c05e780e334839328753e5d1b2fa20f7705b67b4489d2f29abda8695af92d9', 't=1761638400', 't=1761638400,v1=XYZ', '=1,t=1761638400,v1=34c05e780e334839328753e5d1b2fa20f7705b67b4489d2f29abda8695af92d9', 'garbage') as $header) {
			$this->assertFalse(CoinsPay_Signature::verify(self::BODY, $header, self::SECRET, self::TIME), $header);
		}
		$this->assertFalse(CoinsPay_Signature::verify(self::BODY, self::HEADER, '', self::TIME));
	}

	public function test_constant_time_comparison_without_hash_equals_gives_the_same_answers() {
		$this->assertTrue(CoinsPay_Util::equals('abc', 'abc'));
		$this->assertFalse(CoinsPay_Util::equals('abc', 'abd'));
		$this->assertFalse(CoinsPay_Util::equals('abc', 'abcd'));
	}
}
