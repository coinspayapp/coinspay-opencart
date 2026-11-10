<?php
/**
 * Client of the CoinsPay merchant API, on cURL (OpenCart requires the extension in every version).
 */
class CoinsPay_ApiClient {

	const TIMEOUT = 15;
	const CONNECT_TIMEOUT = 10;
	const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

	/** @var string API address without the trailing slash, e.g. https://coinspay.app/api/v1 */
	private $base;

	/** @var string cpk_… or cpk_test_… */
	private $key;

	/** @var string */
	private $userAgent;

	public function __construct($base, $key, $userAgent) {
		$this->base = rtrim(trim((string)$base), '/');
		$this->key = trim((string)$key);
		$this->userAgent = (string)$userAgent;
	}

	/**
	 * GET /shop: the shop of the key and the key itself.
	 *
	 * @return array {shop: {id, name, created_at}, key: {test, access}}; "key" is missing from
	 *               servers that predate key access.
	 */
	public function shop() {
		$data = $this->request('GET', '/shop');
		$this->field($data, 'shop');
		return $data;
	}

	/** GET /currencies: fiat and crypto currencies with their scales. */
	public function currencies() {
		return $this->request('GET', '/currencies');
	}

	/**
	 * POST /invoices.
	 *
	 * @param array  $body
	 * @param string $idempotencyKey The same key and body give the same invoice.
	 * @return array Invoice.
	 */
	public function createInvoice($body, $idempotencyKey) {
		return $this->field($this->request('POST', '/invoices', $body, array('Idempotency-Key: ' . $idempotencyKey)), 'invoice');
	}

	/** GET /invoices/{id}. */
	public function invoice($id) {
		return $this->field($this->request('GET', '/invoices/' . $this->id($id)), 'invoice');
	}

	/** POST /invoices/{id}/cancel: an open invoice can no longer be paid. */
	public function cancelInvoice($id) {
		// An empty JSON object: the API refuses a JSON request without a body.
		return $this->field($this->request('POST', '/invoices/' . $this->id($id) . '/cancel', new stdClass()), 'invoice');
	}

	private function id($id) {
		if (!preg_match(self::UUID, (string)$id)) {
			throw new CoinsPay_ApiError(404, 'NOT_FOUND', 'Not an invoice id');
		}
		return strtolower($id);
	}

	private function request($method, $path, $body = null, $headers = array()) {
		if ($this->base === '' || $this->key === '') {
			throw new CoinsPay_ApiError(0, 'NOT_CONFIGURED', 'The CoinsPay API address or key is not set');
		}
		if (!function_exists('curl_init')) {
			throw new CoinsPay_ApiError(0, 'NETWORK', 'The PHP cURL extension is not installed');
		}

		$headers[] = 'Authorization: Bearer ' . $this->key;
		$headers[] = 'Accept: application/json';
		$curl = curl_init($this->base . $path);
		$options = array(
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			// A redirect would resend the key to another address.
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT => self::TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
			CURLOPT_USERAGENT => $this->userAgent,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		);
		if ($body !== null) {
			$headers[] = 'Content-Type: application/json';
			$options[CURLOPT_POSTFIELDS] = CoinsPay_Util::json($body);
		}
		$options[CURLOPT_HTTPHEADER] = $headers;
		curl_setopt_array($curl, $options);

		$raw = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$error = $raw === false ? curl_error($curl) : '';
		// curl_close() does nothing since PHP 8.0 and is deprecated in 8.5.
		if (PHP_VERSION_ID < 80000) {
			curl_close($curl);
		}
		if ($raw === false) {
			throw new CoinsPay_ApiError(0, 'NETWORK', $error);
		}

		$data = json_decode((string)$raw, true);
		if ($status >= 200 && $status < 300 && is_array($data)) {
			return $data;
		}
		$fault = is_array($data) && isset($data['error']) && is_array($data['error']) ? $data['error'] : array();
		throw new CoinsPay_ApiError(
			$status,
			isset($fault['code']) ? (string)$fault['code'] : 'HTTP_' . $status,
			isset($fault['message']) ? (string)$fault['message'] : '',
			isset($fault['details']) && is_array($fault['details']) ? $fault['details'] : array()
		);
	}

	private function field($data, $key) {
		if (!isset($data[$key]) || !is_array($data[$key])) {
			throw new CoinsPay_ApiError(0, 'BAD_RESPONSE', 'The answer has no "' . $key . '"');
		}
		return $data[$key];
	}
}
