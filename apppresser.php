<?php
/*
Plugin Name: AppPresser
Plugin URI: http://apppresser.com
Description: A mobile app development framework for WordPress.
Text Domain: apppresser
Domain Path: /languages
Version: 3.2.1
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

	const VERSION           = '3.2.1';
	const SETTINGS_NAME     = 'appp_settings';
	public static $settings = 'false';
	public static $instance = null;
	public static $is_app   = null;
	public static $is_apppv = null;
	public static $l10n     = array();
	public static $dir_path;
	public static $inc_path;
	public static $inc_url;
	public static $css_url;
	public static $img_url;
	public static $js_url;
	public static $tmpl_path;
	public static $dir_url;
	public static $pg_url;
	public static $pg_version;
	public static $debug = null;
	public static $deprecate_ver = 0;
	// public static $errorpath = '../php-error-log.php';

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

		self::$pg_version =  ( appp_get_setting( 'appp_pg_version' ) ) ? appp_get_setting( 'appp_pg_version' ) : '3.5.0';

		// Define plugin constants
		self::$dir_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$dir_url  = trailingslashit( plugins_url( dirname( plugin_basename( __FILE__ ) ) ) );
		self::$inc_path = self::$dir_path . 'inc/';
		self::$inc_url  = self::$dir_url  . 'inc/';
		self::$css_url  = self::$dir_url  . 'css/';
		self::$img_url  = self::$dir_url  . 'images/';
		self::$js_url   = self::$dir_url  . 'js/';
		self::$tmpl_path= self::$dir_path . 'templates/';
		self::$pg_url   = self::$dir_url  . 'pg/' . self::$pg_version . '/';

		self::$l10n = array(
			'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
			'debug'                       => ( self::is_js_debug_mode() || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ),
			'home_url'                    => home_url(),
			'mobile_browser_theme_switch' => appp_get_setting( 'mobile_browser_theme_switch' ),
			'admin_theme_switch'          => appp_get_setting( 'admin_theme_switch' ),
			'app_offline_toggle'           => ( appp_get_setting( 'app_offline_toggle' ) == 'on' ) ? '' : '1', // on mean it's disabled
			'is_appp_true'                => self::is_app(),
			'noGoBackFlags'				  => array(),
			'ver'						  => self::get_apv(),
			'alert_pop_title'			  => apply_filters('alert_pop_title', get_bloginfo( 'name' ) )
		);

		// Load translations
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Setup our activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Welcom activation
		// add_action( 'admin_init', array( $this, 'welcome_screen_do_activation_redirect' ) );
		// add_action( 'admin_menu', array( $this, 'welcome_screen_pages' ) );
		// add_action( 'admin_head', array( $this, 'welcome_screen_remove_menus' ) );
		// add_action( 'admin_enqueue_scripts', array( $this, 'welcome_screen_assets' ) );

		// Hook in all our important pieces
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
		add_action( 'admin_init', array( $this, 'check_appp_licenses' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ), 8 );
		add_action( 'wp_head', array( $this, 'do_appp_script' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'ajax_login_init' ) );

		// @since WP 4.7
		add_filter( 'stylesheet', array( $this, 'use_appp_theme_in_customizer') );
		add_filter( 'template', array( $this, 'use_appp_theme_in_customizer') );

		// remove wp version param from cordova enqueued scripts (so script loading doesn't break)
		// This will mean that it's harder to break caching on the cordova script
		add_filter( 'script_loader_src', array( $this, 'remove_query_arg' ), 9999 );

		$this->set_deprecate_version();

		require_once( self::$inc_path . 'AppPresser_Admin_Settings.php' );
		require_once( self::$inc_path . 'plugin-updater.php' );
		require_once( self::$inc_path . 'AppPresser_Ajax_Extras.php' );
		require_once( self::$inc_path . 'AppPresser_Remote_Scripts.php' );
		require_once( self::$inc_path . 'AppPresser_AppGeo.php' );
		require_once( self::$inc_path . 'AppPresser_WPAPI_Mods.php' );

		if( ! is_multisite() ) {
			require_once( self::$inc_path . 'AppPresser_Log_Admin.php' );
			require_once( self::$inc_path . 'AppPresser_Logger.php' );
		}

		if( is_admin() ) {
			require_once( self::$inc_path . 'AppPresser_SystemInfo.php' );
		}

	}

	/**
	 * AppPresser licenses admin notification
	 * @since 2.0.0
	 */
	public function check_appp_licenses() {
		require_once( self::$inc_path . 'AppPresser_License_Check.php' );
		AppPresser_License_Check::run();
	}

	/**
	 * Manually add some vars and our script tag so that we can head off the page if need be
	 * @since  1.0.3
	 */
	function do_appp_script() {

		if( self::is_min_ver( 2 ) ) { // v2 or higher
			wp_localize_script( 'jquery', 'apppCore', self::$l10n );
			return;
		}

		// Only use minified files if not debugging scripts
		$min = self::is_js_debug_mode() ? '' : '.min';

		// If PHP can read the cookie, we'll enqueue the standard way
		if ( is_user_logged_in() || self::is_app() ) {
			wp_enqueue_script( 'appp-core', self::$js_url ."appp$min.js", null, self::VERSION );
			wp_localize_script( 'appp-core', 'apppCore', self::$l10n );
			return;
		}

		if ( ! self::$l10n['mobile_browser_theme_switch'] && ! self::$l10n['admin_theme_switch'] )
			return;

		// Otherwise we want to include the script ASAP to redirect the page if need be.

		foreach ( self::$l10n as $key => $value ) {

			if (is_array($value)) {
				$value = implode(',', $value);
				if( class_exists('AppPresser_Logger') ) {
					AppPresser_Logger::log( 'array to string conversion', $value, __FILE__, __METHOD__, __LINE__ );
				}
			}
			$l10n[$key] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8');
		}

		?>
		<script type='text/javascript'>
		/* <![CDATA[ */
		window.apppCore = <?php echo json_encode( $l10n ); ?>;
		/* ]]> */
		</script>
		<script src="<?php echo self::$js_url; ?>appp<?php echo $min; ?>.js" type="text/javascript"></script>
		<?php
	}

	/**
	 * Load textdomain during the plugins_loaded action hook
	 * @since 1.2.1
	 */
	function load_textdomain() {
		load_plugin_textdomain( 'apppresser', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Include all our important files.
	 * @since  1.0.0
	 */
	function includes() {

		require_once( self::$inc_path . 'AppPresser_Theme_Switcher.php' );
		$this->theme_switcher = new AppPresser_Theme_Switcher();

	}

	/**
	 * Activation hook for the plugin.
	 * @since  1.0.0
	 */
	function activate() {

		// code to execute when plugin is activated

		// @TODO: Define default settings upon activation

		set_transient( '_welcome_screen_activation_redirect', true, 30 );

	}

	function welcome_screen_do_activation_redirect() {
	  // Bail if no activation redirect
		if ( ! get_transient( '_welcome_screen_activation_redirect' ) ) {
		return;
	  }

	  // Delete the redirect transient
	  delete_transient( '_welcome_screen_activation_redirect' );

	  // Bail if activating from network, or bulk
	  if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
		return;
	  }

	  // Redirect to Apppresser about page
	  wp_safe_redirect( add_query_arg( array( 'page' => 'welcome-to-apppresser' ), admin_url( 'index.php' ) ) );

	}

	/**
	 * 
	 * @since 2.1.2
	 */
	public function welcome_screen_pages() {
	  add_dashboard_page(
		'Welcome To Apppresser',
		'Welcome To Apppresser',
		'read',
		'welcome-to-apppresser',
		array( $this, 'welcome_screen_content' )
	  );
	}

	/**
	 * 
	 * @since 2.1.2
	 */
	public function welcome_screen_content() {
	  ?>
	  <div class="wrap apppresser-welcome">
		
		<div class="appp-logo"><img src="https://apppresser.com/wp-content/themes/apppresser/images/logo.png" alt="AppPresser"></div>

		<h1><?php _e('Welcome to', 'apppresser' )?> AppPresser <?php echo self::VERSION ?></h1>
		<div class="about-text"><?php _e('Easily Make a Mobile App From Any WordPress Website', 'apppresser'); ?></div>

		<h2 class="nav-tab-wrapper">
		  <a class="nav-tab nav-tab-active" href="#"><?php _e('Quick Start Guide', 'apppresser') ?></a>
		  <a class="nav-tab" href="#"><?php _e('Features For Developers', 'apppresser') ?></a>
		</h2>

		<div id='sections'>
			<section>
				<h3><?php _e('How it works', 'apppresser'); ?></h3>

				<p><?php _e('AppPresser is a suite of WordPress plugins and theme that you install on your site, customize, then package into an app and submit to the app stores.', 'apppresser'); ?></p>

				<p><?php _e('You have activated the core plugin, which is the first step. Next, you will need to purchase some <a href="https://apppresser.com/pricing" target="_blank">extensions</a> to create your app.', 'apppresser'); ?></p>

				<p><?php _e('We have created extensive documentation so you can easily walk through the steps to get your app up and running:', 'apppresser'); ?></p>

				<ul>
					<li><a href="http://v2docs.apppresser.com/article/123-overview" target="_blank"><?php _e('AppPresser Overview', 'apppresser'); ?></a></li>
					<li><a href="http://v2docs.apppresser.com/article/145-a-quick-start-guide" target="_blank"><?php _e('A Quick Start Guide', 'apppresser'); ?></a></li>
				</ul>

			</section>

			<section>
				<h3><?php _e('Developers', 'apppresser'); ?></h3>

				<p><?php _e("AppPresser is like adding the features of a mobile device to a WordPress site. If you could access the <b>device camera, contacts, geolocation, push notifications, and more</b> through WordPress, what could you build? What couldn't you build?", 'apppresser'); ?></p>

				<h3><?php _e('Make money with AppPresser', 'apppresser'); ?></h3>
				<p><?php _e('Make mobile apps for your clients, adding revenue to your business, right away.', 'apppresser'); ?></p>

				<p><a href="https://apppresser.com/developers/" target="_blank"><?php _e('Use device features through Phonegap', 'apppresser'); ?></a></p>
				<ul>
					<li><?php _e('Full WordPress integration', 'apppresser'); ?></li>
					<li><?php _e('Update site &amp; app at the same time', 'apppresser'); ?></li>
					<li><?php _e('You own &amp; host the app', 'apppresser'); ?></li>
					<li><?php _e('No monthly fees', 'apppresser'); ?></li>
				</ul>

				<p><?php _e('Build apps for your clients with AppPresser.', 'apppresser'); ?> <a href="https://apppresser.com/developers/" target="_blank"><?php _e('Learn more', 'apppresser'); ?></a></p>
			</section>
		</div>

	  </div>
	  <?php
	}

	/**
	 * 
	 * @since 2.1.2
	 */
	public function welcome_screen_remove_menus() {
		remove_submenu_page( 'index.php', 'welcome-to-apppresser' );
	}

	/**
	 * 
	 * @since 2.1.2
	 */
	function welcome_screen_assets( $hook ) {
	  if( 'dashboard_page_welcome-to-apppresser' == $hook ) {
		wp_enqueue_script( 'welcome_screen_js', plugin_dir_url( __FILE__ ) . '/js/welcome-script.js', array( 'jquery' ), self::VERSION, true );
		wp_enqueue_style( 'welcome_screen_css', plugin_dir_url( __FILE__ ) . '/css/welcome-styles.css' );
	  }
	}

	/**
	 * Frontend scripts and styles
	 * @since  1.0.0
	 */
	function frontend_scripts() {

		// Only use minified files if SCRIPT_DEBUG is off
		// $min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// Enqueue cordova scripts if we have an app

		if ( self::get_apv( 1 ) ) { // only v1
			if ( appp_is_ios() ) {
				wp_enqueue_script( 'cordova-core', self::$pg_url .'ios/cordova.js', null, filemtime( self::$dir_path .'pg/' . self::$pg_version . '/ios/cordova_plugins.js' ) );
			} elseif ( appp_is_android() ) {
				wp_enqueue_script( 'cordova-core', self::$pg_url .'android/cordova.js', null, filemtime( self::$dir_path .'pg/' . self::$pg_version . '/android/cordova_plugins.js' ) );
			}
		} else {
			wp_enqueue_script( 'jquery' ); // localized vars are attached to this in v2
		}
	}

	/**
	 * Deactivation hook for the plugin.
	 * @since  1.0.0
	 */
	function deactivate() {

		// AppPresser_Logger may not exist if mulit-site
		if( class_exists('AppPresser_Logger') ) {
			AppPresser_Logger::remove_usermeta();
		}
	}

	/**
	 * Strip query var from enqueued cordova script
	 * @since  1.0.3
	 * @param  string  $src URL
	 * @return string       Modified URL
	 */
	function remove_query_arg( $src ) {
		if ( false !== strpos( $src, 'cordova.js' ) )
			$src = remove_query_arg( 'ver', $src );
		return $src;
	}

	/**
	 * Utility method for getting our plugin's settings
	 * @since  1.0.0
	 * @param  string $key      Optional key to get a specific option
	 * @param  string $fallback Fallback option if none is found.
	 * @return mixed            Array of all options, a specific option, or false if specific option not found.
	 */
	public static function settings( $key = false, $fallback = false ) {
		if ( self::$settings === 'false' ) {
			self::$settings = get_option( self::SETTINGS_NAME );
			self::$settings = empty( self::$settings ) ? array() : (array) self::$settings;
		}
		if ( $key ) {
			$setting = isset( self::$settings[ $key ] ) ? self::$settings[ $key ] : false;
			// Override value or supply fallback
			$return = apply_filters( 'apppresser_setting_default', $setting, $key, self::$settings, $fallback );
			return $return ? $return : $fallback;

		}
		return self::$settings;
	}


	/**
	 * Set the cookie
	 * @since 2.0.0
	 * 
	 * @param int $ver version number
	 */
	public static function set_app_cookie( $ver = 1 ) {
		$ver = ( $ver == 1 ) ? '' : $ver;
		setcookie( 'AppPresser_Appp'.$ver, 'true', time() + ( DAY_IN_SECONDS * 30 ), '/' );
	}

	/**
	 * Set the cookie for debugging scripts
	 * @since 2.0.0
	 */
	public static function set_debug_cookie() {
		setcookie( 'AppPresser_Debug_Scripts', 'true', time() + ( DAY_IN_SECONDS * 30 ), '/' );
	}

	/**
	 * Set the cookie for bypass session
	 * @since 2.0.0
	 */
	public static function set_bypass_cookie( $end_of_session = true ) {

		$timeout = ($end_of_session) ? 0 : time()-86400; // timeout after session or immediately (yesterday)

		setcookie( 'AppPresser_Bypass', 'true', $timeout, '/' );
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
	 * A wrapper for get_apv which returns an integer of the current version number or zero if not found,
	 * this converts it to a boolean; updated in 2.0 for backwards compatiblity.
	 * @since  1.0.0
	 * @return boolean Variable value
	 */
	public static function is_app() {
		return (self::get_apv());
	}

	/**
	 * Gets the appp=1 value whether set by url param or cookie
	 * @since  2.0.0
	 * @return boolean value
	 */
	public static function read_app_version() {
		if ( self::$is_apppv !== null )
			return self::$is_apppv;

		if( isset( $_GET['appp_bypass'] ) && $_GET['appp_bypass'] == 'false' ) {
			self::set_bypass_cookie(false);
		} else if( ( isset( $_GET['appp_bypass'] ) && $_GET['appp_bypass'] == 'true' ) || ( isset( $_COOKIE['AppPresser_Bypass'] ) && $_COOKIE['AppPresser_Bypass'] == 'true' ) ) {
			if( isset( $_GET['appp_bypass'] ) )
				self::set_bypass_cookie();

			return self::$is_apppv = 0;
		}

		if( isset( $_GET['appp'] ) && $_GET['appp'] == 3 || isset( $_COOKIE['AppPresser_Appp3'] ) && $_COOKIE['AppPresser_Appp3'] === 'true' ) {
			self::$is_apppv = 3;
		} else if( isset( $_GET['appp'] ) && $_GET['appp'] == 2 || isset( $_COOKIE['AppPresser_Appp2'] ) && $_COOKIE['AppPresser_Appp2'] === 'true' ) {
			self::$is_apppv = 2;
		} else if( ( isset( $_GET['appp'] ) && $_GET['appp'] == 1 ) || isset( $_COOKIE['AppPresser_Appp'] ) && $_COOKIE['AppPresser_Appp'] === 'true' ) {
			self::$is_apppv = 1;
		} else {
			self::$is_apppv = 0;
		}

		return self::$is_apppv;
	}

	/**
	 * Gets or compares the app version from the appp=X url param or cookie
	 * get_apv() will return an integer of the exact version
	 * get_apv(2) will return boolean if it's an exact match
	 * get_apv(1, true) will return boolean if app is x >= 
	 * @since 2.0.0
	 * @param int $is_ver the version to check against
	 * @param boolean $min_ver to check if the current version is >= $is_ver
	 * @return int|boolean Variable value
	 */
	public static function get_apv( $is_ver = 0, $min_ver = false ) {

		if( $is_ver && $min_ver ) {

			// Compare a minimum version

			return ( self::read_app_version() >= $is_ver );
		} else if( $is_ver ) {
			
			// Compare exact version in $is_ver
			
			if( self::read_app_version() == $is_ver ) {
				return true;
			} else {
				return false;
			}
		} else {
			
			// Return the exact version
			
			return self::read_app_version();
		}
	}

	/**
	 * A wrapper for get_apv when getting the minimum version
	 */
	public static function is_min_ver( $is_ver ) {
		return self::get_apv( $is_ver, true );
	}

	/**
	 * Checks for debug settings either by
	 * - defined constant 'SCRIPT_DEBUG' or
	 * - url parameter 'apppdebug' or 
	 * - cookie 'AppPresser_Debug_Scripts'
	 * @since 2.0
	 * @return boolean value
	 */
	public static function is_js_debug_mode() {
		if( self::$debug === null) {
			if( isset( $_GET['apppdebug'] ) ) {
				self::set_debug_cookie();
			}
			self::$debug = (( isset( $_GET['apppdebug'] ) ) || 
							( isset( $_COOKIE['AppPresser_Debug_Scripts'] ) && $_COOKIE['AppPresser_Debug_Scripts'] === 'true' ) ||
							( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ));
		}

		return self::$debug;
	}

	/**
	 * Gets either the BuddyPress avatar if set or the avatar set by WordPress
	 */
	public function get_avatar_url( $user_id ) {
		if(function_exists('bp_core_fetch_avatar') ) {
			$avatar =  bp_core_fetch_avatar( array(
								'item_id' => $user_id,
								'html'    => false
							) );
		} else {
			$avatar = get_avatar_url( $user_id );
		}

		// Gravatar leaves off the protocol, so we'll add https so it doesn't default to file:// on the app.
		if( $avatar && strpos($avatar, '//') === 0 ) {
			$avatar = 'https:' . $avatar;
		}

		return $avatar;
	}

	/**
	 * Adds language textdomain options for form#loginform modal in apptheme 2.1.3 and ion 1.0.1
	 * @since 2.0.1
	 */
	public function ajax_login_init() {

		$l10n = array( 
			'processing' => __('Logging in....', 'apppresser'),
			'required'   => __('Fields are required', 'apppresser'),
			'error'      => __('Error Logging in', 'apppresser'),
		);

		$l10n = $this->custom_login_redirect( $l10n );

		wp_localize_script( 'jquery', 'appp_ajax_login', $l10n );

	}

	/**
	 * Adds custom login redirect for form#loginform modal in apptheme 2.5.0 and ion 1.4.0
	 * @since 2.7.0
	 * @param array $l10n
	 * @return array $l10n
	 */
	public function custom_login_redirect( $l10n ) {
		if( has_filter( 'appp_login_redirect' ) ) {
			$l10n['login_redirect'] = apply_filters( 'appp_login_redirect', '' );
		}

		return $l10n;
	}

	// @since WP 4.7
	public function use_appp_theme_in_customizer( $theme ) {

		if( strpos( $_SERVER['REQUEST_URI'], 'customize.php' ) && isset( $_GET['theme'] ) ) {
			
			$appp_theme = appp_get_setting( 'appp_theme' );
			
			if( $appp_theme ) {
				return $appp_theme;
			}
		}

		return $theme;
	}

	public static function set_deprecate_version( $deprecate_ver = null ) {
		if( ! is_null( $deprecate_ver ) ) {
			self::$deprecate_ver = $deprecate_ver;
			update_option( 'appp_deprecate_ver', self::$deprecate_ver, true );
			update_option( 'appp_settings_ver', self::$deprecate_ver, true );
		} else if( isset( $_GET['appp_deprecate_ver'] ) && is_numeric( $_GET['appp_deprecate_ver'] ) ) {
			self::$deprecate_ver = (int)$_GET['appp_deprecate_ver'];
			update_option( 'appp_deprecate_ver', self::$deprecate_ver, true );
			update_option( 'appp_settings_ver', self::$deprecate_ver, true );
		} else {
			self::$deprecate_ver = get_option( 'appp_deprecate_ver', self::$deprecate_ver );
		}
	}

	public static function is_deprecated( $deprecate_ver = 0 ) {
		return ( self::$deprecate_ver <= $deprecate_ver );
	}

	public static function get_theme_mod( $key, $default = '' ) {
		$appp_theme = self::settings( 'appp_theme' );
		$theme_settings = self::settings('theme_mods_' . $appp_theme );

		if( isset( $theme_settings, $theme_settings[$key] ) && ! empty( $theme_settings[$key] ) ) {
			return $theme_settings[$key];
		}
		
		return $default;
	}

}

// Singleton rather than a global.. If they want access, they can use:
AppPresser::get();

/**
 * Function wrapper for AppPresser::settings()
 * @since  1.0.0
 * @param  string $key      Optional key to get a specific option
 * @param  string $fallback Fallback option if none is found.
 * @return mixed            Array of all options, a specific option, or false if specific option not found.
 */
function appp_get_setting( $key = false, $fallback = false ) {
	return AppPresser::settings( $key, $fallback );
}

function appp_get_theme_mod( $key, $default = '' ) {
	return AppPresser::get_theme_mod( $key, $default );
}
