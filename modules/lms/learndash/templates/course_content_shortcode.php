<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays content of course
 *
 * Available Variables:
 * $course_id       : (int) ID of the course
 * $course      : (object) Post object of the course
 * $course_settings : (array) Settings specific to current course
 *
 * $courses_options : Options/Settings as configured on Course Options page
 * $lessons_options : Options/Settings as configured on Lessons Options page
 * $quizzes_options : Options/Settings as configured on Quiz Options page
 *
 * $user_id         : Current User ID
 * $logged_in       : User is logged in
 * $current_user    : (object) Currently logged in user object
 *
 * $course_status   : Course Status
 * $has_access  : User has access to course or is enrolled.
 * $has_course_content      : Course has course content
 * $lessons         : Lessons Array
 * $quizzes         : Quizzes Array
 * $lesson_progression_enabled  : (true/false)
 *
 * @since 2.1.0
 *
 * @package LearnDash\Course
 */

?>

<?php
/**
 * Show Course Status
 */
?>
<?php if ( $has_course_content ) : ?>
	<div id='learndash_course_content'>

		<h4 id='learndash_course_content_title'>
			<?php
				// translators: Course Content Label.
				printf( esc_html_x( '%s Content', 'Course Content Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'course' ) );
			?>
		</h4>

		<?php /* Show Lesson List */ ?>
		<?php if ( ! empty( $lessons ) ) : ?>

			<?php if ( $has_topics ) : ?>
				<div class='expand_collapse'>
					<a href='#' onClick='jQuery("#learndash_post_<?php echo $course_id; ?> .learndash_topic_dots").slideDown(); return false;'><?php esc_html_e( 'Expand All', 'learndash' ); ?></a> | <a href='#' onClick='jQuery("#learndash_post_<?php echo $course_id; ?> .learndash_topic_dots").slideUp(); return false;'><?php esc_html_e( 'Collapse All', 'learndash' ); ?></a>
				</div>
			<?php endif; ?>

			<div id='learndash_lessons'>

				<div id='lesson_heading'>
					<span><?php echo LearnDash_Custom_Label::get_label( 'lessons' ); ?></span>
					<span class='right'><?php esc_html_e( 'Status', 'learndash' ); ?></span>
				</div>
				<div id='lessons_list'>

					<?php foreach ( $lessons as $lesson ) : ?>
						<div id='post-<?php echo $lesson['post']->ID; ?>' class='<?php echo $lesson['sample']; ?>'>
							<div class='list-count'><?php echo $lesson['sno']; ?></div>
							<h4>
								<a class='<?php echo $lesson['status']; ?>' href='<?php echo learndash_get_step_permalink( $lesson['post']->ID, $course_id ); ?>'><?php echo $lesson['post']->post_title; ?></a>
								<?php /* Not available message for drip feeding lessons */ ?>
								<?php if ( ! empty( $lesson['lesson_access_from'] ) ) : ?>
									<?php
										SFWD_LMS::get_template(
											'learndash_course_lesson_not_available',
											array(
												'user_id' => $user_id,
												'course_id' => learndash_get_course_id( $lesson['post']->ID ),
												'lesson_id' => $lesson['post']->ID,
												'lesson_access_from_int' => $lesson['lesson_access_from'],
												'lesson_access_from_date' => learndash_adjust_date_time_display( $lesson['lesson_access_from'] ),
												'context' => 'course_content_shortcode',
											), true
										);
									?>
								<?php endif; ?>
								<?php /* Lesson Topis */ ?>
								<?php $topics = @$lesson_topics[ $lesson['post']->ID ]; ?> 

								<?php if ( ! empty( $topics ) ) : ?>
									<div id='learndash_topic_dots-<?php echo $lesson['post']->ID; ?>' class='learndash_topic_dots type-list'>
										<ul>
											<?php $odd_class = ''; ?>
											<?php foreach ( $topics as $key => $topic ) : ?>
												<?php $odd_class       = empty( $odd_class ) ? 'nth-of-type-odd' : ''; ?>
												<?php $completed_class = empty( $topic->completed ) ? 'topic-notcompleted' : 'topic-completed'; ?>
												<li class='<?php echo $odd_class; ?>'>
													<span class='topic_item'>
														<a class='<?php echo $completed_class; ?>' href='<?php echo learndash_get_step_permalink( $topic->ID, $course_id ); ?>' title='<?php echo $topic->post_title; ?>'>
															<span><?php echo $topic->post_title; ?></span>
														</a>
													</span>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

							</h4>
						</div>
					<?php endforeach; ?>

				</div>
			</div>
			<?php
				global $course_lessons_results;
				if ( isset( $course_lessons_results['pager'] ) ) {
					echo SFWD_LMS::get_template( 
						'learndash_pager.php', 
						array(
						'pager_results' => $course_lessons_results['pager'], 
						'pager_context' => 'course_content'
						) 
					);
				}
			?>
		<?php endif; ?>
		<?php
			if ( ( isset( $course_lessons_results['pager'] ) ) && ( !empty( $course_lessons_results['pager'] ) ) ) {
				if ( $course_lessons_results['pager']['paged'] == $course_lessons_results['pager']['total_pages'] ) {
					$show_course_quizzes = true;
				} else {
					$show_course_quizzes = false;
				}
			} else {
				$show_course_quizzes = true;
			}
		?>


		<?php /* Show Quiz List */ ?>	
		<?php 
			if ( $show_course_quizzes == true ) {
			if ( ! empty( $quizzes ) ) : ?>
				<div id='learndash_quizzes'>
					<div id='quiz_heading'>
						<span><?php echo LearnDash_Custom_Label::get_label( 'quizzes' ); ?></span><span class='right'><?php esc_html_e( 'Status', 'learndash' ); ?></span>
					</div>
					<div id='quiz_list'>
						<?php foreach ( $quizzes as $quiz ) : ?>
							<div id='post-<?php echo $quiz['post']->ID; ?>' class='<?php echo $quiz['sample']; ?>'>
								<div class='list-count'><?php echo $quiz['sno']; ?></div>
								<h4><a class='<?php echo $quiz['status']; ?>' href='<?php echo learndash_get_step_permalink( $quiz['post']->ID, $course_id ); ?>'><?php echo $quiz['post']->post_title; ?></a></h4>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; 
			}
		?>	

	</div>
<?php endif; ?>
