<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: In App Purchases for MemberPress - Edited for Multiple IAP support
Plugin URI: https://apppresser.com
Description: This plugin listens for in app purchases or cancellations and adds/removes members from a membership level.
Version: 2.0.0
Author: Scott Bolinger
Author URI: https://apppresser.com
License: GPLv2
*/

class AppPresser_IAP_MemberPress {

	// change this to subscription Level ID
	//public static $membership_level_id = 8382;
	// public static $membership_levels = array(
	// 	array(
	// 		"iapID" => "A",
	// 		"wpMemberPressID" => "108"
	// 	),
	// 	array(
	// 		"iapID" => "B",
	// 		"wpMemberPressID" => "Y"
	// 	),
	// 	array(
	// 		"iapID" => "C",
	// 		"wpMemberPressID" => "Z"
	// 	)
	// );
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
		self::$membership_levels = apppresser_iap_get_option('IAP_MEMBERPRESS_KEYS', array());

		add_action( 'iap_new_purchase_request', array( $this, 'new_purchase' ), 10, 3 );
		add_action( 'iap_new_purchase_recurring_request', array( $this, 'new_purchase' ), 10, 3 );

		add_action( 'iap_subscription_cancel_request', array( $this, 'cancel_membership' ), 10, 3 );
		add_action( 'iap_subscription_cancel_recurring_request', array( $this, 'cancel_membership' ), 10, 3 );

	}

	/*
	 * This function is called when a new purchase is created. Add the user to the appropriate membership level here.
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
				$purchase['amountUSD'] ? $cost = $purchase['amountUSD'] : $cost = 10.00;
				$this->add_user_to_level($user_id, $mid['wpMemberPressID'], $cost);
			} 
		}
		if (!$found){
			AppPresser_Logger::log('Product ID not found for : ' . $user_email, 'Product id: ' . $purchase['productId']);
		}	

	}

	private function add_user_to_level($user_id, $membership_level_id, $cost = 10.00) {
		// is user already subscribed and active? If so, skip
		// https://github.com/wp-premium/memberpress-developer/blob/master/app/models/MeprUser.php
		$mbr_user = new MeprUser( $user_id );
		$subscribed = $mbr_user->is_already_subscribed_to( $membership_level_id );

		if( $subscribed && $mbr_user->is_active() ) {
			AppPresser_Logger::log( 'New in app purchase but we did nothing because ' . $user_id . ' is already active.', $active );
			return;
		}

		// create the purchase in MemberPress
		// https://github.com/wp-premium/memberpress-developer/blob/master/app/models/MeprTransaction.php
		$txn = new MeprTransaction();
	    $txn->amount = $cost;
	    $txn->total = $cost;
	    $txn->user_id = $user_id;
	    $txn->product_id = $membership_level_id;
	    // $txn->trans_num = $order->order_key;
	    $txn->status = 'complete';
	    $txn->txn_type = 'payment';
	    $txn->gateway = 'manual';
	    $txn->created_at = date('c');
	    $txn->expires_at = date('c', strtotime("+395 days") ); // 1 Year + 30 day grace period
	    $txn->store();

		AppPresser_Logger::log( 'User added to MemberPress.', serialize( $txn ) );
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

		$user_id = $user->ID;

		// https://github.com/wp-premium/memberpress-developer/blob/master/app/models/MeprUser.php
		$mbr_user = new MeprUser( $user_id );


		foreach(self::$membership_levels as $mid){
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
				$transactions = $mbr_user->transactions_for_product( $mid['wpMemberPressID'], false, true );
			} 
		}

		if( $transactions[0] ) {
			$txn = new MeprTransaction(  $transactions[0]->id );
			$txn->expires_at = date('c', strtotime( "now" ) );
			$txn->store();
			AppPresser_Logger::log( 'Cancelled user ' . $user_id, serialize( $txn ) );
		}
        else {
            AppPresser_Logger::log('Cancellation - MemberPress Member Transaction not found for : ' . $user_email, 'Product id: ' . $purchase['productId']);
        }

	}

}

AppPresser_IAP_MemberPress::get();