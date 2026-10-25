<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: In App Purchases for WooCommerce Memberships - Edited for Multiple IAP support
Plugin URI: https://apppresser.com
Description: This plugin listens for in app purchases or cancellations and adds/removes members from a membership level.
Version: 2.0.0
Author: Scott Bolinger
Author URI: https://apppresser.com
License: GPLv2
*/

class AppPresser_IAP_Woo {

	// change this to subscription Level ID
	public static $woo_membership_plans = array();

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
		self::$woo_membership_plans = apppresser_iap_get_option('IAP_WOOMP_KEYS', array());

		add_action( 'iap_new_purchase_request', array( $this, 'new_purchase' ), 10, 3 );

		add_action( 'iap_subscription_cancel_request', array( $this, 'cancel_membership' ), 10, 3 );

	}

	/*
	 * This function is called when a new purchase is created. Add the user to the appropriate membership level here.
	 */
	public function new_purchase( $user_email, $purchase, $request ) {

		$user_email = ( isset( $request['applicationUsername'] ) ? $request['applicationUsername'] : null );

		AppPresser_Logger::log( 'New purchase from email: ' . $user_email . ' variation id: ', $variation_id );

		$user = get_user_by( 'email', $user_email );

		if( !$user || is_wp_error( $user ) ) {
			return new WP_Error( 'rest_user_error',
				__( 'Cannot get user for ' . $user_email, 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$user_id = $user->ID;

		//Iterate Over each IAP ID to find a match with the purchase and then assign appropriate MemberPress ID
		$found = false;

		foreach(self::$woo_membership_plans as $mid){
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
				$found = true;
				$this->add_user_to_woo_membership_plan($user_id, $mid['wooMembershipPlanID']);
			}
		}
		if (!$found){
			AppPresser_Logger::log('Product ID not found for : ' . $user_email, 'Product id: ' . $purchase['productId']);
		}

	}
	
	private function add_user_to_woo_membership_plan($user_id, $wooMembershipPlanID) {
		// is user already subscribed and active? If so, skip
		// https://docs.woocommerce.com/document/woocommerce-memberships-function-reference/
		$memberships = wc_memberships_get_user_active_memberships( $user_id );

		if( ! empty( $memberships ) ) {
			AppPresser_Logger::log( 'New in app purchase but we did nothing because ' . $user_id . ' is already active.', $active );
			return;
		}

		// create the membership
		$args = array(
	        // Enter the ID (post ID) of the plan to grant at registration
	        'plan_id'	=> $wooMembershipPlanID,
	        'user_id'	=> $user_id,
	    );
		wc_memberships_create_user_membership( $args );

		AppPresser_Logger::log( 'New in app purchase added to WooCommerce.', $args );
	}

	/*
	 * This function is called when a membership is cancelled.
	 * A user can always click "restore membership" if their membership is cancelled in error.
	 */
	public function cancel_membership( $user_email, $purchase, $request ) {

		$user_email = ( isset( $request['applicationUsername'] ) ? $request['applicationUsername'] : null );

		$user = get_user_by( 'email', $user_email );

		if( !$user || is_wp_error( $user ) ) {
			return new WP_Error( 'rest_user_error',
				__( 'Cannot cancel membership, because cannot get user for ' . $user_email, 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$user_id = $user->ID;

		foreach(self::$woo_membership_plans as $mid){
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
				AppPresser_Logger::log( 'Cancelling membership... ' . $user_id, 'Product id: ' . $purchase['productId'] );

				$user_membership = wc_memberships_get_user_membership( $user_id, $mid['wooMembershipPlanID'] );

				if( $user_membership ) {
					$user_membership->cancel_membership();
					AppPresser_Logger::log( 'Cancelled user ' . $user_id, $user_membership );
				}
			}
		}

	}

}

AppPresser_IAP_Woo::get();