<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays course progress rows for a user
 *
 * Available:
 * $courses_registered: course registered to the user
 * $shortcode_atts: Attributes used in shortcode
 *
 * @since 2.5.5
 *
 * @package LearnDash\Course
 */

if ( $courses_registered ) {
	foreach ( $courses_registered as $course_id ) {
		?>
		<div class='ld-course-info-my-courses'>
		<?php
		if ( ( isset( $shortcode_atts['registered_show_thumbnail'] ) ) && ( 'true' === $shortcode_atts['registered_show_thumbnail'] ) ) {
			echo get_the_post_thumbnail( $course_id );
		}
		?>
			<h2 class="ld-entry-title entry-title"><a href="<?php echo esc_url( get_permalink( $course_id ) ); ?> push-page" rel="bookmark"><?php echo wp_kses_post( get_the_title( $course_id ) ); ?></a></h2>
		</div>
		<?php
	}
}
