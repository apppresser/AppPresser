<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class BuddyBoss_Groups_Endpoint_V1 extends BP_REST_Groups_Endpoint {


	public function __construct() {
		$this->namespace = 'ap-bb/v1';
		$this->rest_base = buddypress()->groups->id;
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'A unique numeric ID for the Group.', 'buddyboss' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item_extend' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		add_filter( 'bp_rest_groups_prepare_value', array( $this, 'filter_group_response' ), 10, 2 );
	}

	public function get_item_extend( $request ) {
		$response = $this->get_item( $request );
		$retval   = array(
			$response->data,
		);

		return $retval;
	}

	/**
	 * Filter group response.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Response $response The response data.
	 * @param WP_REST_Request  $request  Request used to generate the response.
	 * @return WP_REST_Response
	 */
	public function filter_group_response( $response, $request ) {
		// Bail early.
		if ( empty( $response->data ) ) {
			return $response;
		}

		// Add is_user_member field if user is a member of the given group
		$user = appcommunity_get_user( $request['user_id'] );
		if ( $user instanceof WP_User ) {
			$response->data['is_user_member'] = groups_is_user_member( intval( $user->ID ), $response->data['id'] );
		}

		// Add cover image of the give group, if exists
		$cover_url = bp_attachments_get_attachment(
			'url',
			array(
				'object_dir' => 'groups',
				'item_id'    => $response->data['id'],
			)
		);
		if ( $cover_url ) {
			$response->data['cover_url'] = $cover_url;
		}

		return $response;
	}
}
