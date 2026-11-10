<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * BP REST: AppCommunity_Friends_Endpoint_V2 class
 *
 * @since 0.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Friends endpoints.
 *
 * @since 0.3.0
 */
class AppCommunity_Friends_Endpoint_V2 extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v2';
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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_friendship_requests' ),
					'permission_callback' => array( $this, 'get_friendship_requests_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/requests/accept/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'accept_item' ),
					'permission_callback' => array( $this, 'accept_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/requests/reject/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject_item' ),
					'permission_callback' => array( $this, 'reject_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/requests/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_friendship_request' ),
					'permission_callback' => array( $this, 'delete_friendship_request_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get friends(hips).
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		if ( ! class_exists( 'BP_REST_Members_Endpoint' ) ) {
			return new WP_Error(
				'appcommunity_rest_get_friends',
				__( 'Buddypres API is not available', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		$members_endpoint = new BP_REST_Members_Endpoint();
		return $members_endpoint->get_items( $request );
	}

	/**
	 * Check if a given request has access to access friends(hips).
	 *
	 * @since 0.3.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'appcommunity_rest_authorization_required',
				__( 'Sorry, you are not allowed to list friends items.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = appcommunity_get_user( $request['user_id'] );
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'appcommunity_rest_member_invalid_id',
				__( 'Invalid member id.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		return true;
	}

	/**
	 * Add a friendship.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$loggedin = bp_loggedin_user_id();

		if ( friends_check_friendship( $loggedin, $request['user_id'] ) ) {
			return new WP_Error(
				'appcommunity_rest_already_friends',
				__( 'Sorry, you are already friends.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( ! friends_add_friend( $loggedin, $request['user_id'] ) ) {
			return new WP_Error(
				'appcommunity_rest_add_friendship_failed',
				__( 'There was a problem adding the friendship.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$friendship = new BP_Friends_Friendship( $request['user_id'] );
		$response   = rest_ensure_response( $friendship );

		return $response;
	}

	/**
	 * Check if a given request has access to add a friend.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Remove a friendship.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		$loggedin = bp_loggedin_user_id();

		if ( ! friends_check_friendship( $loggedin, $request['user_id'] ) ) {
			return new WP_Error(
				'appcommunity_rest_not_friends',
				__( 'Sorry, you are not friends.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( ! friends_remove_friend( $loggedin, $request['user_id'] ) ) {
			return new WP_Error(
				'appcommunity_rest_remove_friendship_failed',
				__( 'There was a problem removing the friendship.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$friendship = new BP_Friends_Friendship( $request['user_id'] );
		$response   = rest_ensure_response( $friendship );

		return $response;
	}

	/**
	 * Check if a given request has access to remove a friendship.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Accept friendship request.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function accept_item( $request ) {
		$user_id       = $request['user_id'];
		$friendship_id = BP_Friends_Friendship::get_friendship_id( bp_loggedin_user_id(), $user_id );

		if ( ! friends_accept_friendship( $friendship_id ) ) {
			return new WP_Error(
				'appcommunity_rest_accept_friendship_failed',
				__( 'There was a problem accepting the friendship.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$friendship = new BP_Friends_Friendship( $user_id );
		$response   = rest_ensure_response( $friendship );

		return $response;
	}

	/**
	 * Check if a given request has access to accept friendship request.
	 *
	 * @since 0.3.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function accept_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Reject friendship request.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function reject_item( $request ) {
		$user_id       = $request['user_id'];
		$friendship_id = BP_Friends_Friendship::get_friendship_id( bp_loggedin_user_id(), $user_id );

		if ( ! friends_reject_friendship( $friendship_id ) ) {
			return new WP_Error(
				'appcommunity_rest_add_friendship_failed',
				__( 'There was a problem rejecting the friendship.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$friendship = new BP_Friends_Friendship( $user_id );
		$response   = rest_ensure_response( $friendship );

		return $response;
	}

	/**
	 * Check if a given request has access to reject friendship request.
	 *
	 * @since 0.3.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function reject_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Get pending friendship requests.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_friendship_requests( $request ) {
		$requests = friends_get_friendship_request_user_ids( $request['user_id'] );

		$retval = array();
		if ( ! empty( $requests ) ) {
			foreach ( $requests as $user_id ) {
				$avatar = bp_core_fetch_avatar(
					array(
						'item_id' => $user_id,
						'type'    => 'thumb',
						'width'   => 32,
						'height'  => 32,
						'class'   => 'friend-avatar',
						'html'    => false,
					)
				);

				$retval[] = array(
					'avatar'  => $avatar,
					'user_id' => $user_id,
					'name'    => bp_activity_get_user_mentionname( $user_id ),
				);
			}
		}

		$response = rest_ensure_response( $retval );

		return $response;
	}

	/**
	 * Check if a given request has access to get friendship requests.
	 *
	 * @since 0.3.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_friendship_requests_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Withdraw a friendship request.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function delete_friendship_request( $request ) {
		$user_id = $request['user_id'];

		if ( ! friends_withdraw_friendship( bp_loggedin_user_id(), $user_id ) ) {
			return new WP_Error(
				'appcommunity_rest_add_friendship_failed',
				__( 'There was a problem withdrawing the friendship request.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$friendship = new BP_Friends_Friendship( $user_id );
		$response   = rest_ensure_response( $friendship );

		return $response;
	}

	/**
	 * Check if a given request has access to withdraw friendship request.
	 *
	 * @since 0.3.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_friendship_request_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}
}
