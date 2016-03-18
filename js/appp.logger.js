// initiate apppLogger var if it hasn't been
window.apppLogger = typeof window.apppLogger !== 'undefined' ? window.apppLogger : {};

apppLogger.adminToggleLogging = function(checkbox) {
	var postData = {
        action: 'appptogglelog',
        status: (checkbox.is(':checked'))?'on':'off',
    }

    jQuery.ajax({
        type: "POST",
        data: postData,
        dataType:"json",
        url: ajaxurl,
        //This fires when the ajax 'comes back' and it is valid json
        success: function (response) {
            if( response.status ){
            	if( response.status == 'on' ) {
            		response.status += '. Logging will automatically be turned off in 1 hour and a notice will be emailed to <b>'+response.admin_email+'</b>';
            		console.log(response.expire_logging);
            	}
            	jQuery('<div id="message" class="updated notice new-log-status"><p>Logging is now '+response.status+'.</p></div>').insertBefore( jQuery('.tab-log div.wrap h3:first') );
            	jQuery('.new-log-status').hide().fadeIn(1500);
            	setTimeout(function(){
			        jQuery('.new-log-status').fadeOut(5000).remove();
			    },7500);
            }
        }
        //This fires when the ajax 'comes back' and it isn't valid json
    }).fail(function (data) {
        console.log(data);
    });
}