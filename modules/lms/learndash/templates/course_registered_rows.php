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

	echo '<ul class="list applms-registered-rows">';
	foreach ( $courses_registered as $course_id ) {
		$show_thumb = ( isset( $shortcode_atts['registered_show_thumbnail'] ) ) && ( $shortcode_atts['registered_show_thumbnail'] == 'true' );
		?>
		<a class="item push-page <?php if( $show_thumb ) { echo 'item-thumbnail-left'; }?>" href="<?php echo get_permalink( $course_id ); ?>">
			<?php 
				if ( $show_thumb ) {
					echo  get_the_post_thumbnail( $course_id ); 
				} ?>
			<h2><?php echo get_the_title( $course_id ); ?></h2>
		</a>
		<?php
	}
	echo '</ul>';
}
