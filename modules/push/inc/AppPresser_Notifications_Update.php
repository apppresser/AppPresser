<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Updates or creates a new post with notifications post meta.
 *
 * @since  1.0.0
 */
class AppPresser_Notifications_Update extends AppPresser_API_Response_Viewer {

	/**
	 * Pushwoosh API url
	 *
	 * @var string
	 */
	public static $is_using_segments  = null;
	public static $notification_count = 0;
	private static $send_meta_key     = 'send_push_notification';
	private static $bypass_meta_key   = 'bypass_segments_send_push';
	private static $notification_sent_meta_key = 'apppush_notification_sent';
	public $is_disabled;

	/**
	 * Our WordPress hooks
	 *
	 * @since  1.0.0
	 */
	public function hooks() {

		// send push notifications from any cpt
		$post_types = appp_get_setting( 'notifications_post_types' );

		if ( ! is_array( $post_types ) ) {
			$post_types = array();
		}

		$post_types[] = 'apppush';

		if ( is_array( $post_types ) ) {

			add_action( 'all_admin_notices', array( $this, 'send_failed_no_subscribers' ) );
			add_action( 'all_admin_notices', array( $this, 'push_api_fail' ) );
			add_action( 'all_admin_notices', array( $this, 'push_openssl_fail' ) );
			add_action( 'all_admin_notices', array( $this, 'add_notice_device_count' ) );
			add_action( 'all_admin_notices', array( $this, 'add_notice_sent_to_all' ) );
			add_action( 'all_admin_notices', array( $this, 'add_notice_missing_myapp_settings' ) );

			// loop through our post types from our settings
			foreach ( $post_types as $type ) {

				// save our notification and segment preferences on each post
				add_action( "save_post_$type", array( $this, 'set_postmeta_send' ), 11, 3 );

				// And hook in our metabox and publish methods
				add_action( "add_meta_boxes_$type", array( $this, 'add_meta_box' ) );
				add_action( "add_meta_boxes_$type", array( $this, 'api_response_metabox' ) );
				add_action( "publish_$type", array( $this, 'save' ), 10, 2 );

			}
		}

		// Core publishes all scheduled post types on this event at priority 10.
		add_action( 'publish_future_post', array( $this, 'send_notify_on_publish' ), 20 );
		add_action( 'init', array( $this, 'send_missed_scheduled_notifications' ) );
	}

	/**
	 * Check if we want to output debug form data to the content
	 *
	 * @since  1.0.0
	 * @return boolean True if debug mode is on
	 */
	public function is_debug() {
		return ( isset( $_POST['app_debug'] ) || defined( 'APPPRESSER_DEBUG' ) && APPPRESSER_DEBUG );
	}

	/**
	 * @since 3.4.0
	 */
	public function is_disabled() {
		if ( is_null( $this->is_disabled ) ) {
			$this->is_disabled = ( appp_get_setting( 'notifications_disable', false ) == 'on' );
		}

		return $this->is_disabled;
	}

	public function get_devices_by_user_id( $user_ids = array() ) {

		$meta_key = 'ap3_endpoint_arns';

		$device_ids = null;

		$_user_ids = array();

		foreach ( $user_ids as $user ) {
			if ( is_object( $user ) ) {
				$_user_ids[] = $user->user_id;
			} else {
				$_user_ids[] = $user;
			}
		}

		if ( ! empty( $_user_ids ) ) {
			global $wpdb;

			// only accept INT in sql
			$_user_ids = array_map( 'absint', $_user_ids );
			$_user_ids = implode( ',', $_user_ids );
			$sql       = "SELECT `meta_value` FROM $wpdb->usermeta WHERE `meta_key` = '$meta_key' AND `user_id` IN ($_user_ids)";
			$results   = $wpdb->get_results( $sql, ARRAY_N );

			if ( $results ) {

				$device_ids = array();
				// make sure we don't have any multidimensional arrays
				foreach ( $results as $key => $value ) {

					if ( is_array( $value ) ) {

						$value = $value[0];

						if ( is_serialized( $value ) ) {
							$value      = unserialize( $value );
							$device_ids = array_merge( $device_ids, $value );
						} else {
							array_push( $device_ids, $value );
						}
					} else {
						array_push( $device_ids, $value );
					}
				}
			}
		}

		return $device_ids;
	}

