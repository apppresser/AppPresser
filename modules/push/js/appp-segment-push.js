window.appp_segments_push = (function (window, document, $, undefined) {

	'use strict';

	var seg = {
		'admin': {},
		'user': {}
	};

	seg.admin.init = function () {
		$('[data-allow-segments="checkbox"]').on('click', function () {
			appp_segments_push.admin.toggle_taxonomies();
		});

		appp_segments_push.admin.toggle_taxonomies();
	};

	seg.admin.toggle_taxonomies = function () {
		if ($('[data-allow-segments="checkbox"]').is(':checked')) {
			$('.toggle-notification-segments').show();
		} else {
			$.each($('.toggle-notification-segments input'), function (a, b) {
				$(b).attr('checked', false);
			});
			$('.toggle-notification-segments').hide();
		}
	};

	seg.user.init = function () {
		$('.notification-signup input:checkbox').on('click', function (event) {
			appp_segments_push.user.update_meta(this);
		});
	};

	seg.user.update_meta = function (checkbox) {

		$('.item-toggle-' + $(checkbox).data('count') + ' .appp-error-msg').hide();

		var postData = {
			action: 'segment_user_meta_update',
			taxonomy: $(checkbox).data('taxonomy'),
			term_id: $(checkbox).data('termId'),
			post_type: $(checkbox).data('postType'),
			status: ($(checkbox).is(':checked')) ? 'on' : 'off',
		};

		$.ajax({
			type: 'POST',
			data: postData,
			dataType: 'json',
			url: apppCore.ajaxurl,
			//This fires when the ajax 'comes back' and it is valid json
			success: function (response) {
				if (response && response.status && (response.status == 'on' || response.status == 'off')) {
					console.log('user preference updated!');
				} else {
					console.log(response);
					$('.item-toggle-' + $(checkbox).data('count') + ' .appp-error-msg').show();
				}
			}
			//This fires when the ajax 'comes back' and it isn't valid json
		}).fail(function (data) {
			$('.item-toggle-' + $(checkbox).data('count') + ' .appp-error-msg').show();
		});
	};

	return seg;

})(window, document, jQuery);

jQuery(document).on('ready load_ajax_content_done', function () {
	if (jQuery('.wp-admin').length) {
		appp_segments_push.admin.init();
	} else {
		// front-end
		appp_segments_push.user.init();
	}
});