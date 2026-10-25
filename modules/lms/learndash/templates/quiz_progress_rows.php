<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;
 echo '<ul class="list">'; ?>
<?php foreach ( $quizzes as $k => $v ) : ?>
	<?php $quiz = get_post( $v['quiz'] ); ?>
	<?php
	if ( ( !( $quiz instanceof WP_Post ) ) || ( $quiz->post_type != 'sfwd-quiz' ) ) {
		if ( ( isset( $v['pro_quizid'] ) ) && ( !empty( $v['pro_quizid'] ) ) ) {
			$quiz_post_id = learndash_get_quiz_id_by_pro_quiz_id( intval( $v['pro_quizid'] ) );
			if ( !empty( $quiz_post_id ) ) {
				$quiz = get_post( $quiz_post_id );
			}
		} 
	}
	
	if ( ( !( $quiz instanceof WP_Post ) ) || ( $quiz->post_type != 'sfwd-quiz' ) ) {
		continue;
	}
	
	$certificateLink = '';
	$certificate_threshold = 0;

	if (!isset($v['has_graded']))
		$v['has_graded'] = false;

	if ( true === $v['has_graded'] && true === LD_QuizPro::quiz_attempt_has_ungraded_question( $v ) ) {
		$certificateLink = '';
		$certificate_threshold = 0;
		$passstatus = 'red';
	} else {
		$c = learndash_certificate_details( $v['quiz'], $user_id );
		if ((isset($c['certificateLink'])) && (!empty($c['certificateLink'])))
			$certificateLink = $c['certificateLink']; 
		
		if ((isset($c['certificate_threshold'])) && (!empty($c['certificate_threshold'])))
			$certificate_threshold = $c['certificate_threshold'];
		
		$passstatus = isset( $v['pass'] ) ? ( ( $v['pass'] == 1 ) ? 'green' : 'red' ) : '';
	}
	?>
	
	<?php //$passstatus = isset( $v['pass'] ) ? ( ( $v['pass'] == 1 ) ? 'green' : 'red' ) : ''; ?>
	<?php //$c = learndash_certificate_details( $v['quiz'], $user_id ); ?>
	<?php //$certificateLink = $c['certificateLink']; ?>
	<?php // $certificate_threshold = $c['certificate_threshold']; ?>
	<?php $quiz_title = ! empty( $quiz->post_title ) ? $quiz->post_title : @$v['quiz_title']; ?>
	<?php
		$quiz_course_id = 0;
		if ( isset( $v['course'] ) ) {
			$quiz_course_id = intval( $v['course'] );
		} else {
			$quiz_course_id = learndash_get_course_id( $quiz, true );
		}
	?>


	<?php if ( ! empty( $quiz_title ) ) : ?>
		<li id="ld-quiz-<?php echo $v['time']; ?>" class="item">
			<strong><a class="push-page" href="<?php echo learndash_get_step_permalink( $quiz->ID, $quiz_course_id ) ?>"><?php echo $quiz_title ?></a></strong> 
			<?php
				if ( ( isset( $v['course'] ) ) && ( intval( $v['course'] ) != learndash_get_course_id( $quiz, true ) ) ) {
					$quiz_course_title = get_the_title( $v['course'] );
					if ( !empty( $quiz_course_title ) ) {
						echo ' - <a href="'. get_the_permalink( $v['course'] ) .'">'. $quiz_course_title .'</a>';
					}
				}
			?>
			<?php echo isset( $v['percentage'] ) ? " - <span style='color:". $passstatus ."'>". $v['percentage'] ."%</span>" : '' ?>
			<?php if ( ( ( $user_id == get_current_user_id() ) || ( learndash_is_admin_user() ) || ( learndash_is_group_leader_user() ) ) && ( !empty( $certificateLink ) ) ) {
					if ( ( ( isset( $v['pass'] ) ) && ( $v['pass'] == 1 ) )
						&& ( ( isset( $v['percentage'] ) && $v['percentage'] >= $certificate_threshold * 100 ) ) 
						|| ( ( isset( $v['count'] ) ) && ( intval($v['count'] ) ) 
						&& ( isset( $v['score'] ) ) && ( intval( $v['score'] ) )
						&& ( ( intval( $v['score'] ) / intval( $v['count'] ) ) >= $certificate_threshold ) )
				 	) { 
						
						$certificateLink = add_query_arg( array('time' => $v['time'] ), $certificateLink );
						//if ( ( isset( $v['course'] ) ) && ( intval( $v['course'] ) != learndash_get_course_id( $quiz, true ) ) ) {
						//	$certificateLink = add_query_arg( array('course_id' => $v['course'] ), $certificateLink );
						//}
						
					?> - <a href='<?php echo $certificateLink ?>' target='_blank'><?php echo __( 'Certificate', 'learndash' ); ?></a><?php } ?>					
			<?php
			}
			
			if ( ( $user_id == get_current_user_id() ) || ( learndash_is_admin_user() ) || ( learndash_is_group_leader_user() ) ) {
				if ( ( !isset( $v['statistic_ref_id'] ) ) || ( empty( $v['statistic_ref_id'] ) ) ) {
					$v['statistic_ref_id'] = learndash_get_quiz_statistics_ref_for_quiz_attempt( $user_id, $v );
				}
				
				if ( ( isset( $v['statistic_ref_id'] ) ) && ( !empty( $v['statistic_ref_id'] ) ) ) {
					/**
					 *	 @since 2.3
					 * See snippet on use of this filter https://bitbucket.org/snippets/learndash/5o78q
					 */
					if ( apply_filters( 'show_user_profile_quiz_statistics', 
						get_post_meta( $v['quiz'], '_viewProfileStatistics', true ), $user_id, $v, basename( __FILE__ ) ) ) {
							?><a class="user_statistic" data-statistic_nonce="<?php echo wp_create_nonce( 'statistic_nonce_'. $v['statistic_ref_id'] .'_'. get_current_user_id() . '_'. $user_id ); ?>" data-user_id="<?php echo $user_id ?>" data-quiz_id="<?php echo $v['pro_quizid'] ?>" data-ref_id="<?php echo intval( $v['statistic_ref_id'] ) ?>" href="#"><?php _e('Statistics', 'learndash'); ?></a><?php
					}
				}
			}
			?>
			<?php					
			if (isset($v['m_edit_by'])) {
				$manual_edit_user = get_user_by('id', $v['m_edit_by']);
				if ($manual_edit_user instanceof WP_User) {
					$manual_edit_str = sprintf( __('Manual Edit by: %s on %s', 'learndash'), 
						$manual_edit_user->display_name, 
						date_i18n(apply_filters('learndash_date_time_formats', get_option('date_format') .' '. get_option('time_format')), $v['m_edit_time'] + get_option('gmt_offset') * 3600) );
					
					?> <abbr title="<?php echo $manual_edit_str ?>"><?php _e('(m)', 'learndash'); ?></abbr><?php
				}
			}
			?>
			<?php
			if ( current_user_can( 'wpProQuiz_edit_quiz' ) ) {
				?>
				<a href="<?php echo add_query_arg( 'course_id', $quiz_course_id, get_edit_post_link( $quiz->ID )) ?>"><?php echo _x('(edit)', 'profile edit quiz link label', 'learndash') ?></a>
				<?php if ( learndash_show_user_course_complete( $user_id ) ) { ?>
				<a class="remove-quiz" data-quiz-user-id="<?php echo $user_id ?>" data-quiz-nonce="<?php echo wp_create_nonce( 'remove_quiz_'. $user_id .'_'. $v['quiz'] .'_'. $v['time'] ) ?>" href="#" title="<?php echo sprintf( _x('remove this %s item', 'placeholder: quiz', 'learndash'), LearnDash_Custom_Label::label_to_lower( 'quiz' ) ) ?>"><?php echo _x('(remove)', 'profile remove quiz link label', 'learndash') ?></a>
				<?php
				}
			}
			?>
			<br/>

			<?php
				if ( ( true === $v['has_graded'] ) && ( isset( $v['graded'] ) ) && (is_array( $v['graded'] ) ) && (!empty( $v['graded'] ) ) ) {
					foreach($v['graded'] as $quiz_question_id => $graded ) {
						
						if ( isset( $graded['post_id'] ) ) {

							$graded_post = get_post( $graded['post_id'] );
							if ($graded_post instanceof WP_Post) {
							
								if ($graded['status'] == 'graded') {
									$graded_color = ' color: green;';
								} else {
									$graded_color = ' color: red;';
								}
							
								$post_status_object_label = get_post_status_object( $graded['status'] )->label;

								//$post_type_object_label_name = get_post_type_object( $graded_post->post_type )->labels->name;
								
								echo /* $post_type_object_label_name .': '. */ get_the_title( $graded['post_id'] ) . ', '. __('Status', 'learndash') . ': <span style="'. $graded_color .'">' . $post_status_object_label .'</span>, '. __('Points', 'learndash') .': ' .  $graded['points_awarded'];
							
								if (current_user_can('edit_essays')) {
									echo ' <a target="_blank" href="'. get_edit_post_link( $graded['post_id'] ) .'">'. __( 'edit', 'learndash' ) .'</a>';
								}
								echo ' <a target="_blank" href="'. get_permalink( $graded['post_id'] ) .'">'. __( 'view', 'learndash' ) .'</a>';
							
								echo ' <a target="_blank" href="'. get_permalink( $graded['post_id'] ) .'#comments">'. __( 'comments', 'learndash' ) .' '. get_comments_number( $graded['post_id'] ) .'</a>';
								echo '<br />';
							}
						}
					}
				}
			?>

			
			<?php if ( isset( $v['rank'] ) && is_numeric( $v['rank'] ) ) : ?>
				<?php echo __( 'Rank: ', 'learndash' ); ?> <?php echo $v['rank']; ?>, 
			<?php endif; ?>

			<?php echo __( 'Score ', 'learndash' ); ?><?php echo $v['score']; ?> <?php echo __( ' out of ', 'learndash' ); ?> <?php 
			if ( ( isset( $v['question_show_count'] ) ) && ( !empty( $v['question_show_count'] ) ) ) echo $v['question_show_count'];
			else echo $v['count']; ?> <?php echo __( ' question(s)', 'learndash' ); ?>
			
			<?php if ( isset( $v['points'] ) && isset( $v['total_points'] ) ) : ?>
				<?php echo __( ' . Points: ', 'learndash' ); ?> <?php echo $v['points']; ?>/<?php echo $v['total_points']; ?>
			<?php endif; ?>

			<?php echo __( ' on ', 'learndash' ); ?> <?php echo date_i18n(apply_filters('learndash_date_time_formats', get_option('date_format') .' '. get_option('time_format')), $v['time'] + get_option('gmt_offset') * 3600) //date_i18n( DATE_RSS, $v['time'] ); ?>
			
			<?php
			/**
			 * 'course_info_shortcode_after_item' filter
			 *
			 * @todo filter doesn't make sense, change to action?
			 * 
			 * @since 2.1.0
			 */
			?>
			<?php echo apply_filters( 'course_info_shortcode_after_item', '', $quiz, $v, $user_id ); ?>
		</li>
	<?php endif; ?>	
<?php endforeach; ?>
<?php echo '</ul>'; ?>