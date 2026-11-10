<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays the course navigation widget.
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Course
 */
?>
<?php
/**
 * Filter to allow override of widget instance arguments. 
 * @since 2.3.3
 */
if ( !isset( $widget_instance ) ) $widget_instance = array();
$widget_instance = apply_filters( 'learndash_course_navigation_widget_args', $widget_instance, $course_id );
$widget_data = array(
	'course_id' => $course_id,
	'widget_instance' => $widget_instance
);

$widget_data_json = htmlspecialchars( json_encode( $widget_data ) );

if ( $widget_instance['show_widget_wrapper'] != false ) {
	?>
	<div id="course_navigation" class="course_navigation" data-widget_instance="<?php echo $widget_data_json; ?>">
		<div class="ld-course-navigation-widget-content-contaiiner">
		<?php		
}

include( 
	SFWD_LMS::get_template( 
		'course_navigation_widget_rows', 
		null,
		null, 
		true 
	)
);

if ( ( !empty( $widget_instance['current_step_id'] ) ) && ( $widget_instance['current_step_id'] != $course->ID ) ) { ?> 
	<div class="widget_course_return">
		<?php esc_html_e( 'Return to', 'learndash' ); ?>
        <a href='<?php echo esc_attr( get_permalink( $course_id ) ); ?>'><?php echo $course->post_title; ?></a>
	</div>
	<?php 
}

if ( $widget_instance['show_widget_wrapper'] != false ) {
	?>
    </div>
</div> <!-- Closing <div id='course_navigation'> -->
<?php if ( apply_filters('learndash_course_steps_expand_all', false, $course_id, 'course_navigation_widget' ) ) { ?>
	<script>
		jQuery(document).ready(function() {
			jQuery(".course_navigation .list_arrow").trigger('click');
		});
	</script>	
	<?php 
	} 
}


