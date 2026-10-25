<?php
/** Module metadata. Loading this file does not load any feature code. */
defined( 'ABSPATH' ) || exit;

return array(
	'camera' => array(
		'name' => static function () { return __( 'Camera', 'apppresser' ); },
		'description' => static function () { return __( 'Photo uploads, camera shortcodes and photo moderation for your app.', 'apppresser' ); },
		'legacy' => 'appcamera/appcamera.php', 'class' => 'AppPresser_Camera', 'tab' => 'appp-cam',
		'fields' => array( 'photo_upload_moderation', 'photo_upload_email', 'upload_to_feat_img', 'photo_upload_description', 'photo_upload_not_logged_in' ),
		'checkboxes' => array( 'photo_upload_moderation', 'photo_upload_email', 'upload_to_feat_img' ),
		'defaults' => array( 'photo_upload_description' => 'Upload your photos!', 'photo_upload_not_logged_in' => 'Upload your own customer image!' ),
	),
	'commerce' => array(
		'name' => static function () { return __( 'WooCommerce', 'apppresser' ); },
		'description' => static function () { return __( 'WooCommerce carts, checkout sessions and product reviews in your app.', 'apppresser' ); },
		'legacy' => 'appcommerce/appcommerce.php', 'class' => 'AppCommerce', 'tab' => '',
		'fields' => array(), 'checkboxes' => array(), 'defaults' => array(),
		'dependency' => 'woocommerce/woocommerce.php', 'dependency_name' => 'WooCommerce',
	),
	'community' => array(
		'name' => static function () { return __( 'BuddyPress / BuddyBoss', 'apppresser' ); },
		'description' => static function () { return __( 'BuddyPress or BuddyBoss activity, profiles, groups and messages.', 'apppresser' ); },
		'legacy' => 'appcommunity/appcommunity.php', 'class' => 'AppCommunity', 'tab' => '',
		'fields' => array( 'ap_bp_jwt_key', 'buddypress_push_notifications' ),
		'checkboxes' => array( 'buddypress_push_notifications' ), 'defaults' => array(),
		'dependency' => array( 'buddypress/bp-loader.php', 'buddyboss-platform/bp-loader.php' ), 'dependency_name' => 'BuddyPress or BuddyBoss',
	),
	'lms' => array(
		'name' => static function () { return __( 'LearnDash', 'apppresser' ); },
		'description' => static function () { return __( 'Courses, lessons, quizzes and app-specific LearnDash templates.', 'apppresser' ); },
		'legacy' => 'applms/applms.php', 'class' => 'AppLMS', 'tab' => '',
		'fields' => array(), 'checkboxes' => array(), 'defaults' => array(),
		'dependency' => 'sfwd-lms/sfwd_lms.php', 'dependency_name' => 'LearnDash',
	),
	'bridge' => array(
		'name' => static function () { return __( 'App Customization', 'apppresser' ); },
		'description' => static function () { return __( 'Custom CSS, JavaScript and automatic link navigation for web pages inside your app.', 'apppresser' ); },
		'legacy' => 'apppresser-bridge/apppresser-bridge.php', 'class' => 'AppPresserBridge', 'tab' => 'app_customization_tab',
		'fields' => array( 'bridge_custom_styles', 'bridge_custom_javascript', 'add_push_page_class' ),
		'checkboxes' => array( 'add_push_page_class' ), 'defaults' => array(),
	),
	'custom-theme' => array(
		'name' => static function () { return __( 'Custom Theme Support', 'apppresser' ); },
		'description' => static function () { return __( "Enable if you don't have AP3 Ion Theme installed and your own theme is used for iFrame pages in the app. Adds support for app messaging and link behaviours to your existing theme.", 'apppresser' ); },
		'legacy' => 'apppresser-bridge/apppresser-bridge.php', 'class' => 'AppPresser_Custom_Theme_Support', 'tab' => '',
		'fields' => array(), 'checkboxes' => array(), 'defaults' => array(),
	),
	'iap' => array(
		'name' => static function () { return __( 'In-app purchases', 'apppresser' ); },
		'description' => static function () { return __( 'Purchase and subscription APIs with optional membership and course integrations.', 'apppresser' ); },
		'legacy' => 'apppresser-in-app-purchases/apppresser-in-app-purchases.php', 'class' => 'AppPresser_IAP', 'tab' => 'iapsettings',
		'fields' => array(), 'checkboxes' => array(), 'defaults' => array(),
		'options' => array_fill_keys( array( 'IAP_ARMEMBER_ACTIVE', 'IAP_ARMEMBER_KEYS', 'IAP_MEMBERPRESS_ACTIVE', 'IAP_MEMBERPRESS_KEYS', 'IAP_LEARNDASH_ACTIVE', 'IAP_LEARNDASH_KEYS', 'IAP_PMP_ACTIVE', 'IAP_PMP_KEYS', 'IAP_RCP_ACTIVE', 'IAP_RCP_KEYS', 'IAP_WOOMP_ACTIVE', 'IAP_WOOMP_KEYS' ), null ),
	),
	'push' => array(
		'name' => static function () { return __( 'Push notifications', 'apppresser' ); },
		'description' => static function () { return __( 'Send app notifications and manage notification logs and audience segments.', 'apppresser' ); },
		'legacy' => 'apppush/apppush.php', 'class' => 'AppPresser_Notifications', 'tab' => 'appp-notifications',
		'fields' => array( 'notifications_ap3_key', 'notifications_disable', 'notifications_logging', 'notifications_post_types', 'notifications_allow_segments', 'notifications_taxonomy_segments' ),
		'checkboxes' => array( 'notifications_disable', 'notifications_logging' ), 'defaults' => array(),
	),
	'plugin-controls' => array(
		'name' => static function () { return __( 'Plugin Controls', 'apppresser' ); },
		'description' => static function () { return __( 'Choose which plugins load in your app and for WordPress API requests.', 'apppresser' ); },
		'legacy' => 'apppresser-plugin-controls/apppresser-plugin-controls.php', 'class' => 'AppPresser_Plugin_Controls_Admin', 'tab' => 'plugin-controls',
		'legacy_aliases' => array( 'apppresser-plugin-block.php', 'apppresser-plugin-enable.php' ),
		'fields' => array(), 'checkboxes' => array(), 'defaults' => array(),
		'options' => array_fill_keys( array( 'apppresser-plugin-block', 'apppresser-plugin-block-api', 'apppresser-plugin-enable', 'apppresser-plugin-enable-api' ), null ),
	),
);
