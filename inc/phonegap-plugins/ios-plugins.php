<?php
$plugins['camera']['CameraPopoverHandle'] = array(
	'file' => 'plugins/org.apache.cordova.camera/www/ios/CameraPopoverHandle.js',
	'clobbers' => array(
		'CameraPopoverHandle'
	),
);
$plugins['contacts']['contacts-ios'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ios/contacts.js',
	'merges' => array(
		'navigator.contacts'
	),
);
$plugins['contacts']['Contact-iOS'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ios/Contact.js',
	'merges' => array(
		'Contact'
	),
);
$plugins['file']['Entry1'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/ios/Entry.js',
	'merges' => array(
		'window.Entry'
	),
);
