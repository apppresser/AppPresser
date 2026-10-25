<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays the course progress widget.
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Course
 */
?>

<dd class="course_progress" title='<?php printf( esc_html_x( '%1$d out of %2$d steps completed', 'placeholder: completed steps, total steps', 'learndash' ), $completed, $total ); ?>'>
	<div class="course_progress_blue" style='width: <?php echo esc_attr( $percentage ); ?>%;'>
</dd>