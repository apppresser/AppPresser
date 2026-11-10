<?php
/** Filesystem preflight shared by feature installation and legacy replacement. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Module_Files {
	public static function initialize( $context ) {
		if ( ! wp_is_file_mod_allowed( 'apppresser_features' ) ) {
			return new WP_Error( 'file_mods_disabled', __( 'Plugin file changes are disabled on this site.', 'apppresser' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( 'direct' !== get_filesystem_method( array(), $context ) || 'direct' !== get_filesystem_method() ) {
			return new WP_Error( 'filesystem_credentials', __( 'WordPress needs direct write access to the plugin folders for this action. Ask your host to configure access, then try again.', 'apppresser' ) );
		}
		if ( ! WP_Filesystem( array(), $context ) ) {
			return new WP_Error( 'filesystem_unavailable', __( 'WordPress could not access the plugin files.', 'apppresser' ) );
		}
		return true;
	}

	/** Resolve a regular plugin without following links into a source checkout. */
	public static function regular_plugin( $file ) {
		if ( 'custom' === $file || 0 === strpos( $file, 'mu-plugins/' ) || 0 !== validate_file( $file ) ) {
			return new WP_Error( 'custom_legacy', __( 'This legacy implementation must be removed by the site administrator before enabling its replacement.', 'apppresser' ) );
		}
		$root = trailingslashit( wp_normalize_path( realpath( WP_PLUGIN_DIR ) ) );
		$path = realpath( WP_PLUGIN_DIR . '/' . $file );
		$core = plugin_basename( dirname( __DIR__, 2 ) . '/apppresser.php' );
		if ( ! $path || 0 !== strpos( wp_normalize_path( $path ), $root ) || $file === $core ||
			( '.' !== dirname( $core ) && 0 === strpos( $file, dirname( $core ) . '/' ) ) ) {
			return new WP_Error( 'unsafe_legacy_path', __( 'The legacy plugin location cannot be removed automatically.', 'apppresser' ) );
		}
		$directory = dirname( $file );
		$target = '.' === $directory ? $path : realpath( WP_PLUGIN_DIR . '/' . $directory );
		if ( ! $target || wp_normalize_path( $target ) === untrailingslashit( $root ) || 0 !== strpos( trailingslashit( wp_normalize_path( $target ) ), $root ) ) {
			return new WP_Error( 'unsafe_legacy_path', __( 'The legacy plugin folder cannot be removed automatically.', 'apppresser' ) );
		}
		// WordPress removes an entire plugin folder. Never delete another plugin with it.
		if ( '.' !== $directory ) {
			foreach ( get_plugins() as $other => $data ) {
				if ( $other !== $file && 0 === strpos( $other, trailingslashit( $directory ) ) ) {
					return new WP_Error( 'shared_legacy_folder', __( 'The legacy addon shares a folder with another plugin. Separate those files before removing it.', 'apppresser' ) );
				}
			}
		}
		if ( ! is_writable( dirname( $target ) ) ) {
			return new WP_Error( 'legacy_not_writable', __( 'WordPress cannot remove the legacy plugin folder. Check its file permissions and try again.', 'apppresser' ) );
		}
		return $path;
	}
}
