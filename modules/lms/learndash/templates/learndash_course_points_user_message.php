<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays the User Course Points message
 *
 * Available Variables:
 * user_course_points : (integer) the user's current total course points. 
 * user_id : (integer) The user_id whose points to show
 * shortcode_atts: (array) Available ONLY when using the [ld_user_course_points] shortcode
 * 
 * This template is called from the [ld_user_course_points] and the shortcode atts will contain a 
 * 'context' element. The value of this element can have any value defined by the user. The known
 * values used by LearnDash are:
 * ld_user_course_points : default value set from that ld_user_course_points shortcode
 * profile : Set when used within the user's WP Profile output
 * ld_profile : Set when used from the LearnDash [ld_profile] shortcode
 * @since 2.4
 * 
 * @package LearnDash\Course
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// First generate the message
$message = '<strong>'. sprintf( esc_html_x( 'Earned %s Points:', 'placeholders: Course Label, course points', 'learndash' ), LearnDash_Custom_Label::get_label( 'course' ) ) . '</strong> '. $user_course_points;

// The figure out how to display it
if ( $shortcode_atts['context'] == 'profile' ) {
	?><p class="learndash-course-points"><?php echo $message; ?></p><?php
} else if ( $shortcode_atts['context'] == 'ld_profile' ) {
	?><div id="learndash_course_points_user_message"><?php echo $message; ?></div><?php
} else {
	echo $message;
}
