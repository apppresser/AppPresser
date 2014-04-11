<?php
$plugins = array();
$plugins['dialogs.notification'] = array(
	'file' => 'plugins/org.apache.cordova.dialogs/www/notification.js',
	'merges' => array(
		'navigator.notification'
	),
);
$plugins['file.DirectoryEntry'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/DirectoryEntry.js',
	'clobbers' => array(
		'window.DirectoryEntry'
	),
);
$plugins['file.DirectoryReader'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/DirectoryReader.js',
	'clobbers' => array(
		'window.DirectoryReader'
	),
);
$plugins['file.Entry'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/Entry.js',
	'clobbers' => array(
		'window.Entry'
	),
);
$plugins['file.File'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/File.js',
	'clobbers' => array(
		'window.File'
	),
);
$plugins['file.FileEntry'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileEntry.js',
	'clobbers' => array(
		'window.FileEntry'
	),
);
$plugins['file.FileError'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileError.js',
	'clobbers' => array(
		'window.FileError'
	),
);
$plugins['file.FileReader'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileReader.js',
	'clobbers' => array(
		'window.FileReader'
	),
);
$plugins['file.FileSystem'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileSystem.js',
	'clobbers' => array(
		'window.FileSystem'
	),
);
$plugins['file.FileUploadOptions'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileUploadOptions.js',
	'clobbers' => array(
		'window.FileUploadOptions'
	),
);
$plugins['file.FileUploadResult'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileUploadResult.js',
	'clobbers' => array(
		'window.FileUploadResult'
	),
);
$plugins['file.FileWriter'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileWriter.js',
	'clobbers' => array(
		'window.FileWriter'
	),
);
$plugins['file.Flags'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/Flags.js',
	'clobbers' => array(
		'window.Flags'
	),
);
$plugins['file.LocalFileSystem'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/LocalFileSystem.js',
	'clobbers' => array(
		'window.LocalFileSystem'
	),
	'merges' => array(
		'window'
	),
);
$plugins['file.Metadata'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/Metadata.js',
	'clobbers' => array(
		'window.Metadata'
	),
);
$plugins['file.ProgressEvent'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/ProgressEvent.js',
	'clobbers' => array(
		'window.ProgressEvent'
	),
);
$plugins['file.requestFileSystem'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/requestFileSystem.js',
	'clobbers' => array(
		'window.requestFileSystem'
	),
);
$plugins['file.resolveLocalFileSystemURI'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/resolveLocalFileSystemURI.js',
	'clobbers' => array(
		'window.resolveLocalFileSystemURI'
	),
);
$plugins['device.device'] = array(
	'file' => 'plugins/org.apache.cordova.device/www/device.js',
	'clobbers' => array(
		'device'
	),
);
$plugins['device-motion.Acceleration'] = array(
	'file' => 'plugins/org.apache.cordova.device-motion/www/Acceleration.js',
	'clobbers' => array(
		'Acceleration'
	),
);
$plugins['device-motion.accelerometer'] = array(
	'file' => 'plugins/org.apache.cordova.device-motion/www/accelerometer.js',
	'clobbers' => array(
		'navigator.accelerometer'
	),
);
$plugins['device-orientation.CompassError'] = array(
	'file' => 'plugins/org.apache.cordova.device-orientation/www/CompassError.js',
	'clobbers' => array(
		'CompassError'
	),
);
$plugins['device-orientation.CompassHeading'] = array(
	'file' => 'plugins/org.apache.cordova.device-orientation/www/CompassHeading.js',
	'clobbers' => array(
		'CompassHeading'
	),
);
$plugins['device-orientation.compass'] = array(
	'file' => 'plugins/org.apache.cordova.device-orientation/www/compass.js',
	'clobbers' => array(
		'navigator.compass'
	),
);
$plugins['globalization.GlobalizationError'] = array(
	'file' => 'plugins/org.apache.cordova.globalization/www/GlobalizationError.js',
	'clobbers' => array(
		'window.GlobalizationError'
	),
);
$plugins['globalization.globalization'] = array(
	'file' => 'plugins/org.apache.cordova.globalization/www/globalization.js',
	'clobbers' => array(
		'navigator.globalization'
	),
);
$plugins['splashscreen.SplashScreen'] = array(
	'file' => 'plugins/org.apache.cordova.splashscreen/www/splashscreen.js',
	'clobbers' => array(
		'navigator.splashscreen'
	),
);
$plugins['contacts.contacts'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/contacts.js',
	'clobbers' => array(
		'navigator.contacts'
	),
);
$plugins['contacts.Contact'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/Contact.js',
	'clobbers' => array(
		'Contact'
	),
);
$plugins['contacts.ContactAddress'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactAddress.js',
	'clobbers' => array(
		'ContactAddress'
	),
);
$plugins['contacts.ContactError'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactError.js',
	'clobbers' => array(
		'ContactError'
	),
);
$plugins['contacts.ContactField'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactField.js',
	'clobbers' => array(
		'ContactField'
	),
);
$plugins['contacts.ContactFindOptions'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactFindOptions.js',
	'clobbers' => array(
		'ContactFindOptions'
	),
);
$plugins['contacts.ContactName'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactName.js',
	'clobbers' => array(
		'ContactName'
	),
);
$plugins['contacts.ContactOrganization'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactOrganization.js',
	'clobbers' => array(
		'ContactOrganization'
	),
);
$plugins['vibration.notification'] = array(
	'file' => 'plugins/org.apache.cordova.vibration/www/vibration.js',
	'merges' => array(
		'navigator.notification'
	),
);
$plugins['file-transfer.FileTransferError'] = array(
	'file' => 'plugins/org.apache.cordova.file-transfer/www/FileTransferError.js',
	'clobbers' => array(
		'window.FileTransferError'
	),
);
$plugins['file-transfer.FileTransfer'] = array(
	'file' => 'plugins/org.apache.cordova.file-transfer/www/FileTransfer.js',
	'clobbers' => array(
		'window.FileTransfer'
	),
);
$plugins['camera.Camera'] = array(
	'file' => 'plugins/org.apache.cordova.camera/www/CameraConstants.js',
	'clobbers' => array(
		'Camera'
	),
);
$plugins['camera.CameraPopoverOptions'] = array(
	'file' => 'plugins/org.apache.cordova.camera/www/CameraPopoverOptions.js',
	'clobbers' => array(
		'CameraPopoverOptions'
	),
);
$plugins['camera.camera'] = array(
	'file' => 'plugins/org.apache.cordova.camera/www/Camera.js',
	'clobbers' => array(
		'navigator.camera'
	),
);
$plugins['inappbrowser.InAppBrowser'] = array(
	'file' => 'plugins/org.apache.cordova.inappbrowser/www/InAppBrowser.js',
	'clobbers' => array(
		'window.open'
	),
);
$plugins['network-information.network'] = array(
	'file' => 'plugins/org.apache.cordova.network-information/www/network.js',
	'clobbers' => array(
		'navigator.connection',
		'navigator.network.connection'
	),
);
$plugins['network-information.Connection'] = array(
	'file' => 'plugins/org.apache.cordova.network-information/www/Connection.js',
	'clobbers' => array(
		'Connection'
	),
);
$plugins['battery-status.battery'] = array(
	'file' => 'plugins/org.apache.cordova.battery-status/www/battery.js',
	'clobbers' => array(
		'navigator.battery'
	),
);
$plugins['geolocation.Coordinates'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/Coordinates.js',
	'clobbers' => array(
		'Coordinates'
	),
);
$plugins['geolocation.PositionError'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/PositionError.js',
	'clobbers' => array(
		'PositionError'
	),
);
$plugins['geolocation.Position'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/Position.js',
	'clobbers' => array(
		'Position'
	),
);
$plugins['geolocation.geolocation'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/geolocation.js',
	'clobbers' => array(
		'navigator.geolocation'
	),
);
$plugins['media.Media'] = array(
	'file' => 'plugins/org.apache.cordova.media/www/Media.js',
	'clobbers' => array(
		'window.Media'
	),
);
$plugins['media-capture.CaptureAudioOptions'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureAudioOptions.js',
	'clobbers' => array(
		'CaptureAudioOptions'
	),
);
$plugins['media-capture.CaptureImageOptions'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureImageOptions.js',
	'clobbers' => array(
		'CaptureImageOptions'
	),
);
$plugins['media-capture.CaptureVideoOptions'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureVideoOptions.js',
	'clobbers' => array(
		'CaptureVideoOptions'
	),
);
$plugins['media-capture.CaptureError'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureError.js',
	'clobbers' => array(
		'CaptureError'
	),
);
$plugins['media-capture.MediaFileData'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/MediaFileData.js',
	'clobbers' => array(
		'MediaFileData'
	),
);
$plugins['media-capture.MediaFile'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/MediaFile.js',
	'clobbers' => array(
		'MediaFile'
	),
);
$plugins['media-capture.capture'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/capture.js',
	'clobbers' => array(
		'navigator.device.capture'
	),
);
$plugins['media.MediaError'] = array(
	'file' => 'plugins/org.apache.cordova.media/www/MediaError.js',
	'clobbers' => array(
		'window.MediaError'
	),
);
$plugins['console.console'] = array(
	'file' => 'plugins/org.apache.cordova.console/www/console-via-logger.js',
	'clobbers' => array(
		'console'
	),
);
$plugins['console.logger'] = array(
	'file' => 'plugins/org.apache.cordova.console/www/logger.js',
	'clobbers' => array(
		'cordova.logger'
	),
);
