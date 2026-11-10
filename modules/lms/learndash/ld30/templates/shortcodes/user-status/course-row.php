<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


$course      = get_post( $course_id );
$course_link = get_permalink( $course_id );

$progress = learndash_course_progress(
	array(
		'user_id'   => $user_id,
		'course_id' => $course_id,
		'array'     => true,
	)
);

$status = ( 100 === absint( $progress['percentage'] ) ) ? 'completed' : 'notcompleted';
$status = ( absint( $progress['percentage'] ) > 0 && 100 !== absint( $progress['percentage'] ) ? 'progress' : $status );
$since  = learndash_user_group_enrolled_to_course_from( $user_id, $course_id );
if ( empty( $since ) ) {
	$since = ld_course_access_from( $course_id, $user_id );
}

$course_class = apply_filters(
	'learndash-course-row-class',
	'ld-item-list-item ld-item-list-item-course ld-expandable ' . ( 100 === absint( $progress['percentage'] ) ? 'learndash-complete' : 'learndash-incomplete' ),
	$course,
	$user_id
);

$course_icon_class = apply_filters(
	'learndash-course-icon-class',
	'ld-status-icon ' . ( 100 === absint( $progress['percentage'] ) ? 'ld-status-complete' : 'ld-status-incomplete' ),
	$course,
	$user_id
); ?>

<div class="<?php echo esc_attr( $course_class ); ?>" id="<?php echo esc_attr( 'ld-course-list-item-' . $course_id ); ?>">
	<div class="ld-item-list-item-preview">

		<a href="<?php echo esc_url( get_the_permalink( $course_id ) ); ?>" class="push-page ld-item-name">
			<?php learndash_status_icon( $status, get_post_type( 'sfwd-courses' ), null, true ); ?>
			<span class="ld-item-title">
				<?php
				echo esc_html( get_the_title( $course_id ) );

				$components = array(
					// translators: User Status Course Progress
					'progress' => sprintf( esc_html_x( '%s%% Complete', 'User Status Course Progress', 'learndash' ), $progress['percentage'] ),
					// translators: User Status Course Steps.
					'steps'    => sprintf( esc_html_x( '%1$d/%2$d Steps', 'User Status Course Steps', 'learndash' ), $progress['completed'], $progress['total'] ),
				);

				if ( ! empty( $since ) ) {
					// translators: User Status Course Since.
					$components['since'] = sprintf( esc_html_x( 'Since %s', 'User Status Course Since', 'learndash' ), learndash_adjust_date_time_display( $since ) );
				}

				$components = apply_filters( 'learndash_user_status_course_components', $components );
				?>
				<span class="ld-item-components">
					<?php $i = 1; foreach ( $components as $slug => $markup ) : ?>
						<span class="<?php echo esc_attr( 'ld-item-component-' . $slug ); ?>">
							<?php echo wp_kses_post( $markup ); ?>
						</span>
						<?php
						if ( count( $components ) !== $i ) :
							?>
							<span class="ld-sep">|</span>
							<?php
						endif;
						$i++;
endforeach;
					?>
				</span>
			</span>
		</a> <!--/.ld-course-name-->

	</div> <!--/.ld-course-preview-->
</div> <!--/.ld-course-list-item-->
