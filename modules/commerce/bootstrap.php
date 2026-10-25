<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
 * Module source: AppCommerce
 * Plugin URI:  https://apppresser.com
 * Description: Adds a WooCommerce cart REST API and modifications for AppPresser apps.
 * Author:      AppPresser
 * Author URI:  https://apppresser.com
 * Version:     2.3.6
 * Text Domain: appcommerce
 * Domain Path: /languages/
 *
 * Cart API functionality forked from https://github.com/co-cart/co-cart, props to Sébastien Dumont https://sebastiendumont.com/
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! class_exists( 'AppCommerce' ) ) {
	class AppCommerce {

		/**
		 * @var AppCommerce - the single instance of the class.
		 *
		 * @access protected
		 * @static
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Plugin Version
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 */
		public static $version = '2.3.6';

		public static $plugin_slug = 'appcommerce';

		/**
		 * Required WooCommerce Version
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 */
		public static $required_woo = '3.5.0';

		/**
		 * Main AppCommerce Instance.
		 *
		 * Ensures only one instance of AppCommerce is loaded or can be loaded.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @see    AppCommerce()
		 * @return AppCommerce - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Cloning this object is forbidden.', 'appcommerce' ), self::$version );
		} // END __clone()

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'appcommerce' ), self::$version );
		} // END __wakeup()

		/**
		 * Load the plugin.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function __construct() {

			$this->hooks();
		}

		public function hooks() {

			// Check WooCommerce dependency.
			add_action( 'plugins_loaded', array( $this, 'check_woocommerce_dependency' ) );

			// Load translation files.
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Include required files
			if ( did_action( 'woocommerce_loaded' ) ) {
				$this->includes();
			} else {
				add_action( 'woocommerce_loaded', array( $this, 'includes' ) );
			}

			// workarounds to support Basic Auth together with Bearer Auth for backward compatibility
			add_filter( 'rest_pre_dispatch', array( $this, 'rest_pre_dispatch_overwrite' ), 11 );



			if ( isset( $_POST['rating'] ) ) {
				add_filter( 'rest_pre_insert_comment', array( $this, 'change_comment_type_to_insert_comment_endpoint' ), 10, 2 );
				add_action( 'rest_after_insert_comment', array( $this, 'add_rating_meta_to_insert_comment_endpoint' ), 10, 3 );
			}
			if ( isset( $_GET['reviews'] ) && $_GET['reviews'] === 'true' ) {
				add_filter( 'rest_comment_query', array( $this, 'change_comment_type_to_get_comments_endpoint' ), 10, 2 );
				add_action(
					'rest_api_init',
					array( $this, 'add_rating_field_to_get_comment_endpoint' )
				);
			}
		}

		// register for plugin updates
		public function updater() {
			// Bundled modules are updated exclusively with AppPresser core.
		}

		public function change_comment_type_to_insert_comment_endpoint( $prepared_comment, $request ) {
			$prepared_comment['comment_type'] = 'review';

			return $prepared_comment;
		}


		public function add_rating_meta_to_insert_comment_endpoint( $comment, $request, $creating ) {
			// Add rating meta to comment
			$rating = $request->get_param( 'rating' );
			update_comment_meta( (int) $comment->comment_ID, 'rating', $rating );
			// Calculate and update average rating
			$productId                     = (int) $comment->comment_post_ID;
			$product                       = wc_get_product( $productId );
			$productAverageRating          = (float) $product->get_average_rating();
			$calculateProductAverageRating = $productAverageRating ? ( $productAverageRating + $rating ) / 2 : $rating;
			update_post_meta( (int) $comment->comment_post_ID, '_wc_average_rating', $calculateProductAverageRating );
			// Calculate and update rating count
			$productRatingCount          = (int) $product->get_rating_count();
			$calculateProductRatingCount = $productRatingCount + 1;
			update_post_meta( (int) $comment->comment_post_ID, '_wc_rating_count', array( $calculateProductRatingCount ) );
		}

		public function change_comment_type_to_get_comments_endpoint( $prepared_args, $request ) {
			$prepared_args['type'] = 'review';

			return $prepared_args;
		}

		public function add_rating_field_to_get_comment_endpoint() {
			register_rest_field(
				'comment',
				'rating',
				array(
					'get_callback'    => array( $this, 'get_review_rating' ),
					'update_callback' => null,
					'schema'          => null,
				)
			);
		}

		public function get_review_rating( $object ) {
			return get_comment_meta( (int) $object['id'], 'rating', true );
		}

		/**
		 * Get the Plugin Path.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @return string
		 */
		public static function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		public static function plugin_dir() {
			return plugin_dir_url( __FILE__ );
		}

		/**
		 * Ignore the Authorization check from JWT plugin if 'bad_auth_header' is shown (this is a workaround to support Basic Auth)
		 */
		public function rest_pre_dispatch_overwrite( $request ) {
			if ( is_wp_error( $request ) ) {
				if ( $request->get_error_data( 'jwt_auth_bad_auth_header' ) ) {
					return null;
				}
			}

			return $request;
		}

		/**
		 * Check WooCommerce dependency before activation.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function check_woocommerce_dependency() {
			// Check we're running the required version of WooCommerce.
			if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, self::$required_woo, '<' ) || class_exists( 'WC_Cart_Endpoint_REST_API' ) || ! class_exists( 'AppPresser' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );
				return false;
			}
		} // END check_woocommerce_dependency()

		/**
		 * Display a warning message if minimum version of WooCommerce check fails.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function admin_notice() {
			echo '<div class="error"><p>' . sprintf( __( '%1$s requires at least %2$s v%3$s or higher, the AppPresser plugin active, and no other Cart API plugins active.', 'appcommerce' ), 'AppCommerce', 'WooCommerce', self::$required_woo ) . '</p></div>';
		} // END admin_notice()

		/*
		-----------------------------------------------------------------------------------*/
		/*
			Localization                                                                     */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Make the plugin translation ready.
		 *
		 * Translations should be added in the WordPress language directory:
		 *      - WP_LANG_DIR/plugins/cart-rest-api-for-woocommerce-LOCALE.mo
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'appcommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		} // END load_plugin_textdomain()

		/*
		-----------------------------------------------------------------------------------*/
		/*
			Load Files                                                                       */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Includes Cart REST API for WooCommerce Admin.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function includes() {
			include_once $this->plugin_path() . '/includes/class-appcommerce-init.php';
			include_once $this->plugin_path() . '/includes/class-appcommerce-functions.php';
			include_once $this->plugin_path() . '/includes/class-appcommerce-session.php';
		} // END includes()
	} // END class

} // END if class exists

return AppCommerce::instance();
