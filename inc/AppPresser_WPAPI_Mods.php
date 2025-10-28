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
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Initialize hooks.
	 */
	public function hooks() {

		add_action( 'rest_api_init', array( $this, 'add_api_fields' ) );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// This is related to the verify_user() function below.
		add_filter( 'wp_authenticate_user', array( $this, 'check_app_unverified' ), 10, 2 );

		// CORS.
		add_action( 'rest_api_init', array( $this, 'appp_cors' ) );
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

		register_rest_route(
			'appp/v1',
			'/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'api_login' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/login/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'api_login_refresh' ),
					'permission_callback' => array( $this, 'api_login_refresh_permission_check' ),
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/logout',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'api_logout' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/register',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_user' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'verify_user' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/verify-resend',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_verification_code' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/reset-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_password' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/system-info',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'system_information' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/submit-form',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_form' ),
					'permission_callback' => array( $this, 'form_permissions' ),
				),
			)
		);

		register_rest_route(
			'appp/v1',
			'/myappp-verify/(?P<key>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'myappp_verify' ),
					'permission_callback' => array( $this, 'myappp_permissions' ),
				),
			)
		);
	}

	/**
	 * Use:
	 *
	 * Access-Control-Allow-Origin: *
	 *
	 * Applies a filter
	 *
	 * @since 3.6.0
	 */
	public function app_cors_header() {

		$appp_allow_origin  = apply_filters( 'appp_allow_api_origin', '*' );
		$appp_allow_methods = apply_filters( 'appp_allow_api_methods', 'GET,PUT,POST,DELETE,PATCH,OPTIONS' );

		if ( $appp_allow_origin ) {
			header( "Access-Control-Allow-Origin: $appp_allow_origin" );
			header( "Access-Control-Allow-Methods: $appp_allow_methods" );
		}
	}

	/**
	 * A filter to use:
	 *
	 * Access-Control-Allow-Origin: *
	 * when the AppPresser admin setting is on.
	 *
	 * @since 3.6.0
	 */
	public function appp_cors() {

		// Only add when setting is enabled.
		if ( appp_get_setting( 'ap3_enable_cors', false ) ) {
			add_filter(
				'appp_allow_api_origin',
				function () {
					return '*';
				}
			);
			$this->app_cors_header();
		} else {
			add_filter(
				'appp_allow_api_origin',
				function () {
					return false;
				}
			);
		}
	}

	/**
	 * Add featured image urls to post response.
	 * Sample usage in the app files would be data.featured_image_urls.thumbnail.
	 */
	public function add_api_fields() {

		$post_types = apply_filters( 'appp_api_fields_post_types', get_post_types() );

		foreach ( $post_types as $key => $value ) {
			register_rest_field(
				$value,
				'featured_image_urls',
				array(
					'get_callback'    => array( $this, 'image_sizes' ),
					'update_callback' => null,
					'schema'          => null,
				)
			);
		}

		// Add urls for media.
		$post_types = appp_get_setting( 'media_post_types' );

		if ( ! empty( $post_types ) ) {

			foreach ( $post_types as $type ) {
				register_rest_field(
					$type,
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

	/**
	 * Gell all image sizes
	 *
	 * @param Object $post Post.
	 */
	public function image_sizes( $post ) {

		$featured_id = get_post_thumbnail_id( $post['id'] );

		$sizes = wp_get_attachment_metadata( $featured_id );

		$size_data = new stdClass();

		if ( ! empty( $sizes['sizes'] ) ) {

			foreach ( $sizes['sizes'] as $key => $size ) {
				// Use the same method image_downsize() does.
				$image_src = wp_get_attachment_image_src( $featured_id, $key );

				if ( ! $image_src ) {
					continue;
				}

				$size_data->$key = $image_src[0];

			}
		}

		return $size_data;
	}

	/**
	 * Get Media Url
	 *
	 * @param Object $post Post.
	 */
	public function get_media_url( $post ) {

		$value = apply_filters( 'appp_media_url', get_post_meta( $post['id'], 'appp_media_url', true ), $post );

		$data = array();

		if ( ! empty( $value ) ) {
			$data['media_url'] = $value;
		} else {
			return;
		}

		// Optionally add an image when playing the media.
		$thumb = apply_filters( 'appp_media_image', get_post_meta( $post['id'], 'appp_media_image', true ), $post );

		if ( ! empty( $thumb ) ) {
			$data['media_image'] = $thumb;
		}

		return $data;
	}

	/**
	 * Login via API
	 *
	 * @param WP_REST_Request $request Request.
	 * @since 3.6.0
	 */
	public function api_login( $request ) {
		$info['user_login']    = ( $request['username'] ? sanitize_text_field( $request['username'] ) : $_SERVER['PHP_AUTH_USER'] );
		$info['user_password'] = ( $request['password'] ? wp_slash( sanitize_text_field( $request['password'] ) ) : $_SERVER['PHP_AUTH_PW'] );
		$info['remember']      = true;

		if ( empty( $info['user_login'] ) || empty( $info['user_password'] ) ) {

			$msg = array(
				'success' => false,
				'data'    => array(
					'message' => apply_filters( 'appp_login_error', __( 'Missing required fields.', 'apppresser' ), '' ),
					'success' => false,
				),
			);

			return rest_ensure_response( $msg );
		}

		do_action( 'appp_before_signon', $info );

		$user = wp_authenticate( $info['user_login'], $info['user_password'] );

		do_action( 'appp_login_header' );

		if ( is_wp_error( $user ) ) {

			$msg = array(
				'success' => false,
				'data'    => array(
					'message' => apply_filters( 'appp_login_error', __( 'The log in you have entered is not valid.', 'apppresser' ), $info['user_login'] ),
					'success' => false,
				),
			);

			return rest_ensure_response( $msg );

		}

		do_action( 'appp_logged_in', $user );

		// If everything is successfull, return login response.
		return AppPresser_User::getLoginResponse( $user );
	}

	/**
	 * Refresh login data via API
	 *
	 * @since 4.3.2
	 */
	public function api_login_refresh() {
		$current_user = wp_get_current_user();

		return AppPresser_User::getLoginResponse( $current_user );
	}

	/**
	 * Check permision for the API login refresh endpoint
	 */
	public function api_login_refresh_permission_check() {
		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to see this resource.', 'apppresser' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Logout via API
	 *
	 * @since 3.6.0
	 */
	public function api_logout() {

		do_action( 'appp_logout_header' );

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		wp_logout();

		$response = array(
			'message' => __( 'Logout success.', 'apppresser' ),
			'success' => true,
		);

		$redirect = self::get_logout_redirect();
		if ( $redirect ) {
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
		$username   = isset( $request['username'] ) ? sanitize_text_field( $request['username'] ) : '';
		$email      = isset( $request['email'] ) ? sanitize_email( $request['email'] ) : '';
		$first_name = isset( $request['first_name'] ) ? sanitize_text_field( $request['first_name'] ) : '';
		$last_name  = isset( $request['last_name'] ) ? sanitize_text_field( $request['last_name'] ) : '';
		$password   = isset( $request['password'] ) ? wp_slash( sanitize_text_field( $request['password'] ) ) : '';

		if ( ! get_option( 'users_can_register' ) ) {
			return new WP_Error(
				'rest_invalid_registration',
				__( 'Registration is disabled.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( empty( $username ) || empty( $email ) ) {

			return new WP_Error(
				'rest_invalid_registration',
				__( 'Missing required fields.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( email_exists( $email ) || username_exists( $username ) ) {
			return new WP_Error(
				'rest_invalid_registration',
				__( 'Email or username already exists.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( empty( $password ) ) {
			$password = wp_generate_password( 8 );
		}

		$userdata = array(
			'user_login' => $username,
			'user_pass'  => $password,
			'user_email' => $email,
			'first_name' => $first_name,
			'last_name'  => $last_name,
		);

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error(
				'rest_invalid_registration',
				__( 'Something went wrong with registration.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		update_user_meta( $user_id, 'app_unverified', true );

		$mail_sent = $this->send_verification_code( $request );

		if ( ! $mail_sent ) {
			return new WP_Error(
				'rest_invalid_registration',
				__( 'We could not send your verification code, please contact support.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		do_action( 'appp_register_unverified', $user_id );

		$success = __( 'Your verification code has been sent, please check your email.', 'apppresser' );

		$retval = rest_ensure_response( $success );

		return $retval;
	}

	/**
	 * Emails a verification code
	 *
	 * @param WP_REST_Request $request Request.
	 * @since 3.6.0
	 */
	public function send_verification_code( $request ) {
		$email    = sanitize_text_field( $request['email'] );
		$username = sanitize_text_field( $request['username'] );

		if ( empty( $email ) || empty( $username ) ) {
			return new WP_Error(
				'rest_invalid_verification',
				__( 'Missing required field.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! email_exists( $email ) || ! username_exists( $username ) ) {
			return new WP_Error(
				'rest_invalid_verification',
				__( 'Invalid username or email.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_Error(
				'rest_invalid_verification',
				__( 'Invalid user.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		// Generate verification code.
		$verification_code = wp_generate_password( 20, false );
		update_user_meta( $user->ID, 'app_verification_code', $verification_code );

		// Now send verification code.
		$subject = __( 'Your Verification Code', 'apppresser' );
		$subject = apply_filters( 'appp_verification_email_subject', $subject );

		$content = sprintf( __( "Hi, thanks for registering! Here is your verification code: %s \n\nPlease enter this code in the app. \n\nThanks!", 'apppresser' ), $verification_code );
		$content = apply_filters( 'appp_verification_email', $content, $verification_code );

		$mail_sent = wp_mail( $email, $subject, $content );

		return $mail_sent;
	}

	/**
	 * Verify user, then log them in
	 *
	 * @param WP_REST_Request $request Request.
	 * @since 3.6.0
	 */
	public function verify_user( $request ) {

		$email        = sanitize_text_field( $request['email'] );
		$verification = sanitize_text_field( $request['verification'] );

		if ( empty( $email ) || empty( $verification ) ) {
			return new WP_Error(
				'rest_invalid_verification',
				__( 'Missing required field.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		$user = get_users(
			array(
				'meta_key'   => 'app_verification_code',
				'meta_value' => $verification,
			)
		);
		if ( ! $user ) {
			return new WP_Error(
				'rest_invalid_verification',
				__( 'Verification code does not match.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);
		}

		delete_user_meta( $user[0]->ID, 'app_verification_code' );
		delete_user_meta( $user[0]->ID, 'app_unverified' );

		// Log the user in.
		$info                  = array();
		$info['user_login']    = sanitize_text_field( $request['username'] );
		$info['user_password'] = wp_slash( sanitize_text_field( $request['password'] ) );
		$info['remember']      = true;

		$user_signon = wp_signon( $info, false );

		if ( is_wp_error( $user_signon ) || ! $user[0] ) {
			return new WP_Error(
				'rest_invalid_verification',
				__( 'Verification succeeded, please login.', 'apppresser' ),
				array(
					'status' => 200,
				)
			);
		}

		// If everything is successfull, return login response.
		$retval = AppPresser_User::getLoginResponse( $user_signon );

		do_action( 'appp_register_verified', $user_signon->ID );

		return $retval;
	}

	/**
	 * Disallow login if user is unverified
	 *
	 * @param object $user User.
	 * @since 3.6.0
	 */
	public function check_app_unverified( $user ) {

		if ( get_user_meta( $user->ID, 'app_unverified', 1 ) ) {

			return new WP_Error(
				'app_unverified_login',
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
	 * @since 3.2.1
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public static function get_login_redirect() {
		if ( has_filter( 'appp_login_redirect' ) ) {
			$redirect_to = apply_filters( 'appp_login_redirect', '' );

			return self::add_redirect_title( $redirect_to );
		} else {
			return '';
		}
	}

	/**
	 * Get the login redirect for the app's login modal
	 *
	 * @since 3.3.0
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public static function get_logout_redirect() {
		if ( has_filter( 'appp_logout_redirect' ) ) {
			$redirect_to = apply_filters( 'appp_logout_redirect', '' );

			return self::add_redirect_title( $redirect_to );
		} else {
			return '';
		}
	}

	/**
	 * Use the URL to get title or just returns a string if it is a app custom page slug
	 *
	 * @since 3.3.0
	 * @param string $redirect_to The Redirect to URL.
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public static function add_redirect_title( $redirect_to ) {
		if ( is_string( $redirect_to ) && strpos( $redirect_to, 'http' ) !== false ) {
			// A URL.
			$post_id = url_to_postid( $redirect_to );

			return array(
				'url'   => $redirect_to,
				'title' => ( $post_id ) ? get_the_title( $post_id ) : '',
			);
		} else {
			// An app custom page slug.
			return $redirect_to;
		}
	}

	/**
	 * API password reset
	 * First, post the user email, and a reset code is sent to them
	 * Next, post the code and new password, and it's changed
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function reset_password( $request ) {
		$return = array(
			'success' => false,
			'message' => 'Missing required fields.',
		);

		// Sanitize and validate fields.
		$code     = isset( $request['code'] ) ? sanitize_text_field( $request['code'] ) : '';
		$password = isset( $request['password'] ) ? wp_slash( sanitize_text_field( $request['password'] ) ) : '';
		$email    = isset( $request['email'] ) ? sanitize_email( $request['email'] ) : '';

		if ( ! empty( $code ) && ! empty( $password ) ) {
			$return = $this->validate_reset_password( $code, $password );
		} elseif ( ! empty( $email ) && is_email( $email ) ) {
			$return = $this->get_pw_reset_code( $email );
		}

		return $return;
	}

	/**
	 * Returns the installed plugins and their version from a predefind list
	 */
	public function system_information() {
		// Check if get_plugins() function exists. This is required on the front end of the
		// site, since it is in a file that is normally only loaded in the admin.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();

		// List of a predefined plugins that we need to check.
		$predefined_plugins_to_check = array(
			'appcommerce',
			'appcommunity',
			'applms',
			'apppresser',
			'apppresser-in-app-purchases',
		);

		$response = array();
		foreach ( $all_plugins as $current_plugin ) {
			if ( in_array( $current_plugin['TextDomain'], $predefined_plugins_to_check, true ) ) {
				$response[ $current_plugin['TextDomain'] ] = $current_plugin['Version'];
			}
		}

		return $response;
	}

	/**
	 * API password reset
	 *
	 * @param string $email Email.
	 */
	public function get_pw_reset_code( $email ) {
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			// Create a unique code to use one time.
			$hash = wp_generate_password( 20, false );
			update_user_meta( $user->ID, 'app_hash', $hash );

			$subject = __( 'App Password Reset', 'apppresser' );
			$subject = apply_filters( 'appp_pw_reset_email_subject', $subject );

			$message = __( 'Enter the code into the app to reset your password. Code: ', 'apppresser' ) . $hash;
			$message = apply_filters( 'appp_pw_reset_email', $message, $hash );

			wp_mail( $user->user_email, $subject, $message );

			$return = array(
				'success'  => true,
				'got_code' => true,
				'message'  => __( 'Please check your email for your verification code.', 'apppresser' ),
			);
		} else {
			$return = array(
				'success' => false,
				'message' => __( 'The email you have entered is not valid.', 'apppresser' ),
			);
		}

		return $return;
	}

	/**
	 * Validate the reset code, then reset the password
	 *
	 * @param string $code Code.
	 * @param string $password Password.
	 */
	public function validate_reset_password( $code, $password ) {
		$user = get_users(
			array(
				'meta_key'   => 'app_hash',
				'meta_value' => $code,
			)
		);
		if ( $user ) {
			wp_update_user(
				array(
					'ID'        => $user[0]->data->ID,
					'user_pass' => $password,
				)
			);
			// Delete our one time access code.
			delete_user_meta( $user[0]->data->ID, 'app_hash' );

			$return = array(
				'message'    => __( 'Password has been changed, please login.', 'apppresser' ),
				'pw_changed' => true,
				'success'    => true,
			);
		} else {
			$return = array(
				'success' => false,
				'message' => __( 'The code you have entered is not valid.', 'apppresser' ),
			);
		}

		return $return;
	}

	/**
	 * Endpoint to submit a form from the ap-form component
	 *
	 * @param array $data Data.
	 */
	public function submit_form( $data ) {
		do_action( 'appp_form_submission', $data, get_current_user_id() );
		return $data;
	}

	/**
	 * Permissions callback for submitting data
	 */
	public function form_permissions() {

		$has_permission = false;

		if ( is_user_logged_in() ) {
			$has_permission = true;
		}

		$has_permission = apply_filters( 'appp_form_permissions', $has_permission );

		return $has_permission;
	}

	/**
	 * Permissions callback for myapppresser verify endpoint
	 */
	public function myappp_permissions( $request ) {
		$verification_key = $request['key'];
		if ( empty( $verification_key ) ) {
			return new WP_Error( 'unauthorized', 'Authentication missing', array( 'status' => 403 ) );
		}

		$ap3_app_id        = appp_get_setting( 'ap3_app_id' );
		$ap3_site_slug     = appp_get_setting( 'ap3_site_slug' );
		$ap4_account_email = appp_get_setting( 'ap4_account_email' );
		$current_hash      = hash( 'sha256', $ap3_app_id . $ap3_site_slug . $ap4_account_email );

		if ( $verification_key !== $current_hash ) {
			return new WP_Error( 'unauthorized', 'Authentication failed', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * The endpoint that will verify all the needed plugins
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function myappp_verify( $request ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// Plugins.
		$plugins                                  = array();
		$plugins[]['apppresser']                  = AppPresser::VERSION;
		$plugins[]['jwt-auth']                    = $this->get_plugin_data( 'jwt-authentication-for-wp-rest-api/jwt-auth.php' );
		$plugins[]['appcommunity']                = $this->get_appcommunity_data();
		$plugins[]['buddypress']                  = $this->get_plugin_data( 'buddypress/bp-loader.php' );
		$plugins[]['buddyboss']                   = $this->get_plugin_data( 'buddyboss-platform/bp-loader.php' );
		$plugins[]['appcommerce']                 = $this->get_appcommerce_data();
		$plugins[]['woocommerce']                 = $this->get_plugin_data( 'woocommerce/woocommerce.php' );
		$plugins[]['applms']                      = $this->get_applms_data();
		$plugins[]['learndash']                   = $this->get_plugin_data( 'sfwd-lms-1/sfwd_lms.php' );
		$plugins[]['apppresser-in-app-purchases'] = $this->get_in_app_purchase_data();
		$plugins[]['apppresser-push']             = $this->get_apppush_data();
		$plugins[]['appsocial']                   = $this->get_appsocial_data();
		$plugins[]['apppresser-camera']           = $this->get_apppresser_camera_data();
		$plugins[]['apppresser-bridge']           = $this->get_apppresser_bridge_data();
		$response['plugins']                      = $plugins;
		// Themes.
		$themes                = array();
		$themes[]['ion-theme'] = $this->get_ion_theme_data();
		$response['themes']    = $themes;
		$response['success']   = $this->verify_site_slug_app_id( $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Get the plugin data
	 *
	 * @param string $plugin_file Request.
	 */
	private function get_plugin_data( $plugin_file ) {
		$plugin_list = get_option( 'active_plugins' );
		if ( in_array( $plugin_file, $plugin_list, true ) ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );

			return $plugin_data['Version'];
		}

		return false;
	}

	/**
	 * Get AppCommunity data
	 */
	private function get_appcommunity_data() {
		if ( class_exists( 'AppCommunity' ) ) {
			if ( defined( 'AppCommunity::APPP_KEY' ) ) {
				return AppCommunity_VER . ' - Legacy';
			} else {
				return AppCommunity_VER;
			}
		}

		return false;
	}

	/**
	 * Get AppCommerce data
	 */
	private function get_appcommerce_data() {
		if ( class_exists( 'AppCommerce' ) ) {
			if ( defined( 'AppCommerce::APPP_KEY' ) ) {
				return AppCommerce::$version . ' - Legacy';
			} else {
				return AppCommerce::$version;
			}
		}

		return false;
	}

	/**
	 * Get AppLMS data
	 */
	private function get_applms_data() {
		if ( class_exists( 'AppLMS' ) ) {
			if ( defined( 'AppLMS::APPP_KEY' ) ) {
				return AppLMS::APPP_VER . ' - Legacy';
			} else {
				return AppLMS::VERSION;
			}
		}

		return false;
	}

	/**
	 * Get In App Purchase data
	 */
	private function get_in_app_purchase_data() {
		if ( class_exists( 'AppPresser_IAP' ) ) {
			return AppPresser_IAP::VERSION;
		}

		return false;
	}

	/**
	 * Get AppPush data
	 */
	private function get_apppush_data() {
		if ( class_exists( 'AppPresser_Notifications' ) ) {
			if ( defined( 'AppPresser_Notifications::APPP_KEY' ) ) {
				return AppPresser_Notifications::VERSION . ' - Legacy';
			} else {
				return AppPresser_Notifications::VERSION;
			}
		}

		return false;
	}

	/**
	 * Get AppSocial data
	 */
	private function get_appsocial_data() {
		if ( class_exists( 'AppSocial' ) ) {
			return AppSocial::VERSION;
		}

		return false;
	}

	/**
	 * Get AppCamera data
	 */
	private function get_apppresser_camera_data() {
		if ( class_exists( 'AppPresser_Camera' ) ) {
			if ( defined( 'AppPresser_Camera::APPP_KEY' ) ) {
				return AppPresser_Camera::VERSION . ' - Legacy';
			} else {
				return AppPresser_Camera::VERSION;
			}
		}

		return false;
	}

	/**
	 * Get AppPresser Bridge data
	 */
	private function get_apppresser_bridge_data() {
		if ( class_exists( 'AppPresserBridge' ) ) {
			return AppPresserBridge::VERSION;
		}

		return false;
	}

	/**
	 * Get Ion Theme data
	 */
	private function get_ion_theme_data() {
		$theme_name = 'ap3-ion-theme';
		$class_file = 'inc/classes/AppPresser_AP3_Customizer.php';
		// Check if the theme exists.
		$theme = wp_get_theme( $theme_name );
		if ( $theme->exists() ) {
			// Build the file path.
			$theme_root = get_theme_root() . '/' . $theme_name;
			$file_path  = $theme_root . '/' . $class_file;
			if ( file_exists( $file_path ) ) {
				return $theme->Version . ' - Legacy';
			} else {
				return $theme->Version;
			}
		}

		return false;
	}

	/**
	 * Verify the site slug and App ID
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function verify_site_slug_app_id( $request ) {
		if ( isset( $request['ap3_site_slug'] ) && isset( $request['ap3_app_id'] ) ) {
			$site_slug = appp_get_setting( 'ap3_site_slug' );
			$app_id    = appp_get_setting( 'ap3_app_id' );
			if ( $request['ap3_site_slug'] === $site_slug && $request['ap3_app_id'] === $app_id ) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
}

global $apppresser_wpapi_mods;
$apppresser_wpapi_mods = new AppPresser_WPAPI_Mods();
$apppresser_wpapi_mods->hooks();
