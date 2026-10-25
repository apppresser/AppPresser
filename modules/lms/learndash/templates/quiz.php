<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays a quiz.
 *
 * Available Variables:
 * 
 * $course_id       : (int) ID of the course
 * $course      : (object) Post object of the course
 * $course_settings : (array) Settings specific to current course
 * $course_status   : Course Status
 * $has_access  : User has access to course or is enrolled.
 * 
 * $courses_options : Options/Settings as configured on Course Options page
 * $lessons_options : Options/Settings as configured on Lessons Options page
 * $quizzes_options : Options/Settings as configured on Quiz Options page
 * 
 * $user_id         : (object) Current User ID
 * $logged_in       : (true/false) User is logged in
 * $current_user    : (object) Currently logged in user object
 * $post            : (object) The quiz post object
 * $lesson_progression_enabled  : (true/false)
 * $show_content    : (true/false) true if user is logged in and lesson progression is disabled or if previous lesson and topic is completed.
 * $attempts_left   : (true/false)
 * $attempts_count : (integer) No of attempts already made
 * $quiz_settings   : (array)
 * 
 * Note:
 * 
 * To get lesson/topic post object under which the quiz is added:
 * $lesson_post = !empty($quiz_settings["lesson"])? get_post($quiz_settings["lesson"]):null;
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Quiz
 */

global $post;

if( ! empty( $lesson_progression_enabled ) && ! is_quiz_accessable( null, $post ) ) {
    if ( empty( $quiz_settings['lesson'] ) ) {
        echo sprintf( wp_kses_post( _x( 'Please go back and complete the previous %s.<br/>', 'placeholder lesson', 'learndash' ) ), LearnDash_Custom_Label::label_to_lower('lesson') );
    } else {
        echo sprintf( wp_kses_post( _x( 'Please go back and complete the previous %s.<br/>', 'placeholder topic', 'learndash' ) ), LearnDash_Custom_Label::label_to_lower('topic') );
    }
}

if ( $show_content ) {
	if ( ( isset( $materials ) ) && ( !empty( $materials ) ) ) : 
		?>
		<div id="learndash_topic_materials" class="learndash_topic_materials">
			<h4><?php printf( _x( '%s Materials', 'Quiz Materials Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'quiz' ) ); ?></h4>
			<p><?php echo $materials; ?></p>
		</div>
		<?php 
	endif;
	
    echo $content;
    if ( $attempts_left ) {
        echo $quiz_content;
    } else {
		?>
			<p id="learndash_already_taken"><?php echo sprintf( esc_html_x( 'You have already taken this %1$s %2$d time(s) and may not take it again.', 'placeholders: quiz, attempts count', 'learndash' ), LearnDash_Custom_Label::label_to_lower('quiz'), $attempts_count ); ?></p>
		<?php
    }
}