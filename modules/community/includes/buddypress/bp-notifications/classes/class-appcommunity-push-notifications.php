<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


/**
 * AppCommunity_Push_Notifications class
 */
class AppCommunity_Push_Notifications {

	public $is_loggedin;

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		@$this->is_loggedin = is_user_logged_in();
		$this->hooks();
	}

	/**
	 * hooks function.
	 *
	 * @access public
	 * @return void
	 */
	public function hooks() {
		if ( isset( $this->is_loggedin ) && class_exists( 'AppPresser_Notifications' ) && class_exists( 'AppPresser_Notifications_Update' ) && appp_get_setting( 'buddypress_push_notifications' ) ) {
			add_action( 'messages_message_sent', array( $this, 'prepare_message_notification' ) );
			add_action( 'bp_friends_sent_request_email', array( $this, 'send_friend_request' ), 999, 5 );
			add_action( 'bp_activity_sent_mention_email', array( $this, 'send_mention' ), 1, 5 );
			add_filter( 'appp_friend_request_message', array( $this, 'filter_friend_request_message' ), 10, 2 );
		}
	}

	/**
	 * Format the message content
	 */
	public function prepare_message_notification( $message ) {
		// Show a better notification message. Same as BuddyPress email notification
		// Example: (Andre Dubus sent you a new message)
		if ( $message->sender_id ) {
			$username = bp_core_get_user_displayname( $message->sender_id );
			$subject  = sprintf( __( '%1$s sent you a new message: "%2$s"', 'appcommunity' ), $username, $message->subject );
		} else {
			$subject = sprintf( __( 'You have a new message: "%s"', 'appcommunity' ), $message->subject );
		}
		$message->subject = $subject;
		$this->send_notification_message( $message );
	}

	/**
	 * Sends a push notification for any email sent via messages
	 *
	 * @todo needs title
	 * @access public
	 * @return array
	 */
	public function send_notification_message( $bp_message ) {
		$push    = new AppPresser_Notifications_Update();
		$devices = $push->get_devices_by_user_id( $bp_message->recipients );
		if ( empty( $devices ) ) {
			return 'No registered devices, cannot send message.';
		}
		define( 'APPBUDDY_PUSH_LOG', true );
		// $fake_post is used for logging when the option to log private messages is enabled
		$fake_post             = new stdClass();
		$fake_post->post_title = $bp_message->subject;
		$fake_post->post_type  = 'appbuddy-message';
		$message               = apply_filters( 'appp_friend_message_subject', $bp_message->message, $bp_message->sender_id, $bp_message );
		$custom_url            = 'BpNotifications'; // Class name of the page component
		$push->notification_send( 'now', wp_strip_all_tags( stripslashes( $message ) ), 1, $devices, array(), $custom_url, '', '', '', $fake_post );
	}

	/**
	 * Sends a push notification for any friend request
	 *
	 * @todo turn this into toolbar button api
	 * @access public
	 * @return array
	 */
	public function send_friend_request( $friend_id, $subject, $message, $friendship_id, $initiator_id ) {
		$push    = new AppPresser_Notifications_Update();
		$devices = $push->get_devices_by_user_id( array( $friend_id ) );
		if ( empty( $devices ) ) {
			return 'No registered devices, cannot send message.';
		}
		$message    = apply_filters( 'appp_friend_request_message', $subject, $initiator_id );
		$custom_url = 'BpNotifications'; // Class name of the page component
		define( 'APPBUDDY_NO_PUSH_LOG', true );
		$push->notification_send( 'now', $message, 1, $devices, array(), $custom_url, '', '', '_self' );
	}

	/**
	 * Replace placeholders for {{{site.name}}} and {{initiator.name}}
	 *
	 * @access public
	 * @return string
	 */
	public function filter_friend_request_message( $msg, $initiator_id ) {
		$msg  = str_replace( '{{{site.name}}}', get_bloginfo( 'name' ) . ':', $msg );
		$user = get_user_by( 'ID', $initiator_id );
		$msg  = str_replace( '{{initiator.name}}', '@' . $user->user_login, $msg );
		return $msg;
	}

	/**
	 * Sends a push notification for any @mention
	 *
	 * @todo turn this into toolbar button api
	 * @access public
	 * @return array
	 */
	public function send_mention( $activity, $subject, $message, $content, $receiver_user_id ) {
		$sender_user_id = $activity->user_id;
		$user           = get_user_by( 'ID', $sender_user_id );
		$message        = '@' . $user->user_login . ' ' . __( 'just mentioned you in a post', 'appcommunity' );
		$message        = apply_filters( 'app_send_mention_message', $message, $sender_user_id, $receiver_user_id, $activity );
		$push           = new AppPresser_Notifications_Update();
		$devices        = $push->get_devices_by_user_id( array( $receiver_user_id ) );
		if ( empty( $devices ) ) {
			return 'No registered devices, cannot send message.';
		}
		$custom_url = 'BpNotifications'; // Class name of the page component
		define( 'APPBUDDY_NO_PUSH_LOG', true );
		$push->notification_send( 'now', $message, 1, $devices, array(), $custom_url, '', '', '_self' );
	}
}

$AppCommunity_Push_Notifications = new AppCommunity_Push_Notifications();
