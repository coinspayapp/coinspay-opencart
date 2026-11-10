<?php
/**
 * Loads the CoinsPay core. Plain require_once: OpenCart's autoloaders differ between versions.
 */
if (!class_exists('CoinsPay_Plugin', false)) {
	$coinspay_dir = dirname(__FILE__);
	require_once $coinspay_dir . '/Util.php';
	require_once $coinspay_dir . '/ApiError.php';
	require_once $coinspay_dir . '/ApiClient.php';
	require_once $coinspay_dir . '/Signature.php';
	require_once $coinspay_dir . '/Store.php';
	require_once $coinspay_dir . '/Settings.php';
	require_once $coinspay_dir . '/Text.php';
	require_once $coinspay_dir . '/InvoiceRequest.php';
	require_once $coinspay_dir . '/Decision.php';
	require_once $coinspay_dir . '/Repo.php';
	require_once $coinspay_dir . '/Currencies.php';
	require_once $coinspay_dir . '/Tokens.php';
	require_once $coinspay_dir . '/Sync.php';
	require_once $coinspay_dir . '/Payment.php';
	require_once $coinspay_dir . '/Poller.php';
	require_once $coinspay_dir . '/Webhook.php';
	require_once $coinspay_dir . '/Plugin.php';
	require_once $coinspay_dir . '/opencart/Branch.php';
	require_once $coinspay_dir . '/opencart/Store.php';
	require_once $coinspay_dir . '/opencart/Admin.php';
	require_once $coinspay_dir . '/opencart/Catalog.php';
	unset($coinspay_dir);
}
