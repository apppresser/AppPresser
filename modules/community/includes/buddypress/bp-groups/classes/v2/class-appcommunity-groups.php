<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * AppCommunity_Groups class
 *
 * @since 0.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Groups Class.
 *
 * @since 0.3.0
 */
class AppCommunity_Groups {

	/**
	 * Init class.
	 *
	 * @since 0.3.0
	 */
	public function init() {
		add_filter( 'bp_rest_groups_prepare_value', array( $this, 'filter_group_response' ), 10, 2 );
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
