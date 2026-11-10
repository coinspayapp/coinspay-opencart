<?php
/**
 * Webhook signature: x-cpay-signature: t=<unix>,v1=<hex>, HMAC-SHA256 of "<t>.<raw body>" with the whsec_ secret.
 */
class CoinsPay_Signature {

	/** Seconds a signature stays valid either way. */
	const TOLERANCE = 300;

	/**
	 * @param string   $body   Raw request body.
	 * @param string   $header Value of x-cpay-signature.
	 * @param string   $secret whsec_… secret of the shop.
	 * @param int|null $now    Unix time; now by default.
	 * @return bool
	 */
	public static function verify($body, $header, $secret, $now = null) {
		$header = trim((string)$header);
		$secret = (string)$secret;
		if ($header === '' || $secret === '') {
			return false;
		}

		$parts = array();
		foreach (explode(',', $header) as $piece) {
			$eq = strpos($piece, '=');
			if ($eq === false || $eq === 0) {
				return false;
			}
			$parts[trim(substr($piece, 0, $eq))] = trim(substr($piece, $eq + 1));
		}
		if (!isset($parts['t'], $parts['v1']) || !preg_match('/^\d{1,12}$/', $parts['t']) || !preg_match('/^[0-9a-f]{64}$/', $parts['v1'])) {
			return false;
		}

		$now = $now === null ? time() : (int)$now;
		if (abs($now - (int)$parts['t']) > self::TOLERANCE) {
			return false;
		}
		return CoinsPay_Util::equals(hash_hmac('sha256', $parts['t'] . '.' . $body, $secret), $parts['v1']);
	}
}
