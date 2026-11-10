<?php
/**
 * CoinsPay for OpenCart 3.0: the payment method at checkout.
 */
class ModelExtensionPaymentCoinspay extends Model {

	public function getMethod($address, $total) {
		require_once DIR_SYSTEM . 'library/coinspay/loader.php';
		$catalog = new CoinsPay_OpenCartCatalog($this->registry);
		$method = $catalog->method($address, $total);
		return $method ? $method : array();
	}
}
