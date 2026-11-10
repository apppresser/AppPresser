<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays a single topic row
 *
 * Available Variables:
 *
 * $user_id     :   The current user ID
 * $course_id   :   The current course ID
 *
 * $lesson      :   The current lesson
 *
 * $topic       :   The current topic object
 *
 * @since 3.0
 *
 * @package LearnDash\Course
 */

/**
 * Ajax pagination
 *
 * @var [type]
 */

$topic_id = ( isset($_GET['widget_instance']['widget_instance']['current_step_id']) ? $_GET['widget_instance']['widget_instance']['current_step_id'] : $topic->ID );
$post_id  = ( isset($_GET['widget_instance']['widget_instance']['current_step_id']) ? $topic->ID : get_the_ID() );

$topic_class = apply_filters( 'learndash-topic-row-class',
                                'ld-table-list-item-preview ld-primary-color-hover ld-topic-row ' .
                                ( $topic->completed ? 'learndash-complete' : 'learndash-incomplete' )
                                . ' ' . ( $post_id == $topic_id ? 'ld-is-current-item' : '' )
                                , $topic );

$topic_status = apply_filters( 'learndash-topic-status', ( $topic->completed ? 'completed' : 'not-completed' ), $topic, $course_id );


/**
 * Action to add custom content before topic row
 *
 * @since 3.0
 */
do_action( 'learndash-topic-row-before', $topic->ID, $course_id, $user_id ); ?>
<div class="ld-table-list-item" id="<?php echo esc_attr('ld-table-list-item-' . $topic->ID ); ?>">
    <a class="<?php echo esc_attr($topic_class); ?> push-page" href="<?php echo esc_attr( learndash_get_step_permalink( $topic->ID, $course_id ) ); ?>">
        <?php
        /**
         * Action to add custom content before topic status
         *
         * @since 3.0
         */
        do_action( 'learndash-topic-row-status-before', $topic->ID, $course_id, $user_id ); ?>

        <?php learndash_status_icon( $topic_status, get_post_type(), null, true ); ?>

        <?php
        /**
         * Action to add custom content before topic title
         *
         * @since 3.0
         */
        do_action( 'learndash-topic-row-title-before', $topic->ID, $course_id, $user_id ); ?>
        <span class="ld-topic-title"><?php echo wp_kses_post($topic->post_title); ?></span>
        <?php
        /**
         * Action to add custom content after topic title
         *
         * @since 3.0
         */
        do_action( 'learndash-topic-row-title-after', $topic->ID, $course_id, $user_id ); ?>
    </a>
</div> <!--/.ld-table-list-item-->
<?php

/**
 * Action to add custom content before a topic quiz row
 *
 * @since 3.0
 */
do_action( 'learndash-topic-quiz-row-before', $topic->ID, $course_id, $user_id );

$topic_quizzes = learndash_get_lesson_quiz_list( $topic, null, $course_id );

if( $topic_quizzes && !empty($topic_quizzes) ):
    foreach( $topic_quizzes as $quiz ):
        learndash_get_template_part( 'quiz/partials/row.php', array(
            'quiz'      =>  $quiz,
            'context'   =>  'topic',
            'course_id' =>  $course_id,
            'user_id'   =>  $user_id
        ), true );
    endforeach;
endif;

/**
 * Action to add custom content after a topic quiz row
 *
 * @since 3.0
 */
do_action( 'learndash-topic-quiz-row-after', $topic->ID, $course_id, $user_id );

/**
 * Action to add custom content after topic row
 *
 * @since 3.0
 */
do_action( 'learndash-topic-row-after', $topic->ID, $course_id, $user_id ); ?>
