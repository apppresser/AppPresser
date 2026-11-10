<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class BuddyBoss_XProfile_Data_Endpoint_V1 extends BP_REST_XProfile_Data_Endpoint {


	public function __construct() {
		$this->namespace       = 'ap-bb/v1';
		$this->rest_base       = buddypress()->profile->id;
		$this->fields_endpoint = new BP_REST_XProfile_Fields_Endpoint();
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<field_id>[\d]+)/data/(?P<user_id>[\d]+)',
			array(
				'args'   => array(
					'field_id' => array(
						'description' => __( 'The ID of the field the data is from.', 'buddyboss' ),
						'type'        => 'integer',
					),
					'user_id'  => array(
						'description' => __( 'The ID of user the field data is from.', 'buddyboss' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item_extend' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'value' => array(
							'description' => __( 'The list of values for the field data.', 'buddyboss' ),
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	public function update_item_extend( $request ) {
		$response = $this->update_item( $request );
		$retval   = array(
			$response->data,
		);

		return $retval;
	}
}
