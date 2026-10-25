<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Common functions.
 *
 * @since 0.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verify token.
 *
 * @param WP_REST_Request $request Full data about the request.
 * @return WP_Error|bool
 */
function appcommunity_verify_token( $request ) {
	$ap_jwt     = new AP_JWT();
	$validation = $ap_jwt->validate_token( $request );

	// Fail for the token first.
	if ( empty( $request['token'] ) ) {
		return new WP_Error(
			'rest_authorization_required',
			__( 'Missing required fields.', 'appcommunity' ),
			array(
				'status' => rest_authorization_required_code(),
			)
		);
	}

	// Token exist and it was validated.
	if ( true === $validation ) {
		return true;
	}

	if ( ! empty( $validation['error'] ) ) {
		$msg = $validation['error'];
	}

	// Validation failed.
	return new WP_Error(
		'rest_authorization_required',
		sprintf( __( 'Sorry, you are not allowed to do that. %s', 'appcommunity' ), $msg ),
		array(
			'status' => rest_authorization_required_code(),
		)
	);
}

/**
 * Get the user object, if the ID is valid.
 *
 * @since 0.1.0
 *
 * @param int $user_id Supplied user ID.
 * @return WP_User|boolean
 */
function appcommunity_get_user( $user_id ) {
	if ( (int) $user_id <= 0 ) {
		return false;
	}

	$user = get_userdata( (int) $user_id );
	if ( empty( $user ) || ! $user->exists() ) {
		return false;
	}

	return $user;
}

/**
 * Convert the input date to RFC3339 format.
 *
 * @since 0.1.0
 *
 * @param string      $date_gmt Date GMT format.
 * @param string|null $date Optional. Date object.
 * @return string|null ISO8601/RFC3339 formatted datetime.
 */
function appcommunity_prepare_date_response( $date_gmt, $date = null ) {
	if ( isset( $date ) ) {
		return mysql_to_rfc3339( $date );
	}

	if ( '0000-00-00 00:00:00' === $date_gmt ) {
		return null;
	}

	return mysql_to_rfc3339( $date_gmt );
}
