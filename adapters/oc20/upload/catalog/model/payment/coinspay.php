<?php
/**
 * CoinsPay for OpenCart 2.0–2.2: the payment method at checkout.
 */
class ModelPaymentCoinspay extends Model {

	public function getMethod($address, $total) {
		require_once DIR_SYSTEM . 'library/coinspay/loader.php';
		$catalog = new CoinsPay_OpenCartCatalog($this->registry);
		$method = $catalog->method($address, $total);
		return $method ? $method : array();
	}
}
