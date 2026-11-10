<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * AppCommerce Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart REST API class.
 */
class AppCommerce_Functions extends AppCommerce {

	/**
	 * Setup class.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->hooks();
	}

	public function hooks() {
		// Only add our scripts for the app
		if ( ! empty( $_GET['appcommerce'] ) ) {
			add_action( 'init', array( $this, 'set_cookie_cart' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		add_filter( 'appp_login_data', array( $this, 'maybe_add_id_to_login_data' ), 10, 2 );

		add_filter( 'appcommerce_attribute_terms', array( $this, 'attribute_swatches' ) );
	}

	public function attribute_swatches( $terms ) {

		foreach ( $terms as $key => $option ) :
			$terms[ $key ]->attribute_color = get_term_meta( $option->term_id, 'product_attribute_color', 1 );
			$terms[ $key ]->attribute_image = wp_get_attachment_url( get_term_meta( $option->term_id, 'product_attribute_image', 1 ) );
		endforeach;

		return $terms;
	}

	public function set_cookie_cart() {
		$has_handoff = false;
		foreach ( array( 'customer_id', 'session_expiration', 'session_expiring', 'cookie_hash' ) as $field ) {
			if ( isset( $_GET[ $field ] ) ) {
				$has_handoff = true;
				break;
			}
		}
		if ( $has_handoff ) {
			// A partial or invalid handoff must not continue into checkout processing.
			if ( ! $this->set_woocoomerce_cookie() ) {
				wp_die( esc_html__( 'Invalid or expired checkout session. Please refresh your cart.', 'appcommerce' ), '', array( 'response' => 403 ) );
			}
			// Create the checkout URL and redirect to it
			$checkout_url = apply_filters( 'appcommerce_checkout_url', strtok( $_SERVER['REQUEST_URI'], '?' ) . '?appcommerce=1&appp_bypass=true' );
			wp_redirect( $checkout_url );
			exit;
		}
	}

	private function set_woocoomerce_cookie() {
		$handoff = array();
		foreach ( array( 'customer_id', 'session_expiration', 'session_expiring', 'cookie_hash' ) as $field ) {
			if ( ! isset( $_GET[ $field ] ) || ! is_string( $_GET[ $field ] ) ) {
				return false;
			}
			$handoff[ $field ] = wp_unslash( $_GET[ $field ] );
		}

		// Verify before using the supplied identity or issuing either kind of cookie.
		if ( ! AppCommerce_Checkout_Session::verify( $handoff ) || headers_sent() ) {
			return false;
		}
		$session = new WC_Session_Handler_Extended();
		if ( ! $session->set_checkout_cookie( $handoff ) ) {
			return false;
		}

		$customer_id = $handoff['customer_id'];
		$user        = ctype_digit( $customer_id ) ? get_user_by( 'id', $customer_id ) : false;
		if ( $user instanceof WP_User ) {
			wp_set_current_user( $customer_id, $user->user_login );
			wp_set_auth_cookie( $customer_id );
			do_action( 'wp_login', $user->user_login, $user );
		}
		return true;
	}

	static function is_woocommerce_version_greater_than( $required_version ) {
		// Check if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) ) {
			// Current installed WooCommerce version
			$installed_version = defined( 'WC_VERSION' ) ? WC_VERSION : null;

			return version_compare( $installed_version, $required_version, '>=' );
		}

		return false;
	}

	public function enqueue_scripts() {

		// Only use minified files if SCRIPT_DEBUG is off
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		// wp_enqueue_script( 'appcommerce', $this->plugin_dir() .'includes/assets/js/appcommerce' . $min . '.js', array( 'woocommerce' ), $this::$version, true );

		// wp_localize_script( 'appcommerce', 'appcommerceVars', array( 'apiRoot' => get_rest_url() ) );

		wp_enqueue_style( 'appcommerce', $this->plugin_dir() . 'includes/assets/css/appcommerce' . $min . '.css', array( 'woocommerce' ), $this::$version );
	}

	public function maybe_add_id_to_login_data( $data, $user_id ) {

		if ( isset( $data['user_id'] ) ) {
			return $data;
		}

		$data['user_id'] = $user_id;

		return $data;
	}
}
return new AppCommerce_Functions();
