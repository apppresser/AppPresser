<?php
/**
 * AppPresser plugin updater class
 * Handles updates to plugins with Chargebee subscriptions. Has nothing to do with EDD.
 * @since       0.1.0
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'AppPresser_Theme_Updater' ) ) {

    /**
     * AppPresser_Theme_Updater class
     *
     * @since       0.2.0
     */
    class AppPresser_Theme_Updater {

        public static $instance;
        public static $version;
        public static $plugin_slug;

        /**
         * Get active instance
         *
         * @access      public
         * @since       0.2.0
         * @return      object self::$instance The one true AppPresser_Theme_Updater
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new AppPresser_Theme_Updater();
                self::$instance->hooks();
            }
            return self::$instance;
        }

        /**
         * Include necessary files
         *
         * @access      private
         * @since       0.2.0
         * @return      void
         */
        public function hooks() {

            $theme_setup_path = WP_CONTENT_DIR . '/themes/ap3-ion-theme/inc/classes/AppPresser_3_Theme_Setup.php';

            if( !file_exists( $theme_setup_path ) ) {
                // theme is not on site, so don't try to update it
                return;
            }

            $this->check_for_updates();

            require_once( $theme_setup_path );
            $theme_setup = new AppPresser_3_Theme_Setup();
            
            $themes = array();
            $themes[] = array( "slug" => $theme_setup::THEME_SLUG, "version" => $theme_setup::VERSION );

            set_transient( 'apppresser_update_themes', $themes, 72 * HOUR_IN_SECONDS );
            
            // fixes bug where update still shows after already completed
            add_action( 'upgrader_process_complete', function( $upgrader_object, $options ) {
			    delete_transient('apppresser_update_themes');
			    set_transient( 'apppresser_theme_check', 'wait', 7 * DAY_IN_SECONDS );
            }, 10, 2 );

            // only tell our plugin to update if we have data
            if( false !== get_transient( 'apppresser_theme_update_json' ) ) {
                $this->add_update_filters();
            }

            // flush transients when update screen is loaded
            add_action( 'load-update-core.php', array( $this, 'delete_transients' ) );
        }

        // Provide a way to flush transients
        public function delete_transients() {
            delete_transient( 'apppresser_theme_update_json' );
            delete_transient('apppresser_update_themes');
            delete_transient( 'apppresser_theme_check' );
        }

        public function add_update_filters() {
            add_filter( 'site_transient_update_themes', array( $this, 'filter_update_themes' ) );
            add_filter( 'transient_update_themes', array( $this, 'filter_update_themes' ) );
        }

        // This filter has all plugins to be updated, not just AppPresser plugins
        public function filter_update_themes( $update_themes ) {

            $json = get_transient( 'apppresser_theme_update_json' );
            $themes = get_transient('apppresser_update_themes');

            // Check if themes or json data exist
            if( !$themes || !$json ) {
                return $update_themes;
            }

            foreach ($themes as $theme) {
                if( isset( $theme["slug"] ) && isset( $theme["version"] ) ) {
                    $slug = $theme["slug"];
                    var_dump($slug);

                    // Ensure the version exists before comparison
                    if (isset($json->$slug->latest_version)) {
                        $should_update = version_compare( strval( $json->$slug->latest_version ), $theme["version"] );

                        if ($should_update) {
                            // Check if $update_themes is an object or array and contains 'response'
                            if ((is_object($update_themes) || is_array($update_themes)) && property_exists($update_themes, 'response') && isset($update_themes->response[$slug])) {
                                $update_themes->response[$slug] = array(
                                    'theme' => $slug,
                                    'new_version' => $json->$slug->latest_version,
                                    'url' => $json->$slug->description,
                                    'package' => $json->$slug->download_url,
                                );
                            }
                        }
                    }
                }
            }

            return $update_themes;
        }

        /*
         * Check if we should update the plugin. A transient is set so we only make this HTTP call every 3 days.
         */
        public function check_for_updates() {

            $transient = get_transient( 'apppresser_theme_check' );

            if ( $transient && 'wait' === $transient ) {
                return;
            }

            $email = appp_get_setting( 'ap4_account_email' );

            if( empty( $email ) ) {
                return;
            }
            
            // check if user has active subscription
            $response = wp_remote_get( "https://myapppresser.com/wp-json/appp/plugin-update?email=" . $email );

            set_transient( 'apppresser_theme_check', 'wait', 72 * HOUR_IN_SECONDS );

            if( is_wp_error( $response ) || !$response ) {
                error_log('API request failed: ' . print_r($response, true));
                return;
            }

            if ( is_array( $response ) && isset($response['body']) ) {
                $body    = $response['body']; // use the content
            } else {
                error_log('Invalid response structure: ' . print_r($response, true));
                return;
            }

            // Handle different response cases
            if( !$body || $body === "false" ) {
                return;
            }

            if( $body === '"inactive"' ) {
                set_transient( 'apppresser_theme_check', 'wait', 999 * DAY_IN_SECONDS );
                return;
            }

            $json = json_decode( $body );

            if( isset( $json->themes ) ) {
                // Success, store our data
                set_transient( 'apppresser_theme_update_json', $json->themes, 72 * HOUR_IN_SECONDS );
            }
        }
    }
}
