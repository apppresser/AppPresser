<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class BuddyBoss_Member_Avatar_Endpoint_V1 extends BP_REST_Attachments_Member_Avatar_Endpoint {


	public function __construct() {
		$this->namespace       = 'ap-bb/v1';
		$this->rest_base       = 'members';
		$this->avatar_instance = new BP_Attachment_Avatar();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)/avatar',
			array(
				'args'   => array(
					'user_id' => array(
						'description' => __( 'A unique numeric ID for the Member.', 'buddyboss' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item_extend' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	public function create_item_extend( $request ) {
		$response = $this->create_item( $request );
		$retval   = array(
			$response->data,
		);

		return $retval;
	}
}
