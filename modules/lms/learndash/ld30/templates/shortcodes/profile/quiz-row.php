<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


// Defaults for fallbacks
$certificateLink = null;
$score           = null;
$stats           = '--';

/**
 * Set the quiz status and certificate link (if applicable)
 * @var [type]
 */
if ( isset( $quiz_attempt['has_graded'] ) && true === (bool) $quiz_attempt['has_graded'] && true === (bool) LD_QuizPro::quiz_attempt_has_ungraded_question( $quiz_attempt ) ) {
	$status = 'pending';
} else {
	if ( ( isset( $quiz_attempt['certificate']['certificateLink'] ) ) && ( ! empty( $quiz_attempt['certificate']['certificateLink'] ) ) ) {
		$certificateLink = $quiz_attempt['certificate']['certificateLink'];
	} else {
		$certificateLink = '';
	}
	$status = empty( $quiz_attempt['pass'] ) ? 'failed' : 'passed';
}

/**
 * Populate the score variables
 * @var [type]
 */
if ( isset( $quiz_attempt['has_graded'] ) && true === (bool) $quiz_attempt['has_graded'] && true === (bool) LD_QuizPro::quiz_attempt_has_ungraded_question( $quiz_attempt ) ) :
	$score = esc_html_x( 'Pending', 'Pending Certificate Status Label', 'learndash' );
else :
	$score = round( $quiz_attempt['percentage'], 2 ) . '%';
endif;

/**
 * Populate the stats variable
 * @var [type]
 */
if ( get_current_user_id() === absint( $user_id ) || learndash_is_admin_user() || learndash_is_group_leader_user() ) :

	if ( ! isset( $quiz_attempt['statistic_ref_id'] ) || empty( $quiz_attempt['statistic_ref_id'] ) ) {
		$quiz_attempt['statistic_ref_id'] = learndash_get_quiz_statistics_ref_for_quiz_attempt( $user_id, $quiz_attempt );
	}

	if ( isset( $quiz_attempt['statistic_ref_id'] ) && ! empty( $quiz_attempt['statistic_ref_id'] ) ) {
		/**
		 *   @since 2.3
		 * See snippet on use of this filter https://bitbucket.org/snippets/learndash/5o78q
		 */
		if ( apply_filters( 'show_user_profile_quiz_statistics', get_post_meta( $quiz_attempt['post']->ID, '_viewProfileStatistics', true ), $user_id, $quiz_attempt, basename( __FILE__ ) ) ) {
			$stats = '<a class="user_statistic" data-statistic-nonce="' . wp_create_nonce( 'statistic_nonce_' . $quiz_attempt['statistic_ref_id'] . '_' . get_current_user_id() . '_' . $user_id ) . '" data-user-id="' . esc_attr( $user_id ) . '" data-quiz-id="' . esc_attr( $quiz_attempt['pro_quizid'] ) . '" data-ref-id="' . esc_attr( intval( $quiz_attempt['statistic_ref_id'] ) ) . '" href="#"><span class="ld-icon ld-icon-assignment"></span></a>';
		}
	}

endif;

// Quiz title and link...
$quiz_title = ! empty( $quiz_attempt['post']->post_title ) ? $quiz_attempt['post']->post_title : @$quiz_attempt['quiz_title'];
$quiz_link  = ! empty( $quiz_attempt['post']->ID ) ? learndash_get_step_permalink( intval( $quiz_attempt['post']->ID ), $course_id ) : '#'; ?>

