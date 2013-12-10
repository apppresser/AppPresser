<?php
/*
Plugin Name: AppPresser
Plugin URI: http://apppresser.com
Description: A mobile app development framework for WordPress.
Text Domain: apppresser
Domain Path: /languages
Version: 1.0.1
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

class AppPresser {

	const VERSION           = '1.0.0';
	const SETTINGS_NAME     = 'appp_settings';
	public static $settings = 'false';
	public static $instance = null;
	public static $is_app   = null;
	public static $dir_path;
	public static $inc_path;
	public static $inc_url;
	public static $css_url;
	public static $img_url;
	public static $js_url;
	public static $dir_url;
	public static $pg_url;

	/**
	 * Creates or returns an instance of this class.
	 * @since  1.0.0
	 * @return AppPresser A single instance of this class.
	 */
	public static function get() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Let's start Pressin' Apps!
	 * @since  1.0.0
	 */
	function __construct() {

		// Define plugin constants
		self::$dir_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$dir_url  = trailingslashit( plugins_url( dirname( plugin_basename( __FILE__ ) ) ) );
		self::$inc_path = self::$dir_path . 'inc/';
		self::$inc_url  = self::$dir_url  . 'inc/';
		self::$css_url  = self::$dir_url  . 'css/';
		self::$img_url  = self::$dir_url  . 'images/';
		self::$js_url   = self::$dir_url  . 'js/';
		self::$pg_url   = self::$dir_url  . 'pg/';

		// Load translations
		load_plugin_textdomain( 'apppresser', false, 'apppresser/languages' );

		// Setup our activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Hook in all our important pieces
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );

		// remove wp version param from cordova enqueued scripts (so script loading doesn't break)
		// This will mean that it's harder to break caching on the cordova script
		add_filter( 'script_loader_src', array( $this, 'remove_query_arg' ), 9999 );

		require_once( self::$inc_path . 'admin-settings.php' );
		require_once( self::$inc_path . 'plugin-updater.php' );
	}

	/**
	 * Include all our important files.
	 * @since  1.0.0
	 */
	function includes() {

		require_once( self::$inc_path . 'theme-switcher.php' );
		require_once( self::$inc_path . 'mods.php' );
		// Uncomment when we add back in the app panel
		// require_once( self::$inc_path . 'body-class-meta-box.php' );

	}

	/**
	 * Activation hook for the plugin.
	 * @since  1.0.0
	 */
	function activate() {

		// code to execute when plugin is activated

		// @TODO: Define default settings upon activation

	}

	/**
	 * Frontend scripts and styles
	 * @since  1.0.0
	 */
	function frontend_scripts() {

		// Only use minified files if SCRIPT_DEBUG is off
		// $min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// Enqueue cordova scripts if we have an app
		if ( self::is_app() ) {
			if ( appp_is_ios() ) {
				wp_enqueue_script( 'cordova-core', self::$pg_url .'ios/cordova.js', null, '1.0.0' );
			} elseif ( appp_is_android() ) {
				wp_enqueue_script( 'cordova-core', self::$pg_url .'android/cordova.js', null, '1.0.0' );
			}
		}
	}

	/**
	 * Deactivation hook for the plugin.
	 * @since  1.0.0
	 */
	function deactivate() {

		// code to execute when plugin is deactivated

	}

	function remove_query_arg( $src ) {
		if ( false !== strpos( $src, 'cordova.js' ) )
			$src = remove_query_arg( 'ver', $src );
		return $src;
	}

	/**
	 * Utility method for getting our plugin's settings
	 * @since  1.0.0
	 * @param  string $key Optional key to get a specific option
	 * @return mixed       Array of all options, a specific option, or false if specific option not found.
	 */
	public static function settings( $key = false ) {
		if ( self::$settings === 'false' ) {
			self::$settings = get_option( self::SETTINGS_NAME );
		}
		if ( $key ) {
			$setting = isset( self::$settings[ $key ] ) ? self::$settings[ $key ] : false;
			// Override value or supply fallback
			return apply_filters( 'apppresser_setting_default', $setting, $key, self::$settings );
		}
		return self::$settings;
	}

	/**
	 * Checks if WP install is displaying the NEW WordPress style (previously the MP6 plugin)
	 * @since  1.0.0
	 * @return boolean Whether admin has new style
	 */
	public static function is_mp6() {
		global $wp_version;
		return version_compare( $wp_version, '3.7.9', '>' ) || is_plugin_active( 'mp6/mp6.php' );
	}

	/**
	 * Sets/Gets the app_is_app variable
	 * @since  1.0.0
	 * @param  boolean $set Set the variable
	 * @return boolean      Variable value
	 */
	public static function is_app( $set = null ) {
		if ( $set !== null )
			self::$is_app = $set;
		return self::$is_app;
	}

}

// Singleton rather than a global.. If they want access, they can use:
AppPresser::get();

/**
 * Function wrapper for AppPresser::settings()
 * @since  1.0.0
 * @param  string $key Optional key to get a specific option
 * @return mixed       Array of all options, a specific option, or false if specific option not found.
 */
function appp_get_setting( $key = false ) {
	return AppPresser::settings( $key );
}
