<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: AppPresser In App Purchases
Plugin URI: https://apppresser.com
Description: Adds API endpoints to handle in app purchases, along with subscription validation.
Version: 3.0.0
Author: Scott Bolinger
Author URI: https://apppresser.com
License: GPLv2
Requires Plugins: apppresser
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class AppPresser_IAP {

	const VERSION			  = '3.0.0';
	public static $instance   = null;
	public static $dir_path;
	public static $dir_url;
	public static $plugin_slug = 'apppresser-in-app-purchases';

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

		// Define plugin constants
		self::$dir_path = trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$dir_url = trailingslashit( plugins_url( '' , __FILE__ ) );

		$this->hooks();
		

	}

	public function hooks() {

		require_once( self::$dir_path . 'in-app-purchase-endpoints-v2.php' );
		require_once( self::$dir_path . 'in-app-purchase-settings.php' );

		add_action( 'rest_api_init', array( $this, 'api_endpoints' ) );


	}

	// register for plugin updates
	public function updater() {
		// Bundled modules are updated exclusively with AppPresser core.
	}

	public static function do_error_log( $err, $msg ) {
		if( class_exists('AppPresser_Logger') ) {
			AppPresser_Logger::log( $err, $msg );
		}
	}

	public function api_endpoints() {

        register_rest_route( 'appp/v1', '/in-app-purchase', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'purchase_register_login'),
                'permission_callback' => '__return_true',
			),
		) );
		
		register_rest_route( 'appp/v1', '/ios-subscription-notification', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'ios_subscription_notification'),
                'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'appp/v1', '/in-app-purchase-validate', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'iap_validate_subscription'),
                'permission_callback' => '__return_true',
			),
		) );

		// apppresser 3
		register_rest_route( 'appp/v1', '/in-app-purchase-status', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_iap_status'),
                'permission_callback' => '__return_true',
			),
		) );

	}

	/**
	 * Handle registration/login for in app purchases
	 * Create user if they don't exist, add meta, login, fire hook for adding to membership plugins
	 *
	 * @since 3.7.2
	 */
	public function purchase_register_login( $request ) {

		if( empty( $request['email'] ) ) {

			return new WP_Error( 'rest_invalid_registration',
				__( 'Missing required fields.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);

		}

		self::do_error_log( 'New in app purchase, or restored purchase.', 'Email ' . $request['email'] );

		// if a logged in app user makes a purchase, only email will be sent. Otherwise we will have a username.
		$email = ( isset( $request['email'] ) ? urldecode( $request['email'] ) : null );
		
		// maybe create a new user
		if ( !email_exists( $email ) && isset( $request['username'] ) ) {

			// register new user
			if( empty( $request['password'] ) ) {
				$password = wp_generate_password( 8 );
			} else {
				$password = $request['password'];
			}

			// make sure username is unique
			$username = ( !username_exists( $request['username'] ) ? $request['username'] : $this->clean_string( $email ) );

			$userdata = array(
			    'user_login'  =>  $username,
			    'user_pass'   =>  $password,
			    'user_email'  =>  $email,
			    'first_name'  =>  $request['first_name'],
			    'last_name'   =>  $request['last_name']
			);

			$user_id = wp_insert_user( $userdata );

			// add this here in case login fails, meta still gets added
			update_user_meta( $user_id, 'in_app_purchase', true );
			if( isset( $request['transaction_id'] ) ) {
				$this->add_transaction_id_to_usermeta( $request['transaction_id'], $user_id );
			} else {
				self::do_error_log( 'Purchase login error: missing transaction ID.', 'User id: ' . $user_id );
			}

			if ( is_wp_error( $user_id ) ) {
				return new WP_Error( 'rest_invalid_registration',
					__( 'Your purchase was successful, but something went wrong with registration. Please try registering again with a different username.', 'apppresser' ),
					array(
						'status' => 404,
					)
				);
			}

			$userdata['user_password'] = $userdata['user_pass'];

			// login the new user
			$loggedin_user = $this->login_user( $userdata );

			if ( is_wp_error( $loggedin_user[0] ) ) {
				return $loggedin_user[0];
			}

			$message = array(
				'message' => apply_filters( 'appp_login_success', sprintf( __('Welcome back %s!', 'apppresser'), $loggedin_user[0]->display_name), $user_id ),
				'username' => $loggedin_user[0]->user_login,
				'cookie_auth' => $loggedin_user[1],
				'avatar' => get_avatar_url( $user_id ), // v3 only
				'success' => true,
				'user_id' => $user_id,
				'email' => $email
			);

		} else if( isset( $email ) && !isset( $request['username'] ) ) {

			// if we have an email but no username, that means the app user is already logged into the app.

			self::do_error_log( 'Logged in user is purchasing.', 'User email: ' . $email );

			$user_email = $email;

			$user = get_user_by( 'email', $user_email );

			// add meta
			update_user_meta( $user->ID, 'in_app_purchase', true );
			if( isset( $request['transaction_id'] ) ) {
				$this->add_transaction_id_to_usermeta( $request['transaction_id'], $user->ID );
			} else {
				self::do_error_log( 'Purchase login error: missing transaction ID.', 'User id: ' . $user->ID );
			}

			if ( is_wp_error( $user ) || !$user ) {

				return new WP_Error( 'rest_invalid_user_id',
					__( 'Your purchase was successful, but there was a problem logging in, please try again.', 'apppresser' ),
					array(
						'status' => 400,
					)
				);
			}

			$user_id = $user->ID;

			$message = array(
				'message' => apply_filters( 'appp_login_success', sprintf( __('Welcome back %s!', 'apppresser'), $user->display_name), $user_id ),
				'username' => $user->user_login,
				'avatar' => get_avatar_url( $user_id ),
				'success' => true,
				'email' => $email
			);

		} else if( isset( $request['username'] ) && isset( $request['password'] ) ) {

			// this person already exists, so just log them in on the server and add meta
			$info = array();
			$info['user_login'] = $request['username'];
			$info['user_password'] = $request['password'];
			$info['remember'] = true;

			$user_signon = $this->login_user( $info );

			if ( is_wp_error( $user_signon[0] ) ) {
				return $user_signon[0];
			}

			$user_id = $user_signon[0]->ID;

			// add meta
			update_user_meta( $user_id, 'in_app_purchase', true );
			if( isset( $request['transaction_id'] ) ) {
				$this->add_transaction_id_to_usermeta( $request['transaction_id'], $user_id );
			} else {
				self::do_error_log( 'Purchase login error: missing transaction ID.', 'User id: ' . $user_id );
			}

			self::do_error_log( 'Successful login after purchase.', 'User id: ' . $user_id );

			$message = array(
				'message' => apply_filters( 'appp_login_success', sprintf( __('Welcome back %s!', 'apppresser'), $user_signon[0]->display_name), $user_id ),
				'username' => $info['user_login'],
				'cookie_auth' => $user_signon[1],
				'avatar' => get_avatar_url( $user_id ), // v3 only
				'success' => true,
				'user_id' => $user_id,
				'email' => $email
			);

		} else {

			self::do_error_log( 'Error registering user after purchase.', serialize( $request ) );

		}

		// adds user_id and auth token
		$message = apply_filters( 'appp_login_data', $message, $user_id );

		update_user_meta( $user_id, 'in_app_purchase', true );

		do_action( 'iap_new_purchase', $user_id );

		do_action( 'iap_new_purchase_request', $user_id, $request );

		$retval = rest_ensure_response( $message );

		return $retval;

	}

	// login a user
	// Returns array( $user, $cookie_auth );
	public function login_user( $userdata ) {

		$userdata['remember'] = true;

		$user_signon = wp_signon( $userdata, false );

		if ( is_wp_error( $user_signon ) ) {

			self::do_error_log( 'Did not login after purchase.', 'User : ' . $userdata['user_login'] );

			return new WP_Error( 'rest_invalid_login',
				__( 'Your purchase was successful, but there was a problem logging in, please try again.', 'apppresser' ),
				array(
					'status' => 400,
				)
			);
		}

		$cookie_auth = $this->do_cookie_auth( $user_signon->ID );

		return array( $user_signon, $cookie_auth );
			
	}
	
	/* This endpoint receives POST notifications from Apple if a subscription status URL is set for the app. We receive notifications and update user status, that way the app can just ping the server for a boolean.
	 * More information: https://developer.apple.com/library/archive/documentation/NetworkingInternet/Conceptual/StoreKitGuide/Chapters/Subscriptions.html#//apple_ref/doc/uid/TP40008267-CH7-SW13
	*/
	public function ios_subscription_notification( $request ) {
		
		self::do_error_log( 'iOS subscription notification ', serialize($request) );
		
		/*	data looks like this
			$request['auto_renew_status'] = "true"
			$request['notification_type'] = "INTERACTIVE_RENEWAL"
			$request['latest_receipt_info'] = Array
		(
		    [original_purchase_date_pst] => 2018-12-31 11:38:02 America/Los_Angeles
		    [quantity] => 1
		    [unique_vendor_identifier] => 90968843-0272-4791-B3D9-26C79426EDE3
		    [original_purchase_date_ms] => 1546285082000
		    [expires_date_formatted] => 2019-01-02 20:41:56 Etc/GMT
		    [is_in_intro_offer_period] => false
		    [purchase_date_ms] => 1546461536000
		    [expires_date_formatted_pst] => 2019-01-02 12:41:56 America/Los_Angeles
		    [is_trial_period] => false
		    [item_id] => 1448170303
		    [unique_identifier] => 5609cc213e9c6687f74cd5c52d15446feb3aafe2
		    [original_transaction_id] => 1000000490908199
		    [expires_date] => 1546461716000
		    [transaction_id] => 1000000491217456
		    [bvrs] => 1.0.0
		    [web_order_line_item_id] => 1000000042043222
		    [self::_external_identifier] => 0
		    [bid] => com.learnapppresser.app
		    [product_id] => learnsub
		    [purchase_date] => 2019-01-02 20:38:56 Etc/GMT
		    [purchase_date_pst] => 2019-01-02 12:38:56 America/Los_Angeles
		    [original_purchase_date] => 2018-12-31 19:38:02 Etc/GMT
		) */

		$expired = false;

		if( empty( $request['latest_receipt_info']['original_transaction_id'] ) ) {

			if( isset( $request['latest_expired_receipt_info'] ) ) {

				// if we are here the subscription most likely expired
				$og_transaction_id = $request['latest_expired_receipt_info']['original_transaction_id'];

				$expired = true;
			}

			self::do_error_log( 'Error', 'Missing transaction id in Apple subscription notification. Cannot validate.' );
			return;
		} else {
			$og_transaction_id = $request['latest_receipt_info']['original_transaction_id'];
		}
		
		// need to grab transaction ID and match that to a user, then fire off actions
		$user_query = new WP_User_Query( array( 'fields' => 'ID', 'meta_key' => 'in_app_purchase_transaction_id', 'meta_value' => $og_transaction_id ) );
		
		if( $user_query->get_results() && $user_query->get_results()[0] ) {
			// parse receipt and update user if subscription is still valid or not
			$user_id = $user_query->get_results()[0];

			$active = false;

			// grace period to account for billing issues
			$graceperiod = strtotime('-2 days');

			// check if it's expired
			if( strtotime( $request['latest_receipt_info']['expires_date'] ) > $graceperiod ) {
				// if expiration date is after two days ago, membership is still active
			    $active = true;

			    self::do_error_log( 'Expiration date check says user ' . $user_id . ' is active.', 'Expires date: ' . $request['latest_receipt_info']['expires_date'] );

			} else {

				self::do_error_log( 'Expiration date check says user ' . $user_id . ' is expired.', 'Expires date: ' . $request['latest_receipt_info']['expires_date'] );
				self::do_error_log('auto renew status:', $request['auto_renew_status'] );

				// they seem to be expired, one last check before giving them the boot
				if( $request['auto_renew_status'] === 'false' ) {
					$expired = true;
				}

			}

			if( $expired || $request['notification_type'] === 'CANCEL' ) {

				self::do_error_log('Cancelled user', $user_id );

				do_action( 'iap_subscription_cancel', $user_id );

				do_action( 'iap_subscription_cancel_request', $user_id, $request );

			} else if( $request['notification_type'] === 'RENEWAL' || $request['notification_type'] === 'INITIAL_BUY' || $request['notification_type'] === 'INTERACTIVE_RENEWAL' || $active ) {
				update_user_meta( $user_id, 'in_app_purchase', true );
				do_action( 'iap_subscription_renew', $user_id );
			}

		} else {

			self::do_error_log( 'Error: no user found with transaction ID in meta, cannot validate.', 'Transaction ID: ' . $request['latest_receipt_info']['original_transaction_id'] );

		}
		
		// no return value needed
		
	}
	
	/*
	 * Boolean check if user subscription is still active.
	 */
	public function check_iap_status( $request ) {
		
		// grab username and check if their sub is active, return boolean
		
		if( empty( $request['user_id'] ) ) {

			self::do_error_log( 'Check purchase status error.', 'Missing user_id in request.' );

			return new WP_Error( 'rest_invalid_login',
				__( 'Missing required field: user_id.', 'apppresser' ),
				array(
					'status' => 400,
				)
			);
		}
		
		$user_id = $request['user_id'];

		// product ID is sent but not used right now
		//$product_id = $request['product_id'];
		
		$status = get_user_meta( $user_id, 'in_app_purchase', 1 );

		self::do_error_log( 'Check iOS user subscription active for ' . $user_id, 'Status: ' . ( $status === '1' ? 'active' : 'inactive' ) );
		
		return apply_filters( 'appp_iap_status', $status, $user_id );
		
	}

	/**
	 * Used for validating Android in app purchase subscriptions.
	 * 
	 * @return boolean
	 *
	 * @since 3.7.2
	 */
	public function iap_validate_subscription( $request ) {

		$response       = array();
		$parameters 	= $request->get_params();
		
		self::do_error_log( 'Android validation request ', serialize($request) );

		$purchases = $parameters['purchases'];
		$product_id = $parameters['product_id'];
		// Prevents unauthorized requests
		$security = $parameters['sec'];
		$user_id = $parameters['user_id'];

		if( $security != 'jk39DLKj!') {
			return 'You are not allowed to do that.';
		}
		
		$active = "false";

		/* purchases array looks like
			[{"productId":"learnsub","transactionId":"GPA.3335-1475-5486-44912","type":"subs","productType":"subs","signature":"V0deHaYTRaJHFEncKVvgwauHcPjaeXH6bbAIRWGLSZ1f21nbTc5DRZh01DhiBKQ+X3RmNxI/wuidCJQ7FlZFMPt8VXkLZXj01SeZz0o3WkkXisyZSKOqwuYpQyQQw9Hz8xaj6nXe2Ct/jLfaXEig7WukM3JpHHfvjOvOHF+DelIDPKzEgjwF9ocZKZFP/+7Stbp4YQcuU+UwL7oElVWGZTyIf5+K7Jefzu+fc0+pjzKWAHi+PBhCSqo0qsCz+6s8Dz47nyXoJ6yZL6EiGxak/C6hDCTXGkqcDWLXQonkVchAVfLycGzTxepA4ROqFQU5Uc/HL/FBDBhEe7uGCQF+Fg==","receipt":"{"orderId":"GPA.3335-1475-5486-44912","packageName":"com.learnapppresser.app","productId":"learnsub","purchaseTime":1546876047183,"purchaseState":0,"purchaseToken":"jgfmjamoghpejommfeokokfo.AO-J1OwuQe5NwicHjz7h-bajC_8b5F-RMjT3HLLDuWhL5hyeHa01-QcWjojBLlK3ErR1cziRJQT-FvzirkHS5bmehJT-wRGnSR_JjhSlRaLazim7vPDlhiVsuvzEymtdA2pGe_q7FBea","autoRenewing":true}"}]
			*/
		
		// when an Android subscription expires, purchase array is empty, so we'd skip this part.
		if( is_array( $purchases ) && !empty( $purchases ) ) {

			foreach ( $purchases as $purchase ) {
			    // compare dates.
			    if( $product_id === $purchase['productId'] ) {
			    	// if expiration date is after one week ago, membership is still active
			    	$active = "true";
			    	break;
			    }
			}

		}

		if( $active === "false" && isset( $user_id ) ) {
			// this hook is where you should remove user from membership level, send them an email, etc
			do_action( 'iap_subscription_cancel', $user_id );
			$valid = false;
		} else if( $active === "true" && isset( $user_id ) ) {
			do_action( 'iap_subscription_renew', $user_id );
			$valid = true;
		} else {
			self::do_error_log( 'Error: no user id or cannot validate Android in app purchase.', serialize($request) );
		}
		
		self::do_error_log( 'Is subscription valid for ' . $user_id . '?', ( $valid ? 'Yes' : 'No' ) );

		return apply_filters( 'iap_validate_receipt_response', $valid, $request );

	}

	// add transaction_id to user meta. This is used to validate/cancel subscriptions later
	public function add_transaction_id_to_usermeta( $transaction_id, $user_id ) {

		$meta = get_user_meta( $user_id, 'in_app_purchase_transaction_ids', 1 );

		if( empty( $meta ) ) {
			update_user_meta( $user_id, 'in_app_purchase_transaction_ids', array( $transaction_id ) );
		} elseif( is_array( $meta ) && !in_array( $transaction_id, $meta ) ) {
			$meta[] = $transaction_id;
			update_user_meta( $user_id, 'in_app_purchase_transaction_ids', $meta );
		}

		self::do_error_log( 'Added transaction id to user meta for ' . $user_id, serialize( $meta ) );
	}

	/*
	 * Encrypts string for later decoding
	 */
	public function do_cookie_auth( $user_id ) {

		if( function_exists('openssl_encrypt') ) {

			$key = substr( AUTH_KEY, 2, 5 );
			$iv = substr( AUTH_KEY, 0, 16 );
			$cipher="AES-128-CBC";
			$ciphertext = openssl_encrypt($user_id, $cipher, $key, null, $iv );

		} else {
			// no openssl installed
			$ciphertext = $user_id;
		}

		update_user_meta( $user_id, 'app_cookie_auth', $ciphertext );

		return $ciphertext;

	}

	// remove spaces and special characters
    function clean_string($string) {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        $string = strtolower( $string );
        return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }

}

AppPresser_IAP::get();