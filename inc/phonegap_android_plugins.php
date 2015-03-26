<?php
$plugins['dialogs']['notification_android'] = array(
	'file' => 'plugins/org.apache.cordova.dialogs/www/android/notification.js',
	'merges' => array(
		'navigator.notification'
	),
);
