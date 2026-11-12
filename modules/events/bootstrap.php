<?php
/*
Module source: AppEvents
Plugin URI: http://apppresser.com
Description: Adds an events REST API for AppPresser apps, with native support for supported WordPress events plugins.
Text Domain: apppresser-events
Domain Path: /languages
Requires Plugins: apppresser
Version: 0.3.0
Author: AppPresser Team
Author URI: http://apppresser.com
License: GPLv2
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A second copy (for example the old apppresser-events folder) is already loaded.
if ( defined( 'APPP_EVENTS_VERSION' ) ) {
	return;
}

define( 'APPP_EVENTS_VERSION', '0.3.0' );
define( 'APPP_EVENTS_FILE', __FILE__ );
define( 'APPP_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'APPP_EVENTS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Classes load on demand. A normal page view only reads this file and the small hooks
 * file below: no class file, no option read, no query. The REST route, the cache and
 * the admin screen pull in their classes the first time they are used.
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'Appp_Events_' ) ) {
			return;
		}
		$slug = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
		$dir  = ( 0 === strpos( $class_name, 'Appp_Events_Adapter' ) ) ? 'inc/adapters/' : 'inc/';
		if ( is_readable( APPP_EVENTS_DIR . $dir . $slug ) ) {
			require_once APPP_EVENTS_DIR . $dir . $slug;
		}
	}
);

require_once APPP_EVENTS_DIR . 'inc/appp-events-functions.php';

if ( is_admin() ) {
	Appp_Events_Admin::init();
}
