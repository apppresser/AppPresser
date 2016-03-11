<?php

/**
 * @since 2.1.0
 */

class AppPresser_Remote_Scripts {

	public static $instance = null;
	public static $tab_slug = 'appp-cordova-addons';
	public static $pre_setting_key = 'cordova-remote-js-';
	private static $public_nonce_key = 'apg-js-nonce';

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
		add_action( 'apppresser_tab_bottom_general', array( $this, 'file_upload_admin_setting' ) );
		add_action( 'apppresser_tab_top_'.self::$tab_slug, array( $this, 'appp_add_some_text' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 8 );
		add_action( 'init', array( $this, 'handle_upload' ) );
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
	public function file_upload_admin_setting($apppresser) {
		
		
		?>
		<tr valign="top" class="apppresser-facebook-connect">
			<th colspan="2" scope="row" class="appp-section-title">
				<h3>Add Custom JavaScript to Your App</h3>
			</th>
		</tr>
		<tr>
			<th scope="row">
				<label for="apppresser-appfbconnect_appid">Upload .js file</label>
				<a class="help" href="#" title="AppPresser 2.0+ only">?</a>
			</th>
			<td>
				<p>
					<label for="apg-js-file">
						Select .js file to upload:
					</label>
					<input type="file" id="apg-js-file" name="apg-js-file" value="" />
					<?php wp_nonce_field( plugin_basename( __FILE__ ), self::$public_nonce_key ); ?>
					<p class="description">Learn more in <a href="http://docs.apppresser.com" target="_blank">our documentation.</a></p>
				</p>
				<script type="text/javascript">
					jQuery('form').attr('enctype', 'multipart/form-data');
				</script>
			</td>
		</tr>
		<?php
	}

	public function handle_upload() {

		$file_id = 'apg-js-file';

		if( $this->validate_upload( $file_id, self::$public_nonce_key ) ) {
			add_filter('upload_mimes', array( $this, 'add_upload_mimes' ) );
			$file = wp_upload_bits( $_FILES[$file_id]['name'], null, @file_get_contents( $_FILES[$file_id]['tmp_name'] ) );
			remove_filter('upload_mimes', array( $this, 'add_upload_mimes' ) );

			if( $file['error'] ) {
				$this->handle_upload_error();
			} else {
				$this->set_upload_settings( $file['url'] );
			}
		}
	}

	public function set_upload_settings( $url ) {
		$options = $this->get_upload_settings();

		if( is_array($options) && !empty($options) ) {
			array_push($options, $url);
		} else {
			$options = array( $url );
		}

		update_option( 'ap2-remote-js', serialize( $options ) );

	}

	public function get_upload_settings() {
		$options = get_option('ap2-remote-js');

		if( is_string($options) ) {
			return unserialize($options);
		}

		return false;
	}

	public function handle_upload_error() {
		// @TODO:
	}

	public function add_upload_mimes( $mimes ) {
		$mimes['js'] = 'application/x-javascript';

		return $mimes;
	}

	/**
	 * Validates both the $_FILES and nonce
	 * 
	 * @param string $file_id indexed name for the file upload field
	 * @param string $nonce Nonce key
	 * @param string $nonce_action Nonce action to verify
	 */
	function validate_upload( $file_id, $public_nonce_key ) {

		$is_valid_nonce = ( isset( $_POST[ $public_nonce_key ] ) && wp_verify_nonce( $_POST[ $public_nonce_key ], plugin_basename( __FILE__ ) ) );
		$is_valid_upload = ( ! empty( $_FILES ) ) && isset( $_FILES[ $file_id ] );

		return ( $is_valid_upload && $is_valid_nonce );
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

		$js_urls = $this->get_upload_settings();

		if( !empty($js_urls) ) {
			wp_localize_script( 'jquery', 'appp_remote_addon_js', $js_urls );
		}

	}
}
AppPresser_Remote_Scripts::run();