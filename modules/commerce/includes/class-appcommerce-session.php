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
}
