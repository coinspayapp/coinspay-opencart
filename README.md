# CoinsPay for OpenCart

Accept cryptocurrency payments in your OpenCart store. Buyers pay on the CoinsPay payment page, and the money goes on to your own wallets.

## Requirements

OpenCart 1.5 to 4.1, or ocStore 3.0, and a [CoinsPay](https://coinspay.app) account with a shop.

Tested on OpenCart 1.5.6.4, 2.0.3.1, 2.2.0.0, 2.3.0.2, 3.0.3.8, 3.0.5.1, 4.0.1.1, 4.0.2.3 and 4.1.0.4, with PHP 5.6 to 8.5.

## Description

The extension adds a CoinsPay payment method to checkout. The buyer chooses it, goes to the CoinsPay payment page, picks a coin and a network and pays. The order then gets the status you chose for each invoice event, and the buyer returns to your store.

Prices stay in your store currency. CoinsPay converts the total at the current rate and holds the coin amount for 30 minutes, so the rate does not change while the buyer opens their wallet.

### Coins and networks

More than 30 coins and tokens on TRON, Ethereum, BNB Smart Chain, Solana, Bitcoin and Litecoin, among them USDT, USDC, BTC, ETH, BNB, TRX, LTC and SOL. The full list is on [coinspay.app/coins](https://coinspay.app/coins).

### What the extension takes care of

- Each invoice event maps to an order status of your choice, your own custom statuses included. The events cover a payment on its way, partial payment, payment (on time, late, with overpayment or marked paid by hand), expiry and cancellation. A comment with amounts, coins and transaction hashes goes to the order history either way.
- When the store cancels an order, the extension cancels its open invoice, so the buyer can no longer pay it.
- If the paid amount or currency differs from the order, or an order the store cancelled gets paid, the order gets the status you chose for that case and a comment with the details.
- Webhooks are signed and checked. If they do not arrive, the extension asks CoinsPay about open invoices itself.
- The saved API key and webhook secret are never printed into the settings form, so admin users with view-only access cannot read them.
- English and Russian.

### Why CoinsPay

Payments go straight to your wallets. Each network takes up to five payout addresses with percentage shares, for example 70% to you and 30% to a partner, and nothing is kept on the platform.

The fee is as low as 0.3% of each payout: the rate goes down as the monthly turnover of all your shops grows. There is no monthly or setup fee. For the first 10 days after your first payment, payouts up to $500 carry no fee. You can let the buyer pay the CoinsPay fee and the network fee on top of the order total. Details are on the [pricing page](https://coinspay.app/pricing).

## Installation

OpenCart branches differ a lot, so there are five packages:

| OpenCart | Package | How to install |
|---|---|---|
| 1.5.x | `coinspay-opencart-1.5.zip` | Copy the contents of `upload/` to the store root by FTP |
| 2.0 to 2.2 | `coinspay-opencart-2.0-2.2.ocmod.zip` | Copy `upload/` by FTP (the 2.x Extension Installer needs FTP set up) |
| 2.3 | `coinspay-opencart-2.3.ocmod.zip` | Copy `upload/` by FTP, or Extensions → Installer when FTP is set up |
| 3.0.x, ocStore 3.0 | `coinspay-opencart-3.0.ocmod.zip` | Extensions → Installer, upload the package |
| 4.0 to 4.1 | `coinspay.ocmod.zip` | Extensions → Installer, upload, then Install. Keep the file name: OpenCart names the extension folder after it |

Get the packages from the releases of this repository, or build them yourself (see [Development](#development)). Each package includes the installation guide in [English](docs/install-en.md) and [Russian](docs/install-ru.md).

## Configuration

1. Open Extensions → Extensions → Payments (Extensions → Payments in 1.5 and 2.0 to 2.2), find CoinsPay, press Install, then Edit.
2. In the CoinsPay dashboard open your shop:
   - under "API keys" create a key with "Invoices only" access and paste it into "API key". The extension needs nothing more, and such a key cannot withdraw money;
   - leave "API address" empty: the extension then uses `https://coinspay.app/api/v1`;
   - under "Webhook" enter the address the extension shows, save, and copy the `whsec_…` secret into "Webhook secret".
3. Press "Check connection", then "Send test event" in the dashboard. The extension shows when the event arrived.
4. On the "Order statuses" tab choose a status for each invoice event. "Do not change the status" leaves the order as it is.
5. Enable the method and save.

## Test mode

With a test key (`cpk_test_…`) invoices are paid with a button on the payment page and no coins move. By default the method is then shown to the test buyer only: open the store with "Open the store as the test buyer" from the settings (the link works for a day in that browser). A test payment completes only orders of the test buyer.

## When webhooks do not arrive

The extension polls CoinsPay for open invoices:

- from store pages, at most every 5 minutes (OpenCart 2.2 and newer);
- whenever the "Address for cron" from the settings is opened. Add it to your hosting cron every 5 minutes if the store has few visitors, and always on OpenCart 1.5 and 2.0 to 2.1.

In maintenance mode OpenCart 2.3 and 4.x refuse webhooks, and polling catches up afterwards.

## Good to know

- CoinsPay does not use the buyer's selected currency: the invoice is made in the default store currency.
- OpenCart 1.5 subtracts stock when an order is confirmed and does not return it on cancellation. This happens with every payment method.
- Some OpenCart releases have bugs that break any payment method:
  - 4.0.0.0 installed at the site root loses the buyer's session (cookie path `//`). Update to 4.0.2.x or newer;
  - 4.0.1.x sometimes loses the chosen payment method at checkout. Update to 4.0.2.x or newer;
  - 4.0.2.x fails on the order email of an order without shipping when errors are not displayed;
  - 2.2.0.0 prints notices into pages. Set `error_display` to false in `system/config/default.php`.

## Development

The extension is built from one core and five thin adapters:

```
core/              shared core in PHP 5.3 syntax, installed into system/library/coinspay
  Decision.php     what an invoice state means for the order
  Settings.php     settings and the invoice event to order status mapping
  Sync.php         lock, order, decision, order history
  Repo.php         the coinspay_invoice and coinspay_kv tables
  opencart/        the OpenCart layer; Branch.php holds what differs between branches
  lang/            all extension texts
adapters/oc15 … oc4   controllers, models and templates of each branch
adapters/shared/      templates shared by 2.0 to 2.3, OpenCart language files
bin/build.php         builds the five packages into dist/
tests/                unit tests of the core
```

Checks and the build run in Docker:

```bash
P=$(pwd)
docker run --rm -v "$P:/app" -w /app composer:2 install
docker run --rm -v "$P:/app" -w /app php:7.4-cli vendor/bin/phpunit
docker run --rm -v "$P:/app" -w /app php:8.5-cli vendor/bin/phpunit
docker run --rm -v "$P:/app" -w /app php:8.5-cli vendor/bin/phpcs -q
```

`phpcs` checks PHP 5.3 compatibility of the core and of the 1.5 to 3.0 packages. `php bin/build.php` needs PHP with the zip extension.

## Changelog

### 0.1.0

- First release.

## License

GPL-3.0-or-later. The full text is in [LICENSE](LICENSE).
