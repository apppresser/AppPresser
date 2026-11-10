<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * REST API Cart controller
 *
 * Handles requests to the /cart endpoint.
 *
 * @author   AppPresser, forked from Sébastien Dumont's CoCart plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Cart controller class.
 *
 * @package WooCommerce Cart REST API/API
 */
class AppCommerce_Cart_Controller_V1 {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'appcommerce/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'cart';

	/**
	 * Register the routes for cart.
	 *
	 * @access public
	 */
	public function register_routes() {
		// View Cart - appcommerce/v1/cart (GET)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cart' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'thumb' => array(
						'default' => null,
					),
				),
			)
		);

		// Count Items in Cart - appcommerce/v1/cart/count-items (GET)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/count-items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cart_contents_count' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'return' => array(
						'default' => 'numeric',
					),
				),
			)
		);

		// Get Cart Totals - appcommerce/v1/cart/totals (GET)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/totals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_totals' ),
				'permission_callback' => '__return_true',
			)
		);

		// View Cart - appcommerce/v1/cart/clear (POST)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clear',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_cart' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get misc data that we don't have elsewhere (GET)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/misc',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_misc_data' ),
				'permission_callback' => '__return_true',
			)
		);

		// Add Item - appcommerce/v1/cart/add (POST)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_to_cart' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id'     => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
					'quantity'       => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
					'variation_id'   => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
					'variation'      => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_array( $param );
						},
					),
					'cart_item_data' => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_array( $param );
						},
					),
				),
			)
		);

		// Calculate Cart Total - appcommerce/v1/cart/calculate (POST)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/calculate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'calculate_totals' ),
				'permission_callback' => '__return_true',
			)
		);

		// Update, Remove or Restore Item - appcommerce/v1/cart/cart-item (GET, POST, DELETE)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cart-item',
			array(
				'args' => array(
					'cart_item_key' => array(
						'description' => __( 'The cart item key is what identifies the item in the cart.', 'appcommerce' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'restore_item' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'quantity' => array(
							'default'           => 1,
							'validate_callback' => function ( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	} // register_routes()

	/**
	 * Get cart.
	 *
	 * @access public
	 * @since  1.0.0
	 * @param  array $data
	 * @return WP_REST_Response
	 */
	public function get_cart( $data = array() ) {

		$wc_cart = WC()->cart;
		$cart    = $this->get_cart_contents();
		$totals  = $wc_cart->get_totals();
		$count   = $wc_cart->get_cart_contents_count();

		if ( $this->get_cart_contents_count( array( 'return' => 'numeric' ) ) <= 0 ) {
			return new WP_REST_Response( __( 'Cart is empty!', 'appcommerce' ), 200 );
		}

		$show_thumb = ! empty( $data['thumb'] ) ? $data['thumb'] : false;

		$returnArray = array();

		foreach ( $cart as $item_key => $cart_item ) {
			$_product = apply_filters( 'wc_cart_rest_api_cart_item_product', $cart_item['data'], $cart_item, $item_key );

			// Adds the product name as a new variable.
			$cart[ $item_key ]['product_name'] = $_product->get_name();

			// If main product thumbnail is requested then add it to each item in cart.
			if ( $show_thumb ) {
				$thumbnail_id = apply_filters( 'wc_cart_rest_api_cart_item_thumbnail', $_product->get_image_id(), $cart_item, $item_key );

				$thumbnail_src = wp_get_attachment_image_src( $thumbnail_id, 'woocommerce_thumbnail' );

				// Add main product image as a new variable.
				$cart[ $item_key ]['product_image'] = esc_url( $thumbnail_src[0] );
			}

			$returnArray['products'][] = $cart[ $item_key ];
		}

		$totals['cart_contents_count'] = $count;

		$returnArray['cart_total'] = $totals;

		$returnArray['cart_total']['cart_contents_total'] = number_format( (float) $totals['cart_contents_total'], 2, '.', '' );

		return new WP_REST_Response( $returnArray, 200 );
	} // END get_cart()

	/**
	 * Get cart contents count.
	 *
	 * @access public
	 * @since  1.0.0
	 * @param  array $data
	 * @return string|WP_REST_Response
	 */
	public function get_cart_contents_count( $data = array() ) {
		$count = WC()->cart->get_cart_contents_count();

		$return = ! empty( $data['return'] ) ? $data['return'] : '';

		if ( $return != 'numeric' && $count <= 0 ) {
			return new WP_REST_Response( __( 'There are no items in the cart!', 'appcommerce' ), 200 );
		}

		return $count;
	} // END get_cart_contents_count()

	/**
	 * Clear cart.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return WP_ERROR|WP_REST_Response
	 */
	public function clear_cart() {

		WC()->cart->empty_cart();
		WC()->session->set( 'cart', array() ); // Empty the session cart data

		if ( WC()->cart->is_empty() ) {
			return new WP_REST_Response( __( 'Cart is cleared.', 'appcommerce' ), 200 );
		} else {
			return new WP_Error( 'wc_cart_rest_clear_cart_failed', __( 'Clearing the cart failed!', 'appcommerce' ), array( 'status' => 500 ) );
		}
	} // END clear_cart()

	/**
	 * Validate the product id argument.
	 *
	 * @access protected
	 * @since  1.0.0
	 * @param  int $product_id
	 * @return WP_Error
	 */
	protected function validate_product_id( $product_id ) {
		if ( $product_id <= 0 ) {
			return new WP_Error( 'wc_cart_rest_product_id_required', __( 'Product ID number is required!', 'appcommerce' ), array( 'status' => 500 ) );
		}

		if ( ! is_numeric( $product_id ) ) {
			return new WP_Error( 'wc_cart_rest_product_id_not_numeric', __( 'Product ID must be numeric!', 'appcommerce' ), array( 'status' => 500 ) );
		}
	} // END validate_product_id()

	/**
	 * Validate the product quantity argument.
	 *
	 * @access protected
	 * @since  1.0.0
	 * @param  int $quantity
	 * @return WP_Error
	 */
	protected function validate_quantity( $quantity ) {
		if ( ! is_numeric( $quantity ) ) {
			return new WP_Error( 'wc_cart_rest_quantity_not_numeric', __( 'Quantity must be numeric!', 'appcommerce' ), array( 'status' => 500 ) );
		}
	} // END validate_quantity()

	/**
	 * Validate product before it is added to the cart, updated or removed.
	 *
	 * @access protected
	 * @since  1.0.0
	 * @param  int $product_id
	 * @param  int $quantity
	 * @return WP_Error
	 */
	protected function validate_product( $product_id = null, $quantity = 1 ) {
		$this->validate_product_id( $product_id );

		$this->validate_quantity( $quantity );
	} // END validate_product()

	/**
	 * Add to Cart.
	 *
	 * @access public
	 * @since  1.0.0
	 * @param  array $data
	 * @return WP_Error|WP_REST_Response
	 */
	public function add_to_cart( $data = array() ) {
		$product_id     = ! isset( $data['product_id'] ) ? 0 : absint( $data['product_id'] );
		$quantity       = ! isset( $data['quantity'] ) ? 1 : absint( $data['quantity'] );
		$variation_id   = ! isset( $data['variation_id'] ) ? 0 : absint( $data['variation_id'] );
		$variation      = ! isset( $data['variation'] ) ? array() : $data['variation'];
		$cart_item_data = ! isset( $data['cart_item_data'] ) ? array() : $data['cart_item_data'];

		$this->validate_product( $product_id, $quantity );

		$product_data = wc_get_product( $variation_id ? $variation_id : $product_id );

		if ( $quantity <= 0 || ! $product_data || 'trash' === $product_data->get_status() ) {
			return new WP_Error( 'wc_cart_rest_product_does_not_exist', __( 'Warning: This product does not exist!', 'appcommerce' ), array( 'status' => 500 ) );
		}

		// Generate a ID based on product ID, variation ID, variation data, and other cart item data.
		$cart_id = WC()->cart->generate_cart_id( $product_id, $variation_id, $variation, $cart_item_data );

		// Find the cart item key in the existing cart.
		$cart_item_key = $this->find_product_in_cart( $cart_id );

		// Force quantity to 1 if sold individually and check for existing item in cart.
		if ( $product_data->is_sold_individually() ) {
			$quantity = 1;

			$cart_contents = $this->get_cart_contents();

			$found_in_cart = apply_filters( 'woocommerce_add_to_cart_sold_individually_found_in_cart', $cart_item_key && $cart_contents[ $cart_item_key ]['quantity'] > 0, $product_id, $variation_id, $cart_item_data, $cart_id );

			if ( $found_in_cart ) {
				/* translators: %s: product name */
				return new WP_Error( 'wc_cart_rest_product_sold_individually', sprintf( __( 'You cannot add another "%s" to your cart.', 'appcommerce' ), $product_data->get_name() ), array( 'status' => 500 ) );
			}
		}

		// Product is purchasable check.
		if ( ! $product_data->is_purchasable() ) {
			return new WP_Error( 'wc_cart_rest_cannot_be_purchased', __( 'Sorry, this product cannot be purchased.', 'appcommerce' ), array( 'status' => 500 ) );
		}

		// Stock check - only check if we're managing stock and backorders are not allowed.
		if ( ! $product_data->is_in_stock() ) {
			return new WP_Error( 'wc_cart_rest_product_out_of_stock', sprintf( __( 'You cannot add &quot;%s&quot; to the cart because the product is out of stock.', 'appcommerce' ), $product_data->get_name() ), array( 'status' => 500 ) );
		}
		if ( ! $product_data->has_enough_stock( $quantity ) ) {
			/* translators: 1: product name 2: quantity in stock */
			return new WP_Error( 'wc_cart_rest_not_enough_in_stock', sprintf( __( 'You cannot add that amount of &quot;%1$s&quot; to the cart because there is not enough stock (%2$s remaining).', 'appcommerce' ), $product_data->get_name(), wc_format_stock_quantity_for_display( $product_data->get_stock_quantity(), $product_data ) ), array( 'status' => 500 ) );
		}

		// Stock check - this time accounting for whats already in-cart.
		if ( $product_data->managing_stock() ) {
			$products_qty_in_cart = WC()->cart->get_cart_item_quantities();

			if ( isset( $products_qty_in_cart[ $product_data->get_stock_managed_by_id() ] ) && ! $product_data->has_enough_stock( $products_qty_in_cart[ $product_data->get_stock_managed_by_id() ] + $quantity ) ) {
				return new WP_Error(
					'wc_cart_rest_not_enough_stock_remaining',
					sprintf(
						__( 'You cannot add that amount to the cart &mdash; we have %1$s in stock and you already have %2$s in your cart.', 'appcommerce' ),
						wc_format_stock_quantity_for_display( $product_data->get_stock_quantity(), $product_data ),
						wc_format_stock_quantity_for_display( $products_qty_in_cart[ $product_data->get_stock_managed_by_id() ], $product_data )
					),
					array( 'status' => 500 )
				);
			}
		}

		// Add item to cart.
		$item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );

		// Return response to added item to cart or return error.
		if ( $item_key ) {
			$data = WC()->cart->get_cart_item( $item_key );

			do_action( 'wc_cart_rest_add_to_cart', $item_key, $data );

			if ( is_array( $data ) ) {
				return new WP_REST_Response( $data, 200 );
			}
		} else {
			return new WP_Error( 'wc_cart_rest_cannot_add_to_cart', sprintf( __( 'You cannot add "%s" to your cart.', 'appcommerce' ), $product_data->get_name() ), array( 'status' => 500 ) );
		}
	} // END add_to_cart()

	/**
	 * Remove Item in Cart.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @version 1.0.3
	 * @param   array $data
	 * @return  WP_Error|WP_REST_Response
	 */
	public function remove_item( $data = array() ) {
		$cart_item_key = ! isset( $data['cart_item_key'] ) ? '0' : wc_clean( $data['cart_item_key'] );

		if ( $cart_item_key != '0' ) {
			if ( WC()->cart->remove_cart_item( $cart_item_key ) ) {
				return new WP_REST_Response( __( 'Item has been removed from cart.', 'appcommerce' ), 200 );
			} else {
				return new WP_ERROR( 'wc_cart_rest_can_not_remove_item', __( 'Unable to remove item from cart.', 'appcommerce' ), array( 'status' => 500 ) );
			}
		} else {
			return new WP_ERROR( 'wc_cart_rest_cart_item_key_required', __( 'Cart item key is required!', 'appcommerce' ), array( 'status' => 500 ) );
		}
	} // END remove_item()

	/**
	 * Restore Item in Cart.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @version 1.0.3
	 * @param   array $data
	 * @return  WP_Error|WP_REST_Response
	 */
	public function restore_item( $data = array() ) {
		$cart_item_key = ! isset( $data['cart_item_key'] ) ? '0' : wc_clean( $data['cart_item_key'] );

		if ( $cart_item_key != '0' ) {
			if ( WC()->cart->restore_cart_item( $cart_item_key ) ) {
				return new WP_REST_Response( __( 'Item has been restored to the cart.', 'appcommerce' ), 200 );
			} else {
				return new WP_ERROR( 'wc_cart_rest_can_not_restore_item', __( 'Unable to restore item to the cart.', 'appcommerce' ), array( 'status' => 500 ) );
			}
		} else {
			return new WP_ERROR( 'wc_cart_rest_cart_item_key_required', __( 'Cart item key is required!', 'appcommerce' ), array( 'status' => 500 ) );
		}
	} // END restore_item()

	/**
	 * Update Item in Cart.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @version 1.0.3
	 * @param   array $data
	 * @return  WP_Error|WP_REST_Response
	 */
	public function update_item( $data = array() ) {
		$cart_item_key = ! isset( $data['cart_item_key'] ) ? '0' : wc_clean( $data['cart_item_key'] );
		$quantity      = ! isset( $data['quantity'] ) ? 1 : absint( $data['quantity'] );

		// Allows removing of items if quantity is zero should for example the item was with a product bundle.
		if ( $quantity === 0 ) {
			return $this->remove_item( $data );
		}

		$this->validate_quantity( $quantity );

		if ( $cart_item_key != '0' ) {
			$current_data = WC()->cart->get_cart_item( $cart_item_key ); // Fetches the cart item data before it is updated.

			if ( WC()->cart->set_quantity( $cart_item_key, $quantity ) ) {

				$new_data = WC()->cart->get_cart_item( $cart_item_key );

				$product_id   = ! isset( $new_data['product_id'] ) ? 0 : absint( $new_data['product_id'] );
				$variation_id = ! isset( $new_data['variation_id'] ) ? 0 : absint( $new_data['variation_id'] );

				$product_data = wc_get_product( $variation_id ? $variation_id : $product_id );

				if ( $quantity != $new_data['quantity'] ) {
					do_action( 'wc_cart_rest_item_quantity_changed', $cart_item_key, $new_data );
				}

				// Return response based on product quantity increment.
				if ( $quantity > $current_data['quantity'] ) {
					return new WP_REST_Response( sprintf( __( 'The quantity for "%1$s" has increased to "%2$s".', 'appcommerce' ), $product_data->get_name(), $new_data['quantity'] ), 200 );
				} elseif ( $quantity < $current_data['quantity'] ) {
					return new WP_REST_Response( sprintf( __( 'The quantity for "%1$s" has decreased to "%2$s".', 'appcommerce' ), $product_data->get_name(), $new_data['quantity'] ), 200 );
				} else {
					return new WP_REST_Response( sprintf( __( 'The quantity for "%s" has not changed.', 'appcommerce' ), $product_data->get_name() ), 200 );
				}
			} else {
				return new WP_ERROR( 'wc_cart_rest_can_not_update_item', __( 'Unable to update item quantity in cart.', 'appcommerce' ), array( 'status' => 500 ) );
			}
		} else {
			return new WP_ERROR( 'wc_cart_rest_cart_item_key_required', __( 'Cart item key is required!', 'appcommerce' ), array( 'status' => 500 ) );
		}
	} // END update_item()

	/**
	 * Calculate Cart Totals.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return WP_REST_Response
	 */
	public function calculate_totals() {
		if ( $this->get_cart_contents_count( array( 'return' => 'numeric' ) ) <= 0 ) {
			return new WP_REST_Response( __( 'No items in cart to calculate totals.', 'appcommerce' ), 200 );
		}

		WC()->cart->calculate_totals();

		return new WP_REST_Response( __( 'Cart totals have been calculated.', 'appcommerce' ), 200 );
	} // END calculate_totals()

	/**
	 * Returns all calculated totals.
	 *
	 * @access public
	 * @since  1.0.0
	 * @return array
	 */
	public function get_totals() {
		$totals = WC()->cart->get_totals();

		return $totals;
	} // END get_totals()

	/*
	 * Get data for the app that we can't find elsewhere
	 */
	public function get_misc_data() {

		$response = array();

		$response['account']['url'] = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );

		return $response;
	}

	/**
	 * Gets the cart contents.
	 */
	public function get_cart_contents() {
		$cart_contents = ( is_user_logged_in() ) ? WC()->cart->get_cart() : WC()->session->cart;

		foreach ( $cart_contents as $item_key => $cart_item ) {
			// If product data is missing then get product data and apply.
			if ( ! isset( $cart_item['data'] ) ) {
				$cart_item['data']                  = wc_get_product( $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'] );
				$cart_contents[ $item_key ]['data'] = $cart_item['data'];
			}
		}

		return $cart_contents;
	}

	/**
	 * Check if product is in the cart and return cart item key if found.
	 *
	 * Cart item key will be unique based on the item and its properties, such as variations.
	 *
	 * @access public
	 * @since  2.0.0
	 * @param  string $cart_item_key of product to find in the cart.
	 * @return string Returns the same cart item key if valid.
	 */
	public function find_product_in_cart( $cart_item_key = '' ) {
		if ( ! empty( $cart_item_key ) ) {
			if ( is_array( self::get_cart() ) && null !== self::get_cart( array(), $cart_item_key ) ) {
				return $cart_item_key;
			}
		}

		return '';
	} // END find_product_in_cart()
} // END class
