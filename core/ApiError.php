<?php
/**
 * An error of the CoinsPay API or of the way to it.
 */
class CoinsPay_ApiError extends RuntimeException {

	/** @var int HTTP status; 0 when no answer came. */
	private $status;

	/** @var string Stable error code of the API, e.g. VALIDATION_ERROR, or NETWORK. */
	private $errorCode;

	/** @var array Error details: invalid fields and the like. */
	private $details;

	/**
	 * @param int    $status    HTTP status, 0 without an answer.
	 * @param string $errorCode API error code.
	 * @param string $message   Explanation in English.
	 * @param array  $details   Error details.
	 */
	public function __construct($status, $errorCode, $message, $details = array()) {
		parent::__construct((string)$message !== '' ? (string)$message : (string)$errorCode);
		$this->status = (int)$status;
		$this->errorCode = (string)$errorCode;
		$this->details = is_array($details) ? $details : array();
	}

	public function status() {
		return $this->status;
	}

	public function errorCode() {
		return $this->errorCode;
	}

	public function details() {
		return $this->details;
	}

	/**
	 * The invoice does not exist for this key: another shop, the other mode or a sample event.
	 *
	 * @return bool
	 */
	public function isNotFound() {
		return $this->status === 404;
	}

	/**
	 * One line for the log: code, message and details, never the key.
	 *
	 * @return string
	 */
	public function summary() {
		$line = $this->errorCode . ': ' . $this->getMessage();
		if ($this->details) {
			$line .= ' ' . CoinsPay_Util::json($this->details);
		}
		return $line;
	}
}
