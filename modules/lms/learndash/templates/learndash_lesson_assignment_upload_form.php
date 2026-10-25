<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * lesson/topic assignment upload form. 
 *
 * If the lesson/topic is set to be an assignment there will be an upload form displayed to the user. 
 *
 * Available Variables:
 * 
 * $course_step_post : WP_Post object for the Lesson/Topic being shown
 * $user_id : Current user ID
 * $assignment_upload_error_message : string of previous upload error. Will be empty if no previous upload attempt
 * 
 * @since 2.5
 * 
 * @package LearnDash\Lesson
 */

if ( ( isset( $course_step_post ) ) && ( $course_step_post instanceof WP_Post ) ) {
	
	$post_settings = learndash_get_setting( $course_step_post );
	
	$php_max_upload = ini_get('upload_max_filesize');

	if ( ( isset( $post_settings['assignment_upload_limit_size'] ) ) && ( !empty( $post_settings['assignment_upload_limit_size'] ) ) ) {
		if ( ( learndash_return_bytes_from_shorthand( $post_settings['assignment_upload_limit_size'] ) < learndash_return_bytes_from_shorthand( $php_max_upload ) ) ) {
			$php_max_upload = $post_settings['assignment_upload_limit_size'];
		}
	}
	$upload_message = sprintf( esc_html_x( 'Maximum upload file size: %s', 'placeholder: PHP file upload size', 'learndash' ),  $php_max_upload );

	if ( ( isset( $post_settings['assignment_upload_limit_extensions'] ) ) && ( !empty( $post_settings['assignment_upload_limit_extensions'] ) ) ) {
		$limit_file_exts = learndash_validate_extensions( $post_settings['assignment_upload_limit_extensions'] );
		if ( !empty( $limit_file_exts ) ) {
			$upload_message .= ' '. sprintf( esc_html_x('Allowed file types: %s', 'placeholder: comma list of file extentions', 'learndash' ), implode(', ', $limit_file_exts ) );
		}
	}

	if ( isset( $post_settings['assignment_upload_limit_count'] ) ) {
		$assignment_upload_limit_count = intval( $post_settings['assignment_upload_limit_count'] );
		if ( $assignment_upload_limit_count > 0 ) {
			$assignments = learndash_get_user_assignments( $course_step_post->ID, $user_id );
			if ( ( !empty( $assignments ) ) && ( count( $assignments ) >= $assignment_upload_limit_count ) ) {
				return;
			}
		}
	}

	$ret = '';

	global $learndash_assignment_upload_error_message;
	if ( !empty( $learndash_assignment_upload_error_message ) ) {
		$ret .= '<p class="ld-error">'. $learndash_assignment_upload_error_message .'</p>';
	}

	$ret .= '
			<table id="leardash_upload_assignment">
				<tr><td><u>' . esc_html__( 'Upload Assignment', 'learndash' ) . '</u></td></tr>
				<tr>
					<td>
						<form name="uploadfile" id="uploadfile_form" method="POST" enctype="multipart/form-data" action="" accept-charset="utf-8" >
							<input type="file" name="uploadfiles[]" id="uploadfiles" size="35" class="uploadfiles" />
							<input type="hidden" name="MAX_FILE_SIZE" value="'. learndash_return_bytes_from_shorthand( $php_max_upload ) .'" />
							<input type="hidden" value="' . $course_step_post->ID . '" name="post"/>
							<input type="hidden" value="' . learndash_get_course_id( $course_step_post->ID ) . '" name="course_id"/>
							<input type="hidden" name="uploadfile" value="'. wp_create_nonce( 'uploadfile_'. get_current_user_id() .'_'. $course_step_post->ID ) .'"  />
							<input class="button-primary" type="submit" id="uploadfile_btn" value="' . esc_html__( 'Upload', 'learndash' ) . '"  onClick="this.form.submit(); this.disabled=true;;" />
						</form>
					</td>
				</tr>
				<tr><td>'. $upload_message .'</td></tr>
			</table>
	';
	
	echo $ret;
}