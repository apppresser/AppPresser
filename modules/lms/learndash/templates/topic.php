<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays a topic.
 *
 * Available Variables:
 * 
 * $course_id 		: (int) ID of the course
 * $course 		: (object) Post object of the course
 * $course_settings : (array) Settings specific to current course
 * $course_status 	: Course Status
 * $has_access 	: User has access to course or is enrolled.
 * 
 * $courses_options : Options/Settings as configured on Course Options page
 * $lessons_options : Options/Settings as configured on Lessons Options page
 * $quizzes_options : Options/Settings as configured on Quiz Options page
 * 
 * $user_id 		: (object) Current User ID
 * $logged_in 		: (true/false) User is logged in
 * $current_user 	: (object) Currently logged in user object
 * $quizzes 		: (array) Quizzes Array
 * $post 			: (object) The topic post object
 * $lesson_post 	: (object) Lesson post object in which the topic exists
 * $topics 		: (array) Array of Topics in the current lesson
 * $all_quizzes_completed : (true/false) User has completed all quizzes on the lesson Or, there are no quizzes.
 * $lesson_progression_enabled 	: (true/false)
 * $show_content	: (true/false) true if lesson progression is disabled or if previous lesson and topic is completed. 
 * $previous_lesson_completed 	: (true/false) true if previous lesson is completed
 * $previous_topic_completed	: (true/false) true if previous topic is completed
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Topic
 */

/**
 * Topic Dots
 */
?>
<?php if ( ! empty( $topics ) && $logged_in ) : ?>
	<div id='learndash_topic_dots-<?php echo esc_attr( $lesson_id ); ?>' class="learndash_topic_dots type-dots">

		<b><?php printf( esc_html_x( '%s Progress:', 'Topic Progress Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'topic' ) ); ?></b>

		<?php foreach ( $topics as $key => $topic ) : ?>
			<?php $completed_class = empty( $topic->completed ) ? 'topic-notcompleted' : 'topic-completed'; ?>
			<a class='<?php echo esc_attr( $completed_class ); ?>' href='<?php echo learndash_get_step_permalink( $topic->ID, $course_id ); ?>' title='<?php echo esc_attr( $topic->post_title ); ?>'>
				<span title='<?php echo esc_attr( $topic->post_title ); ?>'></span>
			</a>
		<?php endforeach; ?>

	</div>
<?php endif; ?>

<?php /* Removing this since app already has back button
if ( !empty( $course_id ) ) { ?>
<div id="learndash_back_to_lesson"><a href='<?php echo learndash_get_step_permalink( $lesson_id, $course_id ); ?>'>&larr; <?php printf( esc_html_x( 'Back to %s', 'Back to Lesson Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'lesson' ) ); ?></a></div>
<?php } */

?>

<?php if ( $lesson_progression_enabled && ! $previous_topic_completed ) : ?>

	<span id="learndash_complete_prev_topic">
	<?php
		$previous_item = learndash_get_previous( $post );
		if (empty($previous_item)) {
			$previous_item = learndash_get_previous( $lesson_post );
		}
		
		if ( ( !empty( $previous_item ) ) && ( $previous_item instanceof WP_Post ) ) {
			if ( $previous_item->post_type == 'sfwd-quiz') {
				echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholders: quiz URL', 'learndash' ), 
				'<a class="learndash-link-previous-incomplete" href="'. learndash_get_step_permalink( $previous_item->ID, $course_id ) .'">'. LearnDash_Custom_Label::label_to_lower('quiz') .'</a>' );
				
			} else if ( $previous_item->post_type == 'sfwd-topic') {
				echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholders: topic URL', 'learndash' ), 
				'<a class="learndash-link-previous-incomplete" href="'. learndash_get_step_permalink( $previous_item->ID, $course_id ) .'">'. LearnDash_Custom_Label::label_to_lower('topic') .'</a>' );
			} else {
				echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholders: lesson URL', 'learndash' ), 
				'<a class="learndash-link-previous-incomplete" href="'. learndash_get_step_permalink( $previous_item->ID, $course_id ) .'">'. LearnDash_Custom_Label::label_to_lower('lesson').'</a>' );
			}
			
		} else {
			echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholder lesson', 'learndash' ), LearnDash_Custom_Label::label_to_lower('lesson') );
		}
	?></span>
    <br />

