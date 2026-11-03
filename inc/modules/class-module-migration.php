<?php
/** One action removes a legacy addon and enables its bundled replacement. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Module_Migration {
	private $modules;

	public function __construct( $modules ) { $this->modules = $modules; }

	public function run( $id ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) || ! current_user_can( 'delete_plugins' ) ) {
			return new WP_Error( 'migration_permission', __( 'You do not have permission to remove plugins and enable their replacements.', 'apppresser' ) );
		}
		$this->modules->clear_inventory();
		$legacy = $this->modules->legacy_plugins( $id );
		if ( ! $legacy ) { return true; }
		$error = $this->modules->dependency_error( $id, true );
		if ( $error ) { return new WP_Error( 'dependency', $error ); }
		if ( 'plugin-controls' === $id ) {
			return AppPresser_Plugin_Controls_Files::replace_legacy( $this->modules );
		}
		require_once __DIR__ . '/class-module-files.php';
		$access = AppPresser_Module_Files::initialize( WP_PLUGIN_DIR );
		if ( is_wp_error( $access ) ) { return $access; }
		$files = array_keys( $legacy );
		foreach ( $files as $file ) {
			if ( ! current_user_can( 'deactivate_plugin', $file ) ) {
				return new WP_Error( 'migration_permission', __( 'You do not have permission to remove this legacy plugin.', 'apppresser' ) );
			}
			$path = AppPresser_Module_Files::regular_plugin( $file );
			if ( is_wp_error( $path ) ) { return $path; }
		}
		$network_error = $this->network_preflight( $files );
		if ( is_wp_error( $network_error ) ) { return $network_error; }
		$lock = 'apppresser_module_removal_' . $id;
		$locked_at = get_option( $lock );
		if ( $locked_at && (int) $locked_at < time() - 300 ) { delete_option( $lock ); }
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new WP_Error( 'migration_busy', __( 'This feature is already being updated. Try again shortly.', 'apppresser' ) );
		}
		$active = array_values( array_intersect( $files, (array) get_option( 'active_plugins', array() ) ) );
		$removed = false;
		try {
			deactivate_plugins( $files, false, false );
			// AppEvents has an uninstall script. Its cache may be removed, but the
			// four shared settings are still owned by the bundled replacement.
			if ( 'events' === $id ) { add_filter( 'pre_update_option_appp_settings', array( $this, 'retain_events_settings' ), PHP_INT_MAX, 2 ); }
			$result = delete_plugins( $files );
			if ( true !== $result ) {
				return is_wp_error( $result ) ? $result : new WP_Error( 'legacy_delete_failed', __( 'The legacy plugin could not be removed. Its replacement has not been enabled. Check file permissions and try again.', 'apppresser' ) );
			}
			foreach ( $files as $file ) {
				if ( file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
					return new WP_Error( 'legacy_delete_failed', __( 'Legacy plugin files remain on disk. Its replacement has not been enabled.', 'apppresser' ) );
				}
			}
			$removed = true;
			return true;
		} catch ( Throwable $error ) {
			return new WP_Error( 'legacy_migration_failed', __( 'The legacy plugin could not be replaced. Check the site error log and try again.', 'apppresser' ) );
		} finally {
			if ( 'events' === $id ) { remove_filter( 'pre_update_option_appp_settings', array( $this, 'retain_events_settings' ), PHP_INT_MAX ); }
			if ( ! $removed ) {
				$this->restore_activation( $active );
			}
			delete_option( $lock );
			$this->modules->clear_inventory();
		}
	}

	public function retain_events_settings( $value, $old ) {
		$value = is_array( $value ) ? $value : array();
		foreach ( array( 'events_source', 'events_post_type', 'events_collapse', 'events_field_map' ) as $key ) {
			if ( is_array( $old ) && array_key_exists( $key, $old ) ) { $value[ $key ] = $old[ $key ]; }
		}
		return $value;
	}

	private function restore_activation( $active ) {
		foreach ( $active as $file ) {
			if ( is_file( WP_PLUGIN_DIR . '/' . $file ) ) { activate_plugin( $file, '', false, true ); }
		}
	}

	/** Shared-file removal must not interrupt another site's active addon. */
	private function network_preflight( $files ) {
		if ( ! is_multisite() ) { return true; }
		if ( ! current_user_can( 'manage_network_plugins' ) ) {
			return new WP_Error( 'network_permission', __( 'A network administrator must replace shared plugin files.', 'apppresser' ) );
		}
		$network_active = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
		$in_use = (bool) array_intersect( $files, $network_active );
		for ( $offset = 0; ! $in_use; $offset += 100 ) {
			$sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $offset ) );
			foreach ( $sites as $site ) {
				if ( (int) $site !== get_current_blog_id() && array_intersect( $files, (array) get_blog_option( $site, 'active_plugins', array() ) ) ) { $in_use = true; break; }
			}
			if ( count( $sites ) < 100 ) { break; }
		}
		return $in_use ? new WP_Error( 'shared_legacy_active', __( 'This legacy addon is active on other network sites. Coordinate its replacement across those sites before removing the shared plugin files.', 'apppresser' ) ) : true;
	}
}
