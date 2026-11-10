<?php
namespace Opencart\Catalog\Model\Extension\Coinspay\Payment;

/**
 * CoinsPay for OpenCart 4: the payment method at checkout. 4.0.0–4.0.1 ask getMethod(),
 * 4.0.2 and newer getMethods() with an option "coinspay.coinspay".
 */
class Coinspay extends \Opencart\System\Engine\Model {

	private function method(array $address): array {
		require_once DIR_EXTENSION . 'coinspay/system/library/coinspay/loader.php';
		$catalog = new \CoinsPay_OpenCartCatalog($this->registry);
		$method = $catalog->method($address, null);
		return $method ? $method : [];
	}

	public function getMethod(array $address = []): array {
		return $this->method($address);
	}

	public function getMethods(array $address = []): array {
		$method = $this->method($address);
		if (!$method) {
			return [];
		}
		return [
			'code' => 'coinspay',
			'name' => $method['title'],
			'option' => ['coinspay' => ['code' => 'coinspay.coinspay', 'name' => $method['title']]],
			'sort_order' => $method['sort_order'],
		];
	}
}
