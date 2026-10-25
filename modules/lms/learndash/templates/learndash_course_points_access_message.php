<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays the Course Points Access message
 *
 * Available Variables:
 * current_post : (WP_Post Object) Current Post object being display. Equal to global $post in most cases.
 * content_type : (string) Will contain the singlar lowercase common label 'course', 'lesson', 'topic', 'quiz'
 * course_access_points : (integer) Points required to access this course. 
 * user_course_points : (integer) the user's current total course points. 
 * course_settings : (array) Settings specific to current course
 * 
 * @since 2.4
 * 
 * @package LearnDash\Course
 */
?>
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div id="learndash_course_points_access_message"><?php 
	echo sprintf( 
	 esc_html_x(
			'To take this %s you need at least %.01f total points. You currently have %.01f points.', 
			'placeholders: (1) will be Course. (2) course_access_points. (3) user_course_points ', 
			'learndash' 
		), 
		$content_type,
		$course_access_points,
		$user_course_points
	);
	
	echo '<br>';
		
?></div>