<?php elseif ( $lesson_progression_enabled && ! $previous_lesson_completed ) : ?>

	<span id="learndash_complete_prev_lesson">
	<?php
		$previous_item = learndash_get_previous( $post );
		if (empty($previous_item)) {
			$previous_item = learndash_get_previous( $lesson_post );
		}
		
		if ( ( !empty( $previous_item ) ) && ( $previous_item instanceof WP_Post ) ) {
			if ( $previous_item->post_type == 'sfwd-quiz') {
				echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholders: quiz URL', 'learndash' ), 
				'<a class="learndash-link-previous-incomplete" href="'. learndash_get_step_permalink( $previous_item->ID, $course_id ) .'">'. LearnDash_Custom_Label::label_to_lower('quiz') .'</a>' );
				
			} else if ( $previous_item->post_type == 'sfwd-topic') {
				echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholders: topic URL', 'learndash' ), 
				'<a class="learndash-link-previous-incomplete" href="'. learndash_get_step_permalink( $previous_item->ID, $course_id ) .'">'. LearnDash_Custom_Label::label_to_lower('topic') .'</a>' );
			} else {
				echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholders: lesson URL', 'learndash' ), 
				'<a class="learndash-link-previous-incomplete" href="'. learndash_get_step_permalink( $previous_item->ID, $course_id ) .'">'. LearnDash_Custom_Label::label_to_lower('lesson') .'</a>' );
			}
			
		} else {
			echo sprintf( esc_html_x( 'Please go back and complete the previous %s.', 'placeholder lesson', 'learndash' ), LearnDash_Custom_Label::label_to_lower('lesson') );
		}
	?></span>
    <br />

<?php endif; ?>

<?php if ( $show_content ) : ?>
	<?php if ( ( isset( $materials ) ) && ( !empty( $materials ) ) ) : ?>
		<div id="learndash_topic_materials" class="learndash_topic_materials">
			<h4><?php printf( esc_html_x( '%s Materials', 'Topic Materials Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'topic' ) ); ?></h4>
			<p><?php echo $materials; ?></p>
		</div>
	<?php endif; ?>
	
	<div class="learndash_content"><?php echo $content; ?></div>
	
	<?php if ( ! empty( $quizzes ) ) : ?>
		<div id="learndash_quizzes" class="learndash_quizzes">
			<div id="quiz_heading"><span><?php echo LearnDash_Custom_Label::get_label( 'quizzes' ); ?></span><span class="right"><?php _e( 'Status', 'learndash' ) ?></span></div>

			<div id="quiz_list" class="quiz_list">
			<?php foreach( $quizzes as $quiz ) : ?>
				<div id='post-<?php echo esc_attr( $quiz['post']->ID ); ?>' class='<?php echo esc_attr( $quiz['sample'] ); ?>'>
					<div class="list-count"><?php echo $quiz['sno']; ?></div>
					<h4>
						<a class='<?php echo esc_attr( $quiz['status'] ); ?>' href='<?php echo esc_attr( $quiz['permalink'] ); ?>'><?php echo $quiz['post']->post_title; ?></a>
					</h4>
				</div>
			<?php endforeach; ?>
			</div>
		</div>	
	<?php endif; ?>

	<?php if ( ( lesson_hasassignments( $post ) ) && ( !empty( $user_id ) ) ) : ?>
		<?php
			$ret = SFWD_LMS::get_template( 
					'learndash_lesson_assignment_uploads_list.php', 
					array(
						'course_step_post' => $post,
						'user_id' => $user_id
					)
				);
			echo $ret;	
		?>
	<?php endif; ?>
<div class="app-flex-items">

	<?php
    /**
     * Show Mark Complete Button
     */
    ?>
	<?php if ( $all_quizzes_completed && $logged_in && !empty( $course_id ) ) : ?>
		<?php echo '<br />' . learndash_mark_complete( $post ); ?>
	<?php endif; ?>

<?php endif; ?>

<?php echo learndash_previous_post_link(); ?>
	<?php
	/*
	 * See details for filter 'learndash_show_next_link' https://bitbucket.org/snippets/learndash/5oAEX
	 *
	 * @since version 2.3
	 */
	?>
	<?php if ( apply_filters( 'learndash_show_next_link', learndash_is_topic_complete( $user_id, $post->ID ),  $user_id, $post->ID ) ) { ?>
		<?php echo learndash_next_post_link(); ?>
	<?php } ?>
</div>