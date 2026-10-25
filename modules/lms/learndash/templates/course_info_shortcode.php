<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays course information for a user
 *
 * Available:
 * $user_id
 * $courses_registered: course
 * $course_progress: Progress in courses
 * $quizzes
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Course
 */

/**
 * Course registered
 */

global $pagenow;

$shortcode_atts_json = htmlspecialchars( json_encode( $shortcode_atts ) );
?>
<div id="ld_course_info" class="ld_course_info" data-shortcode-atts="<?php echo $shortcode_atts_json; ?>">

	<!-- Course info shortcode -->
	<?php if ( ( $pagenow != 'profile.php' ) && ( $pagenow != 'user-edit.php' ) ) { ?>
		<?php if ( $courses_registered ) : ?>
			<div class="card" id='ld_course_info_mycourses_list'>
				<div class="item card-title"><h2><?php echo sprintf( _x( 'You are registered for the following %s', 'You are registered for the following courses', 'learndash' ), LearnDash_Custom_Label::label_to_lower( 'courses' ) ); ?></h2></div>
				<div class="ld-courseregistered-content-container"><?php
				include( 
					SFWD_LMS::get_template( 
						'course_registered_rows', 
						array( 
							'courses_registered' => $courses_registered,
							'shortcode_atts' => $shortcode_atts
						), 
						null, 
						true 
					)
				);
				?></div>
				<div class="ld-course-registered-pager-container"><?php
					echo SFWD_LMS::get_template( 
						'learndash_pager.php', 
						array(
						'pager_results' => $courses_registered_pager, 
						'pager_context' => 'course_info_courses'
						) 
					);
					?>
				</div>
			</div>
			<div style="clear:both"></div>
			
		<?php endif; ?>
	<?php } ?>

	<?php
		if (is_admin()) {
			$current_screen = get_current_screen();

			if ( ( $pagenow == 'profile.php' ) || ( $pagenow == 'user-edit.php' ) || ($current_screen->id == 'learndash-lms_page_group_admin_page' ) ) { 
				echo do_shortcode('[ld_user_course_points user_id="'. $user_id .'" context="profile"]'); 

				if ( ( learndash_is_admin_user() ) || ( ( learndash_is_group_leader_user() ) && ( learndash_is_group_leader_of_user( get_current_user_id(), $user_id ) ) ) ) {
					?><p><label for="learndash-course-points-user"><strong><?php printf( _x( 'Extra %s points', 'placeholders: Course Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'course' ) ); ?></strong></label> <input id="learndash-course-points-user" name="learndash_course_points" type="number" min="0" step="any" value="<?php echo learndash_format_course_points( get_user_meta( $user_id, 'course_points', true ) ) ?>" /><?php } ?></p>
					<?php 
				} 
			}
		?>
	<?php /* Course progress */ ?>
	<?php if ( !empty( $course_progress ) ) : ?>
		<div id="course_progress_details" class="card course_progress_details">
			<div class="item card-title"><h2><?php printf( _x( '%s progress details:', 'Course progress details Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'course' ) ); ?></h2></div>
			<?php 
				if ( learndash_show_user_course_complete( $user_id ) ) {
					?>
					<input type="hidden" id="user-progress-<?php echo $user_id ?>" name="user_progress[<?php echo $user_id ?>]" value="<?php echo htmlspecialchars( json_encode( array('course' => array(), 'quiz' => array()), JSON_FORCE_OBJECT ) ); ?>" />
					<input type="hidden" name="user_progress-<?php echo $user_id ?>-nonce" value="<?php echo wp_create_nonce( 'user_progress-'. $user_id ) ?>" />
					<?php 
				}
				?><div class="ld-course-progress-content-container"><?php
				include( 
					SFWD_LMS::get_template( 
						'course_progress_rows', 
						array( 
							'user_id' => $user_id,
							'course_progress' => $course_progress,
							'shortcode_atts' => $shortcode_atts
						), 
						null, 
						true 
					)
				);
				?></div><?php
				?><div class="ld-course-progress-pager-container"><?php
				echo SFWD_LMS::get_template( 
					'learndash_pager.php', 
					array(
					'pager_results' => $course_progress_pager, 
					'pager_context' => 'course_info_courses'
					) 
				);
				?></div><div style="clear:both"></div><?php
			?>
		</div>
		<br>
	<?php endif; ?>

	<?php /* Quizzes */ ?>
	<?php if ( $quizzes ) : ?>
		<div id="quiz_progress_details" class="card">
			<?php
				global $learndash_assets_loaded;
			
				if ( !isset( $learndash_assets_loaded['scripts']['learndash_template_script_js'] ) ) {

					$filepath = SFWD_LMS::get_template( 'learndash_template_script.js', null, null, true );
					if ( !empty( $filepath ) ) {
						wp_enqueue_script( 'learndash_template_script_js', learndash_template_url_from_path( $filepath ), array( 'jquery' ), LEARNDASH_SCRIPT_VERSION_TOKEN, true );
						$learndash_assets_loaded['scripts']['learndash_template_script_js'] = __FUNCTION__;

						$data = array();
						$data['ajaxurl'] = admin_url('admin-ajax.php');
						$data = array( 'json' => json_encode( $data ) );
						wp_localize_script( 'learndash_template_script_js', 'sfwd_data', $data );
					}
				}
				LD_QuizPro::showModalWindow();
			?>
			<div class="item card-title"><h2><?php echo sprintf( _x( 'You have taken the following %s:', 'You have taken the following quizzes:', 'learndash' ), LearnDash_Custom_Label::label_to_lower( 'quizzes' ) ); ?></h2></div>

			<?php /* The confirm delete quiz message should not contain HTML. Use \r\n for line breaks */ ?>
			<div id="ld-confirm-quiz-delete-message" style="display:none"><?php echo sprintf( _x( 'Are you sure that you want to remove this %s item?', 'placeholder: Quiz', 'learndash' ), LearnDash_Custom_Label::label_to_lower( 'quiz' ) )  ?></div>
			
			<div class="ld-quiz-progress-content-container">
			<?php
				include( 
					SFWD_LMS::get_template( 
						'quiz_progress_rows', 
						array( 
							'user_id' => $user_id,
							'quizzes' => $quizzes,
							'shortcode_atts' => $shortcode_atts
						), 
						null, 
						true 
					)
				);
			?></div><?php
			?><div class="ld-quiz-progress-pager-container"><?php
			echo SFWD_LMS::get_template( 
				'learndash_pager.php', 
				array(
				'pager_results' => $quizzes_pager, 
				'pager_context' => 'course_info_quizzes'
				) 
			);
			?></div><div style="clear:both"></div><?php
			?>
		</div>
	<?php endif; ?>
	<!-- End Course info shortcode -->
</div>