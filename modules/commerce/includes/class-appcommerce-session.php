<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class WC_Session_Handler_Extended extends WC_Session_Handler {

	public function getCustomerId() {
		return $this->_customer_id;
	}

	public function getSessionExpiration() {
		return $this->_session_expiration;
	}

	public function getSessionExpiring() {
		return $this->_session_expiring;
	}

	/** WooCommerce exposes the active ID publicly, but keeps its expiry fields protected. */
	public static function get_active_session_fields( $session ) {
		return array( $session->get_customer_id(), $session->_session_expiration, $session->_session_expiring );
	}

	/** Issue the cookie using the installed WooCommerce implementation, after proof validation. */
	public function set_checkout_cookie( $handoff ) {
		if ( ! AppCommerce_Checkout_Session::verify( $handoff ) || headers_sent() ) {
			return false;
		}

		$this->_customer_id        = $handoff['customer_id'];
		$this->_session_expiration = (int) $handoff['session_expiration'];
		$this->_session_expiring   = (int) $handoff['session_expiring'];
		AppCommerce_Checkout_Session::set_customer_cookie( $this );
		return true;
	}
}

/**
 * The native client forwards cookie_hash unchanged. It is a checkout bearer proof,
 * not a WooCommerce cookie signature. Never use wp_fast_hash for authorization:
 * its key is public. Bind every transferred field to this site's secret instead.
 */
class AppCommerce_Checkout_Session {

	public static function get_handoff( $session ) {
		if ( ! $session instanceof WC_Session_Handler ) {
			return self::unavailable();
		}
		// Incoming cookies may be absent, invalid, or stale after session renewal.
		// Read only the initialized handler's active state, never re-import request data.
		$fields = WC_Session_Handler_Extended::get_active_session_fields( $session );

		$handoff = array(
			'customer_id'        => (string) $fields[0],
			'session_expiration' => (string) $fields[1],
			'session_expiring'   => (string) $fields[2],
		);
		if ( ! self::valid_fields( $handoff ) ) {
			return self::unavailable();
		}

		// A WooCommerce cookie alone must never authorize minting a login proof.
		if ( ctype_digit( $handoff['customer_id'] ) && ( ! is_user_logged_in() || $handoff['customer_id'] !== (string) get_current_user_id() ) ) {
			return self::unavailable();
		}

		// Mark new guest sessions for WooCommerce's normal shutdown persistence too.
		self::set_customer_cookie( $session );
		$handoff['cookie_hash'] = self::signature( $handoff );
		return $handoff;
	}

	public static function set_customer_cookie( $session ) {
		// Some older setters cast the incoming cookie to string before replacing it.
		$cookie_name = apply_filters( 'woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH );
		if ( isset( $_COOKIE[ $cookie_name ] ) && ! is_string( $_COOKIE[ $cookie_name ] ) ) {
			unset( $_COOKIE[ $cookie_name ] );
		}
		$session->set_customer_session_cookie( true );
	}

	public static function verify( $handoff ) {
		return self::valid_fields( $handoff ) && isset( $handoff['cookie_hash'] ) && is_string( $handoff['cookie_hash'] )
			&& hash_equals( self::signature( $handoff ), $handoff['cookie_hash'] );
	}

	private static function valid_fields( $handoff ) {
		if ( ! is_array( $handoff ) ) {
			return false;
		}
		foreach ( array( 'customer_id', 'session_expiration', 'session_expiring' ) as $field ) {
			if ( ! isset( $handoff[ $field ] ) || ! is_string( $handoff[ $field ] ) ) {
				return false;
			}
		}
		return 1 === preg_match( '/\A[A-Za-z0-9_-]+\z/', $handoff['customer_id'] )
			&& ctype_digit( $handoff['session_expiration'] ) && ctype_digit( $handoff['session_expiring'] )
			&& (int) $handoff['session_expiration'] > time() && (int) $handoff['session_expiring'] > 0
			&& (int) $handoff['session_expiring'] <= (int) $handoff['session_expiration'];
	}

	private static function signature( $handoff ) {
		$message = 'appcommerce-checkout-v1|' . $handoff['customer_id'] . '|' . $handoff['session_expiration'] . '|' . $handoff['session_expiring'];
		return 'ac1.' . hash_hmac( 'sha256', $message, wp_salt( 'auth' ) );
	}

	private static function unavailable() {
		return new WP_Error( 'appcommerce_checkout_session_unavailable', __( 'Unable to prepare the checkout session. Please refresh your cart.', 'appcommerce' ), array( 'status' => 409 ) );
	}
}
