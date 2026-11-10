<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * BP REST: AppCommunity_Notifications_Endpoint_V2 class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifications endpoints.
 *
 * @since 0.1.0
 */
class AppCommunity_Notifications_Endpoint_V2 extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v2';
		$this->rest_base = buddypress()->notifications->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_read_by_component' ),
					'permission_callback' => array( $this, 'mark_read_permissions_check' ),
				),
			)
		);

		add_filter( 'bp_rest_notifications_prepare_value', array( $this, 'filter_notifications_response' ), 10, 3 );
	}

	/**
	 * Mark all items in a given component as read.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function mark_read_by_component( $request ) {
		if ( empty( $request['component'] ) || empty( $request['action'] ) ) {
			return new WP_Error(
				'appcommunity_rest_missing_field',
				__( 'Missing required field.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval   = bp_notifications_mark_notifications_by_type( $request['user_id'], $request['component'], $request['action'] );
		$response = rest_ensure_response( $retval );

		return $response;
	}

	/**
	 * Check if a given request has access to mark a notification as read.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function mark_read_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'appcommunity_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create a notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = appcommunity_get_user( $request['user_id'] );
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'appcommunity_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		return true;
	}

	/**
	 * Filter a notification value returned from the API.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Response              $response    The response data.
	 * @param WP_REST_Request               $request     Request used to generate the response.
	 * @param BP_Notifications_Notification $notification Notification object.
	 */
	public function filter_notifications_response( $response, $request, $notification ) {
		// Bail early.
		if ( empty( $response->data ) ) {
			return $response;
		}

		$bp               = buddypress();
		$component_name   = $notification->component_name;
		$component_action = $notification->component_action;

		// Retrieve the content of the notification using the callback.
		$content = call_user_func(
			$bp->{$component_name}->notification_callback,
			$notification->component_action,
			$notification->item_id,
			$notification->secondary_item_id,
			$notification->is_new,
			'array',
			$notification->id
		);

		// Add the corresponding thread_id for the current message_id, only for the message component
		if ( $component_name === 'messages' ) {
			$thread_id                   = messages_get_message_thread_id( $notification->item_id );
			$response->data['thread_id'] = $thread_id;
		}

		if ( $component_action === 'group_invite' ) {
			$args         = array(
				'item_id' => $notification->item_id,
				'user_id' => $notification->user_id,
			);
			$invites_data = groups_get_invites( $args );

			$response->data['invite_id'] = $invites_data[0]->id;
		}

		if ( isset( $content['text'] ) ) {
			$response->data['content'] = $content['text'];
		}

		if ( isset( $content['link'] ) ) {
			$response->data['href'] = $content['link'];
		}

		$response->data['avatar_urls'] = array(
			'full'  => bp_core_fetch_avatar(
				array(
					'item_id' => $notification->user_id,
					'html'    => false,
					'type'    => 'full',
				)
			),
			'thumb' => bp_core_fetch_avatar(
				array(
					'item_id' => $notification->user_id,
					'html'    => false,
				)
			),
		);

		return $response;
	}
}
