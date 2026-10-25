<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_Notifications_Log {

	public $enabled;
	public function __construct() {
		$this->enabled = ( appp_get_setting( 'notifications_logging', false ) == 'on' );
	}

	public function hooks() {
	}

	public function error( $data, $send_endpoint, $version = 3, $response_body_s = null ) {
		$this->log( $data, $send_endpoint, false, $version, $response_body_s );
	}

	/**
	 * @param $data array Data send to the myapppresser API
	 * $data = array(
	 *   'id' => $app_id,
	 *   'key' => $ap3_key,
	 *   'message' => $content,
	 *   'page' => $custom_page,
	 *   'title' => $title,
	 *   'url'  => $custom_url,
	 *   'device_arns' => $devices,
	 *   'segment' => $segment,
	 *   'target' => $target
	 * );
	 * @param $send_endpoint URL to the API's endpoint
	 * @param $version The app major version number
	 * @param $response_body_s A serialized response from the API response body
	 */
	public function log( $data, $send_endpoint, $success = true, $version = 3, $response_body_s = null ) {

		if ( $this->enabled === false ) {
			return;
		}

		if ( defined( 'APPPUSH_NO_LOG' ) && APPPUSH_NO_LOG ) {
			return;
		}

		$screen = ( function_exists( 'get_current_screen' ) ? get_current_screen() : null );

		$postarr = array(
			'post_content' => ( isset( $data['post_type'] ) && $data['post_type'] == 'appbuddy-message' ) ? $this->obfuscate_value( $data['message'], 1, false ) . ' (AppBuddy subject line)' : $data['message'],
			'post_excerpt' => ( isset( $data['post_type'] ) && $data['post_type'] == 'appbuddy-message' ) ? $this->obfuscate_value( $data['message'], 1, false ) . ' (AppBuddy excerpt)' : $data['message'],
			'post_title'   => ( isset( $data['post_type'] ) && $data['post_type'] == 'appbuddy-message' ) ? '(AppBuddy title is always empty)' : $data['title'],
			'post_type'    => 'apppush-log',
			'post_status'  => 'publish',
			'meta_input'   => array(
				'id'            => $data['id'],
				'key'           => $this->obfuscate_value( $data['key'] ),
				'page'          => $data['page'],
				'url'           => $data['url'],
				'device_arns'   => $data['device_arns'],
				'segment'       => ( $data['segment'] ) ? $data['segment'] : '-- (everyone)',
				'target'        => $data['target'],
				'version'       => $version,
				'response'      => ( $response_body_s ) ? $response_body_s : 'none',
				'success'       => $success,
				'send_endpoint' => $send_endpoint,
				'screen'        => ( isset( $screen ) ) ? $screen : '',
				'post_type'     => ( isset( $data['post_type'] ) ) ? $data['post_type'] : '',
			),
		);

		wp_insert_post( $postarr );
	}

	/**
	 * Turn a string into asterisks with an option to show a certain number
	 * of characters at the beginning or end of the string
	 *
	 * @param $key string The original string to obfuscate
	 * @param $show int The number of original characters to display
	 * @param $end boolean The location where the original characters will display
	 *
	 * @return string The obfuscated string
	 */
	public function obfuscate_value( $key, $show = 4, $end = true ) {

		if ( ! isset( $key ) || empty( $key ) ) {
			return '';
		}

		if ( $end ) {
			$show_char = ( strlen( $key ) > $show ) ? substr( $key, -$show ) : $key;
		} else {
			$show_char = ( strlen( $key ) > $show ) ? substr( $key, 0, $show ) : $key;
		}

		if ( strlen( $key ) > $show ) {
			$new_length = strlen( $key ) - $show;
			if ( $end ) {
				return str_repeat( '*', $new_length ) . $show_char;
			} else {
				return $show_char . str_repeat( '*', $new_length );
			}
		} else {
			return $key;
		}
	}
}
