<?php
// The CoinsPay settings screen for OpenCart 2.0–2.3 (Bootstrap 3 admin, PHP templates).
$e = function ($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-coinspay" data-toggle="tooltip" title="<?php echo $e($t['save']); ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $e($t['cancel']); ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
      </div>
      <h1><?php echo $e($t['heading']); ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $e($breadcrumb['text']); ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid" id="coinspay-settings"
       data-check-url="<?php echo $check_url; ?>" data-refresh-url="<?php echo $refresh_url; ?>"
       data-checking="<?php echo $e($t['checking']); ?>" data-check-failed="<?php echo $e($t['check_failed']); ?>"
       data-copied="<?php echo $e($t['copied']); ?>" data-refresh-failed="<?php echo $e($t['refresh_failed']); ?>">
    <?php if (!empty($errors['warning'])) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $e($errors['warning']); ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php if ($success) { ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $e($success); ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <?php foreach ($warnings as $warning) { ?>
    <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo $e($warning); ?></div>
    <?php } ?>
    <?php if ($test_mode) { ?>
    <div class="alert alert-info"><i class="fa fa-info-circle"></i> <?php echo $e($t['test_mode_notice']); ?></div>
    <?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $e($t['edit']); ?></h3>
      </div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-coinspay" class="form-horizontal">
          <ul class="nav nav-tabs">
            <li class="active"><a href="#coinspay-tab-general" data-toggle="tab"><?php echo $e($t['tab_general']); ?></a></li>
            <li><a href="#coinspay-tab-statuses" data-toggle="tab"><?php echo $e($t['tab_statuses']); ?></a></li>
            <li><a href="#coinspay-tab-invoices" data-toggle="tab"><?php echo $e($t['tab_invoices']); ?></a></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane active" id="coinspay-tab-general">
              <fieldset>
                <legend><?php echo $e($t['checkout']); ?></legend>
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="coinspay-status"><?php echo $e($t['status']); ?></label>
                  <div class="col-sm-10">
                    <select name="<?php echo $prefix; ?>status" id="coinspay-status" class="form-control">
                      <option value="1"<?php echo $values['status'] ? ' selected="selected"' : ''; ?>><?php echo $e($t['enabled']); ?></option>
                      <option value="0"<?php echo !$values['status'] ? ' selected="selected"' : ''; ?>><?php echo $e($t['disabled']); ?></option>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="coinspay-geo-zone"><?php echo $e($t['geo_zone']); ?></label>
                  <div class="col-sm-10">
                    <select name="<?php echo $prefix; ?>geo_zone_id" id="coinspay-geo-zone" class="form-control">
                      <option value="0"><?php echo $e($t['all_zones']); ?></option>
                      <?php foreach ($geo_zones as $zone) { ?>
                      <option value="<?php echo (int)$zone['id']; ?>"<?php echo (int)$zone['id'] === (int)$values['geo_zone_id'] ? ' selected="selected"' : ''; ?>><?php echo $e($zone['name']); ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="coinspay-total"><?php echo $e($t['total']); ?></label>
                  <div class="col-sm-10">
                    <input type="text" name="<?php echo $prefix; ?>total" value="<?php echo $e($values['total']); ?>" id="coinspay-total" class="form-control" />
                    <span class="help-block"><?php echo $e($t['total_help']); ?></span>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="coinspay-sort-order"><?php echo $e($t['sort_order']); ?></label>
                  <div class="col-sm-10">
                    <input type="text" name="<?php echo $prefix; ?>sort_order" value="<?php echo $e($values['sort_order']); ?>" id="coinspay-sort-order" class="form-control" />
                  </div>
                </div>
              </fieldset>
              <fieldset>
                <legend><?php echo $e($t['connection']); ?></legend>
                <p class="help-block"><?php echo $e($t['connection_help']); ?></p>
                <?php foreach (array(array('api_url', 'text', 'https://coinspay.app/api/v1', false), array('api_key', 'password', $saved['api_key'] ? $t['saved_secret'] : '', true)) as $field) { ?>
                <div class="form-group<?php echo $field[3] ? ' required' : ''; ?><?php echo !empty($errors[$field[0]]) ? ' has-error' : ''; ?>">
                  <label class="col-sm-2 control-label" for="coinspay-<?php echo str_replace('_', '-', $field[0]); ?>"><?php echo $e($t[$field[0]]); ?></label>
                  <div class="col-sm-10">
                    <input type="<?php echo $field[1]; ?>" name="<?php echo $prefix . $field[0]; ?>" value="<?php echo $e($values[$field[0]]); ?>" id="coinspay-<?php echo str_replace('_', '-', $field[0]); ?>" placeholder="<?php echo $e($field[2]); ?>" class="form-control" autocomplete="<?php echo $field[1] === 'password' ? 'new-password' : 'off'; ?>" />
                    <?php if (!empty($errors[$field[0]])) { ?><div class="text-danger"><?php echo $e($errors[$field[0]]); ?></div><?php } ?>
                    <span class="help-block"><?php echo $e($t[$field[0] . '_help']); ?></span>
                  </div>
                </div>
                <?php } ?>
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="coinspay-webhook-url"><?php echo $e($t['webhook_url']); ?></label>
                  <div class="col-sm-10">
                    <div class="input-group">
                      <input type="text" value="<?php echo $e($webhook_url); ?>" id="coinspay-webhook-url" class="form-control" readonly="readonly" />
                      <span class="input-group-btn"><button type="button" class="btn btn-default coinspay-copy" data-target="coinspay-webhook-url"><?php echo $e($t['copy']); ?></button></span>
                    </div>
                    <span class="help-block"><?php echo $e($webhook_last ? $webhook_last : $t['webhook_help']); ?></span>
                  </div>
                </div>
                <div class="form-group<?php echo !empty($errors['webhook_secret']) ? ' has-error' : ''; ?>">
                  <label class="col-sm-2 control-label" for="coinspay-webhook-secret"><?php echo $e($t['webhook_secret']); ?></label>
                  <div class="col-sm-10">
                    <input type="password" name="<?php echo $prefix; ?>webhook_secret" value="" placeholder="<?php echo $saved['webhook_secret'] ? $e($t['saved_secret']) : ''; ?>" id="coinspay-webhook-secret" class="form-control" autocomplete="new-password" />
                    <?php if (!empty($errors['webhook_secret'])) { ?><div class="text-danger"><?php echo $e($errors['webhook_secret']); ?></div><?php } ?>
                    <span class="help-block"><?php echo $e($t['webhook_secret_help']); ?></span>
                  </div>
                </div>
                <div class="form-group">
                  <div class="col-sm-offset-2 col-sm-10">
                    <button type="button" id="coinspay-check" class="btn btn-default"><?php echo $e($t['check']); ?></button>
                    <span class="help-block"><?php echo $e($t['check_help']); ?></span>
                    <div id="coinspay-check-result"></div>
                  </div>
                </div>
              </fieldset>
              <fieldset>
                <legend><?php echo $e($t['invoice']); ?></legend>
                <div class="form-group<?php echo !empty($errors['lifetime']) ? ' has-error' : ''; ?>">
                  <label class="col-sm-2 control-label" for="coinspay-lifetime"><?php echo $e($t['lifetime']); ?></label>
                  <div class="col-sm-10">
                    <input type="text" name="<?php echo $prefix; ?>lifetime" value="<?php echo $e($values['lifetime']); ?>" id="coinspay-lifetime" class="form-control" />
                    <?php if (!empty($errors['lifetime'])) { ?><div class="text-danger"><?php echo $e($errors['lifetime']); ?></div><?php } ?>
                    <span class="help-block"><?php echo $e($t['lifetime_help']); ?></span>
                  </div>
                </div>
                <?php foreach (array(array('auto_return', 'auto_return', 'auto_return_help'), array('stablecoins_only', 'stablecoins', ''), array('buyer_pays_fee', 'buyer_fee', 'buyer_fee_help'), array('buyer_pays_network', 'buyer_network', '')) as $flag) { ?>
                <div class="form-group">
                  <div class="col-sm-offset-2 col-sm-10">
                    <div class="checkbox">
                      <label><input type="checkbox" name="<?php echo $prefix . $flag[0]; ?>" value="1"<?php echo !empty($values[$flag[0]]) ? ' checked="checked"' : ''; ?> /> <?php echo $e($t[$flag[1]]); ?></label>
                    </div>
                    <?php if ($flag[2]) { ?><span class="help-block"><?php echo $e($t[$flag[2]]); ?></span><?php } ?>
                  </div>
                </div>
                <?php } ?>
              </fieldset>
              <fieldset>
                <legend><?php echo $e($t['test']); ?></legend>
                <div class="form-group">
                  <div class="col-sm-offset-2 col-sm-10">
                    <div class="checkbox">
                      <label><input type="checkbox" name="<?php echo $prefix; ?>test_buyer_only" value="1"<?php echo !empty($values['test_buyer_only']) ? ' checked="checked"' : ''; ?> /> <?php echo $e($t['test_buyer_only']); ?></label>
                    </div>
                    <span class="help-block"><?php echo $e($t['test_buyer_help']); ?></span>
                    <?php if ($test_url) { ?><a href="<?php echo $e($test_url); ?>" target="_blank" rel="noopener" id="coinspay-test-link" class="btn btn-default"><?php echo $e($t['test_link']); ?></a><?php } ?>
                  </div>
                </div>
              </fieldset>
              <fieldset>
                <legend><?php echo $e($t['advanced']); ?></legend>
                <div class="form-group">
                  <div class="col-sm-offset-2 col-sm-10">
                    <div class="checkbox">
                      <label><input type="checkbox" name="<?php echo $prefix; ?>debug" value="1"<?php echo !empty($values['debug']) ? ' checked="checked"' : ''; ?> /> <?php echo $e($t['debug']); ?></label>
                    </div>
                    <span class="help-block"><?php echo $e($t['debug_help']); ?></span>
                  </div>
                </div>
                <?php if ($cron_url) { ?>
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="coinspay-cron-url"><?php echo $e($t['cron']); ?></label>
                  <div class="col-sm-10">
                    <div class="input-group">
                      <input type="text" value="<?php echo $e($cron_url); ?>" id="coinspay-cron-url" class="form-control" readonly="readonly" />
                      <span class="input-group-btn"><button type="button" class="btn btn-default coinspay-copy" data-target="coinspay-cron-url"><?php echo $e($t['copy']); ?></button></span>
                    </div>
                    <span class="help-block"><?php echo $e($t['cron_help']); ?></span>
                  </div>
                </div>
                <?php } ?>
              </fieldset>
            </div>
            <div class="tab-pane" id="coinspay-tab-statuses">
              <p class="help-block"><?php echo $e($t['statuses_help']); ?></p>
              <table class="table table-bordered">
                <thead><tr><th><?php echo $e($t['event']); ?></th><th><?php echo $e($t['order_status']); ?></th></tr></thead>
                <tbody>
                  <?php foreach ($events as $event) { ?>
                  <tr<?php echo $event['error'] ? ' class="danger"' : ''; ?>>
                    <td><label for="coinspay-<?php echo $event['key']; ?>"><?php echo $e($event['label']); ?></label></td>
                    <td>
                      <select name="<?php echo $event['name']; ?>" id="coinspay-<?php echo $event['key']; ?>" class="form-control coinspay-event" data-event="<?php echo $event['key']; ?>">
                        <option value=""><?php echo $e($t['choose']); ?></option>
                        <?php if ($event['no_change']) { ?><option value="0"<?php echo $event['value'] === '0' ? ' selected="selected"' : ''; ?>><?php echo $e($t['no_change']); ?></option><?php } ?>
                        <?php if ($event['same_as_paid']) { ?><option value="paid"<?php echo $event['value'] === 'paid' ? ' selected="selected"' : ''; ?>><?php echo $e($t['same_as_paid']); ?></option><?php } ?>
                        <?php foreach ($statuses as $status) { ?>
                        <option value="<?php echo (int)$status['id']; ?>"<?php echo $event['value'] === (string)$status['id'] ? ' selected="selected"' : ''; ?>><?php echo $e($status['name']); ?></option>
                        <?php } ?>
                      </select>
                      <?php if ($event['error']) { ?><div class="text-danger"><?php echo $e($event['error']); ?></div><?php } ?>
                    </td>
                  </tr>
                  <?php } ?>
                </tbody>
              </table>
              <div class="form-group">
                <label class="col-sm-2 control-label"><?php echo $e($t['cancel_statuses']); ?></label>
                <div class="col-sm-10">
                  <div class="well well-sm" style="height: 180px; overflow: auto;">
                    <?php foreach ($statuses as $status) { ?>
                    <div class="checkbox">
                      <label><input type="checkbox" name="<?php echo $prefix; ?>cancel_statuses[]" value="<?php echo (int)$status['id']; ?>"<?php echo in_array((int)$status['id'], $cancel_statuses) ? ' checked="checked"' : ''; ?> /> <?php echo $e($status['name']); ?></label>
                    </div>
                    <?php } ?>
                  </div>
                  <span class="help-block"><?php echo $e($t['cancel_statuses_help']); ?></span>
                </div>
              </div>
            </div>
            <div class="tab-pane" id="coinspay-tab-invoices">
              <p class="help-block"><?php echo $e($t['invoices_help']); ?></p>
              <?php if ($invoices) { ?>
              <div class="table-responsive">
                <table class="table table-bordered table-hover">
                  <thead>
                    <tr>
                      <th><?php echo $e($t['col_order']); ?></th><th><?php echo $e($t['col_invoice']); ?></th><th><?php echo $e($t['col_amount']); ?></th><th><?php echo $e($t['col_paid']); ?></th>
                      <th><?php echo $e($t['col_status']); ?></th><th><?php echo $e($t['col_created']); ?></th><th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($invoices as $invoice) { ?>
                    <tr>
                      <td><a href="<?php echo $order_url; ?>&amp;order_id=<?php echo (int)$invoice['order_id']; ?>">#<?php echo (int)$invoice['order_id']; ?></a></td>
                      <td><a href="<?php echo $e($invoice['pay_url']); ?>" target="_blank" rel="noopener"><?php echo (int)$invoice['number']; ?></a><?php if ($invoice['test']) { ?> <span class="label label-default"><?php echo $e($t['test_badge']); ?></span><?php } ?></td>
                      <td><?php echo $e($invoice['amount']); ?></td>
                      <td><?php echo $e($invoice['paid']); ?></td>
                      <td class="coinspay-invoice-status"><?php echo $e($invoice['status_text']); ?></td>
                      <td><?php echo $e($invoice['created']); ?></td>
                      <td>
                        <button type="button" class="btn btn-default btn-xs coinspay-refresh" data-invoice="<?php echo $e($invoice['invoice_id']); ?>"><?php echo $e($t['refresh']); ?></button>
                        <div class="coinspay-refresh-result small"></div>
                      </td>
                    </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
              <?php } else { ?>
              <p><?php echo $e($t['no_invoices']); ?></p>
              <?php } ?>
            </div>
          </div>
        </form>
        <p class="text-muted small"><?php echo $e($version); ?></p>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript"><?php echo $script; ?></script>
<?php echo $footer; ?>
