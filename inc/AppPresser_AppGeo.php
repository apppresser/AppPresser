<?php


class AppPresser_AppGeo {
	/**
	 * Display a message if the GPS timesout
	 */
	public static function geo_timeout_error_js() {

		$title = __('GPS Failed', 'apppresser');
		$message = __('Your GPS did not respond. Be sure you have your GPS enabled and try again.', 'apppresser');
		$button_text = __('Try Again', 'apppresser');

		?>
	<script type="text/javascript">

	jQuery('body').on('geo_timeout_error', onGeo_timeout_error);

	function onGeo_timeout_error() {
		console.warn('The geolocationGetCurrent timed out');

		var html  = '<h2 style="margin-top:36px;"><?php echo $title ?></h2>';
			html += '<p><?php echo $message ?></p>';
			html += '<button onclick="AppGeo_getLoc()"><?php echo $button_text ?></button>';
			html += '<div style="height:1000px"></div>'; // a little hack to hide the checkin button without really tampering with it.

		jQuery('#map-canvas').html(html);

	}

	</script>
		<?php
	}



	/**
	 * Don't use a default location on the checkin map
	 */
	public static function geo_no_default_position() {
		return false;
	}
}

add_action( 'wp_footer', array( 'AppPresser_AppGeo', 'geo_timeout_error_js'), 10 );
add_filter( 'appgeo_default_position', array( 'AppPresser_AppGeo', 'geo_no_default_position'), 9 );


/**
 * Removable hooks:
 * 
 * Add this to your own plugin
 */
// function remove_appgeo_actions() {
// 	remove_action( 'wp_footer', array( 'AppPresser_AppGeo', 'geo_timeout_error_js') );
// 	remove_filter( 'appgeo_default_position', array( 'AppPresser_AppGeo', 'geo_no_default_position') );
// }

// add_action( 'init', 'remove_appgeo_actions' );