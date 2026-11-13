<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: AppPresser In App Purchases
Plugin URI: https://apppresser.com
Description: Adds API endpoints to handle in app purchases, along with subscription validation.
Version: 3.0.0
Author: Scott Bolinger
Author URI: https://apppresser.com
License: GPLv2
Requires Plugins: apppresser
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

class AppPresser_IAP {

	const VERSION			  = '3.0.0';
	public static $instance   = null;
	public static $dir_path;
	public static $dir_url;
	public static $plugin_slug = 'apppresser-in-app-purchases';

	/**
	 * Creates or returns an instance of this class.
	 * @since	1.0.0
	 * @return AppPresser A single instance of this class.
	 */
	public static function get() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * 
	 * @since	1.0.0
	 */
	function __construct() {

		// Define plugin constants
		self::$dir_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$dir_url = trailingslashit( plugins_url( '' , __FILE__ ) );

		$this->hooks();
		

	}

	public function hooks() {

		require_once( self::$dir_path . 'in-app-purchase-endpoints-v2.php' );
		require_once( self::$dir_path . 'in-app-purchase-settings.php' );
	}

	// register for plugin updates
	public function updater() {
		// Bundled modules are updated exclusively with AppPresser core.
	}

	public static function do_error_log( $err, $msg ) {
		if( class_exists('AppPresser_Logger') ) {
			AppPresser_Logger::log( $err, $msg );
		}
	}

}

AppPresser_IAP::get();
