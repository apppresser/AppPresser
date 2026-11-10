<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_Camera_Ajax {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * setup_actions function.
	 *
	 * @access private
	 * @return void
	 */
	private function setup_actions() {
		add_action('wp_ajax_upload_image', array( $this, 'upload_photo') );
		add_action('wp_ajax_nopriv_upload_image', array( $this, 'upload_photo') );
	}


	/**
	 * appbuddy_set_upload_dir function.
	 *
	 * @access public
	 * @param mixed $upload
	 * @return void
	 */
	function appbuddy_set_upload_dir( $upload ) {
		$current_user = wp_get_current_user();

		$upload_info = wp_upload_dir();
		$upload_dir = $upload_info['basedir'];
		$upload_url = $upload_info['baseurl'];

		$path = $upload_dir . '/appcamera/' . $current_user->ID;
		$newbdir = $path;

		if ( !file_exists( $path ) )
			@wp_mkdir_p( $path );

		$newurl    = $upload_dir . '/appcamera/' . $current_user->ID;
		$newburl   = $newurl;
		$newsubdir = '/appcamera/' . $current_user->ID;

		return apply_filters( 'cover_photo_upload_dir', array(
			'path'    => $path,
			'url'     => $newurl,
			'subdir'  => $newsubdir,
			'basedir' => $newbdir,
			'baseurl' => $newburl,
			'error'   => false
		) );
	}

	/**
	 * Used by buddypress activity to obtain the URL of the
	 * uploaded image.  The upload functionality happens in
	 * AppPresser_Camera_Upload::upload_photo()
	 *
	 * @access public
	 * @return void
	 */
	public function upload_photo() {

		global $_FILES, $_POST;

		// Make sure you're submitting files
		if ( empty( $_FILES )
			|| ! isset( $_FILES['appp_cam_file'] )
			|| isset( $_FILES['appp_cam_file']['error'] )
			&& $_FILES['appp_cam_file']['error'] !== 0 && !is_user_logged_in() )
		return;


		$nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'apppcamera-nonce' ) ) return;

		// make sure to include the media uploader
		if ( ! function_exists( 'wp_handle_upload' ) )
			require_once(ABSPATH .'wp-admin/includes/file.php');

		$upload_dir = wp_upload_dir();

		// Prior to 1.0.6 the image used to get upload again here, but now we only need the URL to return via ajax
		$uploaded_file = true;

		if ( $uploaded_file ) {

			$id = $this->get_attachment_id_from_src ( $upload_dir['url'] . '/' . $_FILES['appp_cam_file']['name'] );
			$image = wp_get_attachment_image_src( $id, 'medium' );
			update_post_meta( $id, 'appbuddy', true );

			if( $image === false ) {
				// just in case wp doesn't find the image, we'll use it straight from http
				$image = $upload_dir['url'] . '/' . $_FILES['appp_cam_file']['name'];
			} else {
				// oh good, wp found it
				$image = $image[0];
			}

			wp_send_json( $image );
		} else {
			wp_send_json( __( 'Image not uploaded', 'apppresser-camera' ) );
		}
	}


	public function get_attachment_id_from_src ( $image_src ) {
			global $wpdb;
			$query = "SELECT ID FROM {$wpdb->posts} WHERE guid='$image_src'";
			$id = $wpdb->get_var($query);
			return $id;
	}

}


/**
 * appbuddy_hidden_file_input function.
 *
 * @access public
 * @return void
 */
function appbuddy_hidden_file_input() {
	echo '<input type="hidden" name="attach-image" id="attach-image" value="">';
}
add_action( 'bp_after_activity_post_form', 'appbuddy_hidden_file_input' );
