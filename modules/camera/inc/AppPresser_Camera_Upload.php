<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_Camera_Upload {
	public static function upload_photo() {
		global $_FILES, $_POST, $user_ID;

		// Make sure you're submitting files
		if ( empty( $_FILES ) || ! isset( $_FILES['appp_cam_file'] ) || isset( $_FILES['appp_cam_file']['error'] ) && $_FILES['appp_cam_file']['error'] !== 0 )
			return;

		$files = array_filter( $_FILES['appp_cam_file'] );
		// Make sure you're submitting files
		if ( empty( $files ) )
			return;

		define( 'APPP_IMPORTING', true );

		// make sure to include the media uploader
		if ( ! function_exists( 'wp_handle_upload' ) )
			require_once ABSPATH .'wp-admin/includes/file.php';

		// get post fields and values
		$form_fields = isset( $_POST['form_fields'] ) ? $_POST['form_fields'] : NULL;
		$form_fields = str_replace( array( '[\"','\"]' ), '', $form_fields );
		$form_fields = explode( '\",\"', $form_fields);

		$form_values = isset( $_POST['form_values'] ) ? $_POST['form_values'] : NULL;
		$form_values = str_replace( array( '[\"','\"]' ), '', $form_values );
		$form_values = explode( '\",\"', $form_values);

		// set post values
		foreach ( $form_fields as $key => $value ) {
			$_POST[$value] = $form_values[$key];
		}

		// security nonce check
		$nonce = ( isset( $_POST['apppcamera-upload-image'] ) ) ? $_POST['apppcamera-upload-image'] : ''; // from appcamera
		$nonce = ( empty($nonce) && isset( $_REQUEST['nonce'] ) ) ? $_REQUEST['nonce'] : $nonce; // from appbuddy
		

		define('NONCE_12_HOURS', 1);
		define('NONCE_24_HOURS', 2);
		// DEBUGGING
		// $verified = wp_verify_nonce( $nonce, 'apppcamera-nonce' );
		// $myfile = fopen("nonce.txt", "a") or die();
		// fwrite($myfile, $nonce."\n".$verified."\n");
		// fclose($myfile);
		if ( wp_verify_nonce( $nonce, 'apppcamera-nonce' ) !== NONCE_12_HOURS)
			die( 'Nonce Failed' );

		$uploadedfile = $_FILES['appp_cam_file'];
		$upload_overrides = array( 'test_form' => false );

		$uploaded_file = wp_handle_upload( $uploadedfile, $upload_overrides );

		if ( $uploaded_file ) {

			$photo_upload_moderation = appp_get_setting( 'photo_upload_moderation' );

			//generate the image thumbnail
			$wp_filetype = wp_check_filetype( basename( $uploaded_file['file'] ), null );
			$filename = $uploaded_file['file'];
			$wp_upload_dir = wp_upload_dir();

			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content' => '',
				'post_author' => 1,
				'post_status' => 'inherit',
				'post_type' => 'attachment',
				'guid' => $wp_upload_dir['url'] . '/' . basename( $filename )
			);

			// check appp_action
			$appp_action = ( isset( $_POST['appp_action'] ) ) ? $_POST['appp_action'] : '';
			
			if ( isset( $_REQUEST['action'] ) )
				$appp_action = $_REQUEST['action'];
			
			// check if should be attached to the current post
			if ( $appp_action == 'this' && isset( $_POST['appp_cam_post_id'] ) ) {
				$post_id = $_POST['appp_cam_post_id'];

			// create a new post to attach image to
			} elseif ( $appp_action == 'new' && isset( $_POST['appp_post_type'] ) ) {
				$post_type = $_POST['appp_post_type'];
				$post_title = $_POST['appp_cam_post_title'];
				// set default post_title if blank
				if ( ! $post_title ) {
					$post_title = date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) );
					$post_title = isset( $_FILES['appp_cam_file']['name'] ) ? $post_title .' - '. $_FILES['appp_cam_file']['name'] : $post_title;
				}

				$post_id = wp_insert_post( array(
						'post_type' => sanitize_text_field( $post_type ),
						'post_status' => ( $photo_upload_moderation ) ? 'draft' : 'publish',
						'post_author' => absint( $user_ID ),
						'post_title' => sanitize_text_field( $post_title )
					) );
				// default post_id to 0 if library

			// appbuddy create activity item
			} elseif ( $appp_action == 'attach' ) {

				if ( function_exists( 'bp_activity_add' ) || function_exists( 'bp_core_get_userlink' ) ) {

				    $userlink = bp_core_get_userlink( $user_ID );

					bp_activity_add( array(
						'action' => apply_filters( 'xprofile_new_avatar_action', sprintf( __( '%s uploaded a new picture', 'buddypress' ), $userlink ), $user_ID ),
						'content' => '<img src="'. $uploaded_file['url'] .'">',
						'component' => 'profile',
						'type' => 'activity_update',
						'user_id' => $user_ID,
					) );
				}

				$post_id = 0;

			} else {
				$post_id = 0;
			}

			if( ! $photo_upload_moderation ) {
				$attachment = array_merge( $attachment , array ( 'post_parent' => absint( $post_id ) ) );
			}

			$attachment_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) )
				require_once ABSPATH .'wp-admin/includes/image.php';

			$attach_data = wp_generate_attachment_metadata( absint( $attachment_id ), $uploaded_file['file'] );

			// update the attachment metadata
			wp_update_attachment_metadata( absint( $attachment_id ),  $attach_data );
			$updatepost = array( 'ID' => absint( $post_id ) );

			// Photo Moderation, but not for appbuddy activity ('attach')
			if ( $photo_upload_moderation && $appp_action != 'attach' ) {
				$photos = get_option( 'appp_moderation_photos' );
				// Random key + attachment_id for approving/denying via email
				$key = wp_generate_password( 20, 0, 0 ) . $attachment_id;
				if ( is_array( $photos ) ) {
					$photos = array_merge( $photos, array( $key => array('attachment_id'=>$attachment_id,'parent_id'=>absint( $post_id ) ) ) );
				}else {
					$photos = array( $key => array('attachment_id'=>$attachment_id,'parent_id'=>absint( $post_id ) ) );
				}
				update_option( 'appp_moderation_photos', $photos );
			}

			// Set post content or featured image
			if ( $appp_action == 'new' && $post_id ) {

				if ( appp_get_setting( 'upload_to_feat_img' ) ) {

					set_post_thumbnail( absint( $post_id ), absint( $attachment_id ) );

				} else {

					$updatepost['post_content'] = (string) wp_get_attachment_image(
						absint( $attachment_id ),
						'photo-blog',
						false,
						array( 'class' => 'app-attachment app-photo-blog' )
					);
				}

				wp_update_post( apply_filters( 'appp_insert_photo_post', $updatepost ) );

			}

			// Email new photos to admin
			if ( appp_get_setting( 'photo_upload_email' ) ) {
				$message = wp_get_attachment_image( $attachment_id, 'thumbnail' );
				// Moderation Approve/Deny
				if ( appp_get_setting( 'photo_upload_moderation' ) ) {
					$message .= '<p><a href="' . site_url() . '?appp_approve='.$key.'">'.__('Approve',  'apppresser-camera').'</a> | ';
					$message .= '<a href="' . site_url() . '?appp_deny='.$key.'">'.__('Deny',  'apppresser-camera').'</a></p>';
				}
				$to = apply_filters( 'appp_upload_email_to', get_settings( 'admin_email' ) );
				$subject = apply_filters( 'appp_upload_email_subject', __( 'A new photo was uploaded.', 'apppresser-camera' ) );
				$message = apply_filters( 'appp_upload_email_message', $message );
				add_filter( 'wp_mail_content_type', array( 'AppPresser_Camera_Upload', 'set_html_content_type' ) );
				wp_mail( $to, $subject, $message );
				remove_filter( 'wp_mail_content_type', array( 'AppPresser_Camera_Upload', 'set_html_content_type' ) );
			}

			// Hook for other stuff
			do_action( 'appp_after_process_uploads', $post_id, $attachment_id );
			@define( 'APPP_IMPORTING', false );

			// we're done here, let the app know all is well
			wp_send_json_success( array( 
				'post_id' => $post_id,
				'attachment_id' => $attachment_id,
				'attachment_url' => wp_get_attachment_url( (int)$attachment_id )
			) );
			
		}

	}

	public function set_html_content_type() {
		return 'text/html';
	}

}
