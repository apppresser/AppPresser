<?php
/** Own the early MU loader without deleting or replacing unrelated site files. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Plugin_Controls_Files {
	const LEGACY_SHA256 = 'c926414e576b44c1461e22ea17ec109dedc1cda6aee35a16b464022540971a33';
	const FLAG = 'apppresser_plugin_controls_enabled';

	public static function path() { return trailingslashit( WPMU_PLUGIN_DIR ) . 'apppresser-plugin-controls.php'; }
	public static function enabled() { return (bool) get_site_option( self::FLAG, false ); }

	public static function loader() {
		$core = plugin_basename( dirname( __DIR__, 2 ) . '/apppresser.php' );
		$template = file_get_contents( dirname( __DIR__, 2 ) . '/modules/plugin-controls/mu-loader.php' );
		return str_replace( "'APPPRESSER_CORE_BASENAME'", var_export( $core, true ), $template );
	}

	public static function is_managed() {
		$path = self::path();
		if ( ! is_file( $path ) || is_link( $path ) ) { return false; }
		$hash = @hash_file( 'sha256', $path );
		if ( ! is_string( $hash ) ) { return false; }
		return hash_equals( hash( 'sha256', self::loader() ), $hash ) ||
			( is_string( get_site_option( 'apppresser_plugin_controls_loader_hash' ) ) && hash_equals( get_site_option( 'apppresser_plugin_controls_loader_hash' ), $hash ) );
	}

	public static function permission() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ||
			( is_multisite() && ! current_user_can( 'manage_network_plugins' ) ) ) {
			return new WP_Error( 'controls_permission', __( 'You do not have permission to install or remove the Plugin Controls loader.', 'apppresser' ) );
		}
		return true;
	}

	private static function preflight() {
		$permission = self::permission();
		if ( is_wp_error( $permission ) ) { return $permission; }
		require_once __DIR__ . '/class-module-files.php';
		$context = is_dir( WPMU_PLUGIN_DIR ) ? WPMU_PLUGIN_DIR : dirname( WPMU_PLUGIN_DIR );
		$result = AppPresser_Module_Files::initialize( $context );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( is_link( WPMU_PLUGIN_DIR ) || is_link( self::path() ) || ! is_writable( $context ) ) {
			return new WP_Error( 'mu_not_writable', __( 'The MU plugin folder must be writable and must not be a symbolic link.', 'apppresser' ) );
		}
		return true;
	}

	public static function set_enabled( $enabled, $replace_legacy = false ) {
		$result = self::preflight();
		if ( is_wp_error( $result ) ) { return $result; }
		// Use the main site's uniquely keyed options table to serialize changes
		// to the shared loader across a multisite network.
		$lock = 'apppresser_plugin_controls_file_lock';
		$site = is_multisite() ? get_main_site_id() : get_current_blog_id();
		$locked_at = is_multisite() ? get_blog_option( $site, $lock ) : get_option( $lock );
		if ( $locked_at && (int) $locked_at < time() - 120 ) {
			if ( is_multisite() ) { delete_blog_option( $site, $lock ); } else { delete_option( $lock ); }
		}
		$acquired = is_multisite() ? add_blog_option( $site, $lock, time() ) : add_option( $lock, time(), '', false );
		if ( ! $acquired ) { return new WP_Error( 'controls_busy', __( 'Plugin Controls is already being updated. Try again shortly.', 'apppresser' ) ); }
		try {
			return self::write_state( $enabled, $replace_legacy );
		} finally {
			if ( is_multisite() ) { delete_blog_option( $site, $lock ); } else { delete_option( $lock ); }
		}
	}

	private static function write_state( $enabled, $replace_legacy ) {
		$path = self::path();
		$previous = is_file( $path ) ? file_get_contents( $path ) : null;
		if ( file_exists( $path ) && ! self::is_managed() &&
			! ( $replace_legacy && is_file( $path ) && hash_equals( self::LEGACY_SHA256, hash_file( 'sha256', $path ) ) ) ) {
			return new WP_Error( 'mu_file_conflict', __( 'The Plugin Controls MU file has been customized or is not owned by core. It has been left in place. Review it before trying again.', 'apppresser' ) );
		}
		if ( $enabled ) {
			if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) { return new WP_Error( 'mu_create_failed', __( 'WordPress could not create the MU plugin folder.', 'apppresser' ) ); }
			$content = self::loader();
			$temporary = trailingslashit( WPMU_PLUGIN_DIR ) . '.apppresser-controls-' . wp_generate_uuid4() . '.tmp';
			$written = file_put_contents( $temporary, $content, LOCK_EX );
			if ( strlen( $content ) !== $written || ! hash_equals( hash( 'sha256', $content ), (string) @hash_file( 'sha256', $temporary ) ) || ! @rename( $temporary, $path ) ) {
				if ( is_file( $temporary ) ) { wp_delete_file( $temporary ); }
				return new WP_Error( 'mu_install_failed', __( 'The Plugin Controls loader could not be installed. Check file permissions and try again.', 'apppresser' ) );
			}
			if ( ! hash_equals( hash( 'sha256', $content ), (string) hash_file( 'sha256', $path ) ) ) {
				self::restore( $previous );
				return new WP_Error( 'mu_verify_failed', __( 'The installed Plugin Controls loader could not be verified.', 'apppresser' ) );
			}
		} elseif ( is_file( $path ) && ( ! @unlink( $path ) || file_exists( $path ) ) ) {
			return new WP_Error( 'mu_remove_failed', __( 'The Plugin Controls loader could not be removed. Check file permissions and try again.', 'apppresser' ) );
		}
		update_site_option( self::FLAG, (bool) $enabled );
		if ( self::enabled() !== (bool) $enabled ) {
			self::restore( $previous );
			return new WP_Error( 'mu_flag_failed', __( 'The Plugin Controls feature state could not be saved. The previous loader has been restored.', 'apppresser' ) );
		}
		if ( $enabled ) { update_site_option( 'apppresser_plugin_controls_loader_hash', hash_file( 'sha256', $path ) ); }
		wp_clean_plugins_cache( false );
		if ( function_exists( 'opcache_invalidate' ) ) { @opcache_invalidate( $path, true ); }
		return true;
	}

	private static function restore( $content ) {
		if ( null === $content ) {
			if ( is_file( self::path() ) ) { wp_delete_file( self::path() ); }
		} else {
			file_put_contents( self::path(), $content, LOCK_EX );
		}
	}

	/** Convert the supplied standalone MU plugin without an interruption in service. */
	public static function replace_legacy( $modules ) {
		if ( is_multisite() ) {
			return new WP_Error( 'network_controls_migration', __( 'Replacing an existing shared Plugin Controls MU plugin requires a coordinated network cutover. Keep it installed until every site is ready.', 'apppresser' ) );
		}
		$legacy = $modules->legacy_plugins( 'plugin-controls' );
		if ( array_keys( $legacy ) !== array( 'mu-plugins/apppresser-plugin-controls.php' ) ||
			! is_file( self::path() ) || ! hash_equals( self::LEGACY_SHA256, hash_file( 'sha256', self::path() ) ) ) {
			return new WP_Error( 'custom_controls', __( 'Only the original Plugin Controls MU file can be replaced automatically. Review customized or older Plugin Block/Enable files before removing them.', 'apppresser' ) );
		}
		$result = self::preflight();
		if ( is_wp_error( $result ) ) { return $result; }
		return self::set_enabled( true, true );
	}
}
