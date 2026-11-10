<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Module source: AppCommunity
 * Plugin URI: https://apppresser.com/extensions/appcommunity/
 * Description: Add BuddyPress components to your app via REST API. For AppPresser apps.
 * Author: AppPresser
 * Author URI: https://apppresser.com
 * Version: 2.2.3
 * Text Domain: appcommunity
 * Domain Path: languages/
 * Requires at least: 4.7.0
 * Tested up to: 5.6
 * Requires PHP: 5.6
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AppCommunity' ) ) {

	/**
	 * Main AppCommunity class
	 *
	 * @since       0.1.0
	 */
	class AppCommunity {

		/**
		 * @var         AppCommunity $instance The one true AppCommunity
		 * @since       0.1.0
		 */
		private static $instance;
		public static $plugin_slug = 'appcommunity';

		/**
		 * Get active instance
		 *
		 * @access      public
		 * @since       0.1.0
		 * @return      self The one true AppCommunity
		 */
		public static function instance() {

			if ( ! self::$instance ) {
				self::$instance = new AppCommunity();
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->load_textdomain();
				self::$instance->hooks();
			}

			return self::$instance;
		}

		/**
		 * Setup plugin constants
		 *
		 * @access      private
		 * @since       0.1.0
		 * @return      void
		 */
		private function setup_constants() {
			// Plugin version
			define( 'AppCommunity_VER', '2.2.3' );

			// Plugin path
			define( 'AppCommunity_DIR', __DIR__ );

			// Plugin URL
			define( 'AppCommunity_URL', plugin_dir_url( __FILE__ ) );
		}

		/**
		 * Include necessary files
		 *
		 * @access      private
		 * @since       0.1.0
		 * @return      void
		 */
		private function includes() {

			require_once AppCommunity_DIR . '/includes/buddypress/jwt/jwt-auth.php';
		}

		/**
		 * Run action and filter hooks
		 *
		 * @access      private
		 * @since       0.1.0
		 * @return      void
		 */
		private function hooks() {

			/**
			 * Register BuddyPress endpoints.
			 *
			 * @since 0.1.0
			 */
			add_action( 'bp_rest_api_init', array( $this, 'appcommunity_endpoints' ) );

			add_filter( 'appp_login_data', array( $this, 'add_jwt_to_login_data' ), 10, 2 );

			add_action( 'apppresser_add_settings', array( $this, 'buddypress_push_notifications_settings' ), 30 );

			add_action( 'admin_init', array( $this, 'admin_notices' ) );


			add_filter( 'bp_activity_allowed_tags', array( $this, 'allow_video_tag' ) );
		}

		// register for plugin updates
		public function updater() {
			// Bundled modules are updated exclusively with AppPresser core.
		}

		public function admin_notices() {
			if ( ! class_exists( 'BuddyPress' ) ) {
				add_action( 'admin_notices', array( $this, 'buddypress_plugin_not_active_admin_notice' ) );
			}
		}

		public function buddypress_plugin_not_active_admin_notice() {
			?>
<div class="notice notice-error">
	<p><?php _e( 'AppCommunity requires the BuddyPress plugin to work properly. Please install and activate BuddyPress.', 'apppresser' ); ?>
	</p>
</div>
			<?php
		}

		// adds a token on successful login that is used to authenticate POST requests to the API
		public function add_jwt_to_login_data( $data, $user_id ) {

			if ( $data['success'] === false || ! class_exists( 'AP_JWT' ) ) {
				return $data;
			}

			$token_data = array(
				'url'     => home_url( '/', 'https' ),
				'user_id' => $user_id,
			);

			$AP_JWT = new AP_JWT();
			$key    = $AP_JWT::get_secret_key();

			$jwt = $AP_JWT::encode( $token_data, $key );

			// add user id to data after login so we can use that for posting stuff to BP
			$data['user_id'] = $user_id;
			$data['token']   = $jwt;

			return $data;
		}

		public function appcommunity_endpoints() {
			require_once AppCommunity_DIR . '/includes/functions.php';

			if ( $this->isBuddyBossPluginActive() ) {
				require_once __DIR__ . '/includes/buddyboss/buddyboss.php';
			} else {
				require_once __DIR__ . '/includes/buddypress/buddypress.php';
			}
		}

		private function isBuddyBossPluginActive() {
			$pluginList = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			$plugin     = 'buddyboss-platform/bp-loader.php';

			return in_array( $plugin, $pluginList );
		}

		/**
		 * BuddyPress Push Notifications settings function
		 *
		 * @access public
		 * @param mixed $appp
		 * @return void
		 */
		public function buddypress_push_notifications_settings( $appp ) {
			if ( is_admin() ) {
				// We rely on bp-core/deprecated/2.5.php for push notifications on friend requests
				bp_update_option( '_bp_ignore_deprecated_code', false );
			}

			// Check if AppPush plugin in enabled than add these settings under 'Notifications' tab
			if ( class_exists( 'AppPresser_Notifications' ) ) {
				$appp->add_setting(
					'buddypress_push_notifications',
					__( 'BuddyPress Notifications', 'appcommunity' ),
					array(
						'type'     => 'checkbox',
						'tab'      => 'appp-notifications',
						'helptext' => __( 'Allow BuddyPress friend requests, private and public messages to send push notifications.', 'appcommunity' ),
					)
				);
			}
		}

		/*
		 * Allow video tag in activity for video uploads
		 */
		public function allow_video_tag( $allowedtags ) {
			$allowedtags['video']  = array(
				'controls' => array(),
				'height'   => array(),
				'width'    => array(),
			);
			$allowedtags['source'] = array(
				'src'  => array(),
				'type' => array(),
			);
			return $allowedtags;
		}

		/**
		 * Internationalization
		 *
		 * @access      public
		 * @since       0.1.0
		 * @return      void
		 */
		public function load_textdomain() {

			load_plugin_textdomain( 'appcommunity', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}
	}
} // End if class_exists check

