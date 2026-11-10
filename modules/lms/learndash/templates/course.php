<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays a course
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
 * $materials       : Course Materials
 * $has_course_content      : Course has course content
 * $lessons         : Lessons Array
 * $quizzes         : Quizzes Array
 * $lesson_progression_enabled  : (true/false)
 * $has_topics      : (true/false)
 * $lesson_topics   : (array) lessons topics
 *
 * @since 2.1.0
 *
 * @package LearnDash\Course
 */

$course_price_type = $course_settings['course_price_type'];

?>

<?php
/**
 * Display course status
 */

?>
<?php if ( $logged_in ) : ?>
	<span id="learndash_course_status">
		<b>
		<?php
			// translators: Course Status Label.
			printf( esc_html_x( '%s Status:', 'Course Status Label', 'learndash' ), esc_attr( LearnDash_Custom_Label::get_label( 'course' ) ) );
			?>
			</b> 
	</span>

	<div id="applms-course-progress" data-courseid="<?php echo $course_id; ?>">
		<dd class="course_progress">
			<div class="course_progress_blue"></div>
		</dd>
	</div>

	<?php
		/**
		 * Filter to add custom content after the Course Status section of the Course template output.
		 *
		 * @since 2.3
		 * See https://bitbucket.org/snippets/learndash/7oe9K for example use of this filter.
		 */
		echo apply_filters( 'ld_after_course_status_template_container', '', learndash_course_status_idx( $course_status ), $course_id, $user_id );
	?>

	<?php if ( ! empty( $course_certficate_link ) ) : ?>
		<div id="learndash_course_certificate" class="learndash_course_certificate">
			<a href='<?php echo esc_attr( $course_certficate_link ); ?>' class="btn-blue" target="_blank"><?php echo apply_filters( 'ld_certificate_link_label', esc_html__( 'PRINT YOUR CERTIFICATE', 'learndash' ), $user_id, $post->ID ); ?></a>
		</div>
		<br />
	<?php endif; ?>
<?php endif; ?>

<div class="learndash_content"><?php echo $content; ?></div>

<?php if ( ! $has_access && $logged_in ) {
		echo learndash_payment_buttons( $post );
	} elseif( !$logged_in && $course_price_type != 'open' ) {
		echo '<strong>Please <a href="#" class="app-login-modal">log in</a>.</strong>';
	}
?>

<?php if ( ( isset( $materials ) ) && ( ! empty( $materials ) ) ) : ?>
	<div id="learndash_course_materials" class="learndash_course_materials card">
		<div class="item item-text-wrap card-title"><h2>
		<?php
			// translators: Course Materials Label.
			printf( esc_html_x( '%s Materials', 'Course Materials Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'course' ) );
		?>
		</h2></div>
		<div class="item item-body">
			<p><?php echo $materials; ?></p>
		</div>
	</div>
<?php endif; ?>

<?php if ( $has_course_content ) : ?>
	<?php
		$show_course_content = true;
	if ( ! $has_access ) :
		if ( 'on' === $course_meta['sfwd-courses_course_disable_content_table'] ) :
			$show_course_content = false;
			endif;
		endif;

	if ( $show_course_content ) :
		?>
	<div id="learndash_course_content" class="learndash_course_content card">
		<div class="item item-text-wrap card-title"><h2 id="learndash_course_content_title">
			<?php
			// translators: Course Content Label.
			printf( esc_html_x( '%s Content', 'Course Content Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'course' ) );
			?>
		</h2>

	</div>


		<?php
		/**
		 * Display lesson list
		 */
		?>
		<?php if ( ! empty( $lessons ) ) : ?>

			<div id="learndash_lessons" class="learndash_lessons">

				<!-- <div id="lesson_heading">
						<span><?php echo LearnDash_Custom_Label::get_label( 'lessons' ); ?></span>
					<span class="right"><?php esc_html_e( 'Status', 'learndash' ); ?></span>
				</div> -->

				<div id="lessons_list" class="lessons_list">

					<?php foreach ( $lessons as $lesson ) : ?>
						<div class='post-<?php echo esc_attr( $lesson['post']->ID ); ?> <?php echo esc_attr( $lesson['sample'] ); ?>'>

							<!-- <div class="list-count">
								<?php echo $lesson['sno']; ?>
							</div> -->

							<h4>
								<?php if( $has_access ) : ?>
									<a class='<?php echo 'push-page'; ?>' href='<?php echo esc_attr( learndash_get_step_permalink( $lesson['post']->ID, $course_id ) ); ?>'><span><?php echo $lesson['post']->post_title; ?></span></a>
								<?php else : ?>
									<a class='<?php echo 'push-page'; ?>'><span><?php echo $lesson['post']->post_title; ?></span></a>
								<?php endif; ?>

								<?php
								/**
								 * Not available message for drip feeding lessons
								 */
								?>
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
												'context' => 'course',
											), true
										);
									?>
								<?php endif; ?>


							</h4>
						</div>
					<?php endforeach; ?>

				</div>
			</div>
			<?php
				global $course_pager_results;
				if ( isset( $course_pager_results['pager'] ) ) {
					echo SFWD_LMS::get_template( 
						'learndash_pager.php', 
						array(
						'pager_results' => $course_pager_results['pager'], 
						'pager_context' => 'course_lessons'
						) 
					);
				}
			?>
			<?php
				global $course_lessons_results;
				if ( isset( $course_lessons_results['pager'] ) ) {
					echo SFWD_LMS::get_template( 
						'learndash_pager.php', 
						array(
						'pager_results' => $course_lessons_results['pager'], 
						'pager_context' => 'course_lessons'
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
		<?php
		/**
		 * Display quiz list
		 */
		?>
		<?php 
			if ( $show_course_quizzes == true ) {
				if ( ! empty( $quizzes ) ) { ?>
					<div id="learndash_quizzes" class="learndash_quizzes">
						<div id="quiz_heading">
								<span><?php echo LearnDash_Custom_Label::get_label( 'quizzes' ); ?></span><span class="right"><?php esc_html_e( 'Status', 'learndash' ); ?></span>
						</div>
						<div id="quiz_list" class=“quiz_list”>

							<?php foreach ( $quizzes as $quiz ) : ?>
								<div id='post-<?php echo esc_attr( $quiz['post']->ID ); ?>' class='<?php echo esc_attr( $quiz['sample'] ); ?>'>
									<div class="list-count"><?php echo $quiz['sno']; ?></div>
									<h4>
										<a class='<?php echo 'no-transition ' . esc_attr( $quiz['status'] ); ?>' href='<?php echo esc_attr( learndash_get_step_permalink( $quiz['post']->ID, $course_id ) ); ?>'><span><?php echo $quiz['post']->post_title; ?></span></a>
									</h4>
								</div>						
							<?php endforeach; ?>

						</div>
					</div>
				<?php }
			} 
		?>
	</div>
		<?php endif; ?>
<?php endif; ?>