<?php
// The CoinsPay confirm step for OpenCart 1.5.
$e = function ($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<?php if ($test_mode) { ?>
<div class="attention"><?php echo $e($texts['test']); ?></div>
<?php } ?>
<p><?php echo $e($texts['description']); ?></p>
<div class="buttons">
  <div class="right"><input type="button" value="<?php echo $e($button_confirm); ?>" id="button-confirm" class="button" /></div>
</div>
<script type="text/javascript"><!--
$('#button-confirm').bind('click', function() {
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
			$('#button-confirm').attr('disabled', true);
			$('#button-confirm').after('<span class="wait">&nbsp;<?php echo $e($text_loading); ?></span>');
		},
		success: function(json) {
			if (json['redirect']) {
				location = json['redirect'];
				return;
			}
			$('#button-confirm').attr('disabled', false);
			$('.wait').remove();
			if (json['error']) {
				alert(json['error']);
			}
		},
		error: function(xhr, ajaxOptions, thrownError) {
			$('#button-confirm').attr('disabled', false);
			$('.wait').remove();
			alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
		}
	});
});
//--></script>
