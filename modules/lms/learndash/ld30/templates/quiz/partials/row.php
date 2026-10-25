<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays a single quiz row
 *
 * Available Variables:
 *
 * $user_id     :   The current user ID
 * $course_id   :   The current course ID
 * $lesson      :   The current lesson
 * $topic       :   The current topic object
 * $quiz        :   The current quiz (array)
 *
 * @since 3.0
 *
 * @package LearnDash\Course
 */

$quiz_classes = learndash_quiz_row_classes( $quiz, $context );
$is_sample    = ( isset($lesson['sample']) ? $lesson['sample'] : false );

$atts = apply_filters( 'learndash_quiz_row_atts', ( isset($has_access) && !$has_access && !$is_sample ? 'data-ld-tooltip="' . __( "You don't currently have access to this content", "learndash" ) . '"' : '' ) );

/**
 * Action to add custom content before the quiz row listing
 *
 * @since 3.0
 */
do_action( 'learndash-quiz-row-before', $quiz['post']->ID, $course_id, $user_id ); ?>
<div id="<?php echo esc_attr( 'ld-table-list-item-' . $quiz['post']->ID ); ?>" class="<?php echo esc_attr($quiz_classes['wrapper']); ?>" <?php echo wp_kses_post($atts); ?>>
    <div class="<?php echo esc_attr($quiz_classes['preview']); ?>">
        <a class="<?php echo esc_attr($quiz_classes['anchor']); ?> push-page" href="<?php echo esc_attr( learndash_get_step_permalink( $quiz['post']->ID, $course_id ) ); ?>">
            <?php
            /**
             * Action to add custom content before quiz row status
             *
             * @since 3.0
             */
            do_action( 'learndash-quiz-row-status-before', $quiz['post']->ID, $course_id, $user_id );

            learndash_status_icon( $quiz['status'], 'sfwd-quiz', null, true );
            /**
             * Action to add custom content before quiz row title
             *
             * @since 3.0
             */
            do_action( 'learndash-quiz-row-title-before', $quiz['post']->ID, $course_id, $user_id ); ?>

            <div class="ld-item-title"><?php echo wp_kses_post($quiz['post']->post_title); ?></div>

            <?php
            /**
             * Action to add custom content before quiz row title
             *
             * @since 3.0
             */
            do_action( 'learndash-quiz-row-title-after', $quiz['post']->ID, $course_id, $user_id ); ?>
        </a>
    </div> <!--/.list-item-preview-->
</div>
<?php
/**
 * Action to add custom content after the quiz row listing
 *
 * @since 3.0
 */
do_action( 'learndash-quiz-row-after', $quiz['post']->ID, $course_id, $user_id );
