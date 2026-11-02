<?php
/** Manage feature loading independently of the admin UI. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Modules {
	private static $instance;
	private $registry;
	private $installed;
	private $conflicts = array();
	private $loaded = array();
	private $removed_legacy = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_option( 'appp_modules', array(), '', true );
		$this->registry = require __DIR__ . '/registry.php';
		require_once __DIR__ . '/class-plugin-controls-files.php';
		add_action( 'plugins_loaded', array( $this, 'boot' ), 1 );
		add_action( 'deactivated_plugin', array( $this, 'clear_inventory' ) );
		add_action( 'activated_plugin', array( $this, 'clear_inventory' ) );
		add_action( 'deleted_plugin', array( $this, 'clear_inventory' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear_inventory' ) );
		if ( is_admin() ) {
			require_once __DIR__ . '/class-module-admin.php';
			new AppPresser_Module_Admin( $this );
		}
	}

	public function registry() {
		$registry = $this->registry;
		foreach ( $registry as &$definition ) {
			foreach ( array( 'name', 'description' ) as $key ) {
				$definition[ $key ] = call_user_func( $definition[ $key ] );
			}
		}
		unset( $definition );
		// Display order is shared by Features and the settings sidebar. Runtime
		// loading keeps its independent initialization order.
		$order = array_flip( array( 'push', 'bridge', 'camera', 'commerce', 'community', 'lms', 'plugin-controls', 'iap', 'custom-theme' ) );
		uksort( $registry, static function ( $left, $right ) use ( $order ) {
			return ( isset( $order[ $left ] ) ? $order[ $left ] : count( $order ) ) <=> ( isset( $order[ $right ] ) ? $order[ $right ] : count( $order ) );
		} );
		return $registry;
	}

	public function clear_inventory() {
		$this->installed = null;
		$this->conflicts = array();
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
	}

	private function installed_plugins() {
		if ( null === $this->installed ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$this->installed = get_plugins();
			if ( function_exists( 'get_mu_plugins' ) ) {
				foreach ( get_mu_plugins() as $file => $data ) {
					$this->installed[ 'mu-plugins/' . $file ] = $data;
				}
			}
		}
		return $this->installed;
	}

	/** Installed inactive copies also block a module, including renamed plugin folders. */
	public function legacy_plugins( $id ) {
		// Both features replace the same legacy Bridge plugin and class.
		if ( 'custom-theme' === $id ) { return $this->legacy_plugins( 'bridge' ); }
		if ( ! isset( $this->registry[ $id ] ) ) {
			return array();
		}
		if ( isset( $this->conflicts[ $id ] ) && ( $this->conflicts[ $id ] || isset( $this->loaded[ $id ] ) || ! class_exists( $this->registry[ $id ]['class'], false ) ) ) {
			return $this->conflicts[ $id ];
		}
		$definition = $this->registry[ $id ];
		$matches = array();
		$slug = dirname( $definition['legacy'] );
		foreach ( $this->installed_plugins() as $file => $data ) {
			if ( 'plugin-controls' === $id && 'mu-plugins/apppresser-plugin-controls.php' === $file && AppPresser_Plugin_Controls_Files::is_managed() ) { continue; }
			$domain = isset( $data['TextDomain'] ) ? $data['TextDomain'] : '';
			if ( basename( $file ) === basename( $definition['legacy'] ) || in_array( basename( $file ), isset( $definition['legacy_aliases'] ) ? $definition['legacy_aliases'] : array(), true ) || in_array( $domain, array( $slug, 'apppresser-' . $id ), true ) ||
				( 'plugin-controls' === $id && isset( $data['Name'] ) && 'AppPresser Plugin Controls' === $data['Name'] ) || ( 'push' === $id && 'apppresser-push' === $domain ) ) {
				$matches[ $file ] = $data;
			}
		}
		if ( ! isset( $this->loaded[ $id ] ) && ! isset( $this->removed_legacy[ $id ] ) && class_exists( $definition['class'], false ) && ! $matches ) {
			$matches['custom'] = array( 'Name' => __( 'A custom or must-use legacy implementation', 'apppresser' ), 'Version' => '' );
		}
		$this->conflicts[ $id ] = $matches;
		return $matches;
	}

	/** Check installation separately from activation for the feature list. */
	public function dependency_installed( $id ) {
		$definition = $this->registry[ $id ];
		if ( empty( $definition['dependency'] ) ||
			( 'commerce' === $id && class_exists( 'WooCommerce', false ) ) ||
			( 'community' === $id && function_exists( 'buddypress' ) ) ||
			( 'lms' === $id && defined( 'LEARNDASH_VERSION' ) ) ) {
			return true;
		}
		foreach ( $this->installed_plugins() as $file => $data ) {
			foreach ( (array) $definition['dependency'] as $dependency ) {
				$domain = isset( $data['TextDomain'] ) ? $data['TextDomain'] : '';
				if ( $file === $dependency || basename( $file ) === basename( $dependency ) ||
					$domain === dirname( $dependency ) || ( 'lms' === $id && 'learndash' === $domain ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public function dependency_error( $id, $runtime = false, $translate = true ) {
		$definition = $this->registry[ $id ];
		if ( empty( $definition['dependency'] ) ) {
			return '';
		}
		$active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		$present = (bool) array_intersect( (array) $definition['dependency'], $active );
		if ( 'commerce' === $id && class_exists( 'WooCommerce', false ) ) {
			$present = true;
		}
		if ( 'community' === $id && function_exists( 'buddypress' ) ) {
			$present = true;
		}
		if ( 'lms' === $id && defined( 'LEARNDASH_VERSION' ) ) {
			$present = true;
		}
		if ( ! $present ) {
			return $translate ? sprintf( $this->dependency_installed( $id ) ? __( 'Activate %s to use this feature.', 'apppresser' ) : __( 'Install and activate %s to use this feature.', 'apppresser' ), $definition['dependency_name'] ) : 'dependency';
		}
		if ( $runtime && ( ( 'commerce' === $id && ! class_exists( 'WooCommerce', false ) ) || ( 'community' === $id && ! function_exists( 'buddypress' ) ) || ( 'lms' === $id && ! defined( 'LEARNDASH_VERSION' ) ) ) ) {
			return $translate ? sprintf( __( '%s did not initialize. Check its installation before enabling this feature.', 'apppresser' ), $definition['dependency_name'] ) : 'dependency';
		}
		if ( 'commerce' === $id && ( ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.5.0', '<' ) ) || class_exists( 'WC_Cart_Endpoint_REST_API', false ) ) ) {
			return $translate ? __( 'Commerce requires WooCommerce 3.5 or newer and no conflicting Cart REST API plugin.', 'apppresser' ) : 'dependency';
		}
		return '';
	}

	public function requested( $id ) {
		if ( 'plugin-controls' === $id ) { return AppPresser_Plugin_Controls_Files::enabled(); }
		$options = get_option( 'appp_modules', array() );
		return is_array( $options ) && ! empty( $options[ $id ] );
	}

	public function state( $id ) {
		if ( ! isset( $this->registry[ $id ] ) ) {
			return 'unknown';
		}
		if ( $this->legacy_plugins( $id ) ) {
			return 'legacy';
		}
		if ( 'plugin-controls' === $id ) {
			return $this->requested( $id ) ? ( AppPresser_Plugin_Controls_Files::is_managed() ? 'enabled' : 'installation' ) : 'off';
		}
		if ( $this->dependency_error( $id, did_action( 'plugins_loaded' ) > 0, did_action( 'init' ) > 0 ) ) {
			return 'dependency';
		}
		if ( ! $this->requested( $id ) ) {
			return 'off';
		}
		return 'enabled';
	}

	public function set_enabled( $id, $enabled ) {
		if ( ! isset( $this->registry[ $id ] ) ) {
			return new WP_Error( 'unknown_module', __( 'Unknown feature.', 'apppresser' ) );
		}
		if ( 'plugin-controls' === $id ) {
			$permission = AppPresser_Plugin_Controls_Files::permission();
			if ( is_wp_error( $permission ) ) { return $permission; }
			if ( $enabled && $this->legacy_plugins( $id ) ) {
				return new WP_Error( 'legacy_plugin', __( 'Remove the legacy plugin before enabling this feature.', 'apppresser' ) );
			}
			$result = AppPresser_Plugin_Controls_Files::set_enabled( $enabled );
			if ( true === $result ) { do_action( $enabled ? 'apppresser_module_enabled' : 'apppresser_module_disabled', $id ); }
			return $result;
		}
		if ( $enabled ) {
			$this->clear_inventory();
			if ( $this->legacy_plugins( $id ) ) {
				return new WP_Error( 'legacy_plugin', __( 'Deactivate and delete the legacy plugin before enabling this feature.', 'apppresser' ) );
			}
			$error = $this->dependency_error( $id, true );
			if ( $error ) {
				return new WP_Error( 'dependency', $error );
			}
		}
		$options = (array) get_option( 'appp_modules', array() );
		if ( 'push' === $id && $enabled && empty( $options[ $id ] ) ) {
			update_option( 'apppresser_push_resumed_at', gmdate( 'Y-m-d H:i:s' ), false );
		}
		$options[ $id ] = (bool) $enabled;
		update_option( 'appp_modules', $options, true );
		if ( $this->requested( $id ) !== (bool) $enabled ) {
			return new WP_Error( 'settings_write', __( 'The feature state could not be saved. Try again.', 'apppresser' ) );
		}
		do_action( $enabled ? 'apppresser_module_enabled' : 'apppresser_module_disabled', $id );
		if ( 'bridge' === $id ) { delete_option( 'rewrite_rules' ); }
		return true;
	}

	public function migrate_legacy( $id ) {
		if ( ! isset( $this->registry[ $id ] ) ) { return new WP_Error( 'unknown_module', __( 'Unknown feature.', 'apppresser' ) ); }
		if ( 'custom-theme' === $id ) { $id = 'bridge'; }
		require_once __DIR__ . '/class-module-migration.php';
		$migration = new AppPresser_Module_Migration( $this );
		$result = $migration->run( $id );
		if ( is_wp_error( $result ) ) { return $result; }
		// Classes from the deleted addon remain in this request. The replacement
		// starts on the redirected request, after PHP has unloaded those classes.
		$this->removed_legacy[ $id ] = true;
		$this->clear_inventory();
		if ( 'plugin-controls' === $id && $this->requested( $id ) && AppPresser_Plugin_Controls_Files::is_managed() ) {
			do_action( 'apppresser_module_enabled', $id );
			return true;
		}
		$result = $this->set_enabled( $id, true );
		if ( true === $result && 'bridge' === $id ) {
			$result = $this->set_enabled( 'custom-theme', true );
		}
		return $result;
	}

	/** Early LearnDash setup has to precede loading WordPress's pluggable functions. */
	public function early_boot() {
		if ( $this->requested( 'lms' ) && 'enabled' === $this->state( 'lms' ) ) {
			$this->load( 'lms' );
		}
	}

	public function boot() {
		// Plugins loaded after core can introduce custom legacy classes.
		$this->conflicts = array();
		foreach ( $this->registry as $id => $definition ) {
			if ( $this->requested( $id ) && 'enabled' === $this->state( $id ) ) {
				$this->load( $id );
			}
		}
	}

	private function load( $id ) {
		if ( isset( $this->loaded[ $id ] ) ) {
			return;
		}
		$this->loaded[ $id ] = true;
		require dirname( __DIR__, 2 ) . '/modules/' . $id . '/bootstrap.php';
		do_action( 'apppresser_module_loaded', $id );
	}
}

/** Store already-validated addon values without replacing unrelated configuration. */
function apppresser_update_settings( $changes ) {
	$settings = get_option( 'appp_settings', array() );
	$settings = array_replace( is_array( $settings ) ? $settings : array(), $changes );
	// This is a programmatic write, not the scoped Settings API form submission.
	// The caller validates its own fields. Keep other WordPress sanitizers active.
	$callback = class_exists( 'AppPresser_Admin_Settings', false ) ? array( AppPresser_Admin_Settings::run(), 'settings_validate' ) : null;
	$priority = $callback ? has_filter( 'sanitize_option_appp_settings', $callback ) : false;
	if ( false !== $priority ) { remove_filter( 'sanitize_option_appp_settings', $callback, $priority ); }
	try {
		return update_option( 'appp_settings', $settings );
	} finally {
		if ( false !== $priority ) { add_filter( 'sanitize_option_appp_settings', $callback, $priority ); }
	}
}

/** Read legacy standalone IAP values directly until the customer saves them. */
function apppresser_iap_get_option( $key, $default = false ) {
	$settings = get_option( 'appp_settings', array() );
	return is_array( $settings ) && array_key_exists( $key, $settings ) ? $settings[ $key ] : get_option( $key, $default );
}

function apppresser_iap_update_option( $key, $value ) {
	if ( ! preg_match( '/^IAP_(ARMEMBER|MEMBERPRESS|LEARNDASH|PMP|RCP|WOOMP)_(ACTIVE|KEYS)$/', $key ) ) { return false; }
	return apppresser_update_settings( array( $key => $value ) );
}
