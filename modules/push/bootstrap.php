<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: AppPush
Plugin URI: http://apppresser.com
Description: Push Notifications for AppPresser
Text Domain: apppresser-push
Domain Path: /languages
Requires Plugins: apppresser
Version: 4.2.2
Author: AppPresser Team
Author URI: http://apppresser.com
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class AppPresser_Notifications {

	// A single instance of this class.
	public static $instance    = null;
	public static $this_plugin = null;
	public static $plugin_url  = null;
	public static $plugin_path = null;
	public static $sc_done     = false;
	public static $cpt         = 'apppush';
	public static $plugin_slug = 'apppush';
	public $push_settings;
	public $push_cpt;
	public $push_update;
	public $push_image;
	public $push_log;
	public $push_log_cpt;
	public $push_segment;
	const VERSION = '4.2.2';

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since  0.1.0
	 * @return AppPresser_Notification A single instance of this class.
	 */
	public static function go() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {

		self::$this_plugin = plugin_basename( __FILE__ );
		self::$plugin_url  = trailingslashit( plugins_url( '', __FILE__ ) );
		self::$plugin_path = plugin_dir_path( __FILE__ );
		// The module manager has already verified core and legacy conflicts.
		add_action( 'init', array( $this, 'includes' ), 0 );

		// Register notifications js
		add_action( 'wp_enqueue_scripts', array( $this, 'push_scripts_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_script_styles' ) );

		// Save registered device id
		add_action( 'wp_ajax_ap3_add_device_id', array( $this, 'notifications_add_device_id' ) );
		add_action( 'wp_ajax_nopriv_ap3_add_device_id', array( $this, 'notifications_add_device_id' ) );


	}

	// register for plugin updates
	public function updater() {
		// Bundled modules are updated exclusively with AppPresser core.
	}



	/*
	Add device for Notifications
	*/
	public function notifications_add_device_id() {

		global $user_ID;
		if ( $user_ID ) {
			if ( isset( $_GET['endpoint'] ) ) {
				update_user_meta( $user_ID, 'ap3_endpoint_arns', $_GET['endpoint'] );
				wp_send_json_success( $_GET['endpoint'] );
			} else {
				wp_send_json_error( 'Failed to save device id.' );
			}
		}
	}



	public function includes() {

		// Load translations
		load_plugin_textdomain( 'apppresser-push', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		require_once self::$plugin_path . 'inc/AppPresser_API_Response_Viewer.php';

		// Push notification settings
		$this->classy( 'push_settings', 'Settings', $this->api_ready() );

		// CPT registration
		$this->classy( 'push_cpt', 'CPT' );
		// Push notification
		$this->classy( 'push_update', 'Update' );
		// Android images
		$this->classy( 'push_image', 'Image' );
		// Push log
		$this->classy( 'push_log', 'Log' );
		$this->classy( 'push_log_cpt', 'Log_CPT' );

		if ( $this->api_ready() ) {
			// Segment signup filter
			$this->classy( 'push_segment', 'Segments' );
		}
	}

	/**
	 * Include and initiate class files
	 *
	 * @since  1.0.0
	 */
	public function classy( $var, $class, $config = null ) {

		$class = "AppPresser_Notifications_{$class}";
		require_once self::$plugin_path . "inc/{$class}.php";
		$this->$var = new $class( $config );
		$this->$var->hooks();
	}

	public function api_ready() {
		return (bool) appp_get_setting( 'notifications_ap3_key' );
	}

	/**
	 * Notice if AppPresser Core is not installed (and deactivate)
	 *
	 * @since  1.0.0
	 */
	public function apppresser_required() {
		// The module manager owns dependency checks.
	}

	public function ssl_curl_required() {
		$curl_version = curl_version();
		echo '<div id="message" class="error"><p>' . sprintf( __( '%1$s requires a CURL version that supports OpenSSL. Contact your hosting provider to update CURL on your server.  <br><b>Your versions:</b><br>CURL version: ' . $curl_version['version'] . '<br>SSL version: ' . $curl_version['ssl_version'], 'apppresser-push' ), 'AppPush' ) . '</p></div>';
	}

	/**
	 * Register notifications script
	 *
	 * @since  1.0.0
	 */
	public function push_scripts_styles() {
		// Only use minified files if SCRIPT_DEBUG is off
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Register admin scripts and styles
	 *
	 * @since x.x.x
	 */
	public function admin_script_styles() {
		$screen = get_current_screen();
		if ( ! $screen || ( false === strpos( $screen->id, 'apppresser' ) && ! in_array( $screen->post_type, array( 'apppush', 'apppush-log' ), true ) && ! in_array( $screen->post_type, (array) appp_get_setting( 'notifications_post_types', array() ), true ) ) ) {
			return;
		}
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'appp_segment_push', self::$plugin_url . "js/appp-segment-push$min.js", array( 'jquery' ), self::VERSION );

		wp_register_style( 'apppush_admin', self::$plugin_url . 'css/appp-push-admin.css', array(), self::VERSION );
		wp_enqueue_style( 'apppush_admin' );
	}
}
AppPresser_Notifications::go();
