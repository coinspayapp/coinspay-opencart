<?php
// The CoinsPay settings screen for OpenCart 1.5 (its own admin layout, no Bootstrap).
$e = function ($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<?php echo $header; ?>
<div id="content">
  <div class="breadcrumb">
    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
    <?php echo $breadcrumb['separator']; ?><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $e($breadcrumb['text']); ?></a>
    <?php } ?>
  </div>
  <?php if (!empty($errors['warning'])) { ?><div class="warning"><?php echo $e($errors['warning']); ?></div><?php } ?>
  <?php if ($success) { ?><div class="success"><?php echo $e($success); ?></div><?php } ?>
  <?php foreach ($warnings as $warning) { ?><div class="attention"><?php echo $e($warning); ?></div><?php } ?>
  <?php if ($test_mode) { ?><div class="attention"><?php echo $e($t['test_mode_notice']); ?></div><?php } ?>
  <div class="box" id="coinspay-settings"
       data-check-url="<?php echo $check_url; ?>" data-refresh-url="<?php echo $refresh_url; ?>"
       data-checking="<?php echo $e($t['checking']); ?>" data-check-failed="<?php echo $e($t['check_failed']); ?>"
       data-copied="<?php echo $e($t['copied']); ?>" data-refresh-failed="<?php echo $e($t['refresh_failed']); ?>">
    <div class="heading">
      <h1><img src="view/image/payment.png" alt="" /> <?php echo $e($t['heading']); ?></h1>
      <div class="buttons"><a onclick="$('#form-coinspay').submit();" class="button"><?php echo $e($t['save']); ?></a> <a href="<?php echo $cancel; ?>" class="button"><?php echo $e($t['cancel']); ?></a></div>
    </div>
    <div class="content">
      <div id="coinspay-tabs" class="htabs">
        <a href="#coinspay-tab-general"><?php echo $e($t['tab_general']); ?></a>
        <a href="#coinspay-tab-statuses"><?php echo $e($t['tab_statuses']); ?></a>
        <a href="#coinspay-tab-invoices"><?php echo $e($t['tab_invoices']); ?></a>
      </div>
      <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-coinspay">
        <div id="coinspay-tab-general">
          <table class="form">
            <tr>
              <td><?php echo $e($t['status']); ?></td>
              <td><select name="<?php echo $prefix; ?>status">
                <option value="1"<?php echo $values['status'] ? ' selected="selected"' : ''; ?>><?php echo $e($t['enabled']); ?></option>
                <option value="0"<?php echo !$values['status'] ? ' selected="selected"' : ''; ?>><?php echo $e($t['disabled']); ?></option>
              </select></td>
            </tr>
            <tr>
              <td><?php echo $e($t['geo_zone']); ?></td>
              <td><select name="<?php echo $prefix; ?>geo_zone_id">
                <option value="0"><?php echo $e($t['all_zones']); ?></option>
                <?php foreach ($geo_zones as $zone) { ?>
                <option value="<?php echo (int)$zone['id']; ?>"<?php echo (int)$zone['id'] === (int)$values['geo_zone_id'] ? ' selected="selected"' : ''; ?>><?php echo $e($zone['name']); ?></option>
                <?php } ?>
              </select></td>
            </tr>
            <tr>
              <td><?php echo $e($t['total']); ?><span class="help"><?php echo $e($t['total_help']); ?></span></td>
              <td><input type="text" name="<?php echo $prefix; ?>total" value="<?php echo $e($values['total']); ?>" /></td>
            </tr>
            <tr>
              <td><?php echo $e($t['sort_order']); ?></td>
              <td><input type="text" name="<?php echo $prefix; ?>sort_order" value="<?php echo $e($values['sort_order']); ?>" size="1" /></td>
            </tr>
            <tr><td colspan="2"><h2><?php echo $e($t['connection']); ?></h2><span class="help"><?php echo $e($t['connection_help']); ?></span></td></tr>
            <tr>
              <td><?php echo $e($t['api_url']); ?><span class="help"><?php echo $e($t['api_url_help']); ?></span></td>
              <td><input type="text" name="<?php echo $prefix; ?>api_url" value="<?php echo $e($values['api_url']); ?>" id="coinspay-api-url" size="60" />
                <?php if (!empty($errors['api_url'])) { ?><span class="error"><?php echo $e($errors['api_url']); ?></span><?php } ?></td>
            </tr>
            <tr>
              <td><span class="required">*</span> <?php echo $e($t['api_key']); ?><span class="help"><?php echo $e($t['api_key_help']); ?></span></td>
              <td><input type="password" name="<?php echo $prefix; ?>api_key" value="" placeholder="<?php echo $saved['api_key'] ? $e($t['saved_secret']) : ''; ?>" id="coinspay-api-key" size="60" autocomplete="new-password" />
                <?php if (!empty($errors['api_key'])) { ?><span class="error"><?php echo $e($errors['api_key']); ?></span><?php } ?></td>
            </tr>
            <tr>
              <td><?php echo $e($t['webhook_url']); ?><span class="help"><?php echo $e($webhook_last ? $webhook_last : $t['webhook_help']); ?></span></td>
              <td><input type="text" value="<?php echo $e($webhook_url); ?>" id="coinspay-webhook-url" size="80" readonly="readonly" /> <a class="button coinspay-copy" data-target="coinspay-webhook-url"><?php echo $e($t['copy']); ?></a></td>
            </tr>
            <tr>
              <td><?php echo $e($t['webhook_secret']); ?><span class="help"><?php echo $e($t['webhook_secret_help']); ?></span></td>
              <td><input type="password" name="<?php echo $prefix; ?>webhook_secret" value="" placeholder="<?php echo $saved['webhook_secret'] ? $e($t['saved_secret']) : ''; ?>" size="60" autocomplete="new-password" />
                <?php if (!empty($errors['webhook_secret'])) { ?><span class="error"><?php echo $e($errors['webhook_secret']); ?></span><?php } ?></td>
            </tr>
            <tr>
              <td><span class="help"><?php echo $e($t['check_help']); ?></span></td>
              <td><a id="coinspay-check" class="button"><?php echo $e($t['check']); ?></a><div id="coinspay-check-result"></div></td>
            </tr>
            <tr><td colspan="2"><h2><?php echo $e($t['invoice']); ?></h2></td></tr>
            <tr>
              <td><?php echo $e($t['lifetime']); ?><span class="help"><?php echo $e($t['lifetime_help']); ?></span></td>
              <td><input type="text" name="<?php echo $prefix; ?>lifetime" value="<?php echo $e($values['lifetime']); ?>" size="4" />
                <?php if (!empty($errors['lifetime'])) { ?><span class="error"><?php echo $e($errors['lifetime']); ?></span><?php } ?></td>
            </tr>
            <?php foreach (array(array('auto_return', 'auto_return', 'auto_return_help'), array('stablecoins_only', 'stablecoins', ''), array('buyer_pays_fee', 'buyer_fee', 'buyer_fee_help'), array('buyer_pays_network', 'buyer_network', '')) as $flag) { ?>
            <tr>
              <td><?php echo $e($t[$flag[1]]); ?><?php if ($flag[2]) { ?><span class="help"><?php echo $e($t[$flag[2]]); ?></span><?php } ?></td>
              <td><input type="checkbox" name="<?php echo $prefix . $flag[0]; ?>" value="1"<?php echo !empty($values[$flag[0]]) ? ' checked="checked"' : ''; ?> /></td>
            </tr>
            <?php } ?>
            <tr><td colspan="2"><h2><?php echo $e($t['test']); ?></h2></td></tr>
            <tr>
              <td><?php echo $e($t['test_buyer_only']); ?><span class="help"><?php echo $e($t['test_buyer_help']); ?></span></td>
              <td><input type="checkbox" name="<?php echo $prefix; ?>test_buyer_only" value="1"<?php echo !empty($values['test_buyer_only']) ? ' checked="checked"' : ''; ?> />
                <?php if ($test_url) { ?><br /><a href="<?php echo $e($test_url); ?>" target="_blank" id="coinspay-test-link"><?php echo $e($t['test_link']); ?></a><?php } ?></td>
            </tr>
            <tr><td colspan="2"><h2><?php echo $e($t['advanced']); ?></h2></td></tr>
            <tr>
              <td><?php echo $e($t['debug']); ?><span class="help"><?php echo $e($t['debug_help']); ?></span></td>
              <td><input type="checkbox" name="<?php echo $prefix; ?>debug" value="1"<?php echo !empty($values['debug']) ? ' checked="checked"' : ''; ?> /></td>
            </tr>
            <?php if ($cron_url) { ?>
            <tr>
              <td><?php echo $e($t['cron']); ?><span class="help"><?php echo $e($t['cron_help']); ?></span></td>
              <td><input type="text" value="<?php echo $e($cron_url); ?>" id="coinspay-cron-url" size="80" readonly="readonly" /> <a class="button coinspay-copy" data-target="coinspay-cron-url"><?php echo $e($t['copy']); ?></a></td>
            </tr>
            <?php } ?>
          </table>
        </div>
        <div id="coinspay-tab-statuses">
          <p class="help"><?php echo $e($t['statuses_help']); ?></p>
          <table class="list">
            <thead><tr><td class="left"><?php echo $e($t['event']); ?></td><td class="left"><?php echo $e($t['order_status']); ?></td></tr></thead>
            <tbody>
              <?php foreach ($events as $event) { ?>
              <tr>
                <td class="left"><?php echo $e($event['label']); ?></td>
                <td class="left">
                  <select name="<?php echo $event['name']; ?>" class="coinspay-event" data-event="<?php echo $event['key']; ?>">
                    <option value=""><?php echo $e($t['choose']); ?></option>
                    <?php if ($event['no_change']) { ?><option value="0"<?php echo $event['value'] === '0' ? ' selected="selected"' : ''; ?>><?php echo $e($t['no_change']); ?></option><?php } ?>
                    <?php if ($event['same_as_paid']) { ?><option value="paid"<?php echo $event['value'] === 'paid' ? ' selected="selected"' : ''; ?>><?php echo $e($t['same_as_paid']); ?></option><?php } ?>
                    <?php foreach ($statuses as $status) { ?>
                    <option value="<?php echo (int)$status['id']; ?>"<?php echo $event['value'] === (string)$status['id'] ? ' selected="selected"' : ''; ?>><?php echo $e($status['name']); ?></option>
                    <?php } ?>
                  </select>
                  <?php if ($event['error']) { ?><span class="error"><?php echo $e($event['error']); ?></span><?php } ?>
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
          <table class="form">
            <tr>
              <td><?php echo $e($t['cancel_statuses']); ?><span class="help"><?php echo $e($t['cancel_statuses_help']); ?></span></td>
              <td><div class="scrollbox" style="height: 180px;">
                <?php $class = 'odd'; foreach ($statuses as $status) { $class = $class === 'odd' ? 'even' : 'odd'; ?>
                <div class="<?php echo $class; ?>"><input type="checkbox" name="<?php echo $prefix; ?>cancel_statuses[]" value="<?php echo (int)$status['id']; ?>"<?php echo in_array((int)$status['id'], $cancel_statuses) ? ' checked="checked"' : ''; ?> /> <?php echo $e($status['name']); ?></div>
                <?php } ?>
              </div></td>
            </tr>
          </table>
        </div>
        <div id="coinspay-tab-invoices">
          <p class="help"><?php echo $e($t['invoices_help']); ?></p>
          <?php if ($invoices) { ?>
          <table class="list">
            <thead>
              <tr>
                <td class="left"><?php echo $e($t['col_order']); ?></td><td class="left"><?php echo $e($t['col_invoice']); ?></td><td class="right"><?php echo $e($t['col_amount']); ?></td>
                <td class="right"><?php echo $e($t['col_paid']); ?></td><td class="left"><?php echo $e($t['col_status']); ?></td><td class="left"><?php echo $e($t['col_created']); ?></td><td></td>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($invoices as $invoice) { ?>
              <tr>
                <td class="left"><a href="<?php echo $order_url; ?>&amp;order_id=<?php echo (int)$invoice['order_id']; ?>">#<?php echo (int)$invoice['order_id']; ?></a></td>
                <td class="left"><a href="<?php echo $e($invoice['pay_url']); ?>" target="_blank"><?php echo (int)$invoice['number']; ?></a><?php echo $invoice['test'] ? ' (' . $e($t['test_badge']) . ')' : ''; ?></td>
                <td class="right"><?php echo $e($invoice['amount']); ?></td>
                <td class="right"><?php echo $e($invoice['paid']); ?></td>
                <td class="left coinspay-invoice-status"><?php echo $e($invoice['status_text']); ?></td>
                <td class="left"><?php echo $e($invoice['created']); ?></td>
                <td class="left"><a class="button coinspay-refresh" data-invoice="<?php echo $e($invoice['invoice_id']); ?>"><?php echo $e($t['refresh']); ?></a> <span class="coinspay-refresh-result"></span></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
          <?php } else { ?>
          <p><?php echo $e($t['no_invoices']); ?></p>
          <?php } ?>
        </div>
      </form>
      <p class="help"><?php echo $e($version); ?></p>
    </div>
  </div>
</div>
<script type="text/javascript"><!--
$('#coinspay-tabs a').tabs();
<?php echo $script; ?>
//--></script>
<?php echo $footer; ?>
