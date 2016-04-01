/**
 * You can upload JavaScript files that interact with cordova plugins by uploading them
 * in AppPresser settings in the WordPress admin.
 * 
 * It's all about timing:
 * Depending on your implementation you need to call your own code once the proper events have happened or message have been received
 * 
 * Here are some helpful events from the app:  deviceready, apppinit
 * Here are some helpful postMessages from the iframed web site: site_loaded, remote_pg_loaded, load_ajax_content_done
 * 
 * Sequence of events and messages:
 * deviceready - event (when the app is ready, but this is trigger before this file is loaded)
 * site_loaded - message (when the theme's custom.js has loaded)
 * remote_pg_loaded - message (when the AppPresser plugin's apppresser2-plugins.js has loaded)
 * apppinit - event (triggers after both site_loaded && remote_pg_loaded have occurred)
 * load_ajax_content_done - message (after new content has loaded a new page)
 */

// apppinit is triggered after both site_loaded && remote_pg_loaded have occurred
document.addEventListener('apppinit', test_ss_plugin_exists, false);

// Get message sent from the website in the index.html iframe
window.addEventListener("message", getPostMessage, false);

/**
 * This will capture any messages sent from your website (iframe) using
 * parent.postMessage('my_message', '*');
 */
function getPostMessage(e) {
	console.log(e.data);

	switch(e.data) {
		case 'init_my_code':
			// this message happens too soon to be used in this addon file
			// do something now that the theme's custom.js has loaded
			test_ss_plugin_exists(e);
			break;
		case 'load_ajax_content_done':
			// do something now that the content of a new page has loaded
			test_ss_plugin_exists(e);
			break;
	}
}

/**
 * Test if your plugin exists before using it
 */
function test_ss_plugin_exists(event) {

	test_events_and_messages(event);

	if( typeof window.plugins !== 'undefined' && typeof window.plugins.socialsharing !== 'undefined' ) {
		console.log('Socialsharing plugin is ready.');
		do_stuff();
	} else {
		console.log('Socialsharing plugin is not ready.');
	}
}

/**
 * This is what you want to accomplish, but the timing when 
 * it occurs will be specific to your implementation.
 */
var stuff_is_done = false;
function do_stuff() {
	if( stuff_is_done ) {
		return;
	}
	console.log('Doing Stuff');
	stuff_is_done = true;
}

/**
 * 
 * Use this only during development to test your code.
 * 
 * Test events and messages to determine their order triggered or received.
 * This will help you determine the right time to call your own code.
 */
function test_events_and_messages(event) {
	if( typeof event !== 'undefined' ) {
		if( event.type == 'message' ) {
			if( event.data == 'load_ajax_content_done') {
				where_are_you('load_ajax_content_done');
			} else if( event.data == 'init_my_code' ) {
				where_are_you('init_my_code');
			}
		} else if(event.type == 'apppinit') {
			where_are_you('apppinit');
		}
	}
}

function where_are_you( when ) {
	var iframewin = document.getElementById('myApp').contentWindow;

	console.log('After ' + when + ', I am at ' + iframewin.location.href);
}

where_are_you('sample-cordova-addon.js loaded');
test_ss_plugin_exists();

/**************************************************************
 * Place this on your own web site wp_footer
 * Note: this will probably happen too quickly before sample-cordova-addon.js is ready
 * my_init_code
 **************************************************************/
// <script type="text/javascript">
// (function(window, document, $, undefined) {
// 	$(document).ready(function () {
// 		parent.postMessage('init_my_code', '*');
// 	});
// })(window, document, jQuery);
// </script>