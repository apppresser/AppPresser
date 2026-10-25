<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

$attributes = learndash_get_lesson_attributes( $lesson );
$quizzes    = learndash_get_lesson_quiz_list( $lesson['post']->ID, get_current_user_id(), $course_id );

/**
 * Should this lesson be expandable, false by default
 * @var $expandable boolean
 */
$expandable = false;

if ( isset( $lesson_topics ) && ! empty( $lesson_topics ) ) {
	$expandable = true;
} elseif ( isset( $quizzes ) && ! empty( $quizzes ) ) {
	if ( isset( $widget_instance['show_lesson_quizzes'] ) && true === (bool) $widget_instance['show_lesson_quizzes'] ) {
		$expandable = true;
	}
}

$current_lesson_id = null;

global $post;
global $course_pager_results;

if ( isset( $post ) && is_object( $post ) && isset( $post->post_type ) ) {
	if ( in_array( $post->post_type, array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ), true ) ) {
		if ( 'sfwd-lessons' === $post->post_type ) {
			$current_lesson_id = $post->ID;
		} elseif ( in_array( $post->post_type, array( 'sfwd-topic', 'sfwd-quiz' ), true ) ) {
			$current_lesson_id = learndash_course_get_single_parent_step( $course_id, $post->ID, 'sfwd-lessons' );
		}
	}
}

if ( isset( $_GET['widget_instance']['widget_instance']['current_lesson_id'] ) ) {
	$current_lesson_id = intval( $_GET['widget_instance']['widget_instance']['current_lesson_id'] );
}

$is_current_lesson = ( absint( $current_lesson_id ) === absint( $lesson['post']->ID ) ? true : false );

$lesson_class  = 'ld-lesson-item ' . ( $is_current_lesson ? 'ld-is-current-lesson' : 'ld-is-not-current-lesson' );
$lesson_class .= ( ! empty( $lesson['lesson_access_from'] ) || ! $has_access ? ' learndash-not-available' : '' );
$lesson_class .= ' ' . ( 'completed' === $lesson['status'] ? 'learndash-complete' : 'learndash-incomplete' );
$lesson_class .= ( isset( $lesson['sample'] ) ? ' ' . $lesson['sample'] : '' );

$lesson_class = apply_filters( 'learndash-nav-widget-lesson-class', $lesson_class );

if ( isset( $sections[ $lesson['post']->ID ] ) ) :

	learndash_get_template_part(
		'widgets/navigation/section.php',
		array(
			'section'   => $sections[ $lesson['post']->ID ],
			'course_id' => $course_id,
			'user_id'   => $user_id,
		),
		true
	);

endif; ?>

