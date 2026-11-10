<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Assignment list individual row
 *
 * Available Variables:
 *
 * $course_step_post: WP_Post object for the Lesson/Topic being shown
 *
 * @since 3.0
 *
 * @package LearnDash\Lesson
 */
$assignment_points = learndash_get_points_awarded_array( $assignment->ID ); ?>

<div class="ld-table-list-item">
	<div class="ld-table-list-item-preview">

		<?php
		/**
		 * Action to add custom content before assignment list
		 *
		 * @since 3.0
		 */
		do_action( 'learndash-assignment-row-before', $assignment, get_the_ID(), $course_id, $user_id );
		?>

		<div class="ld-table-list-title">

			<?php
			/**
			 * Action to add custom content before assignment delete link
			 *
			 * @since 3.0
			 */
			do_action( 'learndash-assignment-row-delete-before', $assignment, get_the_ID(), $course_id, $user_id );

			/**
			 * Delete assignment link
			 *
			 */
			if ( ! learndash_is_assignment_approved_by_meta( $assignment->ID ) ) :
				if ( ( isset( $post_settings['lesson_assignment_deletion_enabled'] ) && 'on' === $post_settings['lesson_assignment_deletion_enabled'] && absint( $assignment->post_author ) === absint( $user_id ) ) || ( learndash_is_admin_user( $user_id ) ) || ( learndash_is_group_leader_of_user( $user_id, $post->post_author ) ) ) :
					?>
				<a href="<?php echo esc_url( add_query_arg( 'learndash_delete_attachment', $assignment->ID ) ); ?>" title="<?php esc_html_e( 'Delete this uploaded Assignment', 'learndash' ); ?>">
					<span class="ld-icon ld-icon-delete" aria-label="<?php esc_html_e( 'Delete Assignment', 'learndash' ); ?>"></span>
				</a>
					<?php
				endif;
			endif;

			/**
			 * Action to add custom content before assignment title and link
			 *
			 * @since 3.0
			 */
			do_action( 'learndash-assignment-row-title-before', $assignment, get_the_ID(), $course_id, $user_id );
			?>

			<a href='<?php echo esc_attr( get_post_meta( $assignment->ID, 'file_link', true ) ); ?>' target="_blank">
				<span class="ld-item-icon">
					<span class="ld-icon ld-icon-download" aria-label="<?php esc_html_e( 'Download Assignment', 'learndash' ); ?>"></span>
				</span>
			</a>

			<?php
			$assignment_link = ( true === (bool) $assignment_post_type_object->publicly_queryable ? get_permalink( $assignment->ID ) : get_post_meta( $assignment->ID, 'file_link', true ) );
			?>

			<a href="<?php echo esc_url( $assignment_link ); ?>"><?php echo esc_html( get_the_title( $assignment->ID ) ); ?></a>

			<?php
			/**
			 * Action to add custom content after assignment title and link
			 *
			 * @since 3.0
			 */
			do_action( 'learndash-assignment-row-title-after', $assignment, get_the_ID(), $course_id, $user_id );
			?>

		</div> <!--/.ld-table-list-title-->

		<div class="ld-table-list-columns">

			<?php
			// Use an array so it can be filtered later
			$row_columns = array();

			/**
			 * Action to add custom content before assignment post link
			 *
			 * @since 3.0
			 */
			do_action( 'learndash-assignment-row-columns-before', $assignment, get_the_ID(), $course_id, $user_id );

			ob_start();
			?>

			<?php
			/**
			 * Action to add custom content before assignment comment count & link
			 *
			 * @since 3.0
			 */
			do_action( 'learndash-assignment-row-comments-before', $assignment, get_the_ID(), $course_id, $user_id );

			if ( post_type_supports( 'sfwd-assignment', 'comments' ) && apply_filters( 'comments_open', $assignment->comment_status, $assignment->ID ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WP Core filter
				?>
				<a href='<?php echo esc_attr( get_comments_link( $assignment->ID ) ); ?>' data-ld-tooltip="
					<?php
					echo sprintf(
						// translators: placeholder: comment count.
						esc_html_x( '%d Comments', 'placeholder: comment count', 'learndash' ),
						esc_html( get_comments_number( $assignment->ID ) ) // get_comments_number returns a number. Adding escaping just in case somebody changes the template.
					);
					?>
				"><?php echo esc_html( get_comments_number( $assignment->ID ) ); ?><span class="ld-icon ld-icon-comments"></span></a>
				<?php
			} else {
				echo '';
			};

			// Add the markup to the array
			$row_columns['comments'] = ob_get_clean();
			ob_flush();

			/**
			 * Action to add custom content after assignment comment count & link
			 *
			 * @since 3.0
			 */
			do_action( 'learndash-assignment-row-comments-after', $assignment, get_the_ID(), $course_id, $user_id );

			if ( ! learndash_is_assignment_approved_by_meta( $assignment->ID ) && ! $assignment_points ) :
				ob_start();
				?>

				<span class="ld-status ld-status-waiting ld-tertiary-background">
					<span class="ld-icon ld-icon-calendar"></span>
					<span class="ld-text"><?php esc_html_e( 'Waiting Review', 'learndash' ); ?></span>
				</span> <!--/.ld-status-waiting-->

				<?php
				$row_columns['status'] = ob_get_clean();
				ob_flush();

			elseif ( $assignment_points || learndash_is_assignment_approved_by_meta( $assignment->ID ) ) :

				ob_start();
				?>

				<span class="ld-status ld-status-complete">
					<span class="ld-icon ld-icon-checkmark"></span>
					<?php
					if ( $assignment_points ) :
						echo sprintf(
							// translators: placeholders: points current, points max.
							esc_html_x( '%1$s/%2$s Points Awarded ', 'placeholders: points current, points max', 'learndash' ),
							esc_html( $assignment_points['current'] ),
							esc_html( $assignment_points['max'] )
						) . ' - ';
					endif;

					esc_html_e( 'Approved', 'learndash' );
					?>
				</span>

				<?php
				$row_columns['status'] = ob_get_clean();
				ob_flush();

			endif;

			// Apply a fitler so devs can add more info here later
			$row_columns = apply_filters( 'learndash-assignment-list-columns-content', $row_columns );
			if ( ! empty( $row_columns ) ) :
				foreach ( $row_columns as $slug => $content ) :

					do_action( 'learndash-assignment-row-' . $slug . '-before', $assignment, get_the_ID(), $course_id, $user_id );
					?>
				<div class="<?php echo esc_attr( 'ld-table-list-column ld-' . $slug . '-column' ); ?>">
					<?php
					do_action( 'learndash-assignment-row-' . $slug . '-inside-before', $assignment, get_the_ID(), $course_id, $user_id );

					echo wp_kses_post( $content );

					do_action( 'learndash-assignment-row-' . $slug . '-inside-after', $assignment, get_the_ID(), $course_id, $user_id );
					?>
				</div>
					<?php
					do_action( 'learndash-assignment-row-' . $slug . '-after', $assignment, get_the_ID(), $course_id, $user_id );
					?>
					<?php
				endforeach;
			endif;
			?>

		</div> <!--/.ld-table-list-columns-->

	<?php
	/**
	 * Action to add custom content after all the assignment row content
	 *
	 * @since 3.0
	 */
	do_action( 'learndash-assignment-row-after', $assignment, get_the_ID(), $course_id, $user_id );
	?>
	</div>
</div>
