<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * lesson/topic assignment uploads list. 
 *
 * If ther user has previouly uploaded assignment files they will be show via this template
 *
 * Available Variables:
 * 
 * $course_step_post 		: WP_Post object for the Lesson/Topic being shown
 * 
 * @since 2.5
 * 
 * @package LearnDash\Lesson
 */

if ( ( isset( $course_step_post ) ) && ( $course_step_post instanceof WP_Post ) ) {

	$post_settings = learndash_get_setting( $course_step_post->ID );

	$assignments = learndash_get_user_assignments( $course_step_post->ID, $user_id );
	if ( ! empty( $assignments ) ) {
		?>
		<div id="learndash_uploaded_assignments" class="learndash_uploaded_assignments">
			<h2><?php esc_html_e( 'Files you have uploaded', 'learndash' ); ?></h2>
			<table>
				<?php foreach( $assignments as $assignment ) { ?>
					<tr>
						<td class="ld-assignment-delete-col"><?php 
							if ( !learndash_is_assignment_approved_by_meta( $assignment->ID ) ) {
								if ( (isset( $post_settings['lesson_assignment_deletion_enabled'] ) ) && ( $post_settings['lesson_assignment_deletion_enabled'] == 'on' ) && ( ( $assignment->post_author == $user_id ) || ( learndash_is_admin_user( $current_user_id ) ) || ( learndash_is_group_leader_of_user( $current_user_id, $post->post_author ) ) ) ) { ?>
							<a href="<?php echo add_query_arg('learndash_delete_attachment', $assignment->ID) ?>" title="<?php esc_html_e('Delete this uploaded Assignment', 'learndash'); ?>"><?php esc_html_e('X', 'learndash' ); ?></a><?php
								} 
							}
						?></td>
						<td class="ld-assignment-filename-col">
							<a href='<?php echo esc_attr( get_post_meta( $assignment->ID, 'file_link', true ) ); ?>' target="_blank"><?php esc_html_e( 'Download', 'learndash' ) ?> <?php echo get_post_meta( $assignment->ID, 'file_name', true ); ?></a><br />
							<span class="learndash_uploaded_assignment_points"><?php echo learndash_assignment_points_awarded( $assignment->ID ); ?></span>
						</td>
						<td class="ld-assignment-comments-col"><a href='<?php echo esc_attr( get_permalink( $assignment->ID ) ); ?>'><?php esc_html_e( 'Comments', 'learndash' ); ?></a></td>
					</tr>
				<?php } ?>
			</table>
		</div>
	<?php }
}
		