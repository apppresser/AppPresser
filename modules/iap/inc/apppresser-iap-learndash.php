<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: In App Purchases for LearnDash - Edited for Multiple IAP support
Plugin URI: https://apppresser.com
Description: This plugin listens for in app purchases or cancellations and adds/removes members from a LearnDash course.
Version: 1.0.0
Author: Scott Bolinger
Author URI: https://apppresser.com
License: GPLv2
*/

class AppPresser_IAP_LD {
	public static $course_ids = array();

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
		self::$course_ids = apppresser_iap_get_option('IAP_LEARNDASH_KEYS', array());
		self::hooks();

	}

	public function hooks() {

		add_action( 'iap_new_purchase_request', array( $this, 'new_purchase' ), 10, 3 );

		add_action( 'iap_subscription_cancel_request', array( $this, 'cancel_membership' ), 10, 3 );

	}

	/*
	 * This function is called when a new purchase is created. Add the user to the appropriate course.
	 */
	public function new_purchase( $user_email, $purchase, $request ) {

		$user_email = ( isset( $request['applicationUsername'] ) ? $request['applicationUsername'] : null );

		AppPresser_Logger::log( 'New purchase from email: ', $user_email );

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

		//Iterate Over each IAP ID to find a match with the purchase and then assign appropriate Course ID
		$found = false;

		foreach( self::$course_ids as $mid ) {
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
				$found = true;
                $course_id = learndash_get_course_id( $mid['ldCoursePostId'] );
				$this->add_user_to_course($user_id, $course_id, $purchase);
			}
		}

		if (!$found){
			AppPresser_Logger::log('Product ID not found for : ' . $user_email, 'Product id: ' . $purchase['productId']);
		}

	}

	private function add_user_to_course($user_id, $course_id, $purchase) {
		// add user to course
		ld_update_course_access( $user_id, $course_id, $remove = false );

		// save this so we can cancel them later
		// we save the productId and course ID together so we know what course to cancel when the product is cancelled
		$saved_courses = get_user_meta( $user_id, 'iap_course_ids', 1 );
		if( empty( $saved_courses ) ) {
		    update_user_meta( $user_id, 'iap_course_ids', array( $purchase['productId'] . '-' . $course_id ) );
		} elseif( is_array( $saved_courses ) && !in_array( $course_id, $saved_courses ) ) {
		    $saved_courses[] = $purchase['productId'] . '-' . $course_id;
		    update_user_meta( $user_id, 'iap_course_ids', $saved_courses );
		}

		AppPresser_Logger::log( 'New in app purchase. User ' . $user_id . ' added to course ', $course_id );
	}

	/*
	 * This function is called when a membership is cancelled.
	 * A user can always click "restore membership" if their membership is cancelled in error.
	 */
	public function cancel_membership( $user_email, $purchase, $request ) {

		$user_email = ( isset( $request['applicationUsername'] ) ? $request['applicationUsername'] : null );

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

		foreach( self::$course_ids as $mid ) {
			if (str_ends_with($purchase['productId'], $mid['iapID'])) {
		
				$saved_courses = get_user_meta( $user_id, 'iap_course_ids', 1 );

				$product_id = $purchase['productId'];

				if( is_array( $saved_courses ) ) {

					foreach ($saved_courses as $key => $value) {
						$course_exploded = explode("-", $value);
						$course_name = $course_exploded[0];
						$ld_course_id = $course_exploded[1];

						if( $course_name === $product_id ) {
							unset($saved_courses[$key]);
							AppPresser_Logger::log( 'User removed from course: ', $ld_course_id );
							ld_update_course_access( $user_id, $ld_course_id, $remove = true );
						}
					}

					update_user_meta( $user_id, 'iap_course_ids', $saved_courses );

					AppPresser_Logger::log( 'Cancelling membership... ' . $user_id, 'Product id: ' . $purchase['productId'] );
					
				}
			}
		}

	}

}

AppPresser_IAP_LD::get();