<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_Notifications_Image {

	public function hooks() {
		// only Android can handle images
		add_filter( 'ap3_send_push_data', array( $this, 'image_push__android' ) );
		// add_filter( 'ap3_send_push_data', array( $this, 'custom_push__ios' ) );
	}

	/**
	 * Sample only code to customize iOS only push notifications
	 */
	public function custom_push__ios( $data ) {

		if ( ! isset( $data['custom'] ) ) {
			$data['custom'] = array();
		}

		$data['custom']['ios'] = array(
			'subtitle' => 'Test subtitle',
			'title'    => 'ios overwrite the title',
			'alert'    => 'ios overwrite the message',
		);

		$data['custom']['default_msg'] = 'Overwrite iOS default message';

		return $data;
	}

	public function image_push__android( $data ) {

		global $post;

		if ( isset( $post, $post->ID ) ) {

			$thumb_url = get_the_post_thumbnail_url( $post->ID );

			if ( $thumb_url ) {

				// must be https
				$thumb_url = preg_replace( '/^http:/i', 'https:', $thumb_url );

				if ( ! isset( $data['custom'] ) ) {
					$data['custom'] = array();
				}

				$data['custom']['android'] = array(
					'summaryText' => get_the_excerpt( $post->ID ),
					// 'title' => 'android overwrite the title',
					// 'message' => 'android overwrite the message',
					'style'       => 'picture',
					'picture'     => $thumb_url, // big image
					'image'       => $thumb_url, // right side little image
					// 'image-type' => 'circle',
					// 'vibrationPattern' => array( 0, 500, 1000, 500 ), // some phones
				);
			}
		}

		return $data;
	}
}
