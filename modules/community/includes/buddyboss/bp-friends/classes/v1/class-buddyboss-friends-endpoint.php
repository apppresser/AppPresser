<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class BuddyBoss_Friends_Endpoint_V1 extends WP_REST_Controller {


	public function __construct() {
		$this->namespace = 'ap-bb/v1';
		$this->rest_base = 'friends';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
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
					'permission_callback' => array( $this, 'permissions_check' ),
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
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

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

	public function accept_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

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

	public function permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'appcommunity_rest_authorization_required',
				__( 'Sorry, you are not allowed to list friends items.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = $this->get_user( $request['user_id'] );
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

	function get_user( $user_id ) {
		if ( (int) $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( (int) $user_id );
		if ( empty( $user ) || ! $user->exists() ) {
			return false;
		}

		return $user;
	}
}