	/**
	 * Compile the arguments for the send_post_request function
	 *
	 * @param $send_date string 'now'
	 * @param $content string
	 * @param $badges int The number of badges to show
	 * @param $devices array An array of device ids
	 * @param $data array Custom data
	 * @param $custom_url string
	 * @param $custom_page string
	 * @param $segment string AWS topic arn
	 * @param $target string _blank, _self, _system
	 * @param $post object A post
	 **/
	public function notification_send( $send_date, $content, $badges, $devices = array(), $data = array(), $custom_url = '', $custom_page = '', $segment = '', $target = '', $post = null ) {
		if ( 'enabled' !== AppPresser_Modules::instance()->state( 'push' ) ) {
			return;
		}

		$api_key   = appp_get_setting( 'notifications_ap3_key' );
		$site_slug = appp_get_setting( 'ap3_site_slug' );
		$app_id    = appp_get_setting( 'ap3_app_id' );

		if ( ! $api_key ) {
			return;
		}

		if ( $devices === false ) {
			$devices = array();
		}

		$site_slug = str_replace( '/', '', $site_slug );

		$api_domain = ( defined( 'MYAPPPRESSER_DEV_DOMAIN' ) ) ? MYAPPPRESSER_DEV_DOMAIN : 'https://myapppresser.com/';

		$api = $api_domain . $site_slug . '/wp-json/ap3/v1/';

		$send_endpoint = $api . 'send';

		if ( isset( $_POST['notify_url'] ) ) {

			if ( strpos( $_POST['notify_url'], 'http' ) === 0 ) {
				$custom_url = $_POST['notify_url'];
				$blog_url   = get_bloginfo( 'url' );
				$target     = ( strpos( $custom_url, $blog_url ) === 0 ) ? '_self' : '';
			} else {
				$custom_page = $_POST['notify_url'];
			}
		}

		$title = ( isset( $_POST['post_title'] ) ) ? $this->remove_linebreaks( $_POST['post_title'] ) : '';
		if ( $title === '' && isset( $post, $post->post_title ) ) {
			$title = $this->remove_linebreaks( $post->post_title );
		}

		$content = $this->remove_linebreaks( $content );

		// Encode our data
		$data = array(
			'id'          => $app_id,
			'key'         => $api_key,
			'message'     => $content,
			'page'        => $custom_page,
			'title'       => $title,
			'url'         => $custom_url,
			'device_arns' => $devices,
			'segment'     => $segment,
			'target'      => $target,
		);


		$data = apply_filters( 'ap3_send_push_data', $data );

		// Define the args for the API
		$args['body']    = $data;
		$args['timeout'] = 20;
		// $args['headers']['Content-Type'] = 'Content-Type: application/json';

		if ( $this->is_disabled() ) {
			$response = __( 'AppPush notifications has been disabled in the Admin settings', 'apppresser-push' );
		} else {
			// call the API via the WP HTTP API
			$response = wp_remote_post( esc_url_raw( $send_endpoint ), $args );
		}

		// bail here so we don't send through Pushwoosh also
		return $this->handle_myappp_api_response( $response, $data, $send_endpoint, $post );
	}

