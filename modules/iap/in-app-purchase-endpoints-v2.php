<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_IAP2 extends AppPresser_IAP {

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

		add_action( 'rest_api_init', array( $this, 'api_endpoints' ) );

	}

	public static function do_error_log( $err, $msg ) {
		if( class_exists('AppPresser_Logger') ) {
			AppPresser_Logger::log( $err, $msg );
		}
	}

	public function api_endpoints() {

		register_rest_route( 'appp/v2', '/in-app-purchase-register', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'iap_register_login_v2'),
                'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'appp/v2', '/in-app-purchase-create-user', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'iap_create_user'),
                'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( 'appp/v2', '/in-app-purchase-validate', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'iap_validate_subscription_v2'),
                'permission_callback' => '__return_true',
			),
		) );

	}

	/**
	 * Create user if they don't exist. This is done before the purchase so that we have a user to associate with the purchase when it is complete.
	 * We don't login here, login happens after a successful purchase.
	 *
	 * @since 2.0.3
	 */
	public function iap_create_user( $request ) {

		if( empty( $request['email'] ) ) {

			return new WP_Error( 'rest_invalid_registration',
				__( 'Missing required fields.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);

		}

		self::do_error_log( 'New in app purchase registration', $request['email'] );

		$email = ( isset( $request['email'] ) ? urldecode( $request['email'] ) : null );
		
		// maybe create a new user
		if ( !email_exists( $email ) ) {

			// register new user
			if( empty( $request['password'] ) ) {
				$password = wp_generate_password( 8 );
			} else {
				$password = $request['password'];
			}

			$name = ( isset( $request['first_name'] ) ? $request['first_name'] : null );

			$userdata = array(
			    'user_login'  =>  $email,
			    'user_pass'   =>  $password,
				'user_email'  =>  $email
			);

			if( $name ) {
				$userdata['user_nicename'] = $name;
				$userdata['display_name'] = $name;
				$userdata['first_name'] = $name;
				$userdata['nickname'] = $name;
			}

			$user_id = wp_insert_user( $userdata );

			if ( is_wp_error( $user_id ) ) {
				return new WP_Error( 'rest_invalid_registration',
					__( 'Your purchase was successful, but something went wrong with registration. Please try again.', 'apppresser' ),
					array(
						'status' => 404,
					)
				);
			}

			return array( "success" => true );

		} else {

			// this is still success because all we need is the user to exist
			return array( "success" => true );
		}
	}

	/**
	 * Create user if they don't exist, add meta, login
	 * Only required field is email, and we don't fire iap_new_purchase action because that happens from our validation server.
	 *
	 * @since 2.0
	 */
	public function iap_register_login_v2( $request ) {

		if( empty( $request['email'] ) ) {

			return new WP_Error( 'rest_invalid_registration',
				__( 'Missing required fields.', 'apppresser' ),
				array(
					'status' => 404,
				)
			);

		}

		self::do_error_log( 'New in app purchase registration:', 'Email ' . $request['email'] );

		// if a logged in app user makes a purchase, only email will be sent. Otherwise we will have a username.
		$email = ( isset( $request['email'] ) ? urldecode( $request['email'] ) : null );
		
		// maybe create a new user
		if ( !email_exists( $email ) ) {

			// register new user
			if( empty( $request['password'] ) ) {
				$password = wp_generate_password( 8 );
			} else {
				$password = $request['password'];
			}

            // create a username - Using email for username instead, just leaving this code here in case we need it later
            // $exploded = explode('@', $email );
            // $username = $exploded[0];
            // $username = $this->clean_string( $username );

            // if username exists, make it longer
            // if( username_exists( $username ) ) {
            //     $username = strval( $username . rand(11,99) );
			// }

			$name = ( isset( $request['first_name'] ) ? $request['first_name'] : null );

			$userdata = array(
			    'user_login'  =>  $email,
			    'user_pass'   =>  $password,
				'user_email'  =>  $email
			);

			if( $name ) {
				$userdata['user_nicename'] = $name;
				$userdata['display_name'] = $name;
				$userdata['first_name'] = $name;
				$userdata['nickname'] = $name;
			}

			$user_id = wp_insert_user( $userdata );

			// add this here in case login fails, meta still gets added
			update_user_meta( $user_id, 'in_app_purchase', true );

			if ( is_wp_error( $user_id ) ) {
				return new WP_Error( 'rest_invalid_registration',
					__( 'Your purchase was successful, but something went wrong with registration. Please try again.', 'apppresser' ),
					array(
						'status' => 404,
					)
				);
			}

			$userdata['user_password'] = $userdata['user_pass'];

			// login the new user
			$user_signon = wp_signon( $userdata );

			if ( is_wp_error( $user_signon ) ) {
				return $user_signon;
			}

			$message = AppPresser_User::getLoginResponse($user_signon);
			
			self::do_error_log( 'User registered', 'Email ' . $email );

		} else if( email_exists( $email ) ) {

			if( empty( $request['password'] ) ) {
				$password = wp_generate_password( 8 );
			} else {
				$password = $request['password'];
			}
			
			self::do_error_log( 'New in app purchase user exists, logging in.', 'Email ' . $email );

			// if user exists, log them in
            $userdata = array(
			    'user_login'    =>  $email,
			    'user_password' =>  $password,
				'user_email'    =>  $email
			);

			// login the new user
			$user_signon = wp_signon( $userdata );

			if ( is_wp_error( $user_signon ) ) {
				return $user_signon;
			}

			$message = AppPresser_User::getLoginResponse($user_signon);

		}

		$retval = rest_ensure_response( $message );

		return $retval;

	}

    // this endpoint is for the fovea receipt validator
    // It gets purchases along with email, and we can fire off new purchase or cancellation actions
    public function iap_validate_subscription_v2($request)
    {
        // applicationUsername will either be just an email, or an email with custom data we need to parse. For example, scott@apppresser.com\12345
        if (isset($request['applicationUsername']) && false !== strpos($request['applicationUsername'], "|")) {
            $user_email = explode("|", $request['applicationUsername'])[0];
        } else {
            $user_email = (isset($request['applicationUsername']) ? $request['applicationUsername'] : null);
        }

        // have to loop through purchases
        $purchases = $request['purchases'];

        if (is_array($purchases) && !empty($purchases)) {
            foreach ($purchases as $purchase) {
                if ($purchase['isExpired']) {
                    if ($this->owns_product($user_email, $purchase['productId'])) {
                        // we get here for products that exist but are not owned, so only fire this if we need to
                        self::do_error_log('User owns product, firing cancellation', $user_email . ' ' . $purchase['productId']);
                        $this->add_remove_meta($user_email, $purchase['productId'], true);
                        do_action('iap_subscription_cancel_request', $user_email, $purchase, $request);
                    }
                    do_action('iap_subscription_cancel_recurring_request', $user_email, $purchase, $request);
                } else {
                    if (!$this->owns_product($user_email, $purchase['productId'])) {
                        self::do_error_log('User does not own product', $user_email . ' ' . $purchase['productId']);
                        $this->add_remove_meta($user_email, $purchase['productId'], false);
                        do_action('iap_new_purchase_request', $user_email, $purchase, $request);
                    }
                    do_action('iap_new_purchase_recurring_request', $user_email, $purchase, $request);
                }
            }
        }

        return true;
    }

    // add or remove a purchase from user meta
    public function add_remove_meta($email, $product_id, $remove = false)
    {
        $user_id = get_user_by('email', $email)->ID;
        if ($user_id) {
            $meta = get_user_meta($user_id, 'in_app_purchase_ids', 1);
            if (empty($meta) && !$remove) {
                // add id to empty meta
                $meta = [$product_id];
            } else if (is_array($meta) && $remove) {
                // remove id from meta
                foreach ($meta as $key => $value) {
                    if ($value === $product_id) {
                        unset($meta[$key]);
                    }
                }
            } else if (is_array($meta) && !$remove && !in_array($product_id, $meta)) {
                // add id to meta
                $meta[] = $product_id;
            }
            self::do_error_log('New meta', serialize($meta));
            update_user_meta($user_id, 'in_app_purchase_ids', $meta);
        } else {
            self::do_error_log('User does not exist', $email);
        }
    }

    // check if a person has purchased a product, and it's still active
    // returns boolean
    public function owns_product($email, $product_id)
    {
        $user_id = get_user_by('email', $email)->ID;
        if ($user_id) {
            $meta = get_user_meta($user_id, 'in_app_purchase_ids', 1);
            if (!empty($meta) && in_array($product_id, $meta)) {
                return true;
            }
        }
        return false;
    }
}

AppPresser_IAP2::get();