<?php
/**
 * AppPresser_Media_Settings
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

/**
 * Admin Settings
 */
class AppPresser_Media_Settings extends AppPresser {

	// A single instance of this class.
	public static $instance        = null;

	/**
	 * Creates or returns an instance of this class.
	 * @since  1.0.0
	 * @return AppPresser_Media_Settings A single instance of this class.
	 */
	public static function run() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Setup the AppPresser Settings
	 * @since  1.0.0
	 */
	function __construct() {

		$this->hooks();

	}

	public function hooks() {

		add_action( 'apppresser_add_settings', array( $this, 'download_settings' ) );
		add_filter( 'apppresser_field_override_media_post_types', array( $this, 'media_post_types' ), 10, 4 );

		add_action( 'add_meta_boxes', array( $this, 'media_url_meta_box' ) );
		add_action( 'save_post', array( $this, 'media_url_save' ), 10, 2 );

	}

	public function media_url_meta_box() {

		global $post;

		$post_types = appp_get_setting( 'media_post_types' );

		if( empty( $post_types ) ) {
			return;
		}

		if( in_array( $post->post_type, $post_types ) ) {
			add_meta_box( 'appp_media_url_meta_box', __( 'AppPresser Media URL', 'apppresser' ), array( $this, 'media_url_meta_box_render' ) );
		}

	}

	public function media_url_meta_box_render( $post ) {

		wp_nonce_field( 'appp_media_url_meta_box', 'appp_media_url_meta_box_nonce' );

		$value = get_post_meta( $post->ID, 'appp_media_url', true );

		?>
		<p><?php _e( 'Add the full URL to your media to use this post with a media list.', 'apppresser' ); ?></p>
		<input class="widefat" type="text" id="appp_media_url" name="appp_media_url" placeholder="http://mysite.com/path/to/media.mpx" value="<?php echo esc_url( $value ); ?>" />
		<?php
	}

	/**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function media_url_save( $post_id, $post ) {

		$post_types = appp_get_setting( 'media_post_types' );

		if( empty( $post_types ) ) {
			return $post_id;
		}

		if( !in_array( $post->post_type, $post_types ) ) {
			return $post_id;
		}
 
        // Check if our nonce is set.
        if ( ! isset( $_POST['appp_media_url_meta_box_nonce'] ) ) {
            return $post_id;
        }
 
        $nonce = $_POST['appp_media_url_meta_box_nonce'];
 
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'appp_media_url_meta_box' ) ) {
            return $post_id;
        }
 
        /*
         * If this is an autosave, our form has not been submitted,
         * so we don't want to do anything.
         */
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
 
        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }
 
        /* OK, it's safe for us to save the data now. */
 
        // Sanitize the user input.
        $url = sanitize_text_field( $_POST['appp_media_url'] );
 
        // Update the meta field.
        update_post_meta( $post_id, 'appp_media_url', $url );
    }

	/**
	 * Add download settings
	 * @since  1.0.0
	 */
	public function download_settings( $appp ) {

		$appp->add_setting_label( __( 'Media Settings', 'apppresser' ), array(
				'subtab' => 'general',
			) );

		$appp->add_setting( 'enable_media_urls', __( 'Media post types', 'apppresser' ), 
			array( 
				'type' => 'media_post_types', 
				'subtab' => 'general', 
				'helptext' => __( 'Check post types to use with the app media list. Next, go to each post and add media urls to the meta box.', 'apppresser' ) 
			) 
		);

	}

	public function media_post_types( $field, $key, $value, $args ) {

		$post_types    = get_post_types( array('public' => true ), 'objects' );

		$saved         = appp_get_setting( 'media_post_types' );

		foreach ( $post_types as $post_type => $object ) {

			$checked = '';

			if( $saved ) {
				$setting = is_array( $saved ) && in_array( $post_type, $saved, true );
				$checked = checked( $setting, 1, 0 );
			}

			$field .= '<label><input '. $checked .' type="checkbox" name="appp_settings[media_post_types][]" value="'. esc_attr( $post_type ) .'">&nbsp;'. $object->labels->name .'</label><br>'."\n";
		}

		return $field;
	}

}
AppPresser_Media_Settings::run();