<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: AppPresser Bridge
Plugin URI: http://apppresser.com
Description: A bridge between AppPresser apps and Wordpress themes.
Text Domain: apppresser-bridge
Version: 1.2.0
Author: AppPresser Team
Author URI: http://apppresser.com
License: GPLv2
*/

class AppPresserBridge
{
    public static $plugin_slug = 'apppresser-bridge';
    const VERSION = '1.2.0';

    private static $instance;

    /**
     * Returns the active instance
     */
    public static function run()
    {
        if (!self::$instance) {
            self::$instance = new AppPresserBridge();
            self::$instance->initHooks();
        }

        return self::$instance;
    }

    private function initHooks()
    {
        require_once __DIR__ . '/includes/video-settings.php';
        require_once __DIR__ . '/includes/modify-iframe-source.php';
        require_once __DIR__ . '/includes/create-video-template-url.php';
        // Add javascript and style files
        add_action('wp_enqueue_scripts', [$this, 'appPresserBridgeFrontendScriptAndStyle']);
        add_action('admin_enqueue_scripts', [$this, 'appPresserBridgeBackendScriptAndStyle']);
        // Add settings in AppPresser core plugin
        add_action('apppresser_add_settings', [$this, 'appPresserBridgeSettings'], 60);
        // Add plugin updater

        // Sanitize bridge_custom_styles
        add_filter('apppresser_sanitize_setting_bridge_custom_styles', [$this, 'sanitize_bridge_custom_styles'], 10, 4);
        // Sanitize bridge_custom_javascript
        add_filter('apppresser_sanitize_setting_bridge_custom_javascript', [$this, 'sanitize_bridge_custom_javascript'], 10, 4);
    }

    public function appPresserBridgeFrontendScriptAndStyle()
    {
        // Check if AppPresser core plugin is installed and active
        if (class_exists('AppPresser')) {
            // Don't do this on the desktop
            if (AppPresser::is_app()) {
                if (appp_get_setting('add_push_page_class')) {
                    wp_enqueue_script('apppresser-custom-theme-navigation', plugin_dir_url(__FILE__) . 'js/navigation.js', ['jquery'], AppPresser::VERSION, true);
                }
                // Add custom script from AppPresser settings
                $bridgeCustomJavascript = appp_get_setting('bridge_custom_javascript');
                if ($bridgeCustomJavascript) {
                    wp_enqueue_script('apppresser-bridge-custom-script-js', plugin_dir_url(__FILE__) . 'js/custom-script.js', [], self::VERSION);
                    wp_add_inline_script('apppresser-bridge-custom-script-js', $bridgeCustomJavascript);
                }
                // Add custom inline styles from AppPresser settings
                $bridgeCustomStyles = appp_get_setting('bridge_custom_styles');
                if ($bridgeCustomStyles) {
                    wp_enqueue_style('apppresser-bridge-custom-style-css', plugin_dir_url(__FILE__) . 'css/custom-style.css', [], self::VERSION);
                    wp_add_inline_style('apppresser-bridge-custom-style-css', $bridgeCustomStyles);
                }
            }
        }
    }

    public function appPresserBridgeBackendScriptAndStyle()
    {
        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, 'apppresser_settings' ) ) { return; }
        if (class_exists('AppPresser')) {
            wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
            wp_enqueue_code_editor( array( 'type' => 'text/javascript' ) );
            wp_enqueue_script('apppresser-bridge-code-mirror', plugin_dir_url(__FILE__) . 'js/code-mirror.js', ['jquery', 'code-editor'], AppPresser::VERSION, true);
        }
    }

    public function appPresserBridgeSettings($appp)
    {
        // Add setting tab for theme bridge
        $appp->add_setting_tab(__('App Customization', 'app-customization'), 'app_customization_tab');
        // Add setting label
        $appp->add_setting_label(__('App Customization', 'app-customization'), [
            'tab' => 'app_customization_tab'
        ]);
        // Add input checkbox settings
        $appp->add_setting(
            'bridge_custom_styles',
            __('Custom styles', 'app-customization'),
            [
                'tab' => 'app_customization_tab',
                'type' => 'textarea',
                'description' => __('These customizations only apply when you load a webpage inside your app using an iframe page in AppPresser.', 'app-customization'),
            ]
        );
        // Add input checkbox settings
        $appp->add_setting(
            'bridge_custom_javascript',
            __('Custom javascript', 'app-customization'),
            [
                'tab' => 'app_customization_tab',
                'type' => 'textarea',
                'description' => __('These customizations only apply when you load a webpage inside your app using an iframe page in AppPresser.', 'app-customization'),
            ]
        );
        $appp->add_setting('add_push_page_class', __('Automatic link navigation', 'apppresser'), [
            'tab' => 'app_customization_tab',
            'type' => 'checkbox',
            'description' => __('Add the push-page class to links on app pages so they open in an app-managed page window with Back navigation. Works with AP3 Ion Theme and custom themes. Links starting with # and links marked external are excluded.', 'apppresser'),
        ]);
    }

    function sanitize_bridge_custom_styles($filtered_value, $value, $settings, $appp_settings)
    {
        if (isset($settings['bridge_custom_styles'])) {
            $filtered_value = $value;
        }

        return $filtered_value;
    }

    function sanitize_bridge_custom_javascript($filtered_value, $value, $settings, $appp_settings)
    {
        if (isset($settings['bridge_custom_javascript'])) {
            $filtered_value = $value;
        }

        return $filtered_value;
    }

    public function updater() {
    	// Bundled modules are updated exclusively with AppPresser core.
    }
}

AppPresserBridge::run();
