<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * @var [type]
 */
$course      = get_post( $course_id);
$course_link = get_permalink( $course_id );
$progress    = learndash_course_progress( array(
    'user_id'   => $user_id,
    'course_id' => $course_id,
    'array'     => true
) );

$status = ( $progress['percentage'] == 100 ) ? 'completed' : 'notcompleted';

$course_class = ''; ?>

<?php
/**
 * Action to add custom content before the course row
 *
 * @since 3.0
 */
do_action( 'learndash-course-row-before', $course_id, $user_id ); ?>

<div id="course-<?php echo esc_attr( $user_id ) . '-' . esc_attr( $course->ID ); ?>" class="<?php echo esc_attr($course_class); ?>">

    <div>

        <?php
        /**
         * Action to add custom content before the course row status
         *
         * @since 3.0
         */
        do_action( 'learndash-course-row-status-before', $course_id, $user_id ); ?>

        <a href="<?php echo esc_attr( $course_link ); ?>" class="push-page">
            <?php echo $status; ?>
        </a>

        <?php
        /**
         * Action to add custom content before the course row link
         *
         * @since 3.0
         */
        do_action( 'learndash-course-row-link-before', $course_id, $user_id ); ?>

        <a href="<?php echo esc_attr( $course_link ); ?>">
            <?php echo esc_html($course->post_title); ?>
        </a>

        <?php
        /**
         * Action to add custom content before the course row certificate
         *
         * @since 3.0
         */
        do_action( 'learndash-course-row-certificate-before', $course_id, $user_id ); ?>

        <?php
        $certificateLink = learndash_get_course_certificate_link( $course->ID, $user_id );
        if ( !empty($certificateLink) ): ?>
            <a target="_blank" href="<?php echo esc_attr( $certificateLink ); ?>"><div class="ld-icon ld-icon-certificate"></div></a>
        <?php
        else: ?>
            <a href="#">-</a>
        <?php endif; ?>

        <?php
        /**
         * Action to add custom content before the course row link
         *
         * @since 3.0
         */
        do_action( 'learndash-course-row-expand-before', $course_id, $user_id ); ?>

        <a href="#">Expand / Collapse</a>

        <?php
        /**
         * Action to add custom content before the course row link
         *
         * @since 3.0
         */
        do_action( 'learndash-course-row-expand-after', $course_id, $user_id ); ?>

    </div>
    <div>

        <?php
        /**
         * Include expanded info
         */ ?>

         <?php
         /**
          * Action to add custom content before the course row progress
          *
          * @since 3.0
          */
         do_action( 'learndash-course-row-progress-before', $course_id, $user_id ); ?>

        <div class="learndash_profile_heading course_overview_heading"><?php printf( esc_html_x( '%s Progress Overview', 'Course Progress Overview Label', 'learndash' ), LearnDash_Custom_Label::get_label( 'course' ) ); ?></div>
        <dd class="course_progress" title='<?php echo sprintf( esc_html_x( '%1$d out of %2$d steps completed', 'placeholder: completed steps, total steps', 'learndash' ), $progress['completed'], $progress['total'] ); ?>'>
            <div class="course_progress_blue" style='width: <?php echo esc_attr( $progress["percentage"] ); ?>%;'>
        </dd>

        <?php echo sprintf( esc_html_x( '%1$s%% Complete', 'placeholder: percent complete', 'learndash' ), $progress['percentage'] ); ?>

        <?php
        /**
         * Display quizzes unless disabled
         * @var [type]
         */
        if ( !empty($quiz_attempts[$course_id]) && isset($shortcode_atts['show_quizzes']) && true === $shortcode_atts['show_quizzes'] && apply_filters( 'learndash_show_profile_quizzes', $shortcode_atts['show_quizzes'] ) ): ?>


            <?php
            /**
             * Action to add custom content before the course row quizzes
             *
             * @since 3.0
             */
            do_action( 'learndash-course-row-quizzes-before', $course_id, $user_id ); ?>

            <div class="quiz_title"><?php echo LearnDash_Custom_Label::get_label( 'quizzes' ); ?></div>
            <div class="certificate"><?php esc_html_e( 'Certificate', 'learndash' ); ?></div>
            <div class="scores"><?php esc_html_e( 'Score', 'learndash' ); ?></div>
            <div class="statistics"><?php esc_html_e( 'Statistics', 'learndash' ); ?></div>
            <div class="quiz_date"><?php esc_html_e( 'Date', 'learndash' ); ?></div>

            <?php
            /**
             * Action to add custom content before the course row quizzes
             *
             * @since 3.0
             */
            do_action( 'learndash-course-row-quizzes-attempts-before', $course_id, $user_id ); ?>

            <?php
            foreach( $quiz_attempts[$course_id] as $k => $quiz_attempt ):

                SFWD_LMS::get_template('quiz/partials/attempts.php', array(
                    'quiz_attempt'  =>  $quiz_attempt,
                    'context'       =>  $context,
                    'course_id'  => $course_id,
                ) );

            endforeach;

            /**
             * Action to add custom content before the course row quizzes
             *
             * @since 3.0
             */
            do_action( 'learndash-course-row-quizzes-attempts-after', $course_id, $user_id );

            // TODO 37designs - need pagination
            ?>

        <?php
        endif;

        /**
         * TODO @37designs - I don't think we have access to assignments at this stage so we'll need to query it
         * @var [type]
         */
        if( isset($shortcode_atts['show_assignments']) && true === $shortcode_atts['show_quizzes'] && apply_filters( 'learndash_show_profile_assignments', $shortcode_atts['show_assignments'] ) ):

            /**
             * Action to add custom content before the course row quizzes
             *
             * @since 3.0
             */
            do_action( 'learndash-course-row-assignments-before', $course_id, $user_id );

            foreach( $assignments as $assignment ):

                SFWD_LMS::get_template('assignments/partials/row.php', array(
                    'assignment' => $assignment,
                    'context'    => $context,
                    'course_id'  => $course_id,
                ) );

            endforeach;

            /**
             * Action to add custom content before the course row quizzes
             *
             * @since 3.0
             */
            do_action( 'learndash-course-row-assignments-after', $course_id, $user_id );

            // TODO @37designs - need pagination

        endif; ?>

    </div>
</div>