	public function handle_myappp_api_response( $response, $data, $send_endpoint, $post = null ) {
		$log = new AppPresser_Notifications_Log();

		if ( isset( $post, $post->post_type ) ) {
			$data['post_type'] = $post->post_type;
		}

		if ( $this->is_disabled ) {

			$log->error( $data, $send_endpoint, 3, $response );

		} elseif ( is_wp_error( $response ) ) {

			// API call fail, show admin message
			add_filter( 'redirect_post_location', array( $this, 'add_push_api_fail_query_var' ), 99 );

			$log->error( $data, $send_endpoint );

		} else {
			$response_body   = wp_remote_retrieve_body( $response );
			$response_body_s = serialize( $response_body );
			$response_code   = (int) wp_remote_retrieve_response_code( $response );
			if ( $response_code < 200 || $response_code >= 300 || ( strpos( $response_body, 'Sending' ) === false && strpos( $response_body, 'Sent ' ) === false ) ) {
				$log->error( $data, $send_endpoint, 3, $response_body_s );
			} else {
				if ( isset( $post, $post->post_type ) ) {
					update_post_meta( $post->ID, self::$notification_sent_meta_key, '1' );
					if ( $post->post_type === AppPresser_Notifications::$cpt ) {
						update_post_meta( $post->ID, '_apppush_notification_sent', '1' );
					}
					if ( get_post_meta( $post->ID, self::$send_meta_key . '_future', true ) === 'holding' ) {
						$this->set_future_schedule( $post->ID, $post, 'sent' );
					}
				}
				$log->log( $data, $send_endpoint, true, 3, $response_body_s );
			}
		}

		return $response;
	}

	public static function is_using_segments() {

		if ( self::$is_using_segments === null ) {
			self::$is_using_segments = ( appp_get_setting( 'notifications_allow_segments' ) == '1' );
		}

		return self::$is_using_segments;
	}

	/**
	 * Replace all linebreaks and any multiple spaces with a single space
	 */
	public function remove_linebreaks( $content ) {
		return preg_replace( '/\s+/', ' ', str_replace( array( "\r\n", "\r", "\n" ), ' ', $content ) );
	}

	public function send_push_notification( $post_id, $post ) {

		$custom_url  = '';
		$custom_page = null;
		$target      = '';

		// Add excerpt to message if it exists
		if ( ! empty( $post->post_excerpt ) ) {
			$message = $post->post_excerpt;
		} elseif ( isset( $post->post_content ) ) {
			$message = wp_strip_all_tags( $post->post_content, true );
		}

		// Keep the 150-byte limit without splitting a UTF-8 character.
		if ( function_exists( 'mb_strcut' ) ) {
			$message = mb_strcut( $message, 0, 150, 'UTF-8' );
		} else {
			$length = 150;
			// If the first excluded byte is a continuation byte, omit its whole character.
			while ( $length > 0 && isset( $message[ $length ] ) && ( ord( $message[ $length ] ) & 0xC0 ) === 0x80 ) {
				$length--;
			}
			$message = substr( $message, 0, $length );
		}

		if ( $post->post_type == AppPresser_Notifications::$cpt ) {

			$notify_url = '';

			if ( isset( $_POST['notify_url'] ) && ! empty( $_POST['notify_url'] ) ) {
				$notify_url = $_POST['notify_url'];
			} else {
				$notify_url_post_meta = get_post_meta( $post->ID, 'apppush_notify_url', true );
				if ( $notify_url_post_meta ) {
					$notify_url = $notify_url_post_meta;
				}
			}

			if ( $notify_url ) {
				if ( strpos( $notify_url, 'http' ) === 0 ) {
					$custom_url = $notify_url;
				} else {
					$custom_page = $notify_url;
				}
			}
		} else {

			$send_push = get_post_meta( $post_id, self::$send_meta_key, true );

			if ( $send_push == '1' ) {
				$custom_url = get_permalink( $post_id );
				$target     = '_self';
			} else {
				// Don't send a notification if the checkbox wasn't checked
				return;
			}
		}

		$message = $this->strip_gutenberg_tags( $message );

		// Allow message filtering
		$message    = apply_filters( 'send_push_post_content', $message, $post_id, $post );
		$custom_url = apply_filters( 'send_push_custom_url', $custom_url, $post_id, $post );
		$target     = apply_filters( 'send_push_custom_url_target', $target, $post_id, $post, $custom_url );

		$segment = ( ! empty( $_POST['segments'] ) ? $_POST['segments'] : get_post_meta( $post_id, '_appp_segment', true ) );

		$device_ids = $this->get_device_ids( $post );

		$notifications_api_key = appp_get_setting( 'notifications_ap3_key' );

		$future_notification_meta = get_post_meta( $post_id, self::$send_meta_key . '_future', true );
		if ( $future_notification_meta === 'sent' ) {
			return;
		}

		// if using AP3, just send push, skip all segmenting checks below
		if ( ! empty( $notifications_api_key ) ) {
			$audience = ( isset( $_POST['apppush_audience'] ) ) ? $_POST['apppush_audience'] : get_post_meta( $post->ID, 'apppush_audience', true );
			
			// Clear segment if not using segment audience
			if ( $audience !== 'segment' ) {
				$segment = '';
			}

			// Handle segment audience: get_device_ids returns false for segment, so reset to empty array
			if ( $audience === 'segment' ) {
				$device_ids = array();
				if ( empty( $segment ) ) {
					return;
				}
			}

			// If device_ids is false, no devices found for the targeted users so don't send notification
			if ( $device_ids === false ) {
				return;
			}
			
			$response = $this->notification_send( 'now', $message, 1, $device_ids, null, $custom_url, $custom_page, $segment, $target, $post );
			return $response;
		}
	}

