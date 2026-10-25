<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * This file contains the code that displays the course navigation admin.
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Course
 */

?>
<?php
global $pagenow;
global $course_navigation_admin_pager;

if ( ( isset( $course_id ) ) && ( !empty( $course_id ) ) ) {

	if ( !isset( $widget ) ) $widget = array(
		'show_widget_wrapper' => true,
		'current_lesson_id' => 0,
		'current_step_id' => 0
	);

	// Not sure why this is here. 
	//if ( !isset( $course_progress ) )
	//	$course_progress = array();
	
	$widget_json = htmlspecialchars( json_encode( $widget ) );
		
	if ( ( isset( $widget['show_widget_wrapper'] ) ) && ( $widget['show_widget_wrapper'] == 'true' ) ) { ?>
		<div id="course_navigation-<?php echo $course_id ?>" class="course_navigation" data-widget_instance="<?php echo $widget_json; ?>">
	<?php } ?>

	<div class="learndash_navigation_lesson_topics_list">

			<?php if ( ( isset( $lessons ) ) && ( ! empty( $lessons ) ) ) { ?>
				<?php foreach( $lessons as $course_lesson_id => $course_lesson ) { ?>
					<?php 			
					
					$lesson_meta = get_post_meta($course_lesson['post']->ID, '_sfwd-lessons', true);
					
					$current_topic_ids = '';
					$lesson_topics_list =  learndash_topic_dots( $course_lesson['post']->ID, false, 'array', null, $course_id );					
					$lesson_quizzes_list = learndash_get_lesson_quiz_list( $course_lesson['post']->ID, $user_id, $course_id ); 

					$is_current_lesson = ( $widget['current_lesson_id'] == $course_lesson['post']->ID );
					$lesson_list_class = ( $is_current_lesson ) ? 'active' : 'inactive';
					$lesson_lesson_completed = 'lesson_incomplete';
					$list_arrow_class = ( $is_current_lesson && ! empty( $lesson_topics_list ) ) ? 'expand' : 'collapse';
					?>

					<?php if ( ! empty( $lesson_topics_list ) ) : ?>
						<?php $list_arrow_class .= ' flippable'; ?>
					<?php endif; ?>
				
					<div class='<?php echo $lesson_list_class ?>' id='lesson_list-<?php echo $course_id ?>-<?php echo $course_lesson['post']->ID; ?>'>
						<div class='list_arrow <?php echo $list_arrow_class; ?> <?php echo $lesson_lesson_completed; ?>' onClick='return flip_expand_collapse("#lesson_list-<?php echo $course_id; ?>", <?php echo $course_lesson['post']->ID; ?>);' ></div>
						<div class="list_lessons">
							<div class="lesson" >
								<?php 
									//if ( ( ( $pagenow == 'profile.php' ) || ( $pagenow == 'user-edit.php' ) ) && ( learndash_is_admin_user( ) ) ) { 
									//if ( ( learndash_is_admin_user( ) ) || ( learndash_is_group_leader_user() ) ) {
									if ( learndash_show_user_course_complete( $user_id ) ) {
									
										$user_lesson_progress 				= 	array();
										$user_lesson_progress['user_id'] 	= 	$user_id;
										$user_lesson_progress['course_id']  = 	$course_id;
										$user_lesson_progress['lesson_id'] 	= 	$course_lesson['post']->ID;
									
										if ($course_lesson['status'] == 'completed') {
											$user_lesson_progress['checked'] = true;
										} else {
											$user_lesson_progress['checked'] = false;
										}
										
										$unchecked_children_message = '';
										if ( ( ! empty( $lesson_topics_list ) ) || ( ! empty( $lesson_quizzes_list ) ) ) { 
											$unchecked_children_message = ' data-title-unchecked-children="'. htmlspecialchars( esc_html__( 'Set all children steps as incomplete?', 'learndash' ), ENT_QUOTES ) .'" ';
										} 
										?><input id="learndash-mark-lesson-complete-<?php echo $course_id ?>-<?php echo $course_lesson['post']->ID ?>" type="checkbox" <?php checked($course_lesson['status'], 'completed') ?> class="learndash-mark-lesson-complete" <?php echo $unchecked_children_message; ?> data-name="<?php echo htmlspecialchars( json_encode( $user_lesson_progress, JSON_FORCE_OBJECT ) ) ?>" /> <?php 
									} 
								?>
								<?php
									$edit_url = get_edit_post_link( $course_lesson['post']->ID );
									if ( LearnDash_Settings_Section::get_section_setting('LearnDash_Settings_Courses_Builder', 'shared_steps' ) == 'yes' ) {
										$edit_url = add_query_arg('course_id', $course_id, $edit_url );
									}
								?>
								<a href='<?php echo $edit_url; ?>'><?php //echo '('. $course_lesson['post']->ID .') '; ?><?php echo $course_lesson['post']->post_title; ?></a> 
							</div> 

							<?php 
								if ( ( ! empty( $lesson_topics_list ) ) || ( ! empty( $lesson_quizzes_list ) ) ) { 
									?>
									<div id='learndash_topic_dots-<?php echo $course_id ?>-<?php echo $course_lesson['post']->ID; ?>' class="flip learndash_topic_widget_list"  style='<?php echo ( strpos( $list_arrow_class, 'collapse' ) !== false ) ? 'display:none' : '' ?>'>
										<ul class="learndash-topic-list">
										<?php

											if ( ! empty( $lesson_topics_list ) ) {
												$odd_class = '';

												foreach ( $lesson_topics_list as $key => $topic ) {
													$odd_class = empty( $odd_class ) ? 'nth-of-type-odd' : '';
													$completed_class = 'topic-notcompleted';
												
													$topic_quiz_list = learndash_get_lesson_quiz_list( $topic->ID, $user_id, $course_id );
													
													$unchecked_children_message = '';
													if ( !empty( $topic_quiz_list ) ) {
														$unchecked_children_message = ' data-title-unchecked-children="'. htmlspecialchars( esc_html__( 'Set all children steps as incomplete?', 'learndash' ), ENT_QUOTES ) .'" ';
													}
													?>
													<li class="topic-item">
														<span class="topic_item">
															<?php 
																if ( learndash_show_user_course_complete( $user_id ) ) {
																	$user_topic_progress 				= 	array();
																	$user_topic_progress['user_id'] 	= 	$user_id;
																	$user_topic_progress['course_id'] 	= 	$course_id;
																	$user_topic_progress['lesson_id'] 	= 	$course_lesson['post']->ID;
																	$user_topic_progress['topic_id'] 	= 	$topic->ID;

 																	if ( ( isset( $course_progress[$course_id]['topics'][$course_lesson['post']->ID][$topic->ID] ) ) 
																	  && ( $course_progress[$course_id]['topics'][$course_lesson['post']->ID][$topic->ID] == true ) ) {
																		$topic_checked = ' checked="checked" ';
																		$user_topic_progress['checked'] = true;
																	} else {
																		$topic_checked = '';
																		$user_topic_progress['checked'] = false;
																	}
														
																	?><input type="checkbox" <?php echo $topic_checked ?> id="learndash-mark-topic-complete-<?php echo $course_id ?>-<?php echo $topic->ID ?>" class="learndash-mark-topic-complete" <?php echo $unchecked_children_message ?> data-name="<?php echo htmlspecialchars( json_encode( $user_topic_progress, JSON_FORCE_OBJECT ) ) ?>" /><?php 
																} 
															?>
															<?php
																$edit_url = get_edit_post_link( $topic->ID );
																if ( LearnDash_Settings_Section::get_section_setting('LearnDash_Settings_Courses_Builder', 'shared_steps' ) == 'yes' ) {
																	$edit_url = add_query_arg('course_id', $course_id, $edit_url );
																}
															?>
															
															<a class='<?php echo $completed_class; ?>' href='<?php echo $edit_url; ?>' title='<?php echo $topic->post_title; ?>'><span><?php //echo '('. $topic->ID .') '; ?><?php echo $topic->post_title; ?></span></a>														
														</span>
											
														<?php 
															$topic_quiz_list = learndash_get_lesson_quiz_list( $topic->ID, $user_id, $course_id ); 
															if ( !empty( $topic_quiz_list ) ) {
																?>
																<ul id="learndash-quiz-list-<?php echo $course_id ?>-<?php echo $topic->ID ?>" class="learndash-quiz-list">
																	<?php foreach ( $topic_quiz_list as $quiz ) { ?>
																		<li class="quiz-item">
																			<?php 
																				//if ( ( ( $pagenow == 'profile.php' ) || ( $pagenow == 'user-edit.php' ) ) && ( learndash_is_admin_user( ) ) ) { 
																				//if ( ( learndash_is_admin_user( ) ) || ( learndash_is_group_leader_user() ) ) {
																				if ( learndash_show_user_course_complete( $user_id ) ) {
																												
																					$user_quiz_progress 				= 	array();
																					$user_quiz_progress['user_id'] 		= 	$user_id;
																					$user_quiz_progress['course_id'] 	= 	$course_id;
																					$user_quiz_progress['lesson_id'] 	= 	$course_lesson['post']->ID;
																					$user_quiz_progress['topic_id'] 	= 	$topic->ID;
																					$user_quiz_progress['quiz_id'] 		= 	$quiz['post']->ID;
														
																					if ( $quiz['status'] == 'completed') {
																						$quiz_checked 					= 	' checked="checked" ';
																						$user_quiz_progress['checked'] 	= 	true;
																					} else {
																						$quiz_checked 					= 	'';
																						$user_quiz_progress['checked'] 	= 	false;
																					}
																					$unchecked_message = ' data-title-unchecked="'. htmlspecialchars( esc_html__( 'Set all parent steps as incomplete?', 'learndash' ), ENT_QUOTES ) .'" ';
														
																					?><input type="checkbox" <?php echo $quiz_checked ?>class="learndash-mark-topic-quiz-complete learndash-mark-quiz-complete" <?php echo $unchecked_message; ?> data-name="<?php echo htmlspecialchars( json_encode( $user_quiz_progress, JSON_FORCE_OBJECT ) ) ?>" /><?php 
																				} 
																			?>
																			<?php
																				$edit_url = get_edit_post_link( $quiz['post']->ID );
																				if ( LearnDash_Settings_Section::get_section_setting('LearnDash_Settings_Courses_Builder', 'shared_steps' ) == 'yes' ) {
																					$edit_url = add_query_arg('course_id', $course_id, $edit_url );
																				}
																			?>
																
																			<a href='<?php echo $edit_url; ?>' title='<?php echo $quiz['post']->post_title; ?>'><span><?php //echo '('. $quiz['post']->ID .') '?><?php echo $quiz['post']->post_title; ?></span></a>
																	
																		</li>
																	<?php } ?>
																</ul>
																<?php
															}
														?>
													</li>
													<?php

												}
											}
										
											if ( !empty( $lesson_quizzes_list ) ) {
												foreach ( $lesson_quizzes_list as $quiz ) {
													?><li class="quiz-item"><?php
														//if ( ( ( $pagenow == 'profile.php' ) || ( $pagenow == 'user-edit.php' ) ) && ( learndash_is_admin_user( ) ) ) { 
														//if ( ( learndash_is_admin_user( ) ) || ( learndash_is_group_leader_user() ) ) {
														if ( learndash_show_user_course_complete( $user_id ) ) {
																					
															$user_quiz_progress 				= 	array();
															$user_quiz_progress['user_id'] 		= 	$user_id;
															$user_quiz_progress['course_id'] 	= 	$course_id;
															$user_quiz_progress['lesson_id'] 	= 	$course_lesson['post']->ID;
															$user_quiz_progress['quiz_id'] 		= 	$quiz['post']->ID;
							
															if ( $quiz['status'] == 'completed') {
																$quiz_checked 					= 	' checked="checked" ';
																$user_quiz_progress['checked'] 	= 	true;
															} else {
																$quiz_checked 					= 	'';
																$user_quiz_progress['checked'] 	= 	false;
															}
							
															?><input type="checkbox" <?php echo $quiz_checked ?>class="learndash-mark-lesson-quiz-complete learndash-mark-quiz-complete" data-name="<?php echo htmlspecialchars( json_encode( $user_quiz_progress, JSON_FORCE_OBJECT ) ) ?>" /><?php 
														} 
														?>
														<?php
															$edit_url = get_edit_post_link( $quiz['post']->ID );
															if ( LearnDash_Settings_Section::get_section_setting('LearnDash_Settings_Courses_Builder', 'shared_steps' ) == 'yes' ) {
																$edit_url = add_query_arg('course_id', $course_id, $edit_url );
															}
														?>
														<a href='<?php echo $edit_url; ?>' title='<?php echo $quiz['post']->post_title; ?>'><span><?php //echo '('. $quiz['post']->ID .') '; ?><?php echo $quiz['post']->post_title; ?></span></a>
													</li>
													<?php 
												} 
											}
										?>
										</ul>
									</div>
									<?php
								}
							?>
						</div>
					</div> 
				<?php } ?>

			<?php } ?>
			<?php
				if ( isset( $course_navigation_admin_pager ) ) {
					if ( $course_navigation_admin_pager['paged'] == $course_navigation_admin_pager['total_pages'] ) {
						$show_course_quizzes = true;
					} else {
						$show_course_quizzes = false;
					}
				} else {
					$show_course_quizzes = true;
				}
				if ( $show_course_quizzes == true ) {
				
					if ( ! empty( $course_quiz_list ) ) {
						foreach ( $course_quiz_list as $quiz ) {
							?>
							<div id='quiz_list-<?php echo $quiz['post']->ID; ?>'>
								<div class='list_arrow'></div>
								<div class="list_lessons">
									<div class="lesson" >
										<?php 
											//if ( ( ( $pagenow == 'profile.php' ) || ( $pagenow == 'user-edit.php' ) ) && ( learndash_is_admin_user( ) ) ) { 
											//if ( ( learndash_is_admin_user( ) ) || ( learndash_is_group_leader_user() ) ) {
											if ( learndash_show_user_course_complete( $user_id ) ) {
																
												$user_quiz_progress 				= 	array();
												$user_quiz_progress['user_id'] 		= 	$user_id;
												$user_quiz_progress['course_id'] 	= 	$course_id;
												$user_quiz_progress['quiz_id'] 		= 	$quiz['post']->ID;
		
												if ( $quiz['status'] == 'completed') {
													$quiz_checked 					= 	' checked="checked" ';
													$user_quiz_progress['checked'] 	= 	true;
												} else {
													$quiz_checked 					= 	'';
													$user_quiz_progress['checked'] 	= 	false;
												}
												?><input type="checkbox" <?php echo $quiz_checked ?> class="learndash-mark-quiz-complete learndash-mark-course-quiz-complete" data-name="<?php echo htmlspecialchars( json_encode( $user_quiz_progress, JSON_FORCE_OBJECT ) ) ?>" /><?php 
											} 
										?>
										<a href='<?php echo add_query_arg('course_id', $course_id, get_edit_post_link( $quiz['post']->ID )); ?>' title='<?php echo $quiz['post']->post_title; ?>'><?php //echo '('. $quiz['post']->ID .') '; ?><?php echo $quiz['post']->post_title; ?></a>
					
									</div>
								</div>
							</div>
							<?php
						}
					}
				}
			?>
		</div> <!-- Closing <div class='learndash_navigation_lesson_topics_list'> -->

		<?php
			if ( ( isset( $course_navigation_admin_pager ) ) && ( !empty( $course_navigation_admin_pager ) ) ) {
				echo SFWD_LMS::get_template( 
					'learndash_pager.php', 
					array(
						'pager_results' => $course_navigation_admin_pager, 
						'pager_context' => 'course_navigation_admin'
					) 
				);
			}
		?>
		<?php if ( ( $widget['current_step_id'] != 0 ) && ( $widget['current_step_id'] != $course->ID ) ) { ?> 
			<p class="widget_course_return">
				<?php esc_html_e( 'Return to', 'learndash' ); ?> <a href='<?php echo get_edit_post_link( $course_id ); ?>'>
					<?php echo $course->post_title; ?>
				</a>
			</p>

		<?php } ?>
		
	<?php if ( ( isset( $widget['show_widget_wrapper'] ) ) && ( $widget['show_widget_wrapper'] == 'true' ) ) { ?>
		</div> <!-- Closing <div id='course_navigation'> -->
	<?php } ?>
	<?php
}

