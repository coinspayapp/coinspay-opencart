<?php
// The CoinsPay confirm step for OpenCart 2.0–2.3 (Bootstrap 3 storefront, PHP templates).
$e = function ($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<?php if ($test_mode) { ?>
<div class="alert alert-info"><?php echo $e($texts['test']); ?></div>
<?php } ?>
<p><?php echo $e($texts['description']); ?></p>
<div class="buttons">
  <div class="pull-right">
    <input type="button" value="<?php echo $e($button_confirm); ?>" id="button-confirm" data-loading-text="<?php echo $e($text_loading); ?>" class="btn btn-primary" />
  </div>
</div>
<script type="text/javascript"><!--
$('#button-confirm').on('click', function() {
	$.ajax({
		url: '<?php echo $confirm_url; ?>',
		type: 'post',
		dataType: 'json',
		// Old stores on new PHP print notices before the answer: the JSON is taken from its end.
		dataFilter: function (text) {
			var start = text.lastIndexOf('{"');
			return start > 0 ? text.slice(start) : text;
		},
		beforeSend: function() {
			$('#button-confirm').button('loading');
		},
		success: function(json) {
			if (json['redirect']) {
				location = json['redirect'];
				return;
			}
			$('#button-confirm').button('reset');
			if (json['error']) {
				alert(json['error']);
			}
		},
		error: function(xhr, ajaxOptions, thrownError) {
			$('#button-confirm').button('reset');
			alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
		}
	});
});
//--></script>
