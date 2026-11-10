<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


// Bail early if no core rest support.
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	return;
}

if ( bp_is_active( 'activity' ) ) {
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-activity/classes/v1/class-appcommunity-activity-endpoint.php';
	$controller = new AppCommunity_Activity_Endpoint_V1();
	$controller->register_routes();
	// v2 of API
	require_once AppCommunity_DIR . '/includes/buddypress/bp-activity/classes/v2/class-appcommunity-activity-endpoint.php';
	$controller = new AppCommunity_Activity_Endpoint_V2();
	$controller->register_routes();
}

if ( bp_is_active( 'friends' ) ) {
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-friends/classes/v1/class-appcommunity-friends-endpoint.php';
	$controller = new AppCommunity_Friends_Endpoint_V1();
	$controller->register_routes();
	// v2 of API
	require_once AppCommunity_DIR . '/includes/buddypress/bp-friends/classes/v2/class-appcommunity-friends-endpoint.php';
	$controller = new AppCommunity_Friends_Endpoint_V2();
	$controller->register_routes();
}

if ( bp_is_active( 'groups' ) ) {
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-groups/classes/v1/class-appcommunity-groups-endpoint.php';
	$controller = new AppCommunity_Groups_Endpoint_V1();
	$controller->register_routes();
	// Extended v2 of API
	require_once AppCommunity_DIR . '/includes/buddypress/bp-groups/classes/v2/class-appcommunity-groups.php';
	$controller = new AppCommunity_Groups();
	$controller->init();
}

if ( bp_is_active( 'members' ) ) {
	// Member response filters.
	require_once AppCommunity_DIR . '/includes/buddypress/bp-members/bp-members-filters.php';
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-members/classes/v1/class-appcommunity-members-endpoint.php';
	$controller = new AppCommunity_Members_Endpoint_V1();
	$controller->register_routes();
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-members/classes/v1/class-appcommunity-member-avatar-endpoint.php';
	$controller = new AppCommunity_Member_Avatar_Endpoint_V1();
	$controller->register_routes();
	// v2 of API
	require_once AppCommunity_DIR . '/includes/buddypress/bp-members/classes/v2/class-appcommunity-members-endpoint.php';
	$controller = new AppCommunity_Members_Endpoint_V2();
	$controller->register_routes();
}

if ( bp_is_active( 'messages' ) ) {
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-messages/classes/v1/class-appcommunity-messages-endpoint.php';
	$controller = new AppCommunity_Messages_Endpoint_V1();
	$controller->register_routes();
	// Extended v2 of API
	require_once AppCommunity_DIR . '/includes/buddypress/bp-messages/classes/v2/class-appcommunity-messages.php';
	$controller = new AppCommunity_Messages();
	$controller->init();
}

if ( bp_is_active( 'notifications' ) ) {
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-notifications/classes/v1/class-appcommunity-notifications-endpoint.php';
	$controller = new AppCommunity_Notifications_Endpoint_V1();
	$controller->register_routes();
	// v2 of API
	require_once AppCommunity_DIR . '/includes/buddypress/bp-notifications/classes/v2/class-appcommunity-notifications-endpoint.php';
	$controller = new AppCommunity_Notifications_Endpoint_V2();
	$controller->register_routes();
}

if ( bp_is_active( 'xprofile' ) ) {
	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-xprofile/classes/v1/class-appcommunity-xprofile-fields-endpoint.php';
	$controller = new AppCommunity_XProfile_Fields_Endpoint_V1();
	$controller->register_routes();

	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-xprofile/classes/v1/class-appcommunity-xprofile-field-groups-endpoint.php';
	$controller = new AppCommunity_XProfile_Field_Groups_Endpoint_V1();
	$controller->register_routes();

	// v1 of API (Deprecated)
	require_once AppCommunity_DIR . '/includes/buddypress/bp-xprofile/classes/v1/class-appcommunity-xprofile-data-endpoint.php';
	$controller = new AppCommunity_XProfile_Data_Endpoint_V1();
	$controller->register_routes();
}
