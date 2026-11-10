<?php
/**
 * Small helpers of the CoinsPay core. PHP 5.3 syntax: the core runs from OpenCart 1.5 on PHP 5.3 to OpenCart 4 on PHP 8.5.
 */
class CoinsPay_Util {

	/**
	 * Constant-time string comparison (hash_equals came with PHP 5.6).
	 *
	 * @param string $known
	 * @param string $given
	 * @return bool
	 */
	public static function equals($known, $given) {
		$known = (string)$known;
		$given = (string)$given;
		if (function_exists('hash_equals')) {
			return hash_equals($known, $given); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.hash_equalsFound -- guarded by function_exists
		}
		if (strlen($known) !== strlen($given)) {
			return false;
		}
		$diff = 0;
		for ($i = 0, $n = strlen($known); $i < $n; $i++) {
			$diff |= ord($known[$i]) ^ ord($given[$i]);
		}
		return $diff === 0;
	}

	/**
	 * Random hex string.
	 *
	 * @param int $bytes
	 * @return string
	 */
	public static function randomHex($bytes) {
		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes($bytes)); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.random_bytesFound -- guarded by function_exists
		}
		if (function_exists('openssl_random_pseudo_bytes')) {
			$strong = false;
			$random = openssl_random_pseudo_bytes($bytes, $strong);
			if ($random !== false && $strong) {
				return bin2hex($random);
			}
		}
		$hex = '';
		while (strlen($hex) < $bytes * 2) {
			$hex .= sha1(uniqid(mt_rand(), true) . microtime());
		}
		return substr($hex, 0, $bytes * 2);
	}

	/**
	 * Decimal string without trailing zeros: 49.90 → 49.9, 10.00 → 10.
	 *
	 * @param string $amount
	 * @return string
	 */
	public static function normalize($amount) {
		$amount = trim((string)$amount);
		if (strpos($amount, '.') !== false) {
			$amount = rtrim(rtrim($amount, '0'), '.');
		}
		return $amount === '' ? '0' : $amount;
	}

	/**
	 * Amount as the API takes it: at most $scale decimals, no trailing zeros.
	 *
	 * @param float|string $total
	 * @param int          $scale
	 * @return string
	 */
	public static function formatAmount($total, $scale) {
		return self::normalize(number_format((float)$total, max(0, (int)$scale), '.', ''));
	}

	/**
	 * Whether a decimal string is above zero.
	 *
	 * @param string $amount
	 * @return bool
	 */
	public static function positive($amount) {
		return (string)$amount !== '' && (bool)preg_match('/[1-9]/', (string)$amount);
	}

	/**
	 * A scalar field of an array as a string.
	 *
	 * @param array  $data
	 * @param string $field
	 * @return string
	 */
	public static function value($data, $field) {
		return is_array($data) && isset($data[$field]) && is_scalar($data[$field]) ? (string)$data[$field] : '';
	}

	/**
	 * Cuts UTF-8 text to a number of characters, with an ellipsis.
	 *
	 * @param string $text
	 * @param int    $max
	 * @return string
	 */
	public static function truncate($text, $max) {
		$text = (string)$text;
		if (self::length($text) <= $max) {
			return $text;
		}
		return rtrim(self::cut($text, $max - 1)) . "\xE2\x80\xA6";
	}

	/**
	 * Characters in UTF-8 text.
	 *
	 * @param string $text
	 * @return int
	 */
	public static function length($text) {
		if (function_exists('mb_strlen')) {
			return mb_strlen($text, 'UTF-8');
		}
		return strlen(utf8_decode($text));
	}

	/**
	 * First $length characters of UTF-8 text.
	 *
	 * @param string $text
	 * @param int    $length
	 * @return string
	 */
	public static function cut($text, $length) {
		if (function_exists('mb_substr')) {
			return mb_substr($text, 0, $length, 'UTF-8');
		}
		preg_match('/^.{0,' . (int)$length . '}/us', $text, $match);
		return isset($match[0]) ? $match[0] : '';
	}

	/**
	 * JSON for the API (PHP 5.3 has no JSON_UNESCAPED_* flags; the API reads escaped text the same).
	 *
	 * @param mixed $data
	 * @return string
	 */
	public static function json($data) {
		return json_encode($data);
	}
}
