<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * BP REST: AppCommunity_Friends_Endpoint_V1 class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Members endpoints.
 *
 * @since 0.1.0
 */
class AppCommunity_Friends_Endpoint_V1 extends WP_REST_Users_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v1';
		$this->rest_base = 'friends';
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_friends' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_requests' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/friend/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_remove_friend' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/requests/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'accept_withdraw_friendship' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get friends list
	 *
	 * @since 0.1.0
	 */
	public function get_friends( $request ) {

		if ( empty( $request['user'] ) ) {
			return new WP_Error(
				'bp_rest_invalid_request',
				__( 'Missing required fields.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		// duplicate of members endpoint get
		$members_endpoint = new AppCommunity_Members_Endpoint_V1();

		$friends = $members_endpoint->get_items( $request );

		$retval = rest_ensure_response( $friends );

		do_action( 'appcommunity_friends_get_items', $retval, $request );

		return $retval;
	}

	/**
	 * Get pending friendship requests
	 *
	 * @since 0.1.0
	 */
	public function get_requests( $request ) {

		$user_id = intval( $request['user_id'] );

		$requests = friends_get_friendship_request_user_ids( $user_id );

		$retval = array();

		if ( ! empty( $requests ) ) :

			foreach ( $requests as $id ) {

				$retval[] = array(
					'avatar' => bp_core_fetch_avatar(
						array(
							'item_id' => $id,
							'type'    => 'thumb',
							'width'   => 32,
							'height'  => 32,
							'class'   => 'friend-avatar',
							'html'    => false,
						)
					),
					'id'     => $id,
					'name'   => bp_activity_get_user_mentionname( $id ),
				);
			}

		endif;

		do_action( 'appcommunity_friends_get_requests', $retval, $request );

		return $retval;
	}

	/**
	 * Add a friend
	 *
	 * @since 0.1.0
	 */
	public function add_remove_friend( $request ) {

		if ( empty( $request['id'] ) || empty( $request['user_id'] ) ) {

			return new WP_Error(
				'bp_rest_invalid_request',
				__( 'Missing required fields.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);

		}

		$initiator_userid = intval( $request['user_id'] );
		$friend_userid    = intval( $request['id'] );

		if ( $request['action'] === 'add_friend' ) {

			if ( friends_check_friendship( $initiator_userid, $friend_userid ) ) {
				return __( 'You are already friends.', 'appcommunity' );
			}

			$friend_status = friends_check_friendship_status( $initiator_userid, $friend_userid );

			if ( $friend_status != 'not_friends' ) {
				return __( 'Friendship status is: ', 'appcommunity' ) . $friend_status;
			}

			$retval = friends_add_friend( $initiator_userid, $friend_userid );
			$msg    = __( 'Friendship request sent.', 'appcommunity' );

		} elseif ( $request['action'] === 'remove_friend' ) {

			$retval = friends_remove_friend( $initiator_userid, $friend_userid );
			$msg    = __( 'Friendship removed.', 'appcommunity' );

		} else {
			return new WP_Error(
				'bp_rest_empty_friend_action',
				__( 'Please, enter correct friend action.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( $retval ) {
			return $msg;
		} else {
			return false;
		}
	}

	/**
	 * Accept or withdraw a friendship request
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of groups object data.
	 */
	public function accept_withdraw_friendship( $request ) {

		if ( empty( $request['id'] ) || empty( $request['user_id'] ) ) {

			return new WP_Error(
				'bp_rest_invalid_request',
				__( 'Missing required fields.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);

		}

		$initiator_userid = intval( $request['user_id'] );
		$friend_userid    = intval( $request['id'] );
		$friendship_id    = BP_Friends_Friendship::get_friendship_id( $initiator_userid, $friend_userid );
		global $wpdb;
		$bp = buddypress();

		if ( $request['action'] === 'accept' ) {
			$friend_status = friends_check_friendship_status( $initiator_userid, $friend_userid );
			if ( $friend_status != 'awaiting_response' || ! $friendship_id ) {
				return sprintf( __( "Can't accept friendship. Friendship status is: %s", 'appcommunity' ), $friend_status );
			}
			// copied from buddypress/bp-friends/classes/class-bp-friends-friendship.php
			$retval = $wpdb->query( $wpdb->prepare( "UPDATE {$bp->friends->table_name} SET is_confirmed = 1, date_created = %s WHERE id = %d AND friend_user_id = %d", bp_core_current_time(), $friendship_id, $initiator_userid ) );
			$msg    = 'Friendship accepted.';
		} elseif ( $request['action'] === 'withdraw' ) {
			// copied from buddypress/bp-friends/classes/class-bp-friends-friendship.php
			$retval = $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->friends->table_name} WHERE id = %d AND friend_user_id = %d", $friendship_id, $initiator_userid ) );
			$msg    = 'Friendship request withdrawn.';
		} else {
			return new WP_Error(
				'bp_rest_empty_friend_action',
				__( 'Please, enter correct friend action.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( $retval ) {
			return $msg;
		} else {
			return false;
		}
	}

	/**
	 * Check if a given request has access to get information about a specific item.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {

		$AP_JWT = new AP_JWT();

		$validation = $AP_JWT->validate_token( $request );

		// Bail early.
		if ( $validation !== true ) {

			if ( ! empty( $validation['error'] ) ) {
				$msg = $validation['error'];
			}
			return new WP_Error(
				'rest_authorization_required',
				__( 'API authorization failed. ' . $msg, 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}
}
