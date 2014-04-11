<?php
$plugins = array();
$plugins['dialogs'] = array(
	'notification' => array(
		'file' => 'plugins/org.apache.cordova.dialogs/www/notification.js',
		'merges' => array(
			'navigator.notification'
		),
	),
);
$plugins['file'] = array(
	'DirectoryEntry' => array(
		'file' => 'plugins/org.apache.cordova.file/www/DirectoryEntry.js',
		'clobbers' => array(
			'window.DirectoryEntry'
		),
	),
	'DirectoryReader' => array(
		'file' => 'plugins/org.apache.cordova.file/www/DirectoryReader.js',
		'clobbers' => array(
			'window.DirectoryReader'
		),
	),
	'Entry' => array(
		'file' => 'plugins/org.apache.cordova.file/www/Entry.js',
		'clobbers' => array(
			'window.Entry'
		),
	),
	'File' => array(
		'file' => 'plugins/org.apache.cordova.file/www/File.js',
		'clobbers' => array(
			'window.File'
		),
	),
	'FileEntry' => array(
		'file' => 'plugins/org.apache.cordova.file/www/FileEntry.js',
		'clobbers' => array(
			'window.FileEntry'
		),
	),
	'FileError' => array(
		'file' => 'plugins/org.apache.cordova.file/www/FileError.js',
		'clobbers' => array(
			'window.FileError'
		),
	),
	'FileReader' => array(
		'file' => 'plugins/org.apache.cordova.file/www/FileReader.js',
		'clobbers' => array(
			'window.FileReader'
		),
	),
	'FileSystem' => array(
		'file' => 'plugins/org.apache.cordova.file/www/FileSystem.js',
		'clobbers' => array(
			'window.FileSystem'
		),
	),
	'FileUploadOptions' => array(
		'file' => 'plugins/org.apache.cordova.file/www/FileUploadOptions.js',
		'clobbers' => array(
			'window.FileUploadOptions'
		),
	),
	'FileUploadResult' => array(
		'file' => 'plugins/org.apache.cordova.file/www/FileUploadResult.js',
		'clobbers' => array(
			'window.FileUploadResult'
		),
	),
	'FileWriter' => array(
		'file' => 'plugins/org.apache.cordova.file/www/FileWriter.js',
		'clobbers' => array(
			'window.FileWriter'
		),
	),
	'Flags' => array(
		'file' => 'plugins/org.apache.cordova.file/www/Flags.js',
		'clobbers' => array(
			'window.Flags'
		),
	),
	'LocalFileSystem' => array(
		'file' => 'plugins/org.apache.cordova.file/www/LocalFileSystem.js',
		'clobbers' => array(
			'window.LocalFileSystem'
		),
		'merges' => array(
			'window'
		),
	),
	'Metadata' => array(
		'file' => 'plugins/org.apache.cordova.file/www/Metadata.js',
		'clobbers' => array(
			'window.Metadata'
		),
	),
	'ProgressEvent' => array(
		'file' => 'plugins/org.apache.cordova.file/www/ProgressEvent.js',
		'clobbers' => array(
			'window.ProgressEvent'
		),
	),
	'requestFileSystem' => array(
		'file' => 'plugins/org.apache.cordova.file/www/requestFileSystem.js',
		'clobbers' => array(
			'window.requestFileSystem'
		),
	),
	'resolveLocalFileSystemURI' => array(
		'file' => 'plugins/org.apache.cordova.file/www/resolveLocalFileSystemURI.js',
		'clobbers' => array(
			'window.resolveLocalFileSystemURI'
		),
	),
);
$plugins['device'] = array(
	'device' => array(
		'file' => 'plugins/org.apache.cordova.device/www/device.js',
		'clobbers' => array(
			'device'
		),
	),
);
$plugins['device-motion'] = array(
	'Acceleration' => array(
		'file' => 'plugins/org.apache.cordova.device-motion/www/Acceleration.js',
		'clobbers' => array(
			'Acceleration'
		),
	),
	'accelerometer' => array(
		'file' => 'plugins/org.apache.cordova.device-motion/www/accelerometer.js',
		'clobbers' => array(
			'navigator.accelerometer'
		),
	),
);
$plugins['device-orientation'] = array(
	'CompassError' => array(
		'file' => 'plugins/org.apache.cordova.device-orientation/www/CompassError.js',
		'clobbers' => array(
			'CompassError'
		),
	),
	'CompassHeading' => array(
		'file' => 'plugins/org.apache.cordova.device-orientation/www/CompassHeading.js',
		'clobbers' => array(
			'CompassHeading'
		),
	),
	'compass' => array(
		'file' => 'plugins/org.apache.cordova.device-orientation/www/compass.js',
		'clobbers' => array(
			'navigator.compass'
		),
	),
);
$plugins['globalization'] = array(
	'GlobalizationError' => array(
		'file' => 'plugins/org.apache.cordova.globalization/www/GlobalizationError.js',
		'clobbers' => array(
			'window.GlobalizationError'
		),
	),
	'globalization' => array(
		'file' => 'plugins/org.apache.cordova.globalization/www/globalization.js',
		'clobbers' => array(
			'navigator.globalization'
		),
	),
);
$plugins['splashscreen'] = array(
	'SplashScreen' => array(
		'file' => 'plugins/org.apache.cordova.splashscreen/www/splashscreen.js',
		'clobbers' => array(
			'navigator.splashscreen'
		),
	),
);
$plugins['contacts'] = array(
	'contacts' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/contacts.js',
		'clobbers' => array(
			'navigator.contacts'
		),
	),
	'Contact' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/Contact.js',
		'clobbers' => array(
			'Contact'
		),
	),
	'ContactAddress' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/ContactAddress.js',
		'clobbers' => array(
			'ContactAddress'
		),
	),
	'ContactError' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/ContactError.js',
		'clobbers' => array(
			'ContactError'
		),
	),
	'ContactField' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/ContactField.js',
		'clobbers' => array(
			'ContactField'
		),
	),
	'ContactFindOptions' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/ContactFindOptions.js',
		'clobbers' => array(
			'ContactFindOptions'
		),
	),
	'ContactName' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/ContactName.js',
		'clobbers' => array(
			'ContactName'
		),
	),
	'ContactOrganization' => array(
		'file' => 'plugins/org.apache.cordova.contacts/www/ContactOrganization.js',
		'clobbers' => array(
			'ContactOrganization'
		),
	),
);
$plugins['vibration'] = array(
	'notification' => array(
		'file' => 'plugins/org.apache.cordova.vibration/www/vibration.js',
		'merges' => array(
			'navigator.notification'
		),
	),
);
$plugins['file-transfer'] = array(
	'FileTransferError' => array(
		'file' => 'plugins/org.apache.cordova.file-transfer/www/FileTransferError.js',
		'clobbers' => array(
			'window.FileTransferError'
		),
	),
	'FileTransfer' => array(
		'file' => 'plugins/org.apache.cordova.file-transfer/www/FileTransfer.js',
		'clobbers' => array(
			'window.FileTransfer'
		),
	),
);
$plugins['camera'] = array(
	'Camera' => array(
		'file' => 'plugins/org.apache.cordova.camera/www/CameraConstants.js',
		'clobbers' => array(
			'Camera'
		),
	),
	'CameraPopoverOptions' => array(
		'file' => 'plugins/org.apache.cordova.camera/www/CameraPopoverOptions.js',
		'clobbers' => array(
			'CameraPopoverOptions'
		),
	),
	'camera' => array(
		'file' => 'plugins/org.apache.cordova.camera/www/Camera.js',
		'clobbers' => array(
			'navigator.camera'
		),
	),
);
$plugins['inappbrowser'] = array(
	'InAppBrowser' => array(
		'file' => 'plugins/org.apache.cordova.inappbrowser/www/InAppBrowser.js',
		'clobbers' => array(
			'window.open'
		),
	),
);
$plugins['network-information'] = array(
	'network' => array(
		'file' => 'plugins/org.apache.cordova.network-information/www/network.js',
		'clobbers' => array(
			'navigator.connection',
			'navigator.network.connection'
		),
	),
	'Connection' => array(
		'file' => 'plugins/org.apache.cordova.network-information/www/Connection.js',
		'clobbers' => array(
			'Connection'
		),
	),
);
$plugins['battery-status'] = array(
	'battery' => array(
		'file' => 'plugins/org.apache.cordova.battery-status/www/battery.js',
		'clobbers' => array(
			'navigator.battery'
		),
	),
);
$plugins['geolocation'] = array(
	'Coordinates' => array(
		'file' => 'plugins/org.apache.cordova.geolocation/www/Coordinates.js',
		'clobbers' => array(
			'Coordinates'
		),
	),
	'PositionError' => array(
		'file' => 'plugins/org.apache.cordova.geolocation/www/PositionError.js',
		'clobbers' => array(
			'PositionError'
		),
	),
	'Position' => array(
		'file' => 'plugins/org.apache.cordova.geolocation/www/Position.js',
		'clobbers' => array(
			'Position'
		),
	),
	'geolocation' => array(
		'file' => 'plugins/org.apache.cordova.geolocation/www/geolocation.js',
		'clobbers' => array(
			'navigator.geolocation'
		),
	),
);
$plugins['media'] = array(
	'Media' => array(
		'file' => 'plugins/org.apache.cordova.media/www/Media.js',
		'clobbers' => array(
			'window.Media'
		),
	),
	'MediaError' => array(
		'file' => 'plugins/org.apache.cordova.media/www/MediaError.js',
		'clobbers' => array(
			'window.MediaError'
		),
	),
);
$plugins['media-capture'] = array(
	'CaptureAudioOptions' => array(
		'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureAudioOptions.js',
		'clobbers' => array(
			'CaptureAudioOptions'
		),
	),
	'CaptureImageOptions' => array(
		'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureImageOptions.js',
		'clobbers' => array(
			'CaptureImageOptions'
		),
	),
	'CaptureVideoOptions' => array(
		'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureVideoOptions.js',
		'clobbers' => array(
			'CaptureVideoOptions'
		),
	),
	'CaptureError' => array(
		'file' => 'plugins/org.apache.cordova.media-capture/www/CaptureError.js',
		'clobbers' => array(
			'CaptureError'
		),
	),
	'MediaFileData' => array(
		'file' => 'plugins/org.apache.cordova.media-capture/www/MediaFileData.js',
		'clobbers' => array(
			'MediaFileData'
		),
	),
	'MediaFile' => array(
		'file' => 'plugins/org.apache.cordova.media-capture/www/MediaFile.js',
		'clobbers' => array(
			'MediaFile'
		),
	),
	'capture' => array(
		'file' => 'plugins/org.apache.cordova.media-capture/www/capture.js',
		'clobbers' => array(
			'navigator.device.capture'
		),
	),
);
$plugins['console'] = array(
	'console' => array(
		'file' => 'plugins/org.apache.cordova.console/www/console-via-logger.js',
		'clobbers' => array(
			'console'
		),
	),
	'logger' => array(
		'file' => 'plugins/org.apache.cordova.console/www/logger.js',
		'clobbers' => array(
			'cordova.logger'
		),
	),
);