<div class="<?php echo esc_attr( $lesson_class ); ?>">
	<div class="ld-lesson-item-preview">
		<a class="ld-lesson-item-preview-heading ld-primary-color-hover push-page" href="<?php echo esc_attr( learndash_get_step_permalink( $lesson['post']->ID, $course_id ) ); ?>">

			<?php
			$lesson_progress = learndash_lesson_progress( $lesson['post'] );
			if ( is_array( $lesson_progress ) ) {
				$status = ( $lesson_progress['completed'] > 0 && 'completed' !== $lesson['status'] ? 'progress' : $lesson['status'] );
			} else {
				$status = $lesson['status'];
			}
			learndash_status_icon( $status, 'sfwd-lesson', null, true );
			?>

			<div class="ld-lesson-title">
				<?php
				echo wp_kses_post( $lesson['post']->post_title );
				if ( ! empty( $attributes ) ) :
					foreach ( $attributes as $attribute ) :
						?>
					<span class="ld-status-icon <?php echo esc_attr( $attribute['class'] ); ?>" data-ld-tooltip="<?php echo esc_attr( $attribute['label'] ); ?>"><span class="ld-icon <?php echo esc_attr( $attribute['icon'] ); ?>"></span>
						<?php
					endforeach;
				endif;
				?>
			</div> <!--/.ld-lesson-title-->

		</a> <!--/.ld-lesson-item-preview-heading-->

		<?php
		if ( $expandable ) :

			/**
			 * Filter to contol auto-expanding of lessons in Focus Mode sidebar.
			 * @since 3.0
			 *
			 * @var string $expand_class Value will be 'ld-expanded' if current lesson or empty string.
			 * @var integer $lesson_id Lesson Post ID. @since 3.1
			 * @var integer $course_id Course Post ID. @since 3.1
			 * @var integer $user_id User ID. @since 3.1
			 */
			$expand_class  = apply_filters( 'learndash-nav-widget-expand-class', ( $is_current_lesson ? 'ld-expanded' : '' ), $lesson['post']->ID, $course_id, $user_id );
			$content_count = learndash_get_lesson_content_count( $lesson, $course_id );
			?>

			<span class="ld-expand-button ld-button-alternate <?php echo esc_attr( $expand_class ); ?>" aria-label="<?php esc_html_e( 'Expand Lesson', 'learndash' ); ?>" data-ld-expands="<?php echo esc_attr( 'ld-nav-content-list-' . $lesson['post']->ID ); ?>" data-ld-collapse-text="false">
				<span class="ld-icon-arrow-down ld-icon ld-primary-background"></span>
				<span class="ld-text ld-primary-color">
					<?php
					if ( $content_count['topics'] > 0 ) {
						echo sprintf(
							// translators: placeholders: Topic Count, Topic/Topics Label.
							esc_html_x( '%1$d %2$s', 'placeholders: Topic Count, Topic/Topics Label', 'learndash' ),
							esc_html( $content_count['topics'] ),
							esc_html(
								_n(
									LearnDash_Custom_Label::get_label( 'topic' ),
									LearnDash_Custom_Label::get_label( 'topics' ),
									$content_count['topics'],
									'learndash'
								)
							)
						);
					}

					if ( $content_count['quizzes'] > 0 && $content_count['topics'] > 0 ) {
						echo ' <span class="ld-sep">|</span> ';
					}

					if ( $content_count['quizzes'] > 0 ) {
						echo sprintf(
							// translators: placeholders: Quiz Count, Quiz/Quizzes Label.
							esc_html_x( '%1$d %2$s', 'placeholders: Quiz Count, Quiz/Quizzes Label', 'learndash' ),
							esc_html( $content_count['quizzes'] ),
							esc_html(
								_n(
									LearnDash_Custom_Label::get_label( 'quiz' ),
									LearnDash_Custom_Label::get_label( 'quizzes' ),
									$content_count['quizzes'],
									'learndash'
								)
							)
						);
					}
					?>
				</span>
			</span>
		<?php endif; ?>

	</div> <!--/.ld-lesson-item-preview-->
	<?php if ( $expandable ) : ?>
		<div class="ld-lesson-item-expanded ld-expandable <?php echo esc_attr( $expand_class ); ?>" id="<?php echo esc_attr( 'ld-nav-content-list-' . $lesson['post']->ID ); ?>">
			<div class="ld-table-list ld-topic-list">
				<div class="ld-table-list-items">
					<?php
					if ( isset( $lesson_topics ) && ! empty( $lesson_topics ) ) :
						foreach ( $lesson_topics as $topic ) :

							learndash_get_template_part(
								'widgets/navigation/topic-row.php',
								array(
									'topic'           => $topic,
									'course_id'       => $course_id,
									'user_id'         => $user_id,
									'widget_instance' => $widget_instance,
								),
								true
							);

						endforeach;
					endif;

					if ( isset( $widget_instance['show_lesson_quizzes'] ) && true === (bool) $widget_instance['show_lesson_quizzes'] ) :

						$show_lesson_quizzes = true;

						if ( isset( $course_pager_results[ $lesson['post']->ID ]['pager'] ) && ! empty( $course_pager_results[ $lesson['post']->ID ]['pager'] ) ) :
							$show_lesson_quizzes = ( absint( $course_pager_results[ $lesson['post']->ID ]['pager']['paged'] ) === absint( $course_pager_results[ $lesson['post']->ID ]['pager']['total_pages'] ) ? true : false );
						endif;

						$show_lesson_quizzes = apply_filters( 'learndash-show-lesson-quizzes', $show_lesson_quizzes, $lesson['post']->ID, $course_id, $user_id );

						if ( $quizzes && ! empty( $quizzes ) && $show_lesson_quizzes ) :
							foreach ( $quizzes as $quiz ) :

								learndash_get_template_part(
									'widgets/navigation/quiz-row.php',
									array(
										'course_id' => $course_id,
										'user_id'   => $user_id,
										'context'   => 'lesson',
										'quiz'      => $quiz,
									),
									true
								);

							endforeach;
						endif;

					endif;
					?>
				</div> <!--/.ld-table-list-items-->
				<?php

				if ( isset( $course_pager_results[ $lesson['post']->ID ]['pager'] ) ) :
					?>
					<div class="ld-table-list-footer">
						<?php
						learndash_get_template_part(
							'modules/pagination.php',
							array(
								'pager_results'   => $course_pager_results[ $lesson['post']->ID ]['pager'],
								'pager_context'   => 'course_topics',
								'href_query_arg'  => 'ld-topic-page',
								'lesson_id'       => $lesson['post']->ID,
								'course_id'       => $course_id,
								'href_val_prefix' => $lesson['post']->ID . '-',
							),
							true
						);
						?>
					</div> <!--/.ld-table-list-footer-->
				<?php endif; ?>
			</div> <!--/.ld-topic-list-->
		</div> <!--/.ld-lesson-items-expanded-->
		<?php
	endif
	?>
</div> <!--/.ld-lesson-item-->
