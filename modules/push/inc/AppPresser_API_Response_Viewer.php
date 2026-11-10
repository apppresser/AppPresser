<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_API_Response_Viewer {
	public function array_to_table( $array ) {
		$isIndexed = ( array_values( $array ) === $array );

		echo '<table>';
		foreach ( $array as $key => $value ) {
			echo '<tr>';
			if ( ! $isIndexed ) {
				echo '<th style="text-align:right">' . $key . '</th>';
			}
			echo '<td>';
			if ( gettype( $value ) == 'string' ) {
				echo $value;
			} elseif ( gettype( $value ) == 'array' ) {
				$this->array_to_table( $value );
			} else {
				print_r( $value );
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	public function api_response_pretty() {

		global $post;

		if ( $post ) :

			$data = get_post_meta( $post->ID, '_myappp_push_api_response', true );

			$post_obj = get_post_type_object( $post->post_type );

			$singular_name = ( isset( $post_obj->labels, $post_obj->labels->singular_name ) ) ? $post_obj->labels->singular_name : 'post';

			$myappp_domain = ( defined( 'MYAPPPRESSER_DEV_DOMAIN' ) ) ? MYAPPPRESSER_DEV_DOMAIN : 'https://myapppresser.com/';

			echo '<p>' . sprintf( __( 'The following was the response from %1$s API used to send a push notification at the time when this %2$s was published on %3$s.' ), $myappp_domain, strtolower( $singular_name ), $post->post_date ) . '</p>';

			if ( is_wp_error( $data ) ) {
				$error_string = $data->get_error_message();
				echo '<h4>Response Body</h4>' . PHP_EOL;
				echo '<div class="inside"><b>' . __( 'WP Error' ) . '</b><br>' . $error_string . '</div>';
				return;
			}

			if ( isset( $data['response'], $data['response']['code'] ) && $data['response']['code'] == 404 ) {
				echo '<h4>Response Body</h4>' . PHP_EOL;
				echo '<div class="inside"><b>' . __( '404 Not found' ) . '</b><br>' . __( 'Please check your AppPresser settings for the site slug and app id.' ) . '</div>';
				return;
			}

			if ( isset( $data['headers'] ) ) {
				echo '<h4><a href="#!" class="show-response-headers">Response Headers</a></h4>' . PHP_EOL;
				echo '<div id="api-response-headers" class="inside" style="display:none">' . PHP_EOL;
				if ( $data['headers'] instanceof Requests_Utility_CaseInsensitiveDictionary ) {
					$this->array_to_table( $data['headers']->getAll() );
				} else {
					echo '<pre>';
					print_r( $data['headers'] );
					echo '</pre>' . PHP_EOL;
				}
				echo '</div>' . PHP_EOL;
				echo '<script>jQuery(\'.show-response-headers\').on(\'click\', function() { jQuery(\'#api-response-headers\').toggle(); });</script>' . PHP_EOL;
			}

			if ( isset( $data['headers'], $data['http_response'] ) ) {
				echo '<hr>' . PHP_EOL;
			}

			if ( isset( $data['http_response'] ) ) {
				echo '<h4>Response Body</h4>' . PHP_EOL;
				echo '<div class="inside">' . PHP_EOL;
				if ( $data['http_response'] instanceof WP_HTTP_Requests_Response ) {
					$response_body_data = $data['http_response']->get_data();
					if ( gettype( $response_body_data ) == 'string' ) {
						$message = stripcslashes( $response_body_data );
						if ( strpos( $message, 'invalid key' ) !== false ) {
							echo $message . PHP_EOL;
							echo '<p style="color:red">' . __( 'Please check your <b>Site Slug</b>, <b>App ID</b> and <b>AppPresser Notifications Key</b> in your AppPresser settings. Also, be sure to add the google-services.json file on the push settings on myapppresser.com.' ) . '</p>' . PHP_EOL;
						} elseif ( strpos( $message, '<html' ) !== false ) {
							echo 'Response was HTML and that response is not correct.';
							echo '<p style="color:red">' . __( 'Please check your <b>Site Slug</b>, <b>App ID</b> and <b>AppPresser Notifications Key</b> in your AppPresser settings. Also, be sure to add the google-services.json file on the push settings on myapppresser.com.' ) . '</p>' . PHP_EOL;
						} else {
							echo $message;
						}
					} elseif ( gettype( $response_body_data ) == 'array' ) {
						$this->array_to_table( $response_body_data );
					} else {
						echo '<pre>';
						print_r( $data['http_response']->get_data() );
						echo '</pre>' . PHP_EOL;
					}
				} else {
					echo '<pre>';
					print_r( $data['http_response'] );
					echo '</pre>' . PHP_EOL;
				}
				echo '</div>' . PHP_EOL;
			} else {
				echo '<hr>' . PHP_EOL;
				echo '<pre>';
				print_r( $data );
				echo '</pre>' . PHP_EOL;
			}

		endif;
	}

	public function api_response_status() {
		global $post;

		if ( $post && $post->post_status == 'publish' ) {

			$sent = get_post_meta( $post->ID, 'send_push_notification', true );

			if ( ! $sent && $post->post_type != 'apppush' ) {
				echo '<div class="inside">' . __( 'No notification sent. "Send a push notification" was not selected.' ) . '</div>' . PHP_EOL;
			} else {
				$this->api_response_pretty();
			}
		}
	}

	/**
	 * Shows a metabox on a given posttype to display the API response from myapppresser.com for a push notification.
	 */
	public function api_response_metabox() {

		global $post;

		if ( $post && $post->post_status == 'publish' ) {
			add_meta_box( 'notificationresponse', __( 'Push Notification API Response' ), array( $this, 'api_response_status' ), $post->post_type, 'normal', 'core' );
		}
	}
}
