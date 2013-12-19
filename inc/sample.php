<?php
/**
 * AppPresser Settings API walkthrough
 *
 * To save this as a settings section for your AppPresser theme, save this to a file named `appp-settings.php` in the root folder of your theme.
 *
 * You will need to do this if you want the options to be visible even when the theme is not the currently active theme, but is the selected theme to display for mobile/app/admin users.
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

add_action( 'apppresser_add_settings', 'appp_add_sample_settings' );
/**
 * Add Settings!
 * @param object  $apppresser  AppPresser_Admin_Settings Instance
 */
function appp_add_sample_settings( $apppresser ) {

	// Create a new tab for our settings
	$apppresser->add_setting_tab( __( 'New Tab', 'appp' ), 'new-tab-slug' );

	// Add a license key setting. This works with 'appp_updater_add' or 'appp_theme_updater_add'
	$apppresser->add_setting( MY_LICENSE_SETTING_KEY, __( 'AppPresser Extension License Key', 'apppresser' ), array( 'type' => 'license_key', 'helptext' => __( 'Adding a license key enables automatic updates.', 'apppresser' ) ) );

	// Text input setting
	$apppresser->add_setting( 'my_text_input', __( 'Write some text', 'appp' ), array(
		'tab'         => 'new-tab-slug', // Add these settings to our new tab
		'description' => __( 'Input description (optional)', 'appp' ),
		'helptext'    => __( 'This is a (optional) help text displayed in a tooltip to explain this setting. Will use custom sanitization to replace "red" with "green"', 'appp' ),
	) );

	// Checkbox setting
	$apppresser->add_setting( 'my_checkbox', __( 'Check this box', 'appp' ), array(
		'tab'         => 'new-tab-slug',
		'type'        => 'checkbox',
		'description' => __( 'Input description (optional)', 'appp' ),
		'helptext'    => __( 'This is a (optional) help text displayed in a tooltip to explain this setting.', 'appp' ),
	) );

	// Radio setting
	$apppresser->add_setting( 'my_radio', __( 'Select an option', 'appp' ), array(
		'tab'         => 'new-tab-slug',
		'type'        => 'radio',
		'description' => __( 'Input description (optional)', 'appp' ),
		'helptext'    => __( 'This is a (optional) help text displayed in a tooltip to explain this setting.', 'appp' ),
		'options'     => array(
			'value-1' => __( 'Value 1', 'appp' ),
			'value-2' => __( 'Value 2', 'appp' ),
		),
	) );

	// Add a section label/title
	$apppresser->add_setting_label( __( 'More Options', 'apppresser' ), array(
		'tab' => 'new-tab-slug',
		// 'helptext' => __( 'These are additional options.', 'apppresser' ),
		// 'description' => __( 'This is a sub-title.', 'apppresser' ),
	) );

	// Select setting
	$apppresser->add_setting( 'my_select', __( 'Select another option', 'appp' ), array(
		'tab'         => 'new-tab-slug',
		'type'        => 'select',
		'description' => __( 'Input description (optional)', 'appp' ),
		'helptext'    => __( 'This is a (optional) help text displayed in a tooltip to explain this setting.', 'appp' ),
		'options'     => array(
			'value-1' => __( 'Value 1', 'appp' ),
			'value-2' => __( 'Value 2', 'appp' ),
			'value-3' => __( 'Value 3', 'appp' ),
			'1-more'  => __( 'One More', 'appp' ),
		),
	) );

	// Custom setting type
	$apppresser->add_setting( 'my_custom_setting', __( 'This is a custom type', 'appp' ), array(
		'tab'         => 'new-tab-slug',
		'type'        => 'custom_disabled',
		'description' => __( 'This field is disabled and read only', 'appp' ),
		'helptext'    => __( 'This is a (optional) help text displayed in a tooltip to explain this setting.', 'appp' ),
	) );

}

add_filter( 'apppresser_field_markup_custom_disabled', 'appp_add_custom_type', 10, 4 );
/**
 * Create a custom setting type, a readonly, disabled input
 * @param  string  $field Field html (defaults to text input)
 * @param  string  $key   Option key
 * @param  mixed   $value Option value
 * @param  array   $args  Settings arguments
 * @return string         Modified input html
 */
function appp_add_custom_type( $field, $key, $value, $args ) {

	$field = sprintf( '<input class="regular-text" type="text" disabled="disabled" readonly="readonly" placeholder="Read-only Values" id="apppresser--%1$s" name="appp_settings[%2$s]" value="%3$s" /><p class="description">%4$s</p>'."\n", $key, $key, $value, $args['description'] );

	return $field;
}

add_filter( 'apppresser_sanitize_setting', 'appp_sanitize_custom_type', 10, 2 );
/**
 * Custom Sanitization for our text input. Replace 'red' with 'green'
 * @param  mixed  $sanitized_value Value after default sanitization
 * @param  string $key             Option key
 * @return mixed                   Modified value
 */
function appp_sanitize_custom_type( $sanitized_value, $key ) {

	if ( 'my_text_input' == $key ) {
		$sanitized_value = str_ireplace( 'red', 'green', $sanitized_value );
	}

	return $sanitized_value;
}

add_action( 'apppresser_tab_buttons_new-tab-slug', 'appp_add_sample_button' );
/**
 * Add a secondary button to your tab next to the save button
 */
function appp_add_sample_button() {
	echo '<a class="button-secondary" href="'. home_url() .'">'. __( 'Go Home', 'appp' ) .'</a>';
}

add_action( 'apppresser_tab_bottom_new-tab-slug', 'appp_add_some_text' );
/**
 * Add an arbitrary row to an options tab in the AppPresser Settings API.
 */
function appp_add_some_text() {

	$link = sprintf( '<a href="#link">%s</a>', __( 'link to another resource', 'appp' ) );
	?>
	<tr>
		<td colspan="2">
			<?php printf( __( 'This is a sentence describing the reason for this tab of settings. You can add a %s.', 'appp' ), $link ); ?>
		</td>
	</tr>
	<?php
}
