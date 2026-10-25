<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class BuddyBoss_Messages_Endpoint_V1 extends BP_REST_Messages_Endpoint {


	public function __construct() {
		$this->namespace = 'ap-bb/v1';
		$this->rest_base = buddypress()->messages->id;
	}

	public function register_routes() {
		$thread_endpoint = '/' . $this->rest_base . '/(?P<id>[\d]+)';

		register_rest_route(
			$this->namespace,
			$thread_endpoint,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item_extend' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/send',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'send_message_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		add_filter( 'bp_rest_message_pre_insert_value', array( $this, 'add_image_to_message' ), 10, 2 );
		add_filter( 'bp_rest_messages_prepare_recipient_value', array( $this, 'filter_recipient_response' ), 10, 3 );
		add_filter( 'bp_rest_messages_prepare_value', array( $this, 'filter_messages_thread_response' ), 10, 3 );
		add_filter( 'bp_rest_message_prepare_value', array( $this, 'filter_message_thread_response' ), 10, 3 );
	}

	public function get_item_extend( $request ) {
		$response = $this->get_item( $request );
		$retval   = array(
			$response->data,
		);

		return $retval;
	}

	public function send_message( $request ) {
		$uploadedfile = $_FILES['message_image'];
		if ( ! empty( $uploadedfile ) ) {
			return $this->_sendMessageWithImage( $uploadedfile, $request );
		} else {
			$content = $request['content'];
			return $this->_sendMessageWithContent( $request, $content );
		}
	}

	private function _sendMessageWithImage( $uploadedfile, $request ) {
		// Make sure to include the media uploader
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Upload file
		$uploaded_file = wp_handle_upload( $uploadedfile, array( 'test_form' => false ) );
		if ( $uploaded_file ) {
			// Generate the image thumbnail
			$wp_filetype   = wp_check_filetype( basename( $uploaded_file['file'] ), null );
			$filename      = $uploaded_file['file'];
			$wp_upload_dir = wp_upload_dir();
			$post_id       = 0;

			// Add to media library
			$attachment    = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
				'post_author'    => 1,
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			);
			$attachment_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );

			// Generate image meta data
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$attach_data = wp_generate_attachment_metadata( absint( $attachment_id ), $uploaded_file['file'] );

			// Update the attachment metadata
			wp_update_attachment_metadata( absint( $attachment_id ), $attach_data );

			$content = '<img src="' . $uploaded_file['url'] . '">';

			// Send message with image tag as content
			return $this->_sendMessageWithContent( $request, $content );
		}

		return new WP_Error(
			'rest_send_message_image_not_uploaded',
			__( 'There was a problem on your site.', 'appcommunity' ),
			array(
				'status' => 500,
			)
		);
	}

	private function _sendMessageWithContent( $request, $content ) {
		if ( ! isset( $request['recipients'] ) ) {
			return new WP_Error(
				'message_receipients_does_not_exists',
				__( 'Missing required fields (recipients).', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! messages_is_valid_thread( $request['thread_id'] ) ) {
			return new WP_Error(
				'message_thread_id_does_not_exists',
				__( 'Thread id does not exists.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		$args = array(
			'sender_id'  => $request['user_id'],
			'recipients' => array( $request['recipients'] ),
			'subject'    => stripslashes( $request['subject'] ),
			'content'    => stripslashes( $content ),
			'thread_id'  => $request['thread_id'],
		);

		$retval = messages_new_message( $args );
		if ( is_wp_error( $retval ) || ! $retval ) {
			return $retval;
		}

		return rest_ensure_response( $retval );
	}

	public function send_message_permissions_check( $request ) {
		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to send this message.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Add image to message if available.
	 *
	 * @since 0.3.0
	 *
	 * @param stdClass        $prepared_thread  Message object.
	 * @param WP_REST_Request $request          Full details about the request.
	 * @return stdClass
	 */
	public function add_image_to_message( $prepared_thread, $request ) {

		// Bail early for already messages/thread.
		if ( ! empty( $prepared_thread->last_message_content ) ) {
			return $prepared_thread;
		}

		// Get the file via $_FILES.
		$fileParams = $request->get_file_params();
		$file       = $fileParams['message_image'];

		if ( empty( $file ) ) {
			return $prepared_thread;
		}

		// Make sure to include the media uploader.
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$uploaded_file = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $uploaded_file['error'] ) ) {
			return $prepared_thread;
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

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attach_data = wp_generate_attachment_metadata( absint( $attachment_id ), $uploaded_file['file'] );

		// Update the attachment metadata.
		wp_update_attachment_metadata( absint( $attachment_id ), $attach_data );

		$prepared_thread->content = ' <img src="' . $uploaded_file['url'] . '">';

		return $prepared_thread;
	}

	/**
	 * Filter a recipient value returned from the API.
	 *
	 * @since 0.1.0
	 *
	 * @param array           $data      The recipient value for the REST response.
	 * @param object          $recipient The recipient object.
	 * @param WP_REST_Request $request   Request used to generate the response.
	 */
	public function filter_recipient_response( $data, $recipient, $request ) {
		$display_name = bp_core_get_user_displayname( $recipient->user_id );

		if ( $display_name ) {
			$data['user_display_name'] = $display_name;
		}

		return $data;
	}

	/**
	 * Filter a thread value returned from the API.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Response   $response Response generated by the request.
	 * @param WP_REST_Request    $request  Request used to generate the response.
	 * @param BP_Messages_Thread $thread   The thread object.
	 */
	public function filter_messages_thread_response( $response, $request, $thread ) {
		$user_id = $response->data['last_sender_id'];

		$display_name = bp_core_get_user_displayname( $user_id );
		if ( $display_name ) {
			$response->data['display_name'] = $display_name;
		}

		$response->data['avatar_urls'] = array(
			'full'  => bp_core_fetch_avatar(
				array(
					'item_id' => $user_id,
					'html'    => false,
					'type'    => 'full',
				)
			),
			'thumb' => bp_core_fetch_avatar(
				array(
					'item_id' => $user_id,
					'html'    => false,
				)
			),
		);

		return $response;
	}

	/**
	 * Filter a message value returned from the API.
	 *
	 * @since 0.1.0
	 *
	 * @param array               $data    The message value for the REST response.
	 * @param BP_Messages_Message $message The Message object.
	 * @param WP_REST_Request     $request Request used to generate the response.
	 */
	public function filter_message_thread_response( $data, $message, $request ) {
		$data['avatar_urls'] = array(
			'full'  => bp_core_fetch_avatar(
				array(
					'item_id' => $message->sender_id,
					'html'    => false,
					'type'    => 'full',
				)
			),
			'thumb' => bp_core_fetch_avatar(
				array(
					'item_id' => $message->sender_id,
					'html'    => false,
				)
			),
		);

		return $data;
	}
}
