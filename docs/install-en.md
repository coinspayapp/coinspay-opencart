# CoinsPay for OpenCart — installation

The extension takes crypto payments through CoinsPay: the buyer picks a coin and a network on
the CoinsPay payment page, and the order gets the status you chose for each invoice event.

You need a CoinsPay account with a shop. The API key and the webhook are set up in the CoinsPay
dashboard.

## Which package

| OpenCart | Package | How to install |
|---|---|---|
| 1.5.x | `coinspay-opencart-1.5.zip` | copy the contents of `upload/` to the store root by FTP |
| 2.0 – 2.2 | `coinspay-opencart-2.0-2.2.ocmod.zip` | copy `upload/` by FTP (the 2.x Extension Installer needs FTP set up) |
| 2.3 | `coinspay-opencart-2.3.ocmod.zip` | copy `upload/` by FTP, or Extensions → Installer when FTP is set up |
| 3.0.x, ocStore 3.0 | `coinspay-opencart-3.0.ocmod.zip` | Extensions → Installer → upload the package |
| 4.0 – 4.1 | `coinspay.ocmod.zip` | Extensions → Installer → upload → Install. Keep the file name: OpenCart makes the extension folder from it |

## Setup

1. Extensions → Extensions → Payments (Extensions → Payments in 1.5 and 2.0–2.2) → CoinsPay →
   Install, then Edit.
2. In the CoinsPay dashboard open your shop:
   - under "API keys" issue a key with "Invoices only" access and paste it into "API key". The
     extension needs nothing more, and such a key cannot withdraw money even if an admin user
     reads it;
   - leave "API address" empty: the extension then uses `https://coinspay.app/api/v1`;
   - under "Webhook" enter the address the extension shows, save, and copy the `whsec_…` secret
     into "Webhook secret".
3. Press "Check connection", then "Send test event" in the dashboard: the extension shows when it
   arrived.
4. On the "Order statuses" tab choose the status of each invoice event; your own order statuses
   are in the lists too. "Do not change the status" leaves it as it is; a comment with amounts,
   coins and transaction hashes goes to the order history anyway.
5. Enable the method and save.

## Test mode

With a `cpk_test_` key invoices are paid with a button on the payment page, no coins move. By
default the method then shows to the test buyer only: open the store with "Open the store as the
test buyer" from the settings (the link works for a day in that browser). A test payment completes
only orders of the test buyer.

## When webhooks do not arrive

The extension asks CoinsPay about open invoices itself:
- from store pages, at most every 5 minutes (OpenCart 2.2 and newer);
- when the "Address for cron" from the settings is opened. For a store with few visitors, and for
  OpenCart 1.5 and 2.0–2.1, add it to the cron of your hosting every 5 minutes.

In maintenance mode OpenCart 2.3 and 4.x refuse webhooks; polling catches up afterwards.

## Good to know

- The store cancels an order (a status from "Order cancel statuses"): the extension cancels its
  open invoice and the buyer can no longer pay it. On OpenCart 1.5 polling does this.
- The paid amount or currency differs from the order, or an order the store cancelled is paid: the
  status of the "needs a decision" event and a comment with the details.
- CoinsPay does not take the buyer's currency: the invoice is made in the default currency of the
  store.
- OpenCart 1.5 subtracts the stock when an order is confirmed and does not return it on
  cancellation, with every payment method.
- Bugs of OpenCart itself that break any payment method:
  - 4.0.0.0 at the site root loses the buyer's session (cookie path `//`): update to 4.0.2.x+;
  - 4.0.2.x fails on the order e-mail of an order without shipping when errors are not displayed;
  - 2.2.0.0 prints notices into pages: set `error_display` to false in `system/config/default.php`;
  - 4.0.1.x sometimes loses the chosen payment method at checkout: update to 4.0.2.x+.