	/**
	 * Strip Gutenberg comments like
	 * <!-- wp:paragraph -->
	 * <!-- /wp:paragraph -->
	 */
	public function strip_gutenberg_tags( $content ) {
		$pattern = '/<!-- \/?wp:(.*)-->/mU';
		return preg_replace( $pattern, '', $content );
	}

	public function push_api_fail() {
		if ( ! isset( $_GET['push_api_fail'] ) ) {
			return;
		}
		echo '<div id="message" class="update-nag notice"><p>Notification not sent, we couldn\'t reach the API. Please check your site slug and App ID in the AppPresser settings, then try with a new push.</p></div>';
	}

	public function push_openssl_fail() {
		if ( ! isset( $_GET['push_openssl_fail'] ) ) {
			return;
		}

		$curl_version = curl_version();

		echo '<div id="message" class="error"><p>' . sprintf( __( '<b>Notification not sent; we couldn\'t reach the API to https://myappresser.com/.</b><br> %1$s requires a CURL version that supports OpenSSL. Contact your hosting provider to update CURL on your server.  <br><br><b>Your versions:</b><br>CURL version: ' . $curl_version['version'] . '<br>SSL version: ' . $curl_version['ssl_version'], 'apppresser-push' ), AppPresser_Notifications::PLUGIN ) . '</p></div>';
	}

	public function send_failed_no_subscribers() {
		if ( ! isset( $_GET['send_push_failed'] ) ) {
			return;
		}
		echo '<div id="message" class="update-nag notice"><p>' . sprintf( __( 'Since you have <a href="%s" target="_blank">segmented notifications enabled</a>, the notification did not send because there were no subscribers to any of the selected categories.  You must either select a category to which users subscribe or select the "Send this push to everyone" checkbox before sending.  To try again, you can set this post status to draft, press update, modify your settings and publish again.', 'apppresser-push' ), admin_url( 'admin.php?page=apppresser_settings&tab=tab-appp-notifications' ) ) . '</p></div>';
	}

	public function add_notice_device_count() {
		if ( ! isset( $_GET['add_notice_device_count'] ) || ! is_numeric( $_GET['add_notice_device_count'] ) ) {
			return;
		}
		echo '<div id="message" class="updated notice notice-success is-dismissible"><p>' . sprintf( _n( 'Since you have <a href="%1$s" target="_blank">segmented notifications enabled</a>, the notification was sent to %2$s device.', 'Since you have <a href="%1$s" target="_blank">segmented notifications enabled</a>, the notification was sent to %2$s devices.', (int) $_GET['add_notice_device_count'], 'apppresser-push' ), admin_url( 'admin.php?page=apppresser_settings&tab=tab-appp-notifications' ), $_GET['add_notice_device_count'] ) . '</p></div>';
	}

