<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * AppCommunity_Activity_Endpoint_V2 class
 *
 * @since 0.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity endpoints.
 *
 * @since 0.3.0
 */
class AppCommunity_Activity_Endpoint_V2 extends WP_REST_Users_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v2';
		$this->rest_base = buddypress()->activity->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.3.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		add_filter( 'bp_rest_activity_pre_insert_value', array( $this, 'add_image_to_activity' ), 10, 2 );
		add_filter( 'bp_rest_activity_create_item_permissions_check', array( $this, 'create_item_permissions_check_override' ), 10, 2 );
		add_filter( 'bp_rest_activity_prepare_value', array( $this, 'filter_activity_response' ), 10, 3 );
	}

	/**
	 * Flag an activity item.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		$user_id = $request['user_id'];

		if ( ( 'activity_favorite' === $request['action'] ) && ! is_null( $user_id ) ) {
			$retval = bp_activity_add_user_favorite( $request['id'], $user_id );
		} elseif ( ( 'activity_remove_favorite' === $request['action'] ) && ! is_null( $user_id ) ) {
			$retval = bp_activity_remove_user_favorite( $request['id'], $user_id );
		} elseif ( ( 'activity_flag' === $request['action'] ) ) {
			$retval = bp_activity_update_meta( $request['id'], 'is_flagged', true );
		} else {
			return new WP_Error(
				'appcommunity_rest_missing_field',
				__( 'There are missing fields.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$response = rest_ensure_response( $retval );

		return $response;
	}

	/**
	 * Check if a given request has access to flag an activity.
	 *
	 * @since 0.3.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'appcommunity_rest_authorization_required',
				__( 'Sorry, you are not allowed to create activities.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$activity = bp_activity_get_specific(
			array(
				'activity_ids'     => array( (int) $request['id'] ),
				'display_comments' => true,
			)
		);

		if ( is_array( $activity ) && ! empty( $activity['activities'][0] ) ) {
			return true;
		}

		return new WP_Error(
			'appcommunity_rest_activity_invalid_id',
			__( 'Invalid activity id.', 'appcommunity' ),
			array(
				'status' => 404,
			)
		);
	}

	/**
	 * Add image to activity if available.
	 *
	 * @since 0.3.0
	 *
	 * @param stdClass        $prepared_activity  Activity object.
	 * @param WP_REST_Request $request            Full details about the request.
	 * @return stdClass
	 */
	public function add_image_to_activity( $prepared_activity, $request ) {

		// Get the file via $_FILES.
		$files = $request->get_file_params();

		if ( empty( $files ) || empty( $files['activity_image'] ) && empty( $files['activity_video'] ) ) {
			return $prepared_activity;
		}

		// Make sure to include the media uploader.
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( isset( $uploaded_file['error'] ) ) {
			return $prepared_activity;
		}

		if ( isset( $files['activity_video'] ) ) {
			$uploaded_file = wp_handle_upload( $files['activity_video'], array( 'test_form' => false ) );
		} else {
			$uploaded_file = wp_handle_upload( $files['activity_image'], array( 'test_form' => false ) );
		}

		// Generate the image thumbnail.
		$wp_filetype   = wp_check_filetype( basename( $uploaded_file['file'] ), null );
		$filename      = $uploaded_file['file'];
		$wp_upload_dir = wp_upload_dir();

		// Add to media library.
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => '',
			'post_author'    => 1,
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
		);

		$attachment_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );

		if ( isset( $files['activity_video'] ) ) {
			$prepared_activity->content .= '<video width="320" height="240" controls><source src="' . $uploaded_file['url'] . '" type="video/mp4">Your browser does not support the video tag.</video>';
			return $prepared_activity;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attach_data = wp_generate_attachment_metadata( absint( $attachment_id ), $uploaded_file['file'] );

		// Update the attachment metadata.
		wp_update_attachment_metadata( absint( $attachment_id ), $attach_data );

		$prepared_activity->content .= ' <img src="' . $uploaded_file['url'] . '">';

		return $prepared_activity;
	}

	/**
	 * Checks if a given request has access to create an activity.
	 *
	 * @since 0.3.0
	 *
	 * @param bool            $retval  Returned value.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error
	 */
	public function create_item_permissions_check_override( $retval, $request ) {

		// Bail early.
		if ( is_wp_error( $retval ) ) {
			return $retval;
		}

		// Get the file via $_FILES.
		$files = $request->get_file_params();

		if ( empty( $request['content'] ) && empty( $files ) ) {
			return new WP_Error(
				'appcommunity_rest_create_activity_empty_content',
				__( 'Please, enter some content.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( ! empty( $files ) ) {
			$retval = $this->check_upload_size( $files['activity_image'] );
			if ( is_wp_error( $retval ) ) {
				return $retval;
			}
		}

		return true;
	}

	/**
	 * Check upload size.
	 *
	 * @since 0.3.0
	 *
	 * @param string $file File uploaded.
	 * @return boolean|WP_Error
	 */
	protected function check_upload_size( $file ) {

		// check for upload size only if website is a WordPress multisite
		if ( function_exists( 'get_upload_space_available' ) ) {

			$space_left = get_upload_space_available();
			$file_size  = filesize( $file );

			if ( $space_left < $file_size ) {
				return new WP_Error(
					'appcommunity_rest_create_activity_no_space',
					__( 'Not enough space to upload.', 'appcommunity' ),
					array(
						'status' => 500,
					)
				);
			}

			if ( $file_size > ( KB_IN_BYTES * get_site_option( 'fileupload_maxk', 1500 ) ) ) {
				return new WP_Error(
					'appcommunity_rest_create_activity_image_too_big',
					sprintf(
						__( 'This file is too big. Files must be less than %s KB in size.', 'appcommunity' ),
						get_site_option( 'fileupload_maxk', 1500 )
					),
					array(
						'status' => 500,
					)
				);
			}

			// Include admin function to get access to upload_is_user_over_quota().
			require_once ABSPATH . 'wp-admin/includes/ms.php';

			if ( upload_is_user_over_quota( false ) ) {
				return new WP_Error(
					'appcommunity_rest_create_activity_image_too_big',
					__( 'You have used your space quota. Please delete files before uploading.', 'appcommunity' ),
					array(
						'status' => 500,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Filter BuddyPress activity response.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Response     $response The Response data.
	 * @param WP_REST_Request      $request  Request used to generate the response.
	 * @param BP_Activity_Activity $activity The activity object.
	 * @return WP_REST_Response
	 */
	public function filter_activity_response( $response, $request, $activity ) {

		// Bail early.
		if ( empty( $response->data ) ) {
			return $response;
		}

		if ( isset( $activity->user_id ) ) {
			$response->data['display_name'] = bp_core_get_user_displayname( $activity->user_id );
		}

		if ( isset( $activity->id ) ) {
			$response->data['favorites'] = bp_activity_get_meta( $activity->id, 'favorite_count' );
		}

		return $response;
	}
}