/**
 * The main function responsible for returning the one true EDD_Metrics
 * instance to functions everywhere
 *
 * @since       0.1.0
 * @return      \AppCommunity The one true AppCommunity
 */
function AppCommunity_load() {

	return AppCommunity::instance();
}
add_action( 'plugins_loaded', 'AppCommunity_load' );

/**
 * bp_includes function.
 *
 * BuddyPress code needs to be loaded on bp_inlude hook
 *
 * @return void
 */
function bp_includes() {
	if ( ! AppPresser_Modules::instance()->requested( 'push' ) || 'enabled' !== AppPresser_Modules::instance()->state( 'push' ) ) { return; }
	$plugin_dir = trailingslashit( plugin_dir_path( __FILE__ ) );
	include $plugin_dir . '/includes/buddypress/bp-notifications/classes/class-appcommunity-push-notifications.php';
}
add_action( 'init', 'bp_includes', 1 );

/**
 * The activation hook is called outside of the singleton because WordPress doesn't
 * register the call from within the class, since we are preferring the plugins_loaded
 * hook for compatibility, we also can't reference a function inside the plugin class
 * for the activation function. If you need an activation function, put it here.
 *
 * @since       0.1.0
 * @return      void
 */
function AppCommunity_activation() {
	// is main plugin active? If not, throw a notice and deactivate
	if ( class_exists( 'BP_REST_Activity_Endpoint' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
<div class="notice notice-warning is-dismissible">
	<p><?php _e( 'The AppCommunity may conflict with the BuddyPress REST API plugin.', 'appcommunity' ); ?></p>
</div>
				<?php
			}
		);
		return;
	}

	// Load default settings (app_settings option) for AppCommunity
	$option = get_option( 'appp_settings' );
	if ( $option && ! isset( $option['buddypress_push_notifications'] ) ) {
		// Enable Push Notification for BuddyPress by default
		$option['buddypress_push_notifications'] = 'on';
		update_option( 'appp_settings', $option );
	}
}
