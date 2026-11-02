<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function apppresser_bridge_video_settings( $appp ) {
	// Add video setting label
	$appp->add_setting_label(
		__( 'Video Settings', 'app-customization' ),
		array(
			'tab' => 'app_customization_tab',
		)
	);
	// Add post type checkboxes settings
	$appp->add_setting(
		'youtube_post_types',
		__( 'YouTube Support', 'app-customization' ),
		array(
			'type'     => 'youtube_post_types',
			'tab'      => 'app_customization_tab',
			'helptext' => __( 'Check post types to enable YouTube support in the app.', 'app-customization' ),
		)
	);
}
add_action( 'apppresser_add_settings', 'apppresser_bridge_video_settings', 61 );

function apppresser_bridge_youtube_post_types_field( $field, $key, $value, $args ) {
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	$saved      = appp_get_setting( 'youtube_post_types' );

	$field .= '<p style="margin-bottom: 5px;"><a href="#" class="appp-youtube-select-all">' . esc_html__( 'Select All', 'app-customization' ) . '</a> | <a href="#" class="appp-youtube-select-none">' . esc_html__( 'Clear', 'app-customization' ) . '</a></p>';

	if ( function_exists( 'buddypress' ) ) {
		$checked = '';

		if ( $saved ) {
			$setting = is_array( $saved ) && in_array( 'bp_activity', $saved, true );
			$checked = checked( $setting, 1, false );
		}

		$field .= '<label><input ' . $checked . ' type="checkbox" name="appp_settings[youtube_post_types][]" value="bp_activity">&nbsp;' . esc_html__( 'BuddyPress Activity', 'app-customization' ) . '</label><br>' . "\n";
	}

	foreach ( $post_types as $post_type => $object ) {
		$checked = '';

		if ( $saved ) {
			$setting = is_array( $saved ) && in_array( $post_type, $saved, true );
			$checked = checked( $setting, 1, false );
		}

		$field .= '<label><input ' . $checked . ' type="checkbox" name="appp_settings[youtube_post_types][]" value="' . esc_attr( $post_type ) . '">&nbsp;' . esc_html( $object->labels->name ) . '</label><br>' . "\n";
	}

	$field .= '<script type="text/javascript">(function(){if(typeof document==="undefined"){return;}var allLinks=document.querySelectorAll(".appp-youtube-select-all");allLinks.forEach(function(selectAll){var container=selectAll.closest("tr")||document;var selectNone=container.querySelector(".appp-youtube-select-none");var checkboxes=container.querySelectorAll("input[type=checkbox][name=\"appp_settings[youtube_post_types][]\"]");selectAll.addEventListener("click",function(e){e.preventDefault();checkboxes.forEach(function(checkbox){checkbox.checked=true;});});if(selectNone){selectNone.addEventListener("click",function(e){e.preventDefault();checkboxes.forEach(function(checkbox){checkbox.checked=false;});});}});})();</script>';

	return $field;
}
add_filter( 'apppresser_field_override_youtube_post_types', 'apppresser_bridge_youtube_post_types_field', 10, 4 );

/**
 * Register the appp-video rewrite rule when YouTube support is enabled.
 */
function apppresser_bridge_register_video_rewrite_rule() {
	$youtube_post_types = appp_get_setting( 'youtube_post_types' );

	if ( empty( $youtube_post_types ) || ! is_array( $youtube_post_types ) ) {
		return;
	}

	add_rewrite_rule( '^appp-video/?$', 'index.php?appp_video=1', 'top' );
}
add_action( 'init', 'apppresser_bridge_register_video_rewrite_rule', 20 );

/**
 * Register the query var used by the appp-video rewrite rule.
 *
 * @param array $vars Existing public query vars.
 * @return array
 */
function apppresser_bridge_video_query_vars( $vars ) {
	if ( appp_get_setting( 'youtube_post_types' ) ) { $vars[] = 'appp_video'; }

	return $vars;
}
add_filter( 'query_vars', 'apppresser_bridge_video_query_vars' );

/**
 * Fire a custom action whenever AppPresser settings are saved.
 *
 * @param array $old_settings Previously saved AppPresser settings.
 * @param array $new_settings Newly saved AppPresser settings.
 */
function apppresser_bridge_fire_youtube_support_saved( $old_settings, $new_settings ) {
	$old_types = isset( $old_settings['youtube_post_types'] ) ? (array) $old_settings['youtube_post_types'] : array();
	$youtube_post_types = isset( $new_settings['youtube_post_types'] ) ? (array) $new_settings['youtube_post_types'] : array();
	if ( $old_types === $youtube_post_types ) { return; }

	if ( ! empty( $youtube_post_types ) ) {
		add_rewrite_rule( '^appp-video/?$', 'index.php?appp_video=1', 'top' );
	} else {
		global $wp_rewrite;
		unset( $wp_rewrite->extra_rules_top['^appp-video/?$'] );
	}

	flush_rewrite_rules( false );
}
add_action( 'update_option_appp_settings', 'apppresser_bridge_fire_youtube_support_saved', 10, 2 );
add_action( 'add_option_appp_settings', static function ( $option, $settings ) { apppresser_bridge_fire_youtube_support_saved( array(), $settings ); }, 10, 2 );
