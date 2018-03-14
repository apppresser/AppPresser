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

		add_action( 'rest_api_init', array( $this, 'add_api_fields' ) );

	}

	public function add_api_fields() {

		/***
		* Add featured image urls to post response.
		* Sample usage in the app files would be data.featured_image_urls.thumbnail
		***/
		register_rest_field( 'post',
		    'featured_image_urls',
		    array(
		        'get_callback'    => array( $this, 'image_sizes' ),
		        'update_callback' => null,
	            'schema'          => null,
		    )
		);

		// add urls for media
		$post_types = appp_get_setting( 'media_post_types' );

		foreach ($post_types as $type) {
			register_rest_field( $type,
			    'appp_media',
			    array(
			        'get_callback'    => array( $this, 'get_media_url' ),
			        'update_callback' => null,
		            'schema'          => null,
			    )
			);
		}
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

	public function get_media_url( $post ) {

		$value = get_post_meta( $post['id'], 'appp_media_url', true );

		$data = [];

		if( !empty( $value ) ) {
			$data['media_url'] = $value;
		} else {
			return;
		}

		return $data;

	}

}
$AppPresser_WPAPI_Mods = new AppPresser_WPAPI_Mods();
$AppPresser_WPAPI_Mods->hooks();