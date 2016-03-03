<?php

/**
 * @since 2.1.0
 */

class AppPresser_Remote_Scripts {

	public static $instance = null;
	public static $tab_slug = 'appp-cordova-addons';
	public static $pre_setting_key = 'cordova-remote-js-';

	public static function run() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Setup the Settings
	 * @since  2.1.0
	 */
	public function __construct() {

		if( defined('CORDOVA_JS_ADDONS') && is_numeric( CORDOVA_JS_ADDONS ) ) {
			add_action( 'apppresser_add_settings', array( $this, 'add_settings_tab' ), 60 );
			add_action( 'apppresser_tab_bottom_'.self::$tab_slug, array( $this, 'add_settings' ) );
			add_filter( 'apppresser_sanitize_setting', array( $this, 'appp_sanitize_custom_type' ), 10, 3 );
			add_action( 'apppresser_tab_top_'.self::$tab_slug, array( $this, 'appp_add_some_text' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 8 );
		}
	}

	/**
	 * Add the settings tab
	 * 
	 * @since 2.1.0
	 */
	public function add_settings_tab() {
		$label = __( 'Cordova Add-ons', 'apppresser' );
		AppPresser_Admin_Settings::add_setting_tab( $label, self::$tab_slug );
	}

	/**
	 * Add the settings fields
	 * 
	 * @since 2.1.0
	 */
	public function add_settings($apppresser) {
		
		$label = __('JavaScript URL', 'apppresser');
		$args = array(
			'tab' => self::$tab_slug,
			'description' => __( 'Enter the full URL to the JavaScript you need loaded into your app.', 'apppresser' ),
			'echo' => true,
		);

		for ( $i=1; $i <= CORDOVA_JS_ADDONS; $i++) {
			$key = self::$pre_setting_key.$i;
			$apppresser->add_setting( $key, $label, $args );
		}
	}

	/**
	 * Sanitize the input fields
	 * 
	 * @since 2.1.0
	 */
	public function appp_sanitize_custom_type( $sanitized_value, $key, $value ) {

		$keys = array();

		for ( $i=1; $i <= CORDOVA_JS_ADDONS; $i++) {
			array_push($keys, self::$pre_setting_key.$i);
		}

		if ( in_array($key, $keys) ) {
			$sanitized_value = esc_url_raw( $value, array('http', 'https') );
		}

		return $sanitized_value;
	}

	/**
	 * Enqueue the remote js files
	 * 
	 * The js files will get enqueued and there will be a localized appp_remote_addon_js array
	 * with the URLs for the enqueued files
	 * 
	 * @since 2.1.0
	 */
	public function appp_add_some_text() {

	    $link = sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( 'http://docs.apppresser.com/article/162-adding-apppresser-settings' ), __( 'AppPresser docs', 'appp' ) );
	    ?>
	    <tr>
	        <td colspan="2">
	            <?php printf( __( 'This provides the ability to add JavaScript to the index.html file on your app. Read more at %s.', 'appp' ), $link ); ?>
	        </td>
	    </tr>
	    <?php
	}

	/**
	 * Enqueue the remote js files
	 * 
	 * The js files will get enqueued and there will be a localized appp_remote_addon_js array
	 * with the URLs for the enqueued files
	 * 
	 * @since 2.1.0
	 */
	public function enqueue_scripts() {

		$js_urls = array();

		for ( $i=1; $i <= CORDOVA_JS_ADDONS; $i++) {

			$src_url = appp_get_setting(self::$pre_setting_key.$i);
			if( $src_url ) {
				if ( AppPresser::get_apv( 1 ) ) {
					wp_enqueue_script( 'cordova-addons-'.$i, $src_url );
				}
				array_push($js_urls, $src_url);
			}
		}

		wp_localize_script( 'jquery', 'appp_remote_addon_js', $js_urls );
	}
}
AppPresser_Remote_Scripts::run();