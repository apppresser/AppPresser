<?php
/**
 * Support legacy app quiz tokens without accepting them as nonces for unrelated actions.
 * The normal verifier follows WordPress wp-includes/pluggable.php, GPLv2 or later.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_verify_nonce' ) && ( isset( $_REQUEST['app_token'] ) || isset( $_REQUEST['token'] ) ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		$nonce = (string) $nonce;
		$user = wp_get_current_user();
		$uid = (int) $user->ID;
		if ( ! $uid ) {
			$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
		}
		if ( '' === $nonce ) {
			return false;
		}
		$session = wp_get_session_token();
		$tick = wp_nonce_tick( $action );
		foreach ( array( $tick => 1, $tick - 1 => 2 ) as $nonce_tick => $result ) {
			$expected = substr( wp_hash( $nonce_tick . '|' . $action . '|' . $uid . '|' . $session, 'nonce' ), -12, 10 );
			if ( hash_equals( $expected, $nonce ) ) {
				return $result;
			}
		}
		$is_app = ( isset( $_GET['appp'] ) && '3' === (string) $_GET['appp'] ) || ( isset( $_COOKIE['AppPresser_Appp3'] ) && 'true' === $_COOKIE['AppPresser_Appp3'] );
		// LearnDash binds this action to the quiz IDs and the current user.
		if ( $is_app && $uid > 0 && is_string( $action ) && preg_match( '/^sfwd-quiz-nonce-\d+-\d+-' . $uid . '$/D', $action ) ) {
			$token = isset( $_REQUEST['app_token'] ) ? $_REQUEST['app_token'] : $_REQUEST['token'];
			$key = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : appp_get_setting( 'ap3_jwt_secret_key' );
			$jwt_class = class_exists( 'Tmeister\\Firebase\\JWT\\JWT' ) ? 'Tmeister\\Firebase\\JWT\\JWT' : 'Firebase\\JWT\\JWT';
			$key_class = class_exists( 'Tmeister\\Firebase\\JWT\\Key' ) ? 'Tmeister\\Firebase\\JWT\\Key' : 'Firebase\\JWT\\Key';
			if ( is_string( $token ) && $key && class_exists( $jwt_class ) && class_exists( $key_class ) ) {
				try {
					$decoded = $jwt_class::decode( $token, new $key_class( $key, apply_filters( 'jwt_auth_algorithm', 'HS256' ) ) );
					if ( isset( $decoded->iss, $decoded->data->user->id, $decoded->exp ) && $decoded->iss === get_bloginfo( 'url' ) && (int) $decoded->data->user->id === $uid && (int) $decoded->exp > time() ) {
						return 1;
					}
				} catch ( Throwable $error ) {
					// Invalid tokens follow the ordinary nonce-failure path.
				}
			}
		}
		do_action( 'wp_verify_nonce_failed', $nonce, $action, $user, $session );
		return false;
	}
}
