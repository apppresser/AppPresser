<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * AppCommerce API endpoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart REST API class.
 */
class AppCommerce_Rest_API {

	/**
	 * Setup class.
	 *
	 * @access public
	 */
	public function __construct() {
		// WC Cart REST API.
		$this->cart_rest_api_init();
	} // END __construct()

	/**
	 * Init WC Cart REST API.
	 *
	 * @access  private
	 * @since   1.0.0
	 * @version 1.0.3
	 */
	private function cart_rest_api_init() {
		// REST API was included starting WordPress 4.4.
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		add_action( 'wp_loaded', array( $this, 'include_cart_controller' ), 5 );

		// Init Cart REST API route.
		add_action( 'rest_api_init', array( $this, 'register_cart_routes' ), 0 );
	} // cart_rest_api_init()

	/**
	 * Include Cart REST API controller.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function include_cart_controller() {

		/**
		 * WooCommerce 3.6+ Compatibility
		 *
		 * Cart and notice functions are not included during a REST request.
		 */
		if ( version_compare( WC_VERSION, '3.6.0', '>=' ) && WC()->is_rest_api_request() == 'wc/' ) {
			require_once WC_ABSPATH . 'includes/wc-cart-functions.php';
			require_once WC_ABSPATH . 'includes/wc-notice-functions.php';

			if ( null === WC()->session ) {
				$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler_Extended' );

				// Prefix session class with global namespace if not already namespaced
				if ( false === strpos( $session_class, '\\' ) ) {
					$session_class = '\\' . $session_class;
				}

				WC()->session = new $session_class();
				WC()->session->init();
			}

			/**
			 * For logged in customers, pull data from their account rather than the
			 * session which may contain incomplete data.
			 */
			if ( null === WC()->customer ) {
				if ( is_user_logged_in() ) {
					WC()->customer = new WC_Customer( get_current_user_id() );
				} else {
					WC()->customer = new WC_Customer( 0, true );
				}
			}

			// Load Cart.
			if ( null === WC()->cart ) {
				WC()->cart = new WC_Cart();
			}
		}

		// REST API v1 controller (Deprecated)
		include_once __DIR__ . '/api/v1/class-appcommerce-cart-controller.php';
		// REST API v2 controller
		include_once __DIR__ . '/api/v2/class-appcommerce-cart-controller.php';
	} // include()

	/**
	 * Register Cart REST API routes.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function register_cart_routes() {
		// v1 of API (Deprecated)
		$controller = new AppCommerce_Cart_Controller_V1();
		$controller->register_routes();
		// v2 of API
		$controller = new AppCommerce_Cart_Controller_V2();
		$controller->register_routes();
	} // END register_cart_route
} // END class

return new AppCommerce_Rest_API();