<div class="ld-table-list-item <?php echo esc_attr( $status ); ?>">
	<div class="ld-table-list-item-preview">

		<div class="ld-table-list-title">
			<a class="push-page" href="<?php echo esc_url( $quiz_link ); ?>">
			<?php
			echo wp_kses_post( learndash_status_icon( $status, 'sfwd-quiz' ) );
			?>
			<span><?php echo esc_html( $quiz_title ); ?></span>
			</a>
		</div> <!--/.ld-table-list-title-->

		<div class="ld-table-list-columns">

			<?php

			if ( $certificateLink && ! empty( $certificateLink ) ) {
				$certificateLink = '<a class="ld-certificate-link push-page" href="' . $certificateLink . '" target="_new" aria-label="' . __( 'Certificate', 'learndash' ) . '"><span class="ld-icon ld-icon-certificate"></span></a>';
			}

			$quiz_columns = apply_filters(
				'learndash_profile_quiz_columns',
				array(
					'certificate' => array(
						'id'      => $quiz_list_columns[0]['id'],
						'label'   => $quiz_list_columns[0]['label'],
						'content' => $certificateLink,
						'class'   => '',
					),
					'score'       => array(
						'id'      => $quiz_list_columns[1]['id'],
						'label'   => $quiz_list_columns[1]['label'],
						'content' => $score,
						'class'   => '',
					),
					'stats'       => array(
						'id'      => $quiz_list_columns[2]['id'],
						'label'   => $quiz_list_columns[2]['label'],
						'content' => $stats,
						'class'   => '',
					),
					'date'        => array(
						'id'      => $quiz_list_columns[3]['id'],
						'label'   => $quiz_list_columns[3]['label'],
						'content' => learndash_adjust_date_time_display( $quiz_attempt['time'] ),
						'class'   => '',
					),
				),
				$quiz_attempt,
				$quiz_list_columns
			);
			foreach ( $quiz_columns as $column ) :
				?>
				<div class="<?php echo esc_attr( 'ld-table-list-column ld-table-list-column-' . $column['id'] . ' ' . $column['class'] ); ?>">
					<span class="ld-column-label"><?php echo wp_kses_post( $column['label'] ); ?>: </span>
					<?php echo wp_kses_post( $column['content'] ); ?>
				</div>
			<?php endforeach; ?>

		</div>
	</div> <!--/.ld-table-list-item-preview-->

	<?php
	$essays = ( isset( $quiz_attempt['graded'] ) && ! empty( $quiz_attempt['graded'] ) ? $quiz_attempt['graded'] : false );

	if ( $essays && ! empty( $essays ) ) :
		?>
		<div class="ld-table-list-item-expanded">
			<div class="ld-table-list ld-essay-list">
				<div class="ld-table-list-header">
					<div class="ld-table-list-title">
						<?php echo esc_html_e( 'Essays', 'learndash' ); ?>
					</div> <!--/.ld-table-list-title-->
					<div class="ld-table-list-columns">
						<?php
						$columns = apply_filters(
							'learndash-essay-column-headings',
							array(
								array(
									'id'    => 'comments',
									'label' => __( 'Comments', 'learndash' ),
								),
								array(
									'id'    => 'status',
									'label' => __( 'Status', 'learndash' ),
								),
								array(
									'id'    => 'points',
									'label' => __( 'Points', 'learndash' ),
								),
							)
						);
						foreach ( $columns as $column ) :
							?>
							<div class="<?php echo esc_attr( 'ld-table-list-column ld-table-list-column-' . $column['id'] ); ?>">
								<?php echo esc_html( $column['label'] ); ?>
							</div>
							<?php
						endforeach;
						?>
					</div> <!--/.ld-table-list-columns-->
				</div> <!--/.ld-table-list-header-->
				<div class="ld-table-list-items">
					<?php
					foreach ( $essays as $essay_array ) :

						$essay = get_post( $essay_array['post_id'] );

						learndash_get_template_part(
							'shortcodes/profile/essay-row.php',
							array(
								'essay'     => $essay,
								'user_id'   => $user_id,
								'course_id' => $course_id,
							),
							true
						);

					endforeach;
					?>
				</div> <!--/.ld-table-list-items-->
			</div> <!--/.ld-essay-list-->
		</div> <!--/.ld-table-list-item-expanded-->
	<?php endif; ?>
</div> <!--/.ld-table-list-item-->
