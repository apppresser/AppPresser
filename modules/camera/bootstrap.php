<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/*
Module source: AppCamera
Plugin URI: http://apppresser.com
Description: Integrates device camera with AppPresser
Text Domain: apppresser-camera
Domain Path: /languages
Version: 3.1.4
Author: AppPresser Team
Author URI: http://apppresser.com
License: GPLv2
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class AppPresser_Camera {

    // A single instance of this class.
    public static $instance = null;
    public static $this_plugin = null;
    public static $plugin_url = null;
    public static $plugin_dir = null;
    public static $sc_done = false;
    public static $plugin_slug = "appcamera";
    const VERSION = '3.1.4';
    public $_Settings;

    /**
     * Creates or returns an instance of this class.
     * @since  0.1.0
     * @return AppPresser_Camera A single instance of this class.
     */
    public static function go() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {

        self::$this_plugin = plugin_basename(__FILE__);
        self::$plugin_url = trailingslashit(plugins_url('', __FILE__));
        self::$plugin_dir = plugin_dir_path(__FILE__);
		// The module manager has already verified core and legacy conflicts.
        // Load translations
        load_plugin_textdomain('apppresser-camera', false, dirname(plugin_basename(__FILE__)) . '/languages');



        add_action('plugins_loaded', array($this, 'hook'));

        // Add cordova camera plugins
        add_filter('apppresser_phonegap_plugin_packages', array($this, 'phonegap_camera'));
        // Register camera js
        add_action('wp_enqueue_scripts', array($this, 'scripts_styles'));
        // Add camera shortcode
        add_shortcode('app-camera', array($this, 'camera'));
        // Set a default image size for the photo-blog feature
        add_action('after_setup_theme', array($this, 'register_photo_blog_image_size'), 15);


    }

    /*
     * Setup our plugin updates
     */
    public function updater() {
    	// Bundled modules are updated exclusively with AppPresser core.
    }

    public function register_photo_blog_image_size() {
        // Allow the size to be filtered
        $size = apply_filters('appp_camera_photo_blog_embedded_img_size', array(768, 2500, false));
        $width = isset($size[0]) && is_numeric($size[0]) ? absint($size[0]) : 768;
        $height = isset($size[1]) && is_numeric($size[1]) ? absint($size[1]) : 2500;
        $crop = isset($size[2]) && !!$size[2];
        add_image_size('photo-blog', $width, $height, $crop);
    }

    public function hook() {

        // Include file upload script
        // require_once( self::$plugin_dir .'inc/AppPresser_Camera_Upload.php' );
        // $this->_Upload = new AppPresser_Camera_Upload();
        // add_action( 'init', array( $this->_Upload, 'upload_photo' ), 999 );

        // Include settings
        require_once self::$plugin_dir . 'inc/AppPresser_Camera_Settings.php';
        $this->_Settings = new AppPresser_Camera_Settings();
        $this->_Settings->hooks();

        // Include settings
        require_once self::$plugin_dir . 'inc/AppPresser_Camera_Ajax.php';
        $this->_Settings = new AppPresser_Camera_Ajax();

        // Register camera endpoints
        add_action('rest_api_init', array($this, 'registerEndpoints'));
    }

    /**
     * Registers the camera endpoints
     */
    public function registerEndpoints() {
        require_once self::$plugin_dir . '/classes/api/v1/camera-endpoint.php';
        $controller = new CameraEndpoint();
        $controller->registerRoutes();
    }

    public function apppresser_required() {
    	// The module manager owns dependency checks.
    }

    /**
     * Set the camera text when activating the plugin (if not set)
     * @since  1.0.0
     */
    public function activate() {

        $settings = appp_get_setting();
        $text = appp_get_setting('photo_upload_not_logged_in');

        if (empty($text)) {
            $settings['photo_upload_not_logged_in'] = __('Upload your own customer image!', 'apppresser-camera');
        }

        $text = appp_get_setting('photo_upload_description');
        if (empty($text)) {
            $settings['photo_upload_description'] = __('Upload your photos!', 'apppresser-camera');
        }
        // Update text
        update_option(AppPresser::SETTINGS_NAME, $settings);
    }

    /**
     * Register camera script
     * @since  1.0.0
     */
    public function scripts_styles() {
        // Only use minified files if SCRIPT_DEBUG is off
        $min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        $depends = array('jquery');

        // Don't register appp-camera.(min.).js for version 2.
        // In versions > 2 we still need to localize appcamera,
        // but since we no longer need app-camera.js we'll
        // we'll just piggyback on to the jquery
        if (method_exists('AppPresser', 'is_min_ver') && AppPresser::is_min_ver(2)) { // only for v2 or higher
            $localized_handle = 'jquery';
        } else {
            wp_register_script('appp-camera', self::$plugin_url . "js/appp-camera$min.js", $depends, self::VERSION, true);
            wp_enqueue_script('appp-camera');
            $localized_handle = 'appp-camera';
        }

        wp_localize_script($localized_handle, 'appcamera', array(
            'moderation_on' => appp_get_setting('photo_upload_moderation') == 'on',
            'msg' => array(
                'moderation' => __('Your image has been uploaded and is waiting moderation.', 'apppresser-camera'),
                'loading' => __('Loading', 'apppresser-camera'),
                'error' => __('An error has occurred: Code', 'apppresser-camera'),
                // 'success'       => __( 'Your POST_TYPE_NAME has been created.', 'apppresser-camera' ),
                'success' => __('Your image has been uploaded.', 'apppresser-camera'),
                'default_type' => __('Post', 'apppresser-camera'),
            ),
        ));
    }

    /**
     * 'app-camera' shortcode handler
     * @since  1.0.0
     * @param  array  $atts    Shortcode attributes
     * @param  string $content Content within shortcodes
     * @return string          HTML output for camera upload form
     */
    public static function camera($atts = null, $content = null) {
        global $post;

        // This function/shortcode can/should only be used once.
        if (self::$sc_done) {
            echo '<!-- [app-camera] shortcode can/should only be used once per page -->';
            return;
        }

        $not_logged_in = appp_get_setting('photo_upload_not_logged_in')
        ? appp_get_setting('photo_upload_not_logged_in')
        : __('Upload your own customer image!', 'apppresser-camera');

        $description = appp_get_setting('photo_upload_description')
        ? appp_get_setting('photo_upload_description')
        : __('Upload your photos!', 'apppresser-camera');

        extract($appp_atts = shortcode_atts(array(
            'action' => 'this', // this,new,library
            'post_type' => 'post', //any post type, only works with new
            'post_title' => 'false', // false or true, if true shows a text box for the post title
            'not_logged_in' => $not_logged_in,
            'force_login' => true,
            'description' => $description,
        ), $atts, 'appp_shortcode_camera'));

        $pt_labels = get_post_type_labels(get_post_type_object($appp_atts['post_type']));
        $appp_atts['post_type_label'] = isset($pt_labels->new_item) ? $pt_labels->new_item : __('Post', 'apppresser-camera');

        ob_start();

        if (!is_user_logged_in() && ($force_login === true || $force_login === 'true')) {
            echo '<section class="loggedout-camera-buttons">';
            echo '<p class="camera-login-text">' . apply_filters('appp_camera_not_logged_in_text', $not_logged_in) . ' <a class="io-modal-open" href="#loginModal">' . __('Please login', 'apppresser-camera') . '</a></p>';
            //echo '<p class="camera-login-link">' . apply_filters( 'appp_camera_not_logged_in_link', '<a href="'.wp_login_url( get_permalink() ).'">'.__('Login Now',  'apppresser-camera').'</a>' ) . '</p>';

        } else {
            echo '<section class="loggedin-camera-buttons">';
            echo '<p class="camera-description">' . apply_filters('appp_camera_description', $description) . '</p>';
            ?>
			<form method="post" name="appp_camera_form" id="appp_camera_form" enctype="multipart/form-data">
			<?php wp_nonce_field('apppcamera-nonce', 'apppcamera-upload-image');?>
			<input type="hidden" name="appp_cam_post_id" id="appp_cam_post_id" value="<?php echo absint($post->ID); ?>">
			<?php do_action('appp_before_camera_buttons', $appp_atts);

            // create field for each shortcode att
            foreach ($appp_atts as $att_key => $att_value) {
                //exclude some atts
                if ($att_key != 'not_logged_in' && $att_key != 'description' && $att_key != 'post_title') {?>
					<input type="hidden" name="appp_<?php echo $att_key; ?>" id="appp_<?php echo $att_key; ?>" value="<?php echo $att_value; ?>">
				<?php }
            }

            if ($post_title && $post_title != 'false') {
                echo apply_filters('appp_camera_post_title_label', '<label>' . __('Title:', 'apppresser-camera') . '</label>');?>
				<input type="text" name="appp_cam_post_title" id="appp_cam_post_title" class="appp_cam_post_title" value="">
			<?php //need a hidden box so js wont die
            } else {?>
				<input type="hidden" name="appp_cam_post_title" id="appp_cam_post_title" class="appp_cam_post_title" value="">
			<?php }?>
			<div class="btn-group">
			  	<?php if (AppPresser::is_app()) {?>

			  		<?php

                // .btn-camera determines which button click event is needed: capturePhoto(), attachPhoto(), photoLibrary(), attachLibrary()
                $btn_action_class = ($appp_atts['action'] == 'appbuddy') ? '' : ' btn-camera';

                $btn_css_class = 'btn btn-primary';
                $btn_icon_camera = 'fa fa-camera';
                $btn_icon_gallery = 'fa fa-picture-o';

                $btn_css_class = apply_filters('appcamera_btn_classes', $btn_css_class) . $btn_action_class;

                $btn_icon_camera = apply_filters('appcamera_icon_camera', $btn_icon_camera);

                $btn_icon_gallery = apply_filters('appcamera_icon_gallery', $btn_icon_gallery);

                ?>

			  		<button type="button" class="<?php echo $btn_css_class; ?>" id="capture-photo-btn"><i class="<?php echo $btn_icon_camera; ?>"></i> <?php _e('Take Photo', 'apppresser-camera');?></button>
			  		<button type="button" class="<?php echo $btn_css_class; ?>" id="photo-library-btn"><i class="<?php echo $btn_icon_gallery; ?>"></i> <?php _e('Upload Image', 'apppresser-camera');?></button>

				<?php } else {?>
					<input type="file" name="appp_cam_file" id="appp_cam_file" accept="image/*;capture=camera">
					<input type="submit" name="browserphotoupload" value="<?php _e('Upload', 'apppresser-camera');?>">
				<?php }?>
			</div>
			<div id="cam-progress" style="visibility:hidden;">
				<progress id="progress" value="1" max="100"></progress>
			</div>
			<div id="cam-status"></div>
			</form>
			<?php
do_action('appp_after_camera_buttons', $appp_atts);
        }
        echo '</section>';
        // grab the data from the output buffer and add it to our $content variable
        $content = ob_get_contents();
        ob_end_clean();

        self::$sc_done = true;

        return $content;
    }

}
AppPresser_Camera::go();

/**
 * Helper function wrapper for AppPresser_Camera::camera, the 'app-camera' shortcode handler
 * @since  1.0.0
 * @param  array  $atts    Shortcode attributes
 * @param  string $content Content within shortcoes
 * @param  bool   $echo    Echo or return
 * @return string          HTML output for camera upload form
 */
function appp_camera($atts = null, $content = null, $echo = true) {
    $camera = AppPresser_Camera::camera($atts, $content);
    if ($echo) {
        echo $camera;
    }

    return $camera;
}
