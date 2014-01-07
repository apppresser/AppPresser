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
		add_action( 'plugins_loaded', array( $this, 'switch_theme' ), 9999 );
		add_filter( 'pre_option_show_on_front', array( $this, 'pre_show_on_front' ) );
		add_filter( 'pre_option_page_on_front', array( $this, 'pre_page_on_front' ) );
 		add_action( 'template_redirect', array( $this, 'check_appaware' ) );

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
		if ( self::is_app() ) {
			setcookie( 'AppPresser_Appp', 'true', time() + ( DAY_IN_SECONDS * 30 ) );
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

	/**
	 * Checks if selected theme supports apppresser. Theme should have `add_theme_support( 'apppresser' );`
	 * If not, dies with a message and link to the AppPresser settings page.
	 *
	 * @since  1.0.4
	 */
	public function check_appaware() {
		if ( ! current_theme_supports( 'apppresser' ) ) {
			wp_die( '<p style="text-align:center;font-size:1.1em"><strong>'. __( 'This theme does not support AppPresser.', 'apppresser' ) . '</strong><br>' . sprintf( __( 'Please change your %s to an AppAware theme.', 'apppresser' ), '<a href="'. AppPresser_Admin_Settings::url() .'">'. __( '"App only theme?" setting', 'apppresser' ) .'</a>' ) .'</p>' );
		}
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
