<?php
/**
 * Plugin Updater for extensions hosted on apppresser.com. This does not update this plugin, it only hosts the code needed for other plugins that rely on this one.
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_Updater extends AppPresser {

	// A single instance of this class.
	public static $included = array(
		'theme'  => false,
		'plugin' => false,
	);
	public static $updaters = array(
		'plugins' => array(),
		'themes'  => array(),
	);
	const AUTHOR            = 'AppPresser Team';
	const STORE_URL         = 'https://apppresser.com';

	/**
	 * Includes the EDD_SL_Plugin_Updater and EDD_SL_Theme_Updater classes if needed
	 *
	 * @since  1.0.0
	 */
	public static function include_updater( $plugin = true ) {
		if ( $plugin ) {
			// load plugin updater
			if ( ! self::$included['plugin'] && ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
				include self::$inc_path . 'EDD_SL_Plugin_Updater.php';
			}

			self::$included['plugin'] = true;
		} else {
			// load theme updater
			if ( ! self::$included['theme'] && ! class_exists( 'Appp_EDD_Theme_Updater' ) ) {
				include self::$inc_path . 'EDD_SL_Theme_Updater.php';
			}

			self::$included['theme'] = true;
		}
	}

	public static function add_theme( $theme_slug, $option_key = '', $api_data = array() ) {
		// Include the updater if we haven't
		self::include_updater( false );

		$strings = array(
			'theme-license'             => __( 'Theme License', 'apppresser' ),
			'enter-key'                 => __( 'Enter your theme license key.', 'apppresser' ),
			'license-key'               => __( 'License Key', 'apppresser' ),
			'license-action'            => __( 'License Action', 'apppresser' ),
			'deactivate-license'        => __( 'Deactivate License', 'apppresser' ),
			'activate-license'          => __( 'Activate License', 'apppresser' ),
			'status-unknown'            => __( 'License status is unknown.', 'apppresser' ),
			'renew'                     => __( 'Renew?', 'apppresser' ),
			'unlimited'                 => __( 'unlimited', 'apppresser' ),
			'license-key-is-active'     => __( 'License key is active.', 'apppresser' ),
			'expires%s'                 => __( 'Expires %s.', 'apppresser' ),
			'expires-never'             => __( 'Lifetime License.', 'apppresser' ),
			'%1$s/%2$-sites'            => __( 'You have %1$s / %2$s sites activated.', 'apppresser' ),
			'license-key-expired-%s'    => __( 'License key expired %s.', 'apppresser' ),
			'license-key-expired'       => __( 'License key has expired.', 'apppresser' ),
			'license-keys-do-not-match' => __( 'License keys do not match.', 'apppresser' ),
			'license-is-inactive'       => __( 'License is inactive.', 'apppresser' ),
			'license-key-is-disabled'   => __( 'License key is disabled.', 'apppresser' ),
			'site-is-inactive'          => __( 'Site is inactive.', 'apppresser' ),
			'license-status-unknown'    => __( 'License status is unknown.', 'apppresser' ),
			'update-notice'             => __( "Updating this theme will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.", 'apppresser' ),
			'update-available'          => __( '<strong>%1$s %2$s</strong> is available. <a href="%3$s" class="thickbox" title="%4s">Check out what\'s new</a> or <a href="%5$s"%6$s>update now</a>.', 'apppresser' ),
		);

		if ( $option_key ) {
			// Add to the list of keys to save license statuses
			AppPresser_Admin_Settings::$license_keys[ $option_key ] = $theme_slug;
		}

		$api_data = wp_parse_args(
			$api_data,
			array(
				'author'         => self::AUTHOR,
				'remote_api_url' => self::STORE_URL,
				'license'        => trim( appp_get_setting( $option_key ) ),
				'theme_slug'     => $theme_slug,
				'beta'           => false,
			)
		);
		$updater  = new Appp_EDD_Theme_Updater( $api_data, $strings );

		// Add passed-in vars to the object since the vars are private (derp).
		$updater->public = $api_data + array(
			'api_url'    => $api_data['remote_api_url'],
			'theme_slug' => $theme_slug,
		);

		// Add this updater instance to our array
		self::$updaters['themes'][ $theme_slug ] = $updater;
		return $updater;
	}


	/**
	 * Add a EDD_SL_Plugin_Updater instance
	 *
	 * @since  1.0.0
	 * @param  string $plugin_file    Path to the plugin file.
	 * @param  string $option_key     `appp_get_setting` setting key
	 * @param  array  $api_data       Optional data to send with API calls.
	 * @return EDD_SL_Plugin_Updater     object instance
	 */
	public static function add( $plugin_file, $option_key = '', $api_data = array() ) {

		// Include the updater if we haven't
		self::include_updater();

		$base_name = plugin_basename( $plugin_file );
		if ( $option_key ) {
			// Add to the list of keys to save license statuses
			AppPresser_Admin_Settings::$license_keys[ $option_key ] = $base_name;
		}

		$api_data = wp_parse_args(
			$api_data,
			array(
				'author'  => self::AUTHOR,
				'url'     => self::STORE_URL,
				'license' => trim( appp_get_setting( $option_key ) ),
				'beta'    => false,
			)
		);

		$api_url = $api_data['url'];
		unset( $api_data['url'] );

		// Init updater
		$updater = new EDD_SL_Plugin_Updater( $api_url, $plugin_file, $api_data );

		// Add passed-in vars to the object since the vars are private (derp).
		$updater->public = $api_data + array(
			'api_url'     => $api_url,
			'plugin_file' => $plugin_file,
		);

		// Add this updater instance to our array
		self::$updaters['plugins'][ $base_name ] = $updater;
		return $updater;
	}

	/**
	 * Retrieve a EDD_SL_Plugin_Updater instance
	 *
	 * @since  1.0.0
	 * @param  string  $plugintheme  Path to the plugin file or theme name
	 * @param  boolean $plugin       Whether this is a plugin or theme
	 * @return EDD_SL_Plugin_Updater object instance
	 */
	public static function get_updater( $plugintheme, $plugin = true ) {

		if ( $plugin ) {
			if ( isset( self::$updaters['plugins'][ $plugintheme ] ) ) {
				return self::$updaters['plugins'][ $plugintheme ];
			}

			$base_name = plugin_basename( $plugintheme );
			if ( isset( self::$updaters['plugins'][ $base_name ] ) ) {
				return self::$updaters['plugins'][ $base_name ];
			}
		} elseif ( isset( self::$updaters['themes'][ $plugintheme ] ) ) {
				return self::$updaters['themes'][ $plugintheme ];
		}

		return false;
	}

	/**
	 * Retrieves a license key's status from the store
	 *
	 * @since  1.0.0
	 * @param  string  $license      License Key
	 * @param  string  $plugintheme  Plugin dir/file
	 * @param  boolean $plugin       Whether this is a plugin or theme
	 * @return mixed                 License status or false if failure
	 */
	public static function get_license_status( $license, $plugintheme, $plugin = true ) {

		$plugin = false === strpos( $plugintheme, '/' ) ? false : $plugin;

		if ( ! ( $updater = self::get_updater( $plugintheme, $plugin ) ) ) {
			return false;
		}

		$license = trim( $license );
		if ( empty( $license ) ) {
			return false;
		}

		// Call the custom API.
		$response = wp_remote_post(
			esc_url_raw(
				add_query_arg(
					array(
						'edd_action' => 'activate_license',
						'license'    => $license,
						// 'the_title' filter needed to match EDD's check
						'item_name'  => urlencode( apply_filters( 'the_title', $updater->public['item_name'], 0 ) ),
					),
					$updater->public['api_url']
				)
			)
		);

		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// Send back license status
		return isset( $license_data->license ) ? $license_data->license : false;
	}
}

/**
 * Add a EDD_SL_Plugin_Updater instance
 *
 * @since  1.0.0
 * @param  string $plugin_file   Path to the plugin file.
 * @param  string $option_key    `appp_get_setting` setting key
 * @param  array  $api_data      Optional data to send with API calls.
 * @return EDD_SL_Plugin_Updater    object instance
 */
function appp_updater_add( $plugin_file, $option_key = '', $api_data = array() ) {
	return AppPresser_Updater::add( $plugin_file, $option_key, $api_data );
}

function appp_theme_updater_add( $theme_slug, $option_key = '', $api_data = array() ) {
	return AppPresser_Updater::add_theme( $theme_slug, $option_key, $api_data );
}

/**
 * Helper function. Retrieves a license key's status from the store
 *
 * @since  1.0.0
 * @param  string  $license      License Key
 * @param  string  $plugintheme  Plugin dir/file
 * @param  boolean $plugin       Whether this is a plugin or theme
 * @return mixed                 License status or false if failure
 */
function appp_get_license_status( $license, $plugintheme, $plugin = true ) {
	return AppPresser_Updater::get_license_status( $license, $plugintheme, $plugin );
}
