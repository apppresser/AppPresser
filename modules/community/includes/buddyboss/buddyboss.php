<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


// Bail early if no core rest support.
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	return;
}

if ( bp_is_active( 'activity' ) ) {
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-activity/classes/v1/class-buddyboss-activity-endpoint.php';
	$controller = new BuddyBoss_Activity_Endpoint_V1();
	$controller->register_routes();
}

if ( bp_is_active( 'friends' ) ) {
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-friends/classes/v1/class-buddyboss-friends-endpoint.php';
	$controller = new BuddyBoss_Friends_Endpoint_V1();
	$controller->register_routes();
}

if ( bp_is_active( 'groups' ) ) {
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-groups/classes/v1/class-buddyboss-groups-endpoint.php';
	$controller = new BuddyBoss_Groups_Endpoint_V1();
	$controller->register_routes();
}

if ( bp_is_active( 'members' ) ) {
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-members/classes/v1/class-buddyboss-members-endpoint.php';
	$controller = new BuddyBoss_Members_Endpoint_V1();
	$controller->register_routes();
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-members/classes/v1/class-buddyboss-member-avatar-endpoint.php';
	$controller = new BuddyBoss_Member_Avatar_Endpoint_V1();
	$controller->register_routes();
}

if ( bp_is_active( 'messages' ) ) {
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-messages/classes/v1/class-buddyboss-messages-endpoint.php';
	$controller = new BuddyBoss_Messages_Endpoint_V1();
	$controller->register_routes();
}

if ( bp_is_active( 'notifications' ) ) {
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-notifications/classes/v1/class-buddyboss-notifications-endpoint.php';
	$controller = new BuddyBoss_Notifications_Endpoint_V1();
	$controller->register_routes();
}

if ( bp_is_active( 'xprofile' ) ) {
	require_once AppCommunity_DIR . '/includes/buddyboss/bp-xprofile/classes/v1/class-buddyboss-xprofile-data-endpoint.php';
	$controller = new BuddyBoss_XProfile_Data_Endpoint_V1();
	$controller->register_routes();
}
