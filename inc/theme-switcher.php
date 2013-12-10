<?php
/**
 * Theme Switcher
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_Theme_Switcher extends AppPresser {

	// A single instance of this class.
	public static $instance     = null;
	public static $switch_theme = false;

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return AppPresser_Theme_Switcher A single instance of this class.
	 */
	public static function go() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Party Started
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'switch_theme' ), 1 );
		add_filter( 'pre_option_show_on_front', array( $this, 'pre_show_on_front' ) );
		add_filter( 'pre_option_page_on_front', array( $this, 'pre_page_on_front' ) );
		$this->theme = wp_get_theme();
	}

	/**
	 * AppPresser theme switcher for admins
	 * @since  1.0.0
	 * @return null
	 */
	public function switch_theme() {

		if ( is_admin() )
			return;

		// Set cookie from querystring if request is coming from an app
		if ( isset( $_GET['appp'] ) && $_GET['appp'] == 1 || isset( $_COOKIE['AppPresser_Appp'] ) ) {
			setcookie( 'AppPresser_Appp', 'true', time() + ( DAY_IN_SECONDS * 30 ) );
			self::is_app( 1 );
		}

		if (
			// check if user is running native app
			self::is_app()
			// check if the setting is enabled to view the APP theme as an administrator
			|| ( self::settings( 'admin_theme_switch' ) == 'on' && current_user_can( 'manage_options' ) )
			// it's not an app but we want to switch the theme for mobile
			|| ( ! self::is_app() && self::settings( 'mobile_browser_theme_switch' ) == 'on' && wp_is_mobile() )
		) {

			self::$switch_theme = true;

			// switch the current theme to use the AppPresser theme
			add_filter( 'template', array( $this, 'do_switch' ) );
			add_filter( 'option_template', array( $this, 'do_switch' ) );
			add_filter( 'option_stylesheet', array( $this, 'do_switch' ) );

		}

	}

	/**
	 * AppPresser switch theme function
	 * @since  1.0.0
	 * @return theme name to load
	 */
	public function do_switch( $template = '' ) {

		$template = $template ? $template : $this->theme->name;
		// load the AppPresser theme setting
		$template = self::settings( 'appp_theme' ) ? self::settings( 'appp_theme' ) : $template;

		return $template;
	}

	/**
	 * AppPresser set the default home page view to page if running the APPP theme
	 * @since  1.0.0
	 * @return 'page' if APPP theme is running
	 */
	public function pre_show_on_front() {

		$this->theme = wp_get_theme();
		if ( $this->theme->template == $this->do_switch() && ! is_admin() ) {
			return 'page';
		}

		return false;
	}

	/**
	 * AppPresser set the default home page based on the APPP settings
	 * @since  1.0.0
	 * @return page ID stored in APPP settings
	 */
	public function pre_page_on_front() {

		$this->theme = wp_get_theme();
		if ( $this->theme->template == $this->do_switch() && ! is_admin() ) {
			return appp_get_setting( 'appp_home_page' );
	  	}

		return false;
	}

}
AppPresser_Theme_Switcher::go();

/**
 * AppPresser detect iOS function
 * @since  1.0.0
 * @return true if device is running iOS
 */
function appp_is_ios() {
	$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
	return ( strstr( $ua, 'iphone' ) || strstr( $ua, 'ipod' ) || strstr( $ua, 'ipad' )
	);
}

/**
 * AppPresser detect Android function
 * @since  1.0.0
 * @return true if device is running Android
 */
function appp_is_android() {
	$ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );
	return ( false !== stripos( $ua, 'android' ) );
}

