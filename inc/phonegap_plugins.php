<?php
$plugins = array();
$plugins['org.apache.cordova.dialogs.notification'] = array(
	'file' => 'plugins/org.apache.cordova.dialogs/www/notification.js',
	'merges' => array(
		'navigator.notification'
	),
);
$plugins['org.apache.cordova.file.DirectoryEntry'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/DirectoryEntry.js',
	'clobbers' => array(
		'window.DirectoryEntry'
	),
);
$plugins['org.apache.cordova.file.DirectoryReader'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/DirectoryReader.js',
	'clobbers' => array(
		'window.DirectoryReader'
	),
);
$plugins['org.apache.cordova.file.Entry'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/Entry.js',
	'clobbers' => array(
		'window.Entry'
	),
);
$plugins['org.apache.cordova.file.File'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/File.js',
	'clobbers' => array(
		'window.File'
	),
);
$plugins['org.apache.cordova.file.FileEntry'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileEntry.js',
	'clobbers' => array(
		'window.FileEntry'
	),
);
$plugins['org.apache.cordova.file.FileError'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileError.js',
	'clobbers' => array(
		'window.FileError'
	),
);
$plugins['org.apache.cordova.file.FileReader'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileReader.js',
	'clobbers' => array(
		'window.FileReader'
	),
);
$plugins['org.apache.cordova.file.FileSystem'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileSystem.js',
	'clobbers' => array(
		'window.FileSystem'
	),
);
$plugins['org.apache.cordova.file.FileUploadOptions'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileUploadOptions.js',
	'clobbers' => array(
		'window.FileUploadOptions'
	),
);
$plugins['org.apache.cordova.file.FileUploadResult'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileUploadResult.js',
	'clobbers' => array(
		'window.FileUploadResult'
	),
);
$plugins['org.apache.cordova.file.FileWriter'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/FileWriter.js',
	'clobbers' => array(
		'window.FileWriter'
	),
);
$plugins['org.apache.cordova.file.Flags'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/Flags.js',
	'clobbers' => array(
		'window.Flags'
	),
);
$plugins['org.apache.cordova.file.LocalFileSystem'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/LocalFileSystem.js',
	'clobbers' => array(
		'window.LocalFileSystem'
	),
	'merges' => array(
		'window'
	),
);
$plugins['org.apache.cordova.file.Metadata'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/Metadata.js',
	'clobbers' => array(
		'window.Metadata'
	),
);
$plugins['org.apache.cordova.file.ProgressEvent'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/ProgressEvent.js',
	'clobbers' => array(
		'window.ProgressEvent'
	),
);
$plugins['org.apache.cordova.file.requestFileSystem'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/requestFileSystem.js',
	'clobbers' => array(
		'window.requestFileSystem'
	),
);
$plugins['org.apache.cordova.file.resolveLocalFileSystemURI'] = array(
	'file' => 'plugins/org.apache.cordova.file/www/resolveLocalFileSystemURI.js',
	'clobbers' => array(
		'window.resolveLocalFileSystemURI'
	),
);
$plugins['org.apache.cordova.device.device'] = array(
	'file' => 'plugins/org.apache.cordova.device/www/device.js',
	'clobbers' => array(
		'device'
	),
);
$plugins['org.apache.cordova.device-motion.Acceleration'] = array(
	'file' => 'plugins/org.apache.cordova.device-motion/www/Acceleration.js',
	'clobbers' => array(
		'Acceleration'
	),
);
$plugins['org.apache.cordova.device-motion.accelerometer'] = array(
	'file' => 'plugins/org.apache.cordova.device-motion/www/accelerometer.js',
	'clobbers' => array(
		'navigator.accelerometer'
	),
);
$plugins['org.apache.cordova.device-orientation.CompassError'] = array(
	'file' => 'plugins/org.apache.cordova.device-orientation/www/CompassError.js',
	'clobbers' => array(
		'CompassError'
	),
);
$plugins['org.apache.cordova.device-orientation.CompassHeading'] = array(
	'file' => 'plugins/org.apache.cordova.device-orientation/www/CompassHeading.js',
	'clobbers' => array(
		'CompassHeading'
	),
);
$plugins['org.apache.cordova.device-orientation.compass'] = array(
	'file' => 'plugins/org.apache.cordova.device-orientation/www/compass.js',
	'clobbers' => array(
		'navigator.compass'
	),
);
$plugins['org.apache.cordova.globalization.GlobalizationError'] = array(
	'file' => 'plugins/org.apache.cordova.globalization/www/GlobalizationError.js',
	'clobbers' => array(
		'window.GlobalizationError'
	),
);
$plugins['org.apache.cordova.globalization.globalization'] = array(
	'file' => 'plugins/org.apache.cordova.globalization/www/globalization.js',
	'clobbers' => array(
		'navigator.globalization'
	),
);
$plugins['org.apache.cordova.splashscreen.SplashScreen'] = array(
	'file' => 'plugins/org.apache.cordova.splashscreen/www/splashscreen.js',
	'clobbers' => array(
		'navigator.splashscreen'
	),
);
$plugins['org.apache.cordova.contacts.contacts'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/contacts.js',
	'clobbers' => array(
		'navigator.contacts'
	),
);
$plugins['org.apache.cordova.contacts.Contact'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/Contact.js',
	'clobbers' => array(
		'Contact'
	),
);
$plugins['org.apache.cordova.contacts.ContactAddress'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactAddress.js',
	'clobbers' => array(
		'ContactAddress'
	),
);
$plugins['org.apache.cordova.contacts.ContactError'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactError.js',
	'clobbers' => array(
		'ContactError'
	),
);
$plugins['org.apache.cordova.contacts.ContactField'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactField.js',
	'clobbers' => array(
		'ContactField'
	),
);
$plugins['org.apache.cordova.contacts.ContactFindOptions'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactFindOptions.js',
	'clobbers' => array(
		'ContactFindOptions'
	),
);
$plugins['org.apache.cordova.contacts.ContactName'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactName.js',
	'clobbers' => array(
		'ContactName'
	),
);
$plugins['org.apache.cordova.contacts.ContactOrganization'] = array(
	'file' => 'plugins/org.apache.cordova.contacts/www/ContactOrganization.js',
	'clobbers' => array(
		'ContactOrganization'
	),
);
$plugins['org.apache.cordova.vibration.notification'] = array(
	'file' => 'plugins/org.apache.cordova.vibration/www/vibration.js',
	'merges' => array(
		'navigator.notification'
	),
);
$plugins['org.apache.cordova.file-transfer.FileTransferError'] = array(
	'file' => 'plugins/org.apache.cordova.file-transfer/www/FileTransferError.js',
	'clobbers' => array(
		'window.FileTransferError'
	),
);
$plugins['org.apache.cordova.file-transfer.FileTransfer'] = array(
	'file' => 'plugins/org.apache.cordova.file-transfer/www/FileTransfer.js',
	'clobbers' => array(
		'window.FileTransfer'
	),
);
$plugins['org.apache.cordova.camera.Camera'] = array(
	'file' => 'plugins/org.apache.cordova.camera/www/CameraConstants.js',
	'clobbers' => array(
		'Camera'
	),
);
$plugins['org.apache.cordova.camera.CameraPopoverOptions'] = array(
	'file' => 'plugins/org.apache.cordova.camera/www/CameraPopoverOptions.js',
	'clobbers' => array(
		'CameraPopoverOptions'
	),
);
$plugins['org.apache.cordova.camera.camera'] = array(
	'file' => 'plugins/org.apache.cordova.camera/www/Camera.js',
	'clobbers' => array(
		'navigator.camera'
	),
);
$plugins['org.apache.cordova.inappbrowser.InAppBrowser'] = array(
	'file' => 'plugins/org.apache.cordova.inappbrowser/www/InAppBrowser.js',
	'clobbers' => array(
		'window.open'
	),
);
$plugins['org.apache.cordova.network-information.network'] = array(
	'file' => 'plugins/org.apache.cordova.network-information/www/network.js',
	'clobbers' => array(
		'navigator.connection',
		'navigator.network.connection'
	),
);
$plugins['org.apache.cordova.network-information.Connection'] = array(
	'file' => 'plugins/org.apache.cordova.network-information/www/Connection.js',
	'clobbers' => array(
		'Connection'
	),
);
$plugins['org.apache.cordova.battery-status.battery'] = array(
	'file' => 'plugins/org.apache.cordova.battery-status/www/battery.js',
	'clobbers' => array(
		'navigator.battery'
	),
);
$plugins['org.apache.cordova.geolocation.Coordinates'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/Coordinates.js',
	'clobbers' => array(
		'Coordinates'
	),
);
$plugins['org.apache.cordova.geolocation.PositionError'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/PositionError.js',
	'clobbers' => array(
		'PositionError'
	),
);
$plugins['org.apache.cordova.geolocation.Position'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/Position.js',
	'clobbers' => array(
		'Position'
	),
);
$plugins['org.apache.cordova.geolocation.geolocation'] = array(
	'file' => 'plugins/org.apache.cordova.geolocation/www/geolocation.js',
	'clobbers' => array(
		'navigator.geolocation'
	),
);
$plugins['org.apache.cordova.media.Media'] = array(
	'file' => 'plugins/org.apache.cordova.media/www/Media.js',
	'clobbers' => array(
		'window.Media'
	),
);
$plugins['org.apache.cordova.media-capture.CaptureAudioOptions'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureAudioOptions.js',
	'clobbers' => array(
		'CaptureAudioOptions'
	),
);
$plugins['org.apache.cordova.media-capture.CaptureImageOptions'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureImageOptions.js',
	'clobbers' => array(
		'CaptureImageOptions'
	),
);
$plugins['org.apache.cordova.media-capture.CaptureVideoOptions'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureVideoOptions.js',
	'clobbers' => array(
		'CaptureVideoOptions'
	),
);
$plugins['org.apache.cordova.media-capture.CaptureError'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureError.js',
	'clobbers' => array(
		'CaptureError'
	),
);
$plugins['org.apache.cordova.media-capture.MediaFileData'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/MediaFileData.js',
	'clobbers' => array(
		'MediaFileData'
	),
);
$plugins['org.apache.cordova.media-capture.MediaFile'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/MediaFile.js',
	'clobbers' => array(
		'MediaFile'
	),
);
$plugins['org.apache.cordova.media-capture.capture'] = array(
	'file' => 'plugins/org.apache.cordova.media-capture/www/capture.js',
	'clobbers' => array(
		'navigator.device.capture'
	),
);
$plugins['org.apache.cordova.media.MediaError'] = array(
	'file' => 'plugins/org.apache.cordova.media/www/MediaError.js',
	'clobbers' => array(
		'window.MediaError'
	),
);
$plugins['org.apache.cordova.console.console'] = array(
	'file' => 'plugins/org.apache.cordova.console/www/console-via-logger.js',
	'clobbers' => array(
		'console'
	),
);
$plugins['org.apache.cordova.console.logger'] = array(
	'file' => 'plugins/org.apache.cordova.console/www/logger.js',
	'clobbers' => array(
		'cordova.logger'
	),
);
