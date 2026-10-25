<?php
/**
 * Plugin Name: AppPresser Plugin Controls
 * Description: Managed by AppPresser Settings > Features. Loads plugin controls before ordinary plugins.
 * Author: AppPresser Team
 * License: GPLv2 or later
 */
// AppPresser managed Plugin Controls loader. Format: 1.
defined( 'ABSPATH' ) || exit;

$appp_controls_core = 'APPPRESSER_CORE_BASENAME';
$appp_controls_active = (array) get_option( 'active_plugins', array() );
$appp_controls_network = (array) get_site_option( 'active_sitewide_plugins', array() );
if ( get_site_option( 'apppresser_plugin_controls_enabled', false ) &&
	( in_array( $appp_controls_core, $appp_controls_active, true ) || isset( $appp_controls_network[ $appp_controls_core ] ) ) ) {
	$appp_controls_runtime = WP_PLUGIN_DIR . '/' . dirname( $appp_controls_core ) . '/modules/plugin-controls/runtime.php';
	if ( is_file( $appp_controls_runtime ) ) {
		if ( ! defined( 'APPPRESSER_PLUGIN_CONTROLS_CORE' ) ) { define( 'APPPRESSER_PLUGIN_CONTROLS_CORE', $appp_controls_core ); }
		require_once $appp_controls_runtime;
	}
}
unset( $appp_controls_core, $appp_controls_active, $appp_controls_network, $appp_controls_runtime );
