<?php
/**
 * Theme Switcher
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_Theme_Switcher extends AppPresser {

	public $original_template   = null;
	public $original_stylesheet = null;
	public $theme               = null;
	public $appp_theme          = false;

	/**
	 * Party Started
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'switch_theme' ), 9999 );
		add_action( 'plugins_loaded', array( $this, 'clear_cookies_if_not_app' ), 99999 );
		add_filter( 'pre_option_show_on_front', array( $this, 'pre_show_on_front' ) );
		add_filter( 'pre_option_page_on_front', array( $this, 'pre_page_on_front' ) );

		// cache the activated theme object
		$this->theme = wp_get_theme();
	}

	public function is_theme_customizer() {
		return isset( $_GET['customize_theme'] );
	}

	/**
	 * AppPresser theme switcher for admins
	 * @since  1.0.0
	 * @return null
	 */
	public function switch_theme() {

		$dont_switch = (
			// If viewing the appp_theme customizer, we need the theme to be switched so the theme mods save properly
			( $this->is_theme_customizer() || is_admin() ) && ! $this->is_appp_theme_customizer()
		);

		if ( $dont_switch ) {
			return;
		}

		// Set cookie from querystring if request is coming from an app
		if ( self::get_apv( 1 ) ) { // only v1
			self::set_app_cookie();
		}

		if ( self::get_apv( 2 ) ) { // only v2
			self::set_app_cookie( 2 );
		}

		if ( self::get_apv( 3 ) ) { // only v3
			self::set_app_cookie( 3 );
		}

		$do_switch = appp_get_setting( 'appp_theme' ) && (
			// check if user is running native app
			( self::is_app() )
			// check if the setting is enabled to view the APP theme as an administrator
			|| (
				appp_get_setting( 'admin_theme_switch' ) == 'on'
				&& current_user_can( 'manage_options' )
			)
			// it's not an app but we want to switch the theme for mobile
			|| (
				! self::is_app()
				&& appp_get_setting( 'mobile_browser_theme_switch' ) == 'on'
				&& wp_is_mobile()
			)
			// If we're previewing the app theme
			|| $this->is_appp_theme_customizer()
		);

		if ( ! $do_switch )
			return;

		
		$this->appp_theme = $this->get_app_theme();

		// switch the current theme to use the AppPresser theme
		add_filter( 'option_template', array( $this, 'template_request' ), 5 );
		add_filter( 'option_stylesheet', array( $this, 'stylesheet_request' ), 5 );
		add_filter( 'template', array( $this, 'maybe_switch' ) );
	}

	/*
	 * Clear cookie if not in app or preview. Prevents AP3 theme from being shown to admin after customizing in myapppresser.com. Clears cookie, but requires refresh to show desktop theme.
	 */
	public function clear_cookies_if_not_app() {

		$referrer = ( isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null );

		// if myapppresser is the referrer, we are in the preview. Set cookie so links to other pages stay with AP3 theme
		if( $referrer && preg_match('/myapppresser/', $referrer ) ) {
			setcookie("AppPresser_Preview", "true", time() + (5 * 60), "/");
			return;
		}

		// if not on mobile, and using v3, and not in preview, clear AP3 cookie to show desktop theme
		if( !wp_is_mobile() && self::get_apv() === 3 && isset( $_COOKIE["AppPresser_Appp3"] ) && !isset( $_COOKIE["AppPresser_Preview"] ) ) {
			setcookie( 'AppPresser_Appp3', '', time()-300, '/' );
			header("Refresh:0");
		}
	}

	public function get_app_theme_slug() {

		if( self::is_min_ver( 3 ) ) {

			/**
			 * Child theme:  ion-ap3-child
			 * Parent theme: ap3-ion-theme
			 * Filter:       appp_theme
			 */

			$child_theme_slug = 'ion-ap3-child';

			$child_theme = wp_get_theme( $child_theme_slug );

			if ( $child_theme->exists() ) {
				$theme = $child_theme_slug;
			} else {
				$theme = apply_filters( 'appp_theme', 'ap3-ion-theme' );
			}

		} else {
			// Get the saved setting's theme object
			$theme = appp_get_setting( 'appp_theme' );
		}

		return $theme;
	}

	public function get_app_theme() {
		return wp_get_theme( $this->get_app_theme_slug() );
	}

	/**
	 * Check url to determine if we are in the appp_theme customizer
	 * @since  1.0.7
	 * @return boolean True if we're in the customizer
	 */
	public function is_appp_theme_customizer() {
		if ( isset( $this->is_appp_customizer ) )
			return $this->is_appp_customizer;

		// Check if we're in the appp theme customizer
		$this->is_appp_customizer = isset( $_GET['appp_theme'], $_GET['theme'] )
		// or during ajax requests from the appp theme customizer
		|| ( isset( $_REQUEST['wp_customize'], $_REQUEST['theme'] ) && appp_get_setting( 'appp_theme' ) == $_REQUEST['theme'] );

		return $this->is_appp_customizer;
	}

	/**
	 * Cache our original template and maybe switch themes
	 * @since  1.0.5
	 * @param  string  $template Template name
	 * @return string            Maybe modified template name
	 */
	public function template_request( $template ) {
		// Cache our original template request
		$this->original_template = null === $this->original_template ? $template : $this->original_template;

		return $this->maybe_switch( $template );
	}

	/**
	 * Cache our original stylesheet and maybe switch themes
	 * @since  1.0.5
	 * @param  string  $stylesheet Stylesheet template name
	 * @return string              Maybe modified template name
	 */
	public function stylesheet_request( $stylesheet ) {
		// Cache our original stylesheet request
		$this->original_stylesheet = null === $this->original_stylesheet ? $stylesheet : $this->original_stylesheet;

		return $this->maybe_switch( $stylesheet, true );
	}

	/**
	 * AppPresser switch theme function
	 * @since  1.0.0
	 * @param  string  $template           template name
	 * @param  boolean $stylesheet_request Request for template or stylesheet theme name
	 * @return string                      Modified template name
	 */
	public function maybe_switch( $template = '', $stylesheet_request = false ) {

		// Ensure we return something
		if ( ! $template ) {
			$template = $stylesheet_request
				? $this->original_stylesheet
				: $this->original_template;
		}

		// If we're not doing the theme switch, bail
		if ( ! $this->appp_theme )
			return $template;

		// Ok, do the template switch
		$template = $stylesheet_request
			// If a request for the stylesheet dir name, give back our setting
			? $this->get_app_theme_slug()
			// Otherwise, give back our saved settings parent theme dir (if it has one)
			: $this->appp_theme->get_template();

		// return the switched template
		return $template;
	}

	/**
	 * AppPresser set the default home page view to page if running the APPP theme
	 * @since  1.0.0
	 * @return mixed 'page' if APPP theme is running or false
	 */
	public function pre_show_on_front() {

		if( !appp_get_setting( 'appp_home_page' ) && !appp_get_setting( 'appp_show_on_front' ) ) {
			return false;
		}

		$this->theme = wp_get_theme();
		if ( $this->theme->template == $this->maybe_switch() && ! is_admin() ) {
			
			if( appp_get_setting( 'appp_show_on_front' ) == 'latest_posts' ) {
				return 'posts';
			}

			return 'page';
		}

		return false;
	}

	/**
	 * AppPresser set the default home page based on the APPP settings
	 * @since  1.0.0
	 * @return int page ID stored in APPP settings
	 */
	public function pre_page_on_front() {

		$this->theme = wp_get_theme();
		if ( $this->theme->template == $this->maybe_switch() && ! is_admin() ) {
			return appp_get_setting( 'appp_home_page' );
	  	}

		return false;
	}

}

/**
 * AppPresser detect iOS function
 * @since  1.0.0
 * @return true if device is running iOS
 */
function appp_is_ios() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( $_SERVER['HTTP_USER_AGENT'] ) : '';
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