	public function add_notice_sent_to_all() {
		if ( ! isset( $_GET['sent_to_all'] ) ) {
			return;
		}
		echo '<div id="message" class="updated notice notice-success is-dismissible"><p>' . __( 'The notification was sent to all devices.', 'apppresser-push' ) . '</p></div>';
	}

	/**
	 * If user has entered a AppPresser Notification Key,
	 * let them know they need to add the site slug and app id too.
	 */
	public function add_notice_missing_myapp_settings() {
		if ( appp_get_setting( 'notifications_ap3_key' ) && ! $this->verify_myapppresser_settings() ) {
			echo '<div id="message" class="updated error notice-success is-dismissible"><p>' . sprintf( __( '<b>AppBuddy:</b> You have added an <b>AppPresser Notifications Key</b>, so you must also enter your <b>Site slug</b> and <b>App ID</b> to send push notifications through your myapppresser.com account. %1$sAppPresser Settings%2$s', 'apppresser-push' ), '<a href="' . admin_url( 'admin.php?page=apppresser_settings' ) . '">', '</a>' ) . '</p></div>';
		}
	}

	public function verify_myapppresser_settings() {
		$api_key   = appp_get_setting( 'notifications_ap3_key' );
		$site_slug = appp_get_setting( 'ap3_site_slug' );
		$app_id    = appp_get_setting( 'ap3_app_id' );

		$auto_load = true;

		if ( $api_key && $site_slug && $app_id ) {
			if ( ! get_option( 'verify_ap3_push_settings' ) ) {
				update_option( 'verify_ap3_push_settings', true, $auto_load );
			}
			return true;
		} else {
			update_option( 'verify_ap3_push_settings', false, $auto_load );
			return false;
		}
	}

