<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: In App Purchases for ARMember - Edited for Multiple IAP support
Plugin URI: https://apppresser.com
Description: This plugin listens for in app purchases or cancellations and adds/removes members from a membership plan.
Version: 1.0.0
Author: Jitendra Damor
Author URI: https://apppresser.com
License: GPLv2
*/

class AppPresser_IAP_ARMember {
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
		self::$membership_levels = apppresser_iap_get_option('IAP_ARMEMBER_KEYS', array());

		add_action( 'iap_new_purchase_request', array( $this, 'new_purchase' ), 10, 3 );
		add_action( 'iap_subscription_cancel_request', array( $this, 'cancel_membership' ), 10, 3 );

	}

	/*
	 * This function is called when a new purchase is created. Add the user to the appropriate membership Plan here.
	 */
	public function new_purchase( $user_email, $purchase, $request ) {

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

		//Iterate Over each IAP ID to find a match with the purchase and then assign appropriate MemberPress ID
		$found = false;

		foreach(self::$membership_levels as $mid){
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
				$found = true;
				$this->add_user_to_plan($user_id, $mid['planID']);
			}
		}

		if (!$found){
			AppPresser_Logger::log('Product ID not found for : ' . $user_email, 'Product id: ' . $purchase['productId']);
		}
	}

	private function add_user_to_plan($user_id, $membership_plan_id) {

		do_action('arm_apply_plan_to_member', $membership_plan_id, $user_id);

		AppPresser_Logger::log( 'User added to ARMember.'. $user_id, 'User Subscribed to plan' . $membership_plan_id );
	}

	/*
	 * This function is called when a membership is cancelled. We get this from the iOS subscription API notification, or in Android from the app itself.
	 * A user can always click "restore membership" if their membership is cancelled in error.
	 */
	public function cancel_membership( $user_email, $purchase, $request ) {

		$user = get_user_by( 'email', $user_email );

		if( !$user || is_wp_error( $user ) ) {

			AppPresser_Logger::log( 'Cannot cancel because we cannot get the user.', $user_email );

			return new WP_Error( 'rest_user_error',
				__( 'Cannot cancel membership, because cannot get user for ' . $user_email, 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		global $arm_subscription_plans;
		$user_id = $user->ID;
		$product_id = $purchase['productId'];

		foreach(self::$membership_levels as $mid){
			if (str_ends_with($product_id, $mid['iapID'])) {
				$membership_plan_id = $mid['planID'];
				do_action('arm_cancel_subscription_gateway_action', $user_id, $membership_plan_id);
				$arm_subscription_plans->arm_add_membership_history($user_id, $membership_plan_id, 'cancel_subscription');
				do_action('arm_cancel_subscription', $user_id, $membership_plan_id);
				$arm_subscription_plans->arm_clear_user_plan_detail($user_id, $membership_plan_id);

				AppPresser_Logger::log( 'Cancelled user ' . $user_id, ' From Membership plan ' . $membership_plan_id . ' For product ' . $product_id );
			}
		}
	}

}

AppPresser_IAP_ARMember::get();