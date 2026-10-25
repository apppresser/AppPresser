<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * BP REST: AppCommunity_Messages_Endpoint_V1 class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Messages endpoints.
 *
 * @since 0.1.0
 */
class AppCommunity_Messages_Endpoint_V1 extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v1';
		$this->rest_base = buddypress()->messages->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					// 'args'                => $this->get_collection_params(),
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
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Retrieve threads.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request Thread object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'user_id'      => $request['user_id'],
			'box'          => $request['box'],
			'type'         => $request['type'],
			'page'         => $request['page'],
			'per_page'     => $request['per_page'],
			'search_terms' => $request['search'],
		);

		$args = apply_filters( 'appcommunity_messages_get_items_args', $args, $request );

		$messages_box = new BP_Messages_Box_Template( $args );

		$retval = array();

		if ( $messages_box->threads ) {
			foreach ( $messages_box->threads as $thread ) {
				$retval[] = $this->prepare_response_for_collection(
					$this->prepare_item_for_response( $thread, $request )
				);
			}
		}

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after a thread is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $messages_box Fetched thread.
		 * @param WP_REST_Response $retval       The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'appcommunity_messages_get_items', $messages_box, $retval, $request );

		return $retval;
	}

	/**
	 * Get a single thread
	 */
	public function get_item( $request ) {

		$messages = new BP_Messages_Thread( $request['id'] );

		$retval = $this->prepare_thread( $messages, $request );

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after a thread is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $messages_box Fetched thread.
		 * @param WP_REST_Response $retval       The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'appcommunity_messages_get_item', $retval, $request );

		return $retval;
	}

	/**
	 * Send message using the arguments from request (with or without image)
	 */
	public function send_message( $request ) {
		$uploadedfile = $_FILES['message_image'];
		if ( ! empty( $uploadedfile ) ) {
			return $this->_sendMessageWithImage( $uploadedfile, $request );
		} else {
			$content = $request['content'];
			return $this->_sendMessageWithContent( $request, $content );
		}
	}

	/**
	 * Send a message with image
	 */
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

	/**
	 * Send a message with content (or image tag as content)
	 */
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

	/**
	 * Check if a given request has access to thread items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		$AP_JWT = new AP_JWT();

		$validation = $AP_JWT->validate_token( $request );

		// Bail early.
		if ( $validation !== true ) {

			if ( ! empty( $validation['error'] ) ) {
				$msg = $validation['error'];
			}
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to do that. ' . $msg, 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see( $request ) ) {
			return new WP_Error(
				'rest_user_cannot_view_messages',
				__( 'Sorry, you cannot view the messages.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Prepares thread data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $thread Thread data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $thread, $request ) {
		$data = array(
			'id'                    => $thread->thread_id,
			'prime_association'     => $thread->last_message_id,
			'secondary_association' => $thread->last_sender_id,
			'subject'               => wp_staticize_emoji( stripslashes( $thread->last_message_subject ) ),
			'message'               => wp_staticize_emoji( stripslashes( $thread->last_message_content ) ),
			'date'                  => $this->format_date( $thread->last_message_date ),
			'unread'                => ! empty( $thread->unread_count ) ? $thread->unread_count : 0,
			'sender_ids'            => $thread->sender_ids,
			'messages'              => $thread->messages,
		);

		$sender_id = $this->get_sender_id( $thread, $request );

		if ( is_int( $sender_id ) ) {
			$user                = get_userdata( $sender_id );
			$data['sender_data'] = array(
				'name'   => $user->display_name,
				'avatar' => get_avatar_url( $user->user_email ),
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $thread ) );

		/**
		 * Filter a thread value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'appcommunity_prepare_buddypress_message_value', $response, $request );
	}

	/*
	 * Get ID of sender in thread that is not me (for showing thread avatar)
	 */
	public function get_sender_id( $thread, $request ) {

		if ( is_array( $thread->sender_ids ) && $request['user_id'] ) {

			foreach ( $thread->sender_ids as $key => $sender_id ) {
				if ( $sender_id != intval( $request['user_id'] ) ) {
					// find a sender that's not me to show avatar
					return $sender_id;
				}
			}
		}

		return intval( $request['user_id'] );
	}

	/*
	 * Mostly adds sender information
	 */
	public function prepare_thread( $thread, $request ) {

		foreach ( $thread->messages as $key => $message ) {

			$user                 = get_userdata( $message->sender_id );
			$message->sender_data = array(
				'name'   => $user->display_name,
				'avatar' => get_avatar_url( $user->user_email ),
			);

			$message->subject = stripslashes( $message->subject );
			$message->message = stripslashes( $message->message );
		}

		$thread->messages = array_reverse( $thread->messages );

		return $thread;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array $thread Thread.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $thread ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $thread->thread_id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href' => rest_url( '/wp/v2/users/' . $thread->last_sender_id ),
			),
		);

		return $links;
	}

	/**
	 * Can this user see the message?
	 *
	 * @since 0.1.0
	 *
	 * @param int $thread_id Thread ID.
	 * @return boolean
	 */
	protected function can_see( $request, $thread_id = 0 ) {
		$user_id = $request['user_id'];
		$retval  = true;

		// Check thread access.
		if ( ! empty( $thread_id ) && ! messages_check_thread_access( $thread_id, $user_id ) ) {
			$retval = false;
		}

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool   $retval Return value.
		 * @param int    $user_id User ID.
		 */
		return apply_filters( 'appcommunity_message_endpoint_can_see', $retval, $user_id );
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'thread',
			'type'       => 'object',

			'properties' => array(
				'id'                    => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'appcommunity' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'prime_association'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object primarily associated with this one.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'subject'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML title of the object.', 'appcommunity' ),
					'type'        => 'string',
				),

				'message'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML content of the object.', 'appcommunity' ),
					'type'        => 'string',
				),

				'date'                  => array(
					'description' => __( "The date the object was published, in the site's timezone.", 'appcommunity' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),

				'secondary_association' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'unread'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'messages'              => array(
					'description' => __( 'Childrens of the object.', 'appcommunity' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for collections of plugins.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['box'] = array(
			'description'       => __( 'Filter the result by box.', 'appcommunity' ),
			'type'              => 'string',
			'default'           => 'sentbox',
			'enum'              => array( 'notices', 'sentbox', 'inbox' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Filter the result by thread status.', 'appcommunity' ),
			'type'              => 'string',
			'default'           => 'all',
			'enum'              => array( 'all', 'read', 'unread' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['page'] = array(
			'description'       => __( 'Offset the result set by a specific number of pages of results.', 'appcommunity' ),
			'default'           => 1,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['per_page'] = array(
			'description'       => __( 'Maximum number of results returned per result set.', 'appcommunity' ),
			'default'           => 20,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['search'] = array(
			'description'       => __( 'Limit result set to items that match this search query.', 'appcommunity' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit result to messages created by specific users.', 'appcommunity' ),
			'type'              => 'integer',
			'default'           => ( isset( $request['user_id'] ) ) ? $request['user_id'] : 0,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}

	public function format_date( $dateStr ) {

		return date_i18n( get_option( 'date_format' ), strtotime( $dateStr ) );
	}
}
