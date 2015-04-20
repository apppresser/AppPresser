<?php
/**
 * Custom App-theme Customizer
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_Theme_Customizer extends AppPresser {

	public function __construct() {

		// Get saved apppresser theme
		$appp_theme = appp_get_setting( 'appp_theme' );

		// If we don't have 'Use different theme for app?' option, bail
		if ( ! $appp_theme )
			return;

		// Add customizer link to appp_theme field description
		add_filter( 'apppresser_field_markup_paragraph', array( $this, 'add_customizer_link' ), 10, 4 );

		// If not in the customizer, bail
		if ( ! isset( $_GET['appp_theme'] ) )
			return;

		// If we're trying to customize the apppresser theme, make sure we're viewing the right theme.
		if ( ! isset( $_GET['theme'] ) || $appp_theme != $_GET['theme'] ) {
			add_action( 'admin_init', array( $this, 'redirect_correct_appp_theme' ) );
		}

		$this->mods = array_keys( (array) get_option( "theme_mods_$appp_theme" ) );

		// Cache non-saved theme_mod settings
		add_action( 'customize_register', array( $this, 'get_registered' ), 9999 );

		add_action( 'customize_render_control', array( $this, 'attach_warning' ) );
		// Filter back button url
		add_filter( 'clean_url', array( $this, 'change_back_button_url' ) );
		// Filter the 'save' button text
		add_filter( 'gettext', array( $this, 'modify_customizer_text_strings' ) );
		// Allow some html through for the labels
		add_filter( 'esc_html', array( $this, 'allow_asterisk_markup' ), 10, 2 );
	}

	/**
	 * Add customizer link to the app-only theme setting description
	 * @since 1.0.7
	 * @param string  $field   Field markup
	 * @param string  $key     Setting key
	 * @param mixed   $setting Setting value
	 * @param array   $args    Array of arguments for field
	 */
	public function add_customizer_link( $field, $key, $setting, $args ) {
	
		// Only modify the 'appp_theme' setting
		if ( 'customizer_link' !== $key )
			return $field;

		// Get the customizer url
		$url = esc_url( add_query_arg( array(
			'appp_theme' => true,
			'theme' => $setting,
		), admin_url( 'customize.php' ) ) );

		// Add url to description
		$description_with_url = $args['value'] . sprintf( '<a class="button button-primary button-large" href="%s">%s</a>', $url, __( 'Open Customizer', 'apppresser' ) );

		// Replace description with new
		$field = str_replace( $args['value'], $description_with_url, $field );

		return $field;
	}

	/**
	 * If customizing apppresser theme, makes sure we're viewing the right theme.
	 * @since  1.0.7
	 */
	public function redirect_correct_appp_theme() {
		wp_redirect(  esc_url( add_query_arg( 'theme', appp_get_setting( 'appp_theme' ) ) ) );
	}

	/**
	 * Cache non-saved theme_mod settings
	 * @since  1.0.8
	 * @param  WP_Customize_Manager $wp_customize Theme Customizer object.
	 */
	public function get_registered( $customizer ) {
		$setttings = $customizer->settings();
		if ( ! is_array( $setttings ) || empty( $setttings ) )
			return;

		// Cache JUST our theme_mods as "safe" to change
		foreach ( $customizer->settings() as $id => $control ) {
			// add to the array of "safe" settings
			if ( 'theme_mod' == $control->type ) {
				$this->mods[] = $id;
			}
		}
	}

	/**
	 * Change customizer back button url for our app-theme version
	 * @since  1.0.7
	 * @param  WP_Customize_Control object
	 */
	public function attach_warning( $customize ) {

		// If this is an app-theme mod..
		if ( ! $is_appp_mod = in_array( $customize->id, $this->mods(), true ) ) {
			/// loop through our mods and see if we're looking at a sub-setting
			foreach ( $this->mods() as $mod_key ) {
				if ( false !== stripos( $customize->id, $mod_key ) ) {
					$is_appp_mod = true;
					break;
				}
			}
		}
		// If this is NOT an app-theme mod
		if ( ! $is_appp_mod ) {
			// Add a warning asterisk to the control label
			$customize->label = $customize->label . ' <span class="file-error">*</span>';
		}
	}

	/**
	 * Change customizer back button url for our app-theme version
	 * @since  1.0.7
	 * @param  string  $url Original url
	 * @return string       Maybe modified url
	 */
	public function change_back_button_url( $url ) {
		if ( $url == admin_url( 'themes.php' ) ) {
			return AppPresser_Admin_Settings::url();
		}
		return $url;
	}

	/**
	 * Change text for certain customizer strings four our custom version.
	 * @since  1.0.7
	 * @param  string  $translated_text Input
	 * @return string                   Maybe modified text
	 */
	public function modify_customizer_text_strings( $translated_text ) {
		switch ( $translated_text ) {
			case 'Save &amp; Publish':
				return __( 'Save App Settings', 'apppresser' );
			case 'You are previewing %s':
				$notice = '<p>'. __( 'You are previewing the app-only theme: ', 'apppresser' ) .'</p>';
				// This is the theme title, do not remove
				$notice .= '<p>%s</p>';
				// Fair Warning on non-app-theme settings
				$notice .= sprintf( '<span class="file-error">%s</span> ', __( 'WARNING:', 'apppresser' ) );
				$notice .= sprintf( __( 'Settings with an asterisk (%s) are not specific to the app-only theme, and will effect your desktop theme.', 'apppresser' ), '<span class="file-error">*</span>' );
				$notice = sprintf( '<p>%s</p>', $notice );
				return $notice;
		}
		return $translated_text;
	}

	/**
	 * This filters `esc_html()` and allows some markup for our red warning asterisks
	 * @since  1.0.7
	 * @param  string  $safe_text Sanitized text
	 * @param  string  $text      Unsanitized text
	 * @return string             Maybe modified sanitized text
	 */
	public function allow_asterisk_markup( $safe_text, $text ) {
		// If we have a warning asterisk
		if ( false !== strpos( $text, '<span class="file-error">*</span>' ) ) {
			// Remove markup
			$safe_text = str_replace( '<span class="file-error">*</span>', '', $text );
			// Clean up the text
			$safe_text = wp_check_invalid_utf8( $safe_text );
			$safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
			// And re-add the markup
			$safe_text = $safe_text . '<span class="file-error">*</span>';
		}
		// Send back sanitized text
		return $safe_text;
	}

	/**
	 * Our cached theme mod settings
	 * @since  1.0.8
	 * @return array  Cached theme mod settings array
	 */
	public function mods() {
		return isset( $this->mods ) ? $this->mods : array();
	}

}
