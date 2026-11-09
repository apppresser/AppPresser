<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


/**
 * REST API Cart controller
 *
 * Handles requests to the /cart endpoint.
 *
 * @author AppPresser, forked from Sébastien Dumont's CoCart plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Cart controller class.
 *
 * @package WooCommerce Cart REST API/API
 */
class AppCommerce_Cart_Controller_V2 {


	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'appcommerce/v2';

	/**
	 * Register the routes for cart.
	 *
	 * @access public
	 */
	public function register_routes() {
		// View products - appcommerce/v2/system_data (GET)
		register_rest_route(
			$this->namespace,
			'/system_status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_system_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// View products - appcommerce/v2/products (GET)
		register_rest_route(
			$this->namespace,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => '__return_true',
			)
		);

		// View product - appcommerce/v2/products/<id> (GET)
		register_rest_route(
			$this->namespace,
			'/products/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product' ),
				'permission_callback' => '__return_true',
			)
		);

		// View product variations - appcommerce/v2/products/<id>/variations (GET)
		register_rest_route(
			$this->namespace,
			'/products/(?P<id>[\d]+)/variations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_variations' ),
				'permission_callback' => '__return_true',
			)
		);

		// View products reviews - appcommerce/v2/products/reviews (GET)
		register_rest_route(
			$this->namespace,
			'/products/reviews',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reviews' ),
				'permission_callback' => '__return_true',
			)
		);

		// View orders - appcommerce/v2/orders (GET)
		register_rest_route(
			$this->namespace,
			'/orders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_orders' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// View customer account - appcommerce/v2/account (GET)
		register_rest_route(
			$this->namespace,
			'/account',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_account' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// View categories - appcommerce/v2/products/categories (GET)
		register_rest_route(
			$this->namespace,
			'/products/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_categories' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get - appcommerce/v2/products/attribute-terms (GET)
		register_rest_route(
			$this->namespace,
			'/products/attribute-terms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_global_attribute_terms' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get - appcommerce/v2/products/(?P<id>[\d]+)/attributes (GET)
		register_rest_route(
			$this->namespace,
			'/products/(?P<id>[\d]+)/attributes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_product_attributes' ),
				'permission_callback' => '__return_true',
			)
		);

		// View Cart - appcommerce/v2/cart (GET)
		register_rest_route(
			$this->namespace,
			'/cart',
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

		// Count Items in Cart - appcommerce/v2/cart/count-items (GET)
		register_rest_route(
			$this->namespace,
			'/cart/count-items',
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

		// Get Cart Totals - appcommerce/v2/cart/totals (GET)
		register_rest_route(
			$this->namespace,
			'/cart/totals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_totals' ),
				'permission_callback' => '__return_true',
			)
		);

		// View Cart - appcommerce/v2/cart/clear (POST)
		register_rest_route(
			$this->namespace,
			'/cart/clear',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_cart' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get misc data that we don't have elsewhere (GET)
		register_rest_route(
			$this->namespace,
			'/cart/misc',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_misc_data' ),
				'permission_callback' => '__return_true',
			)
		);

		// Add Item - appcommerce/v2/cart/add (POST)
		register_rest_route(
			$this->namespace,
			'/cart/add',
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

		// Calculate Cart Total - appcommerce/v2/cart/calculate (POST)
		register_rest_route(
			$this->namespace,
			'/cart/calculate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'calculate_totals' ),
				'permission_callback' => '__return_true',
			)
		);

		// Update, Remove or Restore Item - appcommerce/v2/cart/cart-item (GET, POST, DELETE)
		register_rest_route(
			$this->namespace,
			'/cart/cart-item',
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

	// Get product attribute terms and names for filter in app
	public function get_global_attribute_terms( $data ) {

		// get attributes by category. Currently not used.
		if ( ! empty( $data['category'] ) ) {
			return $this->get_attributes_by_category( $data['category'] );
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$taxonomy_terms       = array();

		if ( $attribute_taxonomies ) :
			foreach ( $attribute_taxonomies as $tax ) :
				if ( taxonomy_exists( wc_attribute_taxonomy_name( $tax->attribute_name ) ) ) :
					$options = get_terms( wc_attribute_taxonomy_name( $tax->attribute_name ), 'orderby=name&hide_empty=0' );

					$taxonomy_terms[] = array(
						'label'   => $tax->attribute_label,
						'name'    => $tax->attribute_name,
						'options' => apply_filters( 'appcommerce_attribute_terms', $options ),
					);

			endif;
		endforeach;
		endif;

		return $taxonomy_terms;
	}

	// get attributes for a single product that don't exist globally.
	// used to support variable products with attributes set to "any"
	// without this we cannot get attribute option values if an attribute is set to "any"
	public function get_product_attributes( $data ) {

		if ( empty( $data['id'] ) ) {
			return 'Missing id';
		}

		$product = wc_get_product( $data['id'] );

		$attributes = $product->get_variation_attributes();

		foreach ( $attributes as $taxonomy => $term_names ) {

			// $terms[] = $product->get_attribute($taxonomy);
			$options = array();
			foreach ( $term_names as $value ) {
				// Get term data for each option
				$term = get_term_by( 'slug', $value, $taxonomy );
				if ( $term ) {
					$options[] = array(
						'slug' => $term->slug,
						'name' => $term->name,
					);
				} else {
					$options[] = $value;
				}
			}
			// Get the attribute label
			$ret[] = array(
				'name'    => wc_attribute_label( $taxonomy ),
				'options' => $options,
			);
		}

		return $ret;
	}

	// Get attributes by category. Currently not used.
	// categories have different attributes, for example books and clothes
	public function get_attributes_by_category( $category ) {

		$filter_raw = array();
		$return     = array();
		$attrs_raw  = wc_get_attribute_taxonomy_names(); // Getting data of attributes assign in backend.
		$cat_name   = get_term( $category, 'product_cat', ARRAY_A ); // Category data by category ID.
		$args       = array(
			'category' => array( $cat_name['slug'] ),
		);

		foreach ( wc_get_products( $args ) as $product ) {
			foreach ( $product->get_attributes() as $attr_name => $attr ) {
				$filter_raw[] = $attr_name;
				if ( is_array( $attr->get_terms() ) ) {
					foreach ( $attr->get_terms() as $term ) {
						$terms_raw[] = $term->name;
					}
				}
			}
		}
		$filters = array_unique( array_intersect( (array) $filter_raw, (array) $attrs_raw ) ); // Filtering the attributes used by products in particular category

		if ( is_array( $filters ) ) {
			foreach ( $filters as $filter ) {
				$options = get_terms( $filter, 'orderby=name&hide_empty=0' );
				if ( ! empty( $options ) ) {

					$return[] = array(
						'label'   => $filter,
						'name'    => $filter,
						'options' => apply_filters( 'appcommerce_attribute_terms', $options ),
					);

				} else {
					$return[] = 'empty';
				}
			}
		}

		return $return;
	}

	/**
	 * Gets products
	 *
	 * @return WP_REST_Response
	 */
	public function get_system_status() {
		$returnArray = array(
			'settings' => array(
				'currency'           => get_woocommerce_currency(),
				'currency_symbol'    => get_woocommerce_currency_symbol(),
				'currency_position'  => get_option( 'woocommerce_currency_pos' ),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'decimal_separator'  => wc_get_price_decimal_separator(),
				'number_of_decimals' => wc_get_price_decimals(),
				'checkout_url'       => wc_get_checkout_url(),
			),
		);

		return new WP_REST_Response( $returnArray, 200 );
	}

	/**
	 * Gets products
	 *
	 * @return WP_REST_Response
	 */
	public function get_products( $data = array() ) {
		$returnArray = array();

		// Default arguments
		$args = array(
			'status' => 'publish',
		);

		// Arguments given by the user
		if ( isset( $data['page'] ) ) {
			$args['page'] = $data['page'];
		}
		if ( isset( $data['per_page'] ) ) {
			$args['limit'] = $data['per_page'];
		}
		if ( isset( $data['search'] ) ) {
			$args['s'] = $data['search'];
		}
		// if (isset($data['after'])) {
		// }
		// if (isset($data['before'])) {
		// }
		if ( isset( $data['exclude'] ) ) {
			$args['exclude'] = explode( ',', $data['exclude'] );
		}
		if ( isset( $data['include'] ) ) {
			$args['include'] = explode( ',', $data['include'] );
		}
		if ( isset( $data['offset'] ) ) {
			$args['offset'] = $data['offset'];
		}
		if ( isset( $data['order'] ) ) {
			$args['order'] = $data['order'];
		}
		if ( isset( $data['orderby'] ) ) {
			if ( $data['orderby'] === 'popularity' ) {
				// popularity is determined by total sales
				$order            = ( isset( $data['order'] ) ? $data['order'] : 'DESC' );
				$args['orderby']  = array(
					'meta_value_num' => $order,
					'title'          => 'ASC',
				);
				$args['meta_key'] = 'total_sales';
			} elseif ( $data['orderby'] === 'price' ) {
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
			} else {
				$args['orderby'] = $data['orderby'];
			}
		}
		if ( isset( $data['parent'] ) ) {
			$args['parent'] = $data['parent'];
		}
		if ( isset( $data['parent_exclude'] ) ) {
			$args['parent_exclude'] = explode( ',', $data['parent_exclude'] );
		}
		// if (isset($data['slug'])) {
		// }
		if ( isset( $data['type'] ) ) {
			$types = explode( ',', $data['type'] );
			foreach ( $types as $type ) {
				$args['type'][] = $type;
			}
		}
		if ( isset( $data['sku'] ) ) {
			$args['sku'] = $data['sku'];
		}
		if ( isset( $data['featured'] ) ) {
			$args['featured'] = $data['featured'];
		}
		if ( ! empty( $data['category'] ) ) {

			if ( false === strpos( $data['category'], ',' ) ) {
				$categoryParams = array( $data['category'] );
			} else {
				$categoryParams = explode( ',', $data['category'] );
			}

			foreach ( $categoryParams as $categoryParam ) {
				if ( is_numeric( $categoryParam ) ) {
					$category           = get_term_by( 'id', $categoryParam, 'product_cat' );
					$args['category'][] = $category->slug;
				} else {
					$args['category'][] = $categoryParam;
				}
			}
		}
		if ( isset( $data['tag'] ) ) {
			$tagParams = explode( ',', $data['tag'] );
			foreach ( $tagParams as $tagParam ) {
				if ( is_numeric( $tagParam ) ) {
					$tag           = get_term_by( 'id', $tagParam, 'product_tag' );
					$args['tag'][] = $tag->slug;
				} else {
					$args['tag'][] = $tagParam;
				}
			}
		}
		if ( isset( $data['shipping_class'] ) ) {
			$args['shipping_class'] = $data['shipping_class'];
		}
		if ( isset( $data['visibility'] ) ) {
			$args['visibility'] = $data['visibility'];
		}
		// if (isset($data['attribute'])) {
		// }
		// if (isset($data['attribute_term'])) {
		// }
		if ( isset( $data['tax_class'] ) ) {
			$args['tax_class'] = $data['tax_class'];
		}
		// if (isset($data['min_price'])) {
		// }
		// if (isset($data['max_price'])) {
		// }
		if ( isset( $data['stock_status'] ) ) {
			$args['stock_status'] = $data['stock_status'];
		}

		// filter by product attribute.
		// Usage: filter[term]=value Example: ?filter[pa_color]=red&filter[pa_size]=large
		// WC has weird term names, like pa_color instead of color. Use wc_get_attribute_taxonomies and wc_attribute_taxonomy_name to find term names.
		if ( isset( $data['filter'] ) ) {
			$keys = array_keys( $data['filter'] );

			foreach ( $keys as $key ) {
				if ( ! empty( $data['filter'][ $key ] ) ) {
					$tax_queries[] = array(
						'taxonomy' => $key,
						'field'    => 'slug',
						'terms'    => $data['filter'][ $key ],
						'operator' => 'IN',
					);
				}
			}
			$args['tax_query'] = $tax_queries;
		}
		// https://github.com/woocommerce/woocommerce/wiki/wc_get_products-and-WC_Product_Query

		$args = apply_filters( 'appcommerce_get_products_args', $args );

		$products = wc_get_products( $args );

		foreach ( $products as $product ) {
			if ( isset( $data['on_sale'] ) ) {
				if ( $product->is_on_sale() ) {
					$returnArray[] = $this->getProductData( $product );
				}
			} else {
				$returnArray[] = $this->getProductData( $product );
			}
		}

		$returnArray = apply_filters( 'appcommerce_get_products_response', $returnArray, $args );

		return new WP_REST_Response( $returnArray, 200 );
	}

	/**
	 * Gets given product
	 *
	 * @return WP_REST_Response
	 */
	public function get_product( $data = array() ) {
		$product = wc_get_product( $data['id'] );

		// @TODO consider checking if product status is 'published'

		$productData = $this->getProductData( $product );

		// @TODO consider adding a filter before return

		return new WP_REST_Response( $productData, 200 );
	}

	/**
	 * Gets given product variations
	 *
	 * @return WP_REST_Response
	 */
	public function get_variations( $data = array() ) {
		$returnArray = array();

		$product = wc_get_product( $data['id'] );

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return new WP_Error( 'woocommerce_product_does_not_exist', __( 'This product does not exist!', 'appcommerce' ) );
		}

		foreach ( $product->get_children() as $child_id ) {
			$variation     = wc_get_product( $child_id );
			$returnArray[] = $this->getProductData( $variation );
		}

		// @TODO consider adding a filter before return

		return new WP_REST_Response( $returnArray, 200 );
	}

	/**
	 * Get standard product data that applies to every product type
	 *
	 * @return array
	 */
	public function getProductData( $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return new WP_Error( 'woocommerce_product_does_not_exist', __( 'This product does not exist!', 'appcommerce' ) );
		}

		return apply_filters(
			'appcommerce_get_product_response',
			array(
				'id'                 => $product->get_id(),
				'name'               => $product->get_name(),
				'slug'               => $product->get_slug(),
				'permalink'          => $product->get_permalink(),
				'date_created'       => $this->formatDatetime( $product->get_date_created(), false, true ),
				'date_created_gmt'   => $this->formatDatetime( $product->get_date_created(), true, true ),
				'date_modified'      => $this->formatDatetime( $product->get_date_modified(), false, true ),
				'date_modified_gmt'  => $this->formatDatetime( $product->get_date_modified(), true, true ),
				'type'               => $product->get_type(),
				'status'             => $product->get_status(),
				'featured'           => $product->is_featured(),
				'catalog_visibility' => $product->get_catalog_visibility(),
				'description'        => wpautop( do_shortcode( $product->get_description() ) ),
				'short_description'  => $product->get_short_description(),
				'sku'                => $product->get_sku(),
				'price'              => $product->get_price(),
				'regular_price'      => $product->get_regular_price(),
				'sale_price'         => $product->get_sale_price() ? $product->get_sale_price() : null,
				'price_html'         => $product->get_price_html(),
				'on_sale'            => $product->is_on_sale(),
				'purchasable'        => $product->is_purchasable(),
				'total_sales'        => $product->get_total_sales(),
				'virtual'            => $product->is_virtual(),
				'downloadable'       => $product->is_downloadable(),
				// 'downloads' => $this->getProductDownloads($product),
				// 'download_limit' => $product->get_download_limit(),
				// 'download_expiry' => $product->get_download_expiry(),
				'external_url'       => $product->is_type( 'external' ) ? $product->get_product_url() : '',
				'button_text'        => $product->is_type( 'external' ) ? $product->get_button_text() : '',
				// 'tax_status' => $product->get_tax_status(),
				// 'tax_class' => $product->get_tax_class(),
				'manage_stock'       => $product->managing_stock(),
				'stock_quantity'     => $product->get_stock_quantity(),
				'stock_status'       => $this->getProductStockStatus( $product ),
				'backorders_allowed' => $product->backorders_allowed(),
				'backordered'        => $product->is_on_backorder(),
				'sold_individually'  => $product->is_sold_individually(),
				// 'weight' => $product->get_weight() ? $product->get_weight() : null,
				// 'dimensions' => array(
				// 'length' => $product->get_length(),
				// 'width' => $product->get_width(),
				// 'height' => $product->get_height(),
				// 'unit' => get_option('woocommerce_dimension_unit'),
				// ),
				'shipping_required'  => $product->needs_shipping(),
				// 'shipping_taxable' => $product->is_shipping_taxable(),
				// 'shipping_class' => $product->get_shipping_class(),
				// 'shipping_class_id' => (0 !== $product->get_shipping_class_id()) ? $product->get_shipping_class_id() : null,
				'reviews_allowed'    => $product->get_reviews_allowed(),
				'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
				'rating_count'       => $product->get_rating_count(),
				'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
				'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
				'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
				'parent_id'          => $product->get_parent_id(),
				'purchase_note'      => wpautop( do_shortcode( wp_kses_post( $product->get_purchase_note() ) ) ),
				'categories'         => $this->getProductCategories( $product ),
				'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ),
				'images'             => $this->getImages( $product ),
				'attributes'         => $this->getAttributes( $product ),
				'variations'         => $this->getVariations( $product ),
				'grouped_products'   => $this->getGroupedProducts( $product ),
			// 'menu_order' => $product->get_menu_order(),
			// 'meta_data' => $product->get_meta_data()
			)
		);
	}

	/**
	 * Format a unix timestamp or MySQL datetime into an RFC3339 datetime
	 *
	 * @param int|string $timestamp unix timestamp or MySQL datetime
	 * @param bool       $convert_to_utc
	 * @param bool       $convert_to_gmt Use GMT timezone.
	 * @return string RFC3339 datetime
	 */
	public function formatDatetime( $timestamp, $convert_to_utc = false, $convert_to_gmt = false ) {
		if ( $timestamp === null ) {
			return null;
		}

		if ( $convert_to_gmt ) {
			if ( is_numeric( $timestamp ) ) {
				$timestamp = date( 'Y-m-d H:i:s', $timestamp );
			}

			$timestamp = get_gmt_from_date( $timestamp );
		}

		if ( $convert_to_utc ) {
			$timezone = new DateTimeZone( wc_timezone_string() );
		} else {
			$timezone = new DateTimeZone( 'UTC' );
		}

		try {
			if ( is_numeric( $timestamp ) ) {
				$date = new DateTime( "@{$timestamp}" );
			} else {
				$date = new DateTime( $timestamp, $timezone );
			}

			// convert to UTC by adjusting the time based on the offset of the site's timezone
			if ( $convert_to_utc ) {
				$date->modify( -1 * $date->getOffset() . ' seconds' );
			}
		} catch ( Exception $e ) {
			$date = new DateTime( '@0' );
		}

		$format = apply_filters( 'appcommerce_date_format', 'Y-m-d\TH:i:s\Z' );

		return $date->format( $format );
	}

	/**
	 * Get the downloads for a product or product variation
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	public function getProductDownloads( $product ) {
		$downloads = array();

		if ( $product->is_downloadable() ) {

			foreach ( $product->get_downloads() as $file_id => $file ) {

				$downloads[] = array(
					'id'   => $file_id, // do not cast as int as this is a hash
					'name' => $file['name'],
					'file' => $file['file'],
				);
			}
		}

		return $downloads;
	}

	/**
	 * Gets the stock status
	 */
	public function getProductStockStatus( $product ) {
		// Compatibility for WC versions from 2.5.x to 3.0+
		if ( method_exists( $product, 'get_stock_status' ) ) {
			return $product->get_stock_status(); // For version 3.0+
		} else {
			return $product->stock_status; // Older than version 3.0
		}
	}

	/**
	 * Gets product categories
	 */
	public function getProductCategories( $product ) {
		$returnArray = array();

		$productTerms = wc_get_product_terms( $product->get_id(), 'product_cat' );
		foreach ( $productTerms as $productTerm ) {
			// Prepare product category array
			$productCategoryArray = array(
				'id'   => $productTerm->term_id,
				'name' => $productTerm->name,
				'slug' => $productTerm->slug,
			);

			$returnArray[] = $productCategoryArray;
		}

		return $returnArray;
	}

	/**
	 * Gets the images for a product or product variation
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	public function getImages( $product ) {
		$images         = array();
		$attachment_ids = array();
		$product_image  = $product->get_image_id();

		// Add featured image.
		if ( ! empty( $product_image ) ) {
			$attachment_ids[] = $product_image;
		}

		// Add gallery images.
		$attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

		// Build image data.
		foreach ( $attachment_ids as $position => $attachment_id ) {
			$attachment_post = get_post( $attachment_id );

			if ( is_null( $attachment_post ) ) {
				continue;
			}

			$attachment = wp_get_attachment_image_src( $attachment_id, 'full' );

			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$images[] = array(
				'id'                => (int) $attachment_id,
				'date_created'      => $this->formatDatetime( $attachment_post->post_date_gmt, false, true ),
				'date_created_gmt'  => $this->formatDatetime( $attachment_post->post_date_gmt, true, true ),
				'date_modified'     => $this->formatDatetime( $attachment_post->post_modified_gmt, false, true ),
				'date_modified_gmt' => $this->formatDatetime( $attachment_post->post_modified_gmt, true, true ),
				'src'               => current( $attachment ),
				'name'              => get_the_title( $attachment_id ),
				'alt'               => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'position'          => (int) $position,
			);
		}

		return $images;
	}


	/**
	 * Gets the attributes for a product or product variation
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	public function getAttributes( $product ) {
		$attributes = array();

		if ( $product->is_type( 'variation' ) ) {
			// variation attributes
			foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {
				// Extends the option to show the attribute name
				// 1) if it is entered from the Products/Attributes as "wp_terms"
				// 2) else if it is entered manualy on the product item as "string"
				$taxonomy = str_replace( 'attribute_', '', wc_attribute_taxonomy_slug( $attribute_name ) );
				$meta     = get_post_meta( $product->get_id(), 'attribute_' . $taxonomy, true );
				$term     = get_term_by( 'slug', $meta, $taxonomy );
				$option   = ( $term ) ? $term->slug : $attribute;

				// taxonomy-based attributes are prefixed with `pa_`, otherwise simply `attribute_`
				$attributes[] = array(
					'name'   => wc_attribute_label( str_replace( 'attribute_', '', $attribute_name ), $product ),
					'slug'   => $taxonomy,
					'option' => $option,
				);
			}
		} else {
			foreach ( $product->get_attributes() as $attribute ) {
				$attributes[] = array(
					'name'      => wc_attribute_label( $attribute['name'], $product ),
					'slug'      => wc_attribute_taxonomy_slug( $attribute['name'] ),
					'position'  => (int) $attribute['position'],
					'visible'   => (bool) $attribute['visible'],
					'variation' => (bool) $attribute['variation'],
					'options'   => $this->getAttributeOptions( $product->get_id(), $attribute ),
				);
			}
		}

		return $attributes;
	}

	/**
	 * Gets attribute options
	 *
	 * @param int   $product_id
	 * @param array $attribute
	 * @return array
	 */
	public function getAttributeOptions( $productId, $attribute ) {
		if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
			return wc_get_product_terms( $productId, $attribute['name'], array( 'fields' => 'names' ) );
		} elseif ( isset( $attribute['value'] ) ) {
			return array_map( 'trim', explode( '|', $attribute['value'] ) );
		}

		return array();
	}

	/**
	 * Adds variations to variable products
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	public function getVariations( $product ) {
		if ( $product->is_type( 'variable' ) && $product->has_child() ) {
			return $product->get_children();
		} else {
			return array();
		}
	}

	/**
	 * Adds grouped products data
	 *
	 * @param WC_Product|WC_Product_Variation $product
	 * @return array
	 */
	public function getGroupedProducts( $product ) {
		if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
			return $product->get_children();
		} else {
			return array();
		}
	}

	/**
	 * Gets products reviews
	 *
	 * @return WP_REST_Response
	 */
	public function get_reviews( $data = array() ) {
		$reviews = array();

		$productId = absint( $data['product_id'] );

		// Default arguments
		$args = array(
			'status' => 'approve',
		);

		// Arguments given by the user
		$args['number'] = $this->handlePerPageCommentArgument( $data ); // handle 'per_page' argument
		$args['offset'] = $this->handlePageCommentArgument( $data, (int) $args['number'] ); // handle 'page' argument

		$comments = get_approved_comments( $productId, $args );
		foreach ( $comments as $comment ) {
			$reviews[] = array(
				'id'                   => intval( $comment->comment_ID ),
				'date_created'         => $this->formatDatetime( $comment->comment_date_gmt, false, true ),
				'date_created_gmt'     => $this->formatDatetime( $comment->comment_date_gmt, true, true ),
				'product_id'           => $comment->comment_post_ID,
				'review'               => $comment->comment_content,
				'rating'               => get_comment_meta( $comment->comment_ID, 'rating', true ),
				'reviewer_name'        => $comment->comment_author,
				'reviewer_email'       => $comment->comment_author_email,
				'verified'             => wc_review_is_from_verified_owner( $comment->comment_ID ),
				'reviewer_avatar_urls' => rest_get_avatar_urls( $comment->comment_author_email ),
			);
		}

		// @TODO consider adding a filter before return

		return new WP_REST_Response( $reviews, 200 );
	}

	/**
	 * Handles 'per_page' comment argument
	 */
	public function handlePerPageCommentArgument( $data ) {
		if ( isset( $data['per_page'] ) ) {
			return $data['per_page'];
		} else {
			return 10; // 10 = default categories per page
		}
	}

	/**
	 * Handles 'page' comment argument
	 */
	public function handlePageCommentArgument( $data, $itemsPerPage ) {
		if ( isset( $data['page'] ) ) {
			$page = (int) $data['page'] - 1;
			return $page * $itemsPerPage;
		} else {
			return 0; // 0 = default (start from the beginning)
		}
	}

	/**
	 * Gets categories
	 *
	 * @return WP_REST_Response
	 */
	public function get_categories( $data = array() ) {
		$returnArray = array();

		// Default arguments
		$args = array(
			'taxonomy' => 'product_cat',
		);

		// Arguments given by the user
		$args['number'] = $this->handlePerPageCategoryArgument( $data ); // handle 'per_page' argument
		$args['offset'] = $this->handlePageCategoryArgument( $data, (int) $args['number'] ); // handle 'page' argument
		if ( isset( $data['search'] ) ) {
			$args['search'] = $data['search'];
		}
		if ( isset( $data['exclude'] ) ) {
			$args['exclude'] = explode( ',', $data['exclude'] );
		}
		if ( isset( $data['include'] ) ) {
			$args['include'] = explode( ',', $data['include'] );
		}
		if ( isset( $data['order'] ) ) {
			$args['order'] = $data['order'];
		}
		if ( isset( $data['orderby'] ) ) {
			$args['orderby'] = $data['orderby'];
		}
		if ( isset( $data['hide_empty'] ) ) {
			$args['hide_empty'] = $data['hide_empty'];
		}
		if ( isset( $data['parent'] ) ) {
			$args['parent'] = $data['parent'];
		}

		if ( isset( $data['slug'] ) ) {
			$args['slug'] = explode( ',', $data['slug'] );
		}
		if ( isset( $data['get'] ) && $data['get'] === 'all' ) {
			$args['get'] = 'all';
		}

		$categories = get_categories( $args );

		foreach ( $categories as $category ) {
			// Prepare category array
			$categoryArray = array(
				'id'          => $category->term_id,
				'name'        => $category->name,
				'slug'        => $category->slug,
				'parent'      => $category->parent,
				'description' => $category->description,
				'display'     => $this->getCategoryDisplayType( $category ),
				'image'       => $this->getCategoryImage( $category ),
				'count'       => $category->count,
			);

			$categoryArray['children'] = $this->getChildCategories( $category->term_id );

			$returnArray[] = $categoryArray;
		}

		// @TODO consider adding a filter before return

		return new WP_REST_Response( $returnArray, 200 );
	}

	public function getChildCategories( $parent_id ) {
		return get_categories(
			array(
				'parent'   => $parent_id,
				'taxonomy' => 'product_cat',
			)
		);
	}

	/**
	 * Handles 'per_page' category argument
	 */
	public function handlePerPageCategoryArgument( $data ) {
		if ( isset( $data['per_page'] ) ) {
			return $data['per_page'];
		} else {
			return 10; // 10 = default categories per page
		}
	}

	/**
	 * Handles 'page' category argument
	 */
	public function handlePageCategoryArgument( $data, $itemsPerPage ) {
		if ( isset( $data['page'] ) ) {
			$page = (int) $data['page'] - 1;
			return $page * $itemsPerPage;
		} else {
			return 0; // 0 = default (start from the beginning)
		}
	}

	/**
	 * Gets category display type
	 */
	public function getCategoryDisplayType( $category ) {
		$displayType = get_term_meta( $category->term_id, 'display_type', true );

		return $displayType ? $displayType : 'default';
	}

	/**
	 * Gets the category image data
	 */
	public function getCategoryImage( $category ) {
		// Get category image
		if ( $imageId = get_term_meta( $category->term_id, 'thumbnail_id', true ) ) {
			$imageSrc = wp_get_attachment_url( $imageId );

			return array(
				'src' => $imageSrc ? esc_url( $imageSrc ) : '',
			);
		}
	}

	/**
	 * Gets orders
	 *
	 * @return WP_REST_Response
	 */
	public function get_orders( $data = array() ) {
		$returnArray = array();

		// Default arguments
		$args = array(
			'customer' => get_current_user_id(),
		);

		// Arguments given by the user
		if ( isset( $data['page'] ) ) {
			$args['paged'] = $data['page'];
		}
		if ( isset( $data['per_page'] ) ) {
			$args['limit'] = $data['per_page'];
		}
		// https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query

		$orders = wc_get_orders( $args );

		foreach ( $orders as $order ) {
			$returnArray[] = array(
				'id'                   => $order->get_id(),
				'parent_id'            => $order->get_parent_id(),
				'number'               => $order->get_order_number(),
				'order_key'            => $order->get_order_key(),
				'create_via'           => $order->get_created_via(),
				'version'              => $order->get_version(),
				'status'               => $order->get_status(),
				'currency'             => $order->get_currency(),
				'date_created'         => $this->formatDatetime( $order->get_date_created(), false, true ),
				'date_created_gmt'     => $this->formatDatetime( $order->get_date_created(), true, true ),
				'date_modified'        => $this->formatDatetime( $order->get_date_modified(), false, true ),
				'date_modified_gmt'    => $this->formatDatetime( $order->get_date_modified(), true, true ),
				'discount_total'       => $order->get_discount_total(),
				'discount_tax'         => $order->get_discount_tax(),
				'shipping_total'       => $order->get_shipping_total(),
				'shipping_tax'         => $order->get_shipping_tax(),
				'cart_tax'             => $order->get_cart_tax(),
				'total'                => $order->get_total(),
				'total_tax'            => $order->get_total_tax(),
				'prices_include_tax'   => $order->get_prices_include_tax(),
				'customer_id'          => $order->get_customer_id(),
				'customer_ip_address'  => $order->get_customer_ip_address(),
				'customer_user_agent'  => $order->get_customer_user_agent(),
				'customer_note'        => $order->get_customer_note(),
				'billing'              => array(
					'first_name' => $order->get_billing_first_name(),
					'last_name'  => $order->get_billing_last_name(),
					'company'    => $order->get_billing_company(),
					'address_1'  => $order->get_billing_address_1(),
					'address_2'  => $order->get_billing_address_2(),
					'city'       => $order->get_billing_city(),
					'state'      => $order->get_billing_state(),
					'postcode'   => $order->get_billing_postcode(),
					'country'    => $order->get_billing_country(),
					'email'      => $order->get_billing_email(),
					'phone'      => $order->get_billing_phone(),
				),
				'shipping'             => array(
					'first_name' => $order->get_shipping_first_name(),
					'last_name'  => $order->get_shipping_last_name(),
					'company'    => $order->get_shipping_company(),
					'address_1'  => $order->get_shipping_address_1(),
					'address_2'  => $order->get_shipping_address_2(),
					'city'       => $order->get_shipping_city(),
					'state'      => $order->get_shipping_state(),
					'postcode'   => $order->get_shipping_postcode(),
					'country'    => $order->get_shipping_country(),
				),
				'payment_method'       => $order->get_payment_method(),
				'payment_method_title' => $order->get_payment_method_title(),
				'transaction_id'       => $order->get_transaction_id(),
				'date_paid'            => $this->formatDatetime( $order->get_date_paid(), false, true ),
				'date_paid_gmt'        => $this->formatDatetime( $order->get_date_paid(), true, true ),
				'date_completed'       => $this->formatDatetime( $order->get_date_completed(), false, true ),
				'date_completed_gmt'   => $this->formatDatetime( $order->get_date_completed(), true, true ),
				'cart_hash'            => $order->get_cart_hash(),
				'meta_data'            => $order->get_meta_data(),
				'line_items'           => $this->getLineItems( $order ),
				'refunds'              => $order->get_refunds(),
				'currency_symbol'      => $order->get_currency(),
			);
		}

		// @TODO consider adding a filter before return

		return new WP_REST_Response( $returnArray, 200 );
	}


	/**
	 * Check if request has permission
	 *
	 * @return WP_Error|bool
	 */
	public function permissions_check() {
		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to see this resource.', 'appcommerce' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Gets customer data
	 *
	 * @return WP_REST_Response
	 */
	public function get_account() {
		$customer = new WC_Customer( get_current_user_id() );

		$returnArray = array(
			'id'                 => $customer->get_is_paying_customer(),
			'date_created'       => $this->formatDatetime( $customer->get_date_created(), false, true ),
			'date_created_gmt'   => $this->formatDatetime( $customer->get_date_created(), true, true ),
			'date_modified'      => $this->formatDatetime( $customer->get_date_modified(), false, true ),
			'date_modified_gmt'  => $this->formatDatetime( $customer->get_date_modified(), true, true ),
			'email'              => $customer->get_email(),
			'first_name'         => $customer->get_first_name(),
			'last_name'          => $customer->get_last_name(),
			'role'               => $customer->get_role(),
			'username'           => $customer->get_username(),
			'billing'            => $customer->get_billing(),
			'shipping'           => $customer->get_shipping(),
			'is_paying_customer' => $customer->get_is_paying_customer(),
			'avatar_url'         => $customer->get_avatar_url(),
			'meta_data'          => $customer->get_meta_data(),
		);

		return new WP_REST_Response( $returnArray, 200 );
	}

	/**
	 * Gets line items from order
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	public function getLineItems( $order ) {
		$returnArray = array();

		foreach ( $order->get_items() as $lineItem ) {
			// Iterating through each "line" items in the order
			$returnArray[] = array(
				'id'           => $lineItem->get_id(),
				'name'         => $lineItem->get_name(),
				'product_id'   => $lineItem->get_product_id(),
				'variation_id' => $lineItem->get_variation_id(),
				'quantity'     => $lineItem->get_quantity(),
				'tax_class'    => $lineItem->get_tax_class(),
				'subtotal'     => $lineItem->get_subtotal(),
				'subtotal_tax' => $lineItem->get_subtotal_tax(),
				'total'        => $lineItem->get_total(),
				'total_tax'    => $lineItem->get_total_tax(),
				'taxes'        => $lineItem->get_taxes(),
				'meta_data'    => $lineItem->get_meta_data(),
			);
		}

		return $returnArray;
	}

	/**
	 * Get cart.
	 *
	 * @access public
	 * @since 1.0.0
	 * @param array $data
	 * @return WP_REST_Response
	 */
	public function get_cart( $data = array() ) {
		$wc_cart = WC()->cart;
		$totals  = $wc_cart->get_totals();
		$count   = $wc_cart->get_cart_contents_count();

		if ( $this->get_cart_contents_count( array( 'return' => 'numeric' ) ) <= 0 ) {
			return new WP_REST_Response( __( 'Cart is empty!', 'appcommerce' ), 200 );
		}

		$handoff = AppCommerce_Checkout_Session::get_handoff( WC()->session );
		if ( is_wp_error( $handoff ) ) {
			return $handoff;
		}
		$cart = $this->get_cart_contents();

		$show_thumb = ! empty( $data['thumb'] ) ? $data['thumb'] : false;

		$returnArray = array();

		foreach ( $cart as $item_key => $cart_item ) {
			$_product = apply_filters( 'wc_cart_rest_api_cart_item_product', $cart_item['data'], $cart_item, $item_key );

			// Adds the product name as a new variable.
			$cart[ $item_key ]['product_name'] = $_product->get_name();

			// Update the line_total when tax_display_cart is "include"
			if ( $wc_cart->tax_display_cart === 'incl' ) {
				$cart[ $item_key ]['line_total'] += $cart[ $item_key ]['line_tax'];
			}

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

		// Update the cart_contents_total when tax_display_cart is "include"
		if ( $wc_cart->tax_display_cart === 'incl' ) {
			$totals['cart_contents_total'] += $totals['cart_contents_tax'];
		}

		$returnArray['cart_total'] = $totals;

		$returnArray['cart_total']['cart_contents_total'] = number_format( (float) $totals['cart_contents_total'], 2, '.', '' );

		$returnArray['session_cookie'] = $handoff;

		$response = new WP_REST_Response( $returnArray, 200 );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	} // END get_cart()

	/**
	 * Get cart contents count.
	 *
	 * @access public
	 * @since 1.0.0
	 * @param array $data
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @param int $product_id
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
	 * @since 1.0.0
	 * @param int $quantity
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
	 * @since 1.0.0
	 * @param int $product_id
	 * @param int $quantity
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
	 * @since 1.0.0
	 * @param array $data
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
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.3
	 * @param array $data
	 * @return WP_Error|WP_REST_Response
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
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.3
	 * @param array $data
	 * @return WP_Error|WP_REST_Response
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
	 * @access public
	 * @since 1.0.0
	 * @version 1.0.3
	 * @param array $data
	 * @return WP_Error|WP_REST_Response
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 2.0.0
	 * @param string $cart_item_key of product to find in the cart.
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