	public function add_push_api_fail_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		return add_query_arg( array( 'push_api_fail' => '1' ), $location );
	}

	public function add_push_openssl_fail_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		return add_query_arg( array( 'push_openssl_fail' => '1' ), $location );
	}

	public function add_notice_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		return add_query_arg( array( 'send_push_failed' => '1' ), $location );
	}

	public function add_notice_device_count_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_device_count_query_var' ), 99 );
		return add_query_arg( array( 'add_notice_device_count' => self::$notification_count ), $location );
	}

	public function add_notice_send_to_all_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_send_to_all_query_var' ), 99 );
		return add_query_arg( array( 'sent_to_all' => '1' ), $location );
	}

	/**
	 * Gets the device id based on whether the admin chooses to use segments and based
	 * user's preferences
	 *
	 * @param $post object A post
	 *
	 * @return array|boolean Either an empty array, an array of strings of device ids or false
	 */
	public function get_device_ids( $post ) {
		if ( ! is_object( $post ) ) {
			return array();
		}

		$audience = ( isset( $_POST['apppush_audience'] ) ) ? $_POST['apppush_audience'] : get_post_meta( $post->ID, 'apppush_audience', true );
		$device_ids = array();

		if ( $audience === 'role' ) {
			$roles = ( isset( $_POST['apppush_roles'] ) ) ? $_POST['apppush_roles'] : get_post_meta( $post->ID, 'apppush_roles', true );
			if ( ! empty( $roles ) ) {
				$args = array(
					'role__in' => $roles,
					'fields'   => 'ID',
				);
				$users = get_users( $args );
				$device_ids = $this->get_devices_by_user_id( $users );
			}
		} elseif ( $audience === 'specific' ) {
			$specific_users = ( isset( $_POST['apppush_specific_users'] ) ) ? $_POST['apppush_specific_users'] : get_post_meta( $post->ID, 'apppush_specific_users', true );
			if ( ! empty( $specific_users ) ) {
				$user_ids = explode( ',', $specific_users );
				$device_ids = $this->get_devices_by_user_id( $user_ids );
			}
		} elseif ( $audience === 'memberpress' ) {
			$membership_id = ( isset( $_POST['apppush_memberpress_membership'] ) ) ? $_POST['apppush_memberpress_membership'] : get_post_meta( $post->ID, 'apppush_memberpress_membership', true );
			if ( ! empty( $membership_id ) ) {
				$user_ids = AppPresser_Notifications_CPT::get_memberpress_membership_users( $membership_id );
				if ( ! empty( $user_ids ) ) {
					$device_ids = $this->get_devices_by_user_id( $user_ids );
				}
			}
		} elseif ( $audience === 'rcp' ) {
			$level_id = ( isset( $_POST['apppush_rcp_level'] ) ) ? $_POST['apppush_rcp_level'] : get_post_meta( $post->ID, 'apppush_rcp_level', true );
			if ( ! empty( $level_id ) ) {
				$user_ids = AppPresser_Notifications_CPT::get_rcp_membership_level_users( $level_id );
				if ( ! empty( $user_ids ) ) {
					$device_ids = $this->get_devices_by_user_id( $user_ids );
				}
			}
		} elseif ( $audience === 'segment' ) {
			// If audience is segment, we return false here so we don't send to specific devices.
			// The send_push_notification function will handle the segment parameter.
			return false;
		} elseif ( $audience === 'all' || empty( $audience ) ) {
			// If 'all' or empty (legacy/default), we return empty array which means send to all in AP3
			return array();
		}

		// If we have specific devices, return them. 
		// If we selected a role/specific users/memberpress/rcp but found no devices, return false to prevent sending to all.
		if ( ! empty( $device_ids ) ) {
			return $device_ids;
		} elseif ( $audience === 'role' || $audience === 'specific' || $audience === 'memberpress' || $audience === 'rcp' ) {
			return false;
		}

		// Fallback to legacy segment logic if needed, or just return empty array
		if ( $this->is_using_segments() && ! $this->is_send_to_all( $post ) ) {
			$device_ids = AppPresser_Notifications_Segments::get_device_ids_by_post( $post );
			if ( empty( $device_ids ) ) {
				return false;
			} else {
				return $device_ids;
			}
		}

		return array();
	}

	public function is_send_to_all( $post ) {
		$checked = get_post_meta( $post->ID, self::$bypass_meta_key, true );

		return ( $checked === '1' );
	}

	public function add_meta_box( $post ) {

		// Don't add the metabox to the apppush post type
		if ( $post->post_type == 'apppush' ) {
			return;
		}

		// Don't show metabox once post has been published
		if ( 'publish' == $post->post_status ) {
			return;
		}

		add_meta_box(
			'appnotifications',
			__( 'AppPush', 'apppresser-push' ),
			array( $this, 'notification_metabox' ),
			$post->post_type,
			'side',
			'high'
		);
	}

	public function notification_metabox( $post ) {

		$post_type = get_post_type_object( $post->post_type );

		$checked = get_post_meta( $post->ID, self::$send_meta_key, true );

		$notifications_api_key = appp_get_setting( 'notifications_ap3_key' );

		if ( $post->post_type != 'apppush' || self::is_using_segments() ) {
			echo '<table id="segments-new-post-options" class="widefat">';

			if ( $post->post_type != 'apppush' ) {
				echo '<tr><td><input type="checkbox" name="' . self::$send_meta_key . '" value="1" ' . checked( $checked, '1', false ) . '></td><td>' . sprintf( __( 'Send a push notification when this %s is published.', 'apppresser-push' ), $post_type->labels->singular_name );
				echo wp_nonce_field( 'push_text_description', 'apppush_nonce' ) . '</td></tr>';
			}

			if ( $notifications_api_key ) {
				$segments = AppPresser_Notifications_CPT::get_segments();
				if ( $segments ) {
					echo '<tr><td colspan="2">';
						$segment_cat = get_post_meta( $post->ID, '_appp_segment', true );
						AppPresser_Notifications_CPT::get_segments_dropdown( $segments, $segment_cat );
					echo '</td></tr>';
				}
			}

			echo '</table>';
		}
	}

	public function set_postmeta_send( $post_id, $post, $update = null ) {

		$send_push_notification = '';

		// Check if our nonce is set.
		if ( ! isset( $_POST['apppush_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['apppush_nonce'], 'push_text_description' ) ) {
			die( 'nonce invalid' );
		}

		if ( isset( $_POST[ self::$send_meta_key ] ) && $_POST[ self::$send_meta_key ] == '1' ) {
			update_post_meta( $post_id, self::$send_meta_key, '1' );
			$send_push_notification = '1';
		} else {
			delete_post_meta( $post_id, self::$send_meta_key );
		}

		if ( $post->post_status == 'future' && ( $send_push_notification == '1' || $post->post_type == AppPresser_Notifications::$cpt ) ) {
			$this->set_future_schedule( $post_id, $post );
		}

		if ( isset( $_POST[ self::$bypass_meta_key ] ) && $_POST[ self::$bypass_meta_key ] == '1' ) {
			update_post_meta( $post_id, self::$bypass_meta_key, '1' );
		} else {
			delete_post_meta( $post_id, self::$bypass_meta_key );
		}

		if ( isset( $_POST['segments'] ) ) {
			update_post_meta( $post_id, '_appp_segment', $_POST['segments'] );
		} else {
			delete_post_meta( $post_id, '_appp_segment' );
		}
	}

	public function set_future_schedule( $post_id, $post, $status = 'holding' ) {
			update_post_meta( $post_id, self::$send_meta_key . '_future', $status );
	}

	public function send_missed_scheduled_notifications() {
		$post_ids = $this->get_missed_scheduled_notifications();

		if ( $post_ids ) {
			foreach ( $post_ids as $post_id ) {
				$this->send_notify_on_publish( (int) $post_id );
			}
		}
	}

	public function get_missed_scheduled_notifications() {
		global $wpdb;

		$sql = "
			SELECT post_id FROM `{$wpdb->postmeta}` m
				JOIN `{$wpdb->posts}` p on p.ID = m.post_id
					WHERE `meta_key` = 'send_push_notification_future' 
						AND `meta_value` = 'holding'
						AND `post_status` = 'publish'
							AND p.post_date_gmt >= %s
		";

		$results = $wpdb->get_col( $wpdb->prepare( $sql, get_option( 'apppresser_push_resumed_at', '1970-01-01 00:00:00' ) ) );

		return $results;
	}

	public function send_notify_on_publish( $post_id, $post = null ) {

		if ( $post === null ) {
			$post = get_post( $post_id );
		}

		$post_types = appp_get_setting( 'notifications_post_types' );
		if ( ! is_array( $post_types ) ) {
			$post_types = array();
		}
		$post_types[] = AppPresser_Notifications::$cpt;
		if ( ! $post || ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// Check if notification was already sent
		$notification_sent = get_post_meta( $post_id, self::$notification_sent_meta_key, true );
		if ( $notification_sent === '1' || ( $post->post_type === AppPresser_Notifications::$cpt && get_post_meta( $post_id, '_apppush_notification_sent', true ) === '1' ) ) {
			return;
		}

		$send_push_notification = get_post_meta( $post_id, self::$send_meta_key, true );

		if ( $post->post_status == 'publish' && ( $send_push_notification == '1' || $post->post_type == AppPresser_Notifications::$cpt ) ) {
			// send push notification
			$response = $this->send_push_notification( $post_id, $post );
			update_post_meta( $post_id, '_myappp_push_api_response', $response );
		}
	}

	public function save( $post_id, $post ) {

		/**
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['apppush_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['apppush_nonce'], 'push_text_description' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted,
		// so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( $post->post_type === 'apppush' && empty( $post->post_excerpt ) ) {
			update_post_meta( $post_id, '_myappp_push_api_response', 'Notification content is required, please add notification text and press update.' );
			return;
		}

		// The checkbox is a send preference, not evidence that a push was sent.
		$sent_meta_key = ( $post->post_type === AppPresser_Notifications::$cpt ) ? '_apppush_notification_sent' : self::$notification_sent_meta_key;
		$notification_sent = get_post_meta( $post_id, $sent_meta_key, true );
		if ( $notification_sent === '1' ) {
			return;
		}

		// Use the submitted checkbox so an unchecked draft preference cannot trigger a push.
		if ( $post->post_type !== AppPresser_Notifications::$cpt && ( ! isset( $_POST[ self::$send_meta_key ] ) || $_POST[ self::$send_meta_key ] != '1' ) ) {
			return;
		}

		if ( isset( $_POST[ self::$send_meta_key ] ) && $_POST[ self::$send_meta_key ] == '1' ) {
			update_post_meta( $post_id, self::$send_meta_key, '1' );

			if ( $post->post_status == 'future' ) {
				update_post_meta( $post_id, self::$send_meta_key . '_future', 'holding' );
			}
		}

		if ( $post->post_type == AppPresser_Notifications::$cpt && $post->post_status == 'future' ) {
			update_post_meta( $post_id, self::$send_meta_key . '_future', 'holding' );
		}

		if ( isset( $_POST[ self::$bypass_meta_key ] ) && $_POST[ self::$bypass_meta_key ] == '1' ) {
			update_post_meta( $post_id, self::$bypass_meta_key, '1' );
		}

		if ( isset( $_POST['segments'] ) ) {
			update_post_meta( $post_id, '_appp_segment', $_POST['segments'] );
		}

		// send push notification
		$response = $this->send_push_notification( $post_id, $post );

		update_post_meta( $post_id, '_myappp_push_api_response', $response );
	}

	/**
	 * Sends an code to the app, used to grant permission to reset the password
	 * from within the app.
	 *
	 * @param $user_login string
	 * @param $endpointArn string
	 *
	 * @return $response string The notification send response
	 */
	public function send_password_reset_code( $user_login, $endpointArn ) {

		if ( empty( $user_login ) ) {
			return false;
		} elseif ( strpos( $user_login, '@' ) ) {
			$user = get_user_by( 'email', trim( $user_login ) );
			if ( empty( $user ) ) {
				return false;
			}
		} else {
			$login = trim( $user_login );
			$user  = get_user_by( 'login', $login );
			if ( empty( $user ) ) {
				return false;
			}
		}

		$devices = $this->get_devices_by_user_id( $user->ID );

		$send_date = 'now';
		$passcode  = $this->randomPasscode( apply_filters( 'apppush_random_passcode_length', 6 ) );
		$content   = apply_filters( 'apppush_random_passcode_msg', $passcode );
		$badges    = 1;
		$devices   = array();

		define( 'APPPUSH_NO_LOG', true );
		$response = $this->notification_send( $send_date, $content, $badges, $devices );

		$response = apply_filters( 'apppush_random_passcode_response', $response );

		return $response;
	}

	/**
	 * Generate a randomPasscode
	 *
	 * @param $length number The length of the passcode to create
	 */
	public function randomPasscode( $length ) {
		$chars      = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!$&+';
		$pass       = array(); // remember to declare $pass as an array
		$charLength = strlen( $chars ) - 1; // put the length -1 in cache
		for ( $i = 0; $i < 8; $i++ ) {
			$n      = rand( 0, $charLength );
			$pass[] = $chars[ $n ];
		}
		return implode( $pass ); // turn the array into a string
	}
}


/**
 * apppush_send_notification function.
 *
 * @access public
 * @param mixed $content
 * @return void
 */
function apppush_send_notification( $content ) {

	if ( empty( $content ) ) {
		return;
	}

	$appp_push = new AppPresser_Notifications_Update();
	$response  = $appp_push->notification_send( 'now', $content, 1 );

	do_action( 'apppush_send_notification', $content );

	return $response;
}
