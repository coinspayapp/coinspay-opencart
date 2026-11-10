<?php
/**
 * What the core needs from OpenCart. Each branch (1.5, 2.0–2.2, 2.3, 3.0, 4.x) has its own
 * adapter: paths, setting keys, order calls and routes differ between them.
 */
interface CoinsPay_Store {

	/**
	 * Runs SQL; rows for SELECT, an empty array otherwise.
	 *
	 * @param string $sql
	 * @return array
	 */
	public function query($sql);

	/** Escapes a value for SQL inside quotes. */
	public function escape($value);

	/** Table prefix (DB_PREFIX). */
	public function prefix();

	/**
	 * A plugin setting by its key without the branch prefix ("api_key", not "payment_coinspay_api_key").
	 *
	 * @param string $key
	 * @return mixed Null when not set.
	 */
	public function setting($key);

	/** Default currency of the store (config_currency). */
	public function defaultCurrency();

	/** Default order status of the store (config_order_status_id): the fallback for a new order. */
	public function defaultOrderStatus();

	/**
	 * The order, or null.
	 *
	 * @param int $orderId
	 * @return array|null {id, status_id, total, currency_code, currency_value, payment_code, items: [{name, quantity}]}.
	 *                    total is in the default currency, as OpenCart stores it.
	 */
	public function order($orderId);

	/**
	 * Adds an order history entry the customer is not notified about. $statusId is the status
	 * to set; the current one for a comment only. The first status of a new order (status 0)
	 * confirms it the way the branch does.
	 *
	 * @param int    $orderId
	 * @param int    $statusId
	 * @param string $comment
	 * @return int The order status afterwards: fraud extensions may set another one.
	 */
	public function addHistory($orderId, $statusId, $comment);

	/** Link the buyer comes back by from the payment page. */
	public function returnUrl($orderId, $token);

	/** Address of the webhook route. */
	public function webhookUrl();

	/** Address of the keyed polling route. */
	public function cronUrl($key);

	/** Store address: part of the idempotency key, so a staging copy never gets the live store's invoices. */
	public function siteUrl();

	/** Language of order comments and admin messages: en or ru. */
	public function adminLanguage();

	/** Language of the payment page for the buyer: en or ru. */
	public function buyerLanguage();

	/** User-Agent of API requests. */
	public function userAgent();

	/**
	 * Writes to the log of the store.
	 *
	 * @param string $message
	 * @param bool   $error   Errors are logged always, the rest with the debug setting only.
	 */
	public function log($message, $error);
}
