<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Module source:     AppLMS
 * Plugin URI:      https://apppresser.com
 * Description:     LearnDash integration for AppPresser
 * Version:         2.4.0
 * Author:          AppPresser
 * Author URI:      https://apppresser.com
 * Text Domain:     applms
 *
 * @author          AppPresser
 * @copyright       Copyright (c) AppPresser 2018
 */
if ( ! defined( 'LEARNDASH_REST_API_ENABLED' ) ) { define( 'LEARNDASH_REST_API_ENABLED', true ); }

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// this needs to be early
require_once plugin_dir_path( __FILE__ ) . 'includes/nonce-fix.php';

if ( ! class_exists( 'AppLMS' ) ) {

	/**
	 * Main AppLMS class
	 *
	 * @since       0.1.0
	 */
	class AppLMS {

		/**
		 * @var         AppLMS $instance The one true AppLMS
		 * @since       0.1.0
		 */
		private static $instance;
		public static $plugin_slug = 'applms';
		const VERSION              = '2.4.0';


		/**
		 * Get active instance
		 *
		 * @access      public
		 * @since       0.1.0
		 * @return      self The one true AppLMS
		 */
		public static function instance() {

			if ( ! self::$instance ) {
				self::$instance = new AppLMS();
				self::$instance->setup_constants();
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
			define( 'AppLMS_VER', self::VERSION );

			// Plugin path
			define( 'AppLMS_DIR', plugin_dir_path( __FILE__ ) );

			// Plugin URL
			define( 'AppLMS_URL', plugin_dir_url( __FILE__ ) );
		}

		/**
		 * Run action and filter hooks
		 *
		 * @access      private
		 * @since       0.1.0
		 * @return      void
		 */
		private function hooks() {

			if ( ! class_exists( 'AppPresser' ) ) {
				return;
			}

			add_action( 'init', array( $this, 'includes' ) );



			// only do this stuff in the app so we don't affect the desktop site
			if ( AppPresser::is_min_ver( 3 ) ) {

				// to overwrite custom templates, add to child theme
				add_action( 'setup_theme', array( $this, 'custom_ld_templates' ), 99 );
			}
		}

		/**
		 * Include necessary files
		 *
		 * @access      private
		 * @since       0.1.0
		 * @return      void
		 */
		public function includes() {

			require_once AppLMS_DIR . 'includes/class-applms-functions.php';

			require_once AppLMS_DIR . 'includes/class-applms-api.php';
		}

		/*
		 * Setup our plugin updates
		 */
		public function updater() {
			// Bundled modules are updated exclusively with AppPresser core.
		}

		public function custom_ld_templates() {
			if ( ! defined( 'LEARNDASH_TEMPLATES_DIR' ) ) {
				define( 'LEARNDASH_TEMPLATES_DIR', AppLMS_DIR . 'learndash/templates/' );
			}
		}

		/**
		 * Licensed plugins can look for updates
		 */
		public function licensing() {

			if ( ! class_exists( 'AppPresser' ) ) {
				return;
			}
		}


		/**
		 * Internationalization
		 *
		 * @access      public
		 * @since       0.1.0
		 * @return      void
		 */
		public function load_textdomain() {

			load_plugin_textdomain( 'applms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}
	}
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_Metrics
 * instance to functions everywhere
 *
 * @since       0.1.0
 * @return      \AppLMS The one true AppLMS
 */
function AppLMS_load() {

	return AppLMS::instance();
}
add_action( 'plugins_loaded', 'AppLMS_load' );


/**
 * The activation hook is called outside of the singleton because WordPress doesn't
 * register the call from within the class, since we are preferring the plugins_loaded
 * hook for compatibility, we also can't reference a function inside the plugin class
 * for the activation function. If you need an activation function, put it here.
 *
 * @since       0.1.0
 * @return      void
 */
function AppLMS_activation() {
	/* Activation functions here */
}

