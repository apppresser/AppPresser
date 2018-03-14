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
			$checked = is_array( $saved ) && in_array( $post_type, $saved, true );
			$field .= '<label><input '. checked( $checked, 1, 0 ).' type="checkbox" name="appp_settings[media_post_types][]" value="'. esc_attr( $post_type ) .'">&nbsp;'. $object->labels->name .'</label><br>'."\n";
		}

		return $field;
	}

}
AppPresser_Media_Settings::run();