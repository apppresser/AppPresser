<?php
/**
 * Modifications to the WP-API
 *
 * @package AppPresser
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_WPAPI_Mods {

	/**
	 * Party Started
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->hooks();
	}

	public function hooks() {

		add_action( 'rest_api_init', array( $this, 'add_featured_urls' ) );

	}

	/***
	* Add featured image urls to post response.
	* Sample usage in the app files would be data.featured_image_urls.thumbnail
	***/
	public function add_featured_urls() {
		register_rest_field( 'post',
		    'featured_image_urls',
		    array(
		        'get_callback'    => array( $this, 'image_sizes' ),
		        'update_callback' => null,
	            'schema'          => null,
		    )
		);
	}

	public function image_sizes( $post ) {

	    $featured_id = get_post_thumbnail_id( $post['id'] );

		$sizes = wp_get_attachment_metadata( $featured_id );

		$size_data = new stdClass();
				
		if ( ! empty( $sizes['sizes'] ) ) {

			foreach ( $sizes['sizes'] as $key => $size ) {
				// Use the same method image_downsize() does
				$image_src = wp_get_attachment_image_src( $featured_id, $key );

				if ( ! $image_src ) {
					continue;
				}
				
				$size_data->$key = $image_src[0];
				
			}

		}

		return $size_data;
	    
	}

}
$AppPresser_WPAPI_Mods = new AppPresser_WPAPI_Mods();
$AppPresser_WPAPI_Mods->hooks();