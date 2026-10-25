<?php
/** Early plugin filtering adapted from AppPresser Plugin Controls 2.0. */
defined( 'ABSPATH' ) || exit;

class AppPresserPluginControls {
	public static $instance;
	public static $is_apppv;
	const VERSION = '3.0';
	const PLUGIN = 'AppPresser Plugin Controls';

	public static function run() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public function __construct() {
		add_filter( 'option_active_plugins', array( $this, 'appp_filter_plugins' ), 5 );
		add_filter( 'site_option_active_sitewide_plugins', array( $this, 'filter_network_plugins' ), 5 );
	}

	/** Accept old double-serialized lists while using independent core settings. */
	public static function setting( $key, $default = false ) {
		$values = get_option( 'apppresser_module_settings_plugin-controls', null );
		$value = is_array( $values ) ? ( isset( $values[ $key ] ) ? $values[ $key ] : $default ) : get_option( $key, $default );
		for ( $i = 0; $i < 2 && is_string( $value ) && is_serialized( $value ); $i++ ) { $value = maybe_unserialize( $value ); }
		return $value;
	}

	private function applies( $api_key ) {
		if ( is_admin() ) { return false; }
		if ( self::read_app_version() ) { return true; }
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
		$is_api = isset( $_GET['rest_route'] ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			(bool) preg_match( '#/' . preg_quote( $prefix, '#' ) . '(?:/|\?|$)#', $uri );
		return $is_api && '1' === (string) self::setting( $api_key );
	}

	private function plugins( $key, $hook ) {
		$list = self::setting( $key, array() );
		$list = apply_filters( $hook, is_array( $list ) ? $list : array() );
		$core = defined( 'APPPRESSER_PLUGIN_CONTROLS_CORE' ) ? APPPRESSER_PLUGIN_CONTROLS_CORE : 'apppresser/apppresser.php';
		return array_values( array_filter( is_array( $list ) ? $list : array(), static function ( $file ) use ( $core ) {
			return is_string( $file ) && $file !== $core && 0 === validate_file( $file ) && is_file( WP_PLUGIN_DIR . '/' . $file );
		} ) );
	}

	public function appp_filter_plugins( $active = array() ) {
		if ( ! is_array( $active ) || is_admin() ) { return $active; }
		if ( $this->applies( 'apppresser-plugin-block-api' ) ) {
			$active = array_diff( $active, $this->plugins( 'apppresser-plugin-block', 'appp_exclude_plugins' ) );
		}
		if ( $this->applies( 'apppresser-plugin-enable-api' ) ) {
			$active = array_merge( $active, $this->plugins( 'apppresser-plugin-enable', 'appp_include_plugins' ) );
		}
		return array_values( array_unique( $active ) );
	}

	public function filter_network_plugins( $active ) {
		if ( ! is_array( $active ) || ! $this->applies( 'apppresser-plugin-block-api' ) ) { return $active; }
		return array_diff_key( $active, array_flip( $this->plugins( 'apppresser-plugin-block', 'appp_exclude_plugins' ) ) );
	}

	public static function read_app_version() {
		if ( null !== self::$is_apppv ) { return self::$is_apppv; }
		for ( $version = 3; $version >= 1; $version-- ) {
			if ( ( isset( $_GET['appp'] ) && is_scalar( $_GET['appp'] ) && (string) $version === (string) $_GET['appp'] ) ||
				( isset( $_COOKIE[ 'AppPresser_Appp' . $version ] ) && 'true' === $_COOKIE[ 'AppPresser_Appp' . $version ] ) ) {
				self::$is_apppv = $version;
				return $version;
			}
		}
		self::$is_apppv = 0;
		return 0;
	}
}

