<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: AppPresser In App Purchases for Restrict Content Pro (v2) - Edited for Multiple IAP support
Plugin URI: https://apppresser.com
Description: This plugin listens for in app purchases or cancellations and adds/removes members from a membership level.
Version: 2.0.0
Author: Scott Bolinger
Author URI: https://apppresser.com
License: GPLv2
*/

class AppPresser_IAP_RCP {

	// change this to your RCP subscription_id
	public static $membership_levels = array();

	public static $instance   = null;

	/**
	 * Creates or returns an instance of this class.
	 * @since	1.0.0
	 * @return AppPresser A single instance of this class.
	 */
	public static function get() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * 
	 * @since	1.0.0
	 */
	function __construct() {

		self::hooks();

	}

	public function hooks() {
		self::$membership_levels = apppresser_iap_get_option('IAP_RCP_KEYS', array());

		add_action( 'iap_new_purchase_request', array( $this, 'new_purchase' ), 10, 3 );

		add_action( 'iap_subscription_cancel_request', array( $this, 'cancel_membership' ), 10, 3 );

	}

	/*
	 * This function is called when a new purchase is created. Add the user to the appropriate membership level here.
	 */
	public function new_purchase( $user_email, $purchase, $request ) {

		AppPresser_Logger::log( 'New purchase from email: ', $user_email );

		$user = get_user_by( 'email', $user_email );

		if( !$user || is_wp_error( $user ) ) {

			AppPresser_Logger::log( 'Cannot add to membership level because user does not exist.', $user_email );

			return new WP_Error( 'rest_user_error',
				__( 'Cannot get user for ' . $user_email, 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$user_id = $user->ID;

		if( !function_exists('rcp_get_subscription_id') ) {

			AppPresser_Logger::log( 'Cannot add to membership level because function rcp_get_subscription_id() does not exist.', $user_email );

			return new WP_Error( 'rest_rcp_error',
				__( 'Restrict Content Pro is not installed properly.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		//Iterate Over each IAP ID to find a match with the purchase and then assign appropriate MemberPress ID
		$found = false;

		foreach(self::$membership_levels as $mid){
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
				$found = true;
				$this->add_user_to_membership_level($user_id, $mid['rcpSubscriptionID']);
			}
		}
		if (!$found){
			AppPresser_Logger::log('Product ID not found for : ' . $user_email, 'Product id: ' . $purchase['productId']);
		}

	}

	private function add_user_to_membership_level($user_id,$rcp_subscription_id) {
		// is user already subscribed and active? If so, skip
		$member_subscription = intval( rcp_get_subscription_id( $user_id ) );

		if( $member_subscription === $rcp_subscription_id ) {
			AppPresser_Logger::log( 'New in app purchase but we did nothing', 'user ' . $user_id . ' is already active on level ' . $member_subscription );
			return;
		}

		// add user to level

        $args = array(
            'subscription_id'    => $rcp_subscription_id,
            'status'             => 'active',
            'expiration'         => date( 'Y-m-d 23:59:59', strtotime( '+5 years', current_time( 'timestamp' ) ) )
        );

        // returns level id if it works
        $membership_success = rcp_add_user_to_subscription( $user_id, $args );

		AppPresser_Logger::log( 'User ' . $user_id . ' added to RCP? Subscription level id: ', $membership_success );
	}

	/*
	 * This function is called when a membership is cancelled.
	 * A user can always click "restore membership" if their membership is cancelled in error.
	 */
	public function cancel_membership( $user_email, $purchase, $request ) {

		$user = get_user_by( 'email', $user_email );

		if( !$user || is_wp_error( $user ) ) {

			AppPresser_Logger::log( 'Cannot cancel membership because user does not exist.', $user_email );

			return new WP_Error( 'rest_user_error',
				__( 'Cannot cancel membership, because cannot get user for ' . $user_email, 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$user_id = $user->ID;

		foreach(self::$membership_levels as $mid){
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
				$member = new RCP_Member( $user_id );

				if( is_object( $member ) && $member->get_subscription_id() == $mid['rcpSubscriptionID'] ) {
					$member->cancel();
					$result = true;
				} else {
					$result = false;
				}

				AppPresser_Logger::log( 'Cancelling membership... ' . $user_id, 'Level: ' . $mid['rcpSubscriptionID'] . ' Success? ' . $result );
			}
		}

	}

}

AppPresser_IAP_RCP::get();