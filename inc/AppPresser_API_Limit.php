<?php
class AppPresser_API_Limit {

	private static $limit = 30;

	private static function get_user_route() {
		$current_url      = $_SERVER['REQUEST_URI'];
		$api_base         = rest_get_url_prefix();
		$current_endpoint = str_replace( $api_base, '', $current_url );
		$current_endpoint = ltrim( $current_endpoint, '/' );

		return $current_endpoint;
	}

	private static function get_rate_limited_data( $user_ip ) {
		return get_transient( $user_ip . 'limited_data' );
	}

	private static function save_rate_limited_data( $user_ip, $current_minute ) {
		$rate_limit_data = array(
			'minute'   => $current_minute,
			'requests' => 1,
		);
		set_transient( $user_ip . 'limited_data', $rate_limit_data, 60 );
	}

	private static function update_rate_limited_data( $user_ip, $current_minute, $rate_limit_data ) {
		$rate_limit_data['minute']    = $current_minute;
		$rate_limit_data['requests'] += 1;
		set_transient( $user_ip . 'limited_data', $rate_limit_data, 60 );
	}

	public static function appresser_api_limit() {
		$user_ip                = $_SERVER['REMOTE_ADDR'];
		$requests_per_minute    = apply_filters( 'limited_requests_per_minute', self::$limit );
		$current_route          = self::get_user_route();
		$default_limited_routes = array(
			'appp/v1/reset-password',
			'appp/v1/login',
			'appp/v1/verify-resend',
		);
		$limited_routes         = apply_filters( 'appresser_limited_routes', $default_limited_routes );
		if ( in_array( $current_route, $limited_routes ) ) {
			$rate_limit_data = self::get_rate_limited_data( $user_ip );
			$current_minute  = floor( time() / 60 );
			if ( $rate_limit_data ) {
				if ( $rate_limit_data['minute'] === $current_minute ) {
					if ( $rate_limit_data['requests'] >= $requests_per_minute ) {
						$response = array(
							'message' => __( 'Too Many Requests.', 'apppresser' ),
						);
						wp_send_json_error( $response, 429 );
					} else {
						self::update_rate_limited_data( $user_ip, $current_minute, $rate_limit_data );
					}
				} else {
					self::save_rate_limited_data( $user_ip, $current_minute );
				}
			} else {
				self::save_rate_limited_data( $user_ip, $current_minute );
			}
		}
	}
}
add_action( 'rest_api_init', array( 'AppPresser_API_Limit', 'appresser_api_limit' ) );
