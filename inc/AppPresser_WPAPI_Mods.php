<?php
/**
 * Modifications to the WP-API
 *
 * @package AppPresser
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_WPAPI_Mods {

	/**
	 * Party Started
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->hooks();
	}

	public function hooks() {

		add_action( 'rest_api_init', array( $this, 'add_api_fields' ) );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// this is related to the verify_user() function below
		add_filter( 'wp_authenticate_user', array( $this, 'check_app_unverified' ), 10, 2 );
		
		// CORS
		add_action( 'rest_api_init', array( $this, 'appp_cors') );
	}

	/**
	 * API routes for in-app login and registration
	 * 
	 * @since 3.6.0
	 */
	public function register_routes() {

		// Bail early if no core rest support.
		if ( ! class_exists( 'WP_REST_Controller' ) ) {
			return;
		}

		register_rest_route( 'appp/v1', '/login', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'api_login' )
			),
		) );

		register_rest_route( 'appp/v1', '/logout', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'api_logout' )
			),
		) );

		register_rest_route( 'appp/v1', '/register', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'register_user')
			),
		) );

		register_rest_route( 'appp/v1', '/verify', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'verify_user')
			),
		) );

		register_rest_route( 'appp/v1', '/verify-resend', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_verification_code')
			),
		) );

		register_rest_route( 'appp/v1', '/reset-password', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_password')
			),
		) );

        register_rest_route( 'appp/v1', '/system-info', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'system_information')
			),
		) ); 
	}

	/**
	 * Use:
	 * 
	 *  Access-Control-Allow-Origin: *
	 * 
	 * Applies a filter 
	 * 
	 * @since 3.6.0
	 */
	public function app_cors_header() {
		
		$appp_allow_origin = apply_filters( 'appp_allow_api_origin', '*' );
		$appp_allow_methods = apply_filters( 'appp_allow_api_methods', 'GET,PUT,POST,DELETE,PATCH,OPTIONS' );

		if( $appp_allow_origin ) {
			header("Access-Control-Allow-Origin: $appp_allow_origin");
			header("Access-Control-Allow-Methods: $appp_allow_methods");
		}
	}

	/**
	 * A filter to use:
	 * 
	 *  Access-Control-Allow-Origin: *
	 * 
	 * when the AppPresser admin setting is on.
	 * 
	 * @since 3.6.0
	 */
	public function appp_cors() {

		// Only add when setting is enabled
		if( appp_get_setting( 'ap3_enable_cors', false ) ) {
			add_filter( 'appp_allow_api_origin', function() {
				return '*';
			} );
			$this->app_cors_header();
		} else {
			add_filter( 'appp_allow_api_origin', function() {
				return false;
			} );
		}
		
	}

	public function add_api_fields() {

		/***
		* Add featured image urls to post response.
		* Sample usage in the app files would be data.featured_image_urls.thumbnail
		***/
		register_rest_field( 'post',
		    'featured_image_urls',
		    array(
		        'get_callback'    => array( $this, 'image_sizes' ),
		        'update_callback' => null,
	            'schema'          => null,
		    )
		);

		// add urls for media
		$post_types = appp_get_setting( 'media_post_types' );

		if( !empty( $post_types ) ) {

			foreach ($post_types as $type) {
				register_rest_field( $type,
				    'appp_media',
				    array(
				        'get_callback'    => array( $this, 'get_media_url' ),
				        'update_callback' => null,
			            'schema'          => null,
				    )
				);
			}
			
		}
		
	}

	public function image_sizes( $post ) {

	    $featured_id = get_post_thumbnail_id( $post['id'] );

		$sizes = wp_get_attachment_metadata( $featured_id );

		$size_data = new stdClass();
				
		if ( ! empty( $sizes['sizes'] ) ) {

			foreach ( $sizes['sizes'] as $key => $size ) {
				// Use the same method image_downsize() does
				$image_src = wp_get_attachment_image_src( $featured_id, $key );

				if ( ! $image_src ) {
					continue;
				}
				
				$size_data->$key = $image_src[0];
				
			}

		}

		return $size_data;
	    
	}

	public function get_media_url( $post ) {

		$value = get_post_meta( $post['id'], 'appp_media_url', true );

		$data = [];

		if( !empty( $value ) ) {
			$data['media_url'] = $value;
		} else {
			return;
		}

		// optionally add an image when playing the media
		$thumb = get_post_meta( $post['id'], 'appp_media_image', true );

		if( !empty( $thumb ) ) {
			$data['media_image'] = $thumb;
		}

		return $data;

	}

	/**
	 * Login via API
	 * 
	 * @since 3.6.0
	 */
	public function api_login( $request ) {

		$info['user_login'] = ( $_POST['username'] ? $_POST['username'] : $_SERVER['PHP_AUTH_USER'] );
		$info['user_password'] = ( $_POST['password'] ? $_POST['password'] : $_SERVER['PHP_AUTH_PW'] );
		$info['remember'] = true;

		if( empty( $info['user_login'] ) || empty( $info['user_password'] ) ) {
			
			$msg = array(
				'success' => false,
				'data' => array(
					'message' =>  apply_filters( 'appp_login_error', __('Missing required fields.', 'apppresser'), '' ),
					'success' => false
				)
			);
			
			return rest_ensure_response( $msg );
		}

		do_action( 'appp_before_signon', $info );
		
		$user_signon = wp_signon( $info, false );

		do_action( 'appp_login_header' );
		
		if( is_wp_error( $user_signon ) ) {
		
			$msg = array(
				'success' => false,
				'data' => array(
					'message' =>  apply_filters( 'appp_login_error', __('The log in you have entered is not valid.', 'apppresser'), $info['user_login'] ),
					'success' => false
				)
			);
			
			return rest_ensure_response( $msg );
			
		}

        // If everything is successfull, return login response
        return AppPresser_User::getLoginResponse($user_signon);
	}

	/**
	 * Logout via API
	 * 
	 * @since 3.6.0
	 */
	public function api_logout( $request ) {

		do_action( 'appp_logout_header' );

		if( ! defined('DOING_AJAX') ) {
			define('DOING_AJAX', true);
		}

		wp_logout();

		$response = array(
			'message' => __('Logout success.', 'apppresser'),
			'success' => true
		);

		$redirect = $this->get_logout_redirect();
		if($redirect) {
			$response['logout_redirect'] = $redirect;
		}

		$retval = rest_ensure_response( $response );

		return $retval;

	}

	/**
	 * Register user via API
	 * First, we add the user to WordPress, and set a meta key of app_unverified to true
	 * Next, we send them a key, which is a short hash
	 * They have to grab the key and send it back to verify_user(), which logs them in and deletes the app_unverified meta
	 *
	 * @since 3.6.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of activities object data.
	 */
	public function register_user( $request ) {

		if( empty( $request['username'] ) || empty( $request['email'] ) ) {

			return new WP_Error( 'rest_invalid_registration',
				__( 'Missing required fields.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);

		}

		if ( email_exists( $request['email'] ) || username_exists( $request['username'] ) ) {
			return new WP_Error( 'rest_invalid_registration',
				__( 'Email or username already exists.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		if( empty( $request['password'] ) ) {
			$password = wp_generate_password( 8 );
		} else {
			$password = $request['password'];
		}

		$userdata = array(
		    'user_login'  =>  $request['username'],
		    'user_pass'   =>  $password,
		    'user_email'  =>  $request['email'],
		    'first_name'  =>  $request['first_name'],
		    'last_name'   =>  $request['last_name']
		);

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'rest_invalid_registration',
				__( 'Something went wrong with registration.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		update_user_meta( $user_id, 'app_unverified', true );

		$mail_sent = $this->send_verification_code( $request );

		if ( !$mail_sent ) {
			return new WP_Error( 'rest_invalid_registration',
				__( 'We could not send your verification code, please contact support.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		do_action( 'appp_register_unverified', $user_id );

		$success = __( "Your verification code has been sent, please check your email.", "apppresser" );

		$retval = rest_ensure_response( $success );

		return $retval;

	}

	/**
	 * Emails a verification code
	 * 
	 * @since 3.6.0
	 */
	public function send_verification_code( $request ) {

		if( empty( $request['email'] ) || empty( $request['username'] ) ) {
			return new WP_Error( 'rest_invalid_verification',
				__( 'Missing required field.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( !email_exists( $request['email'] ) || !username_exists( $request['username'] ) ) {

			return new WP_Error( 'rest_invalid_verification',
				__( 'Invalid username or email.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
			
		}

		// now send verification code
		$verification_code = hash( "md5", $request['username'] . $request['email'] );
		// make it shorter
		$verification_code = substr($verification_code, 1, 4);
		$subject = __( 'Your Verification Code', 'apppresser' );
		$subject = apply_filters( 'appp_verification_email_subject', $subject );

		$content = sprintf( __( "Hi, thanks for registering! Here is your verification code: %s \n\nPlease enter this code in the app. \n\nThanks!", "apppresser" ), $verification_code );

		$content = apply_filters( 'appp_verification_email', $content, $verification_code );

		$mail_sent = wp_mail( $request["email"], $subject, $content );

		return $mail_sent;
	}

	/**
	 * Verify user, then log them in
	 * 
	 * @since 3.6.0
	 */
	public function verify_user( $request ) {

		if( empty( $request['email'] ) || empty( $request['verification'] ) ) {
			return new WP_Error( 'rest_invalid_verification',
				__( 'Missing required field.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$verification_code = hash( "md5", $request['username'] . $request['email'] );
		$verification_code = substr($verification_code, 1, 4);

		if( $request['verification'] != strval( $verification_code ) ) {
			// fail
			return new WP_Error( 'rest_invalid_verification',
				__( 'Verification code does not match.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$user = get_user_by( 'email', $request['email'] );

		delete_user_meta( $user->ID, 'app_unverified' );

		// log the user in
		$info = array();
		$info['user_login'] = $request['username'];
		$info['user_password'] = $request['password'];
		$info['remember'] = true;
		
		$user_signon = wp_signon( $info, false );

		if ( is_wp_error( $user_signon ) || !$user ) {
			return new WP_Error( 'rest_invalid_verification',
				__( 'Verification succeeded, please login.', 'apppresser' ),
				array(
					'status' => 200,
				)
			);
		}

        // If everything is successfull, return login response
        $retval = AppPresser_User::getLoginResponse($user_signon);
        
		do_action( 'appp_register_verified', $user_signon->ID );

		return $retval;
	}

	/**
	 * Disallow login if user is unverified
	 * 
	 * @since 3.6.0
	 */
	public function check_app_unverified( $user, $password ) {

		if( get_user_meta( $user->ID, 'app_unverified', 1 ) ) {

			return new WP_Error( 'app_unverified_login',
				__( 'You have not verified by email, please contact support.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		return $user;

	}

	/**
	 * Get the login redirect for the app's login modal
	 * 
	 * @since 3.3.0
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public function get_logout_redirect() {

		if( has_filter( 'appp_logout_redirect' ) ) {
			$redirect_to = apply_filters( 'appp_logout_redirect', '' );

			return AppPresser_Ajax_Extras::add_redirect_title( $redirect_to );
			
		} else {
			return '';
		}
	}

	/*
	 * API password reset
	 * First, post the user email, and a reset code is sent to them
	 * Next, post the code and new password, and it's changed
	 */
	public function reset_password( $request ) {

		$return = array(
			'success' => false,
			'message' => 'Missing required fields.'
		);

		if( isset( $request['code'] ) && isset( $request['password'] ) ) {

			$return = $this->validate_reset_password( $request );

		} elseif( isset( $request['email'] ) ) {

			$return = $this->get_pw_reset_code( $request );
		}

		return $return;

	}

    /**
     * Returns the installed plugins and their version from a predefind list
     */
    public function system_information()
    {
        // Check if get_plugins() function exists. This is required on the front end of the
        // site, since it is in a file that is normally only loaded in the admin.
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();

        // List of a predefined plugins that we need to check
        $predefined_plugins_to_check = array(
            'appcommerce',
            'appcommunity',
            'applms',
            'apppresser',
            'apppresser-in-app-purchases'
        );

        $response = array();
        foreach ($all_plugins as $current_plugin) {
            if (in_array($current_plugin['TextDomain'], $predefined_plugins_to_check)) {
                $response[$current_plugin['TextDomain']] = $current_plugin['Version'];
            }
        }

        return $response;
    }

	/*
	 * API password reset
	 */
	public function get_pw_reset_code( $request ) {

		$return;

		$email = $request['email'];

		$user = get_user_by( 'email', $email );

		if( $user ) {

			$time = current_time( 'mysql' );
			// create a unique code to use one time
			$hash = $this->get_short_reset_code();

			update_user_meta( $user->ID, 'app_hash', $hash );

			$subject = __('App Password Reset', 'apppresser');
			$subject = apply_filters( 'appp_pw_reset_email_subject', $subject );

			$message = __('Enter the code into the app to reset your password. Code: ', 'apppresser') . $hash;

			$message = apply_filters( 'appp_pw_reset_email', $message, $hash );

			$mail = wp_mail( $user->user_email, $subject, $message );

			$return = array(
				'success' => true,
				'got_code' => true,
				'message' =>  __('Please check your email for your verification code.', 'apppresser')
			);

		} else {

			$return = array(
				'success' => false,
				'message' =>  __('The email you have entered is not valid.', 'apppresser')
			);

		}

		return $return;
	}

	public function get_short_reset_code() {
		
		$numbers = str_split('1234567890');
		shuffle($numbers);
		$letters = str_split('abcdefghijklmnopqrstuvwxyz');
		shuffle($letters);

		$code = $numbers[1].$letters[1].$letters[2].$numbers[3];

		return $code;
	}

	/**
	 * Validate the reset code, then reset the password
	 *
	 * @access public
	 */
	public function validate_reset_password( $request ) {

		global $wpdb;
		$return;

		$code 		= $request['code'];
		$password 	= $request['password'];

		$user = get_users( array( 'meta_key' => 'app_hash', 'meta_value' => $code ) );

		if( $user ) {

			wp_update_user( array ('ID' => $user[0]->data->ID, 'user_pass' => $password ) ) ;
			// delete our one time access code
			delete_user_meta( $user[0]->data->ID, 'app_hash');

			$return = array(
				'message' => __('Password has been changed, please login.', 'apppresser'),
				'pw_changed' => true,
				'success' => true
			);

		} else {

			$return = array(
				'success' => false,
				'message' =>  __('The code you have entered is not valid.', 'apppresser')
			);

		}

		return $return;
	}

}
global $AppPresser_WPAPI_Mods;
$AppPresser_WPAPI_Mods = new AppPresser_WPAPI_Mods();
$AppPresser_WPAPI_Mods->hooks();