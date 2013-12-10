/*
Title: Camera Extension Hooks
*/

# AppPresser Camera Hooks

## Action hooks

### appp_after_camera_buttons
Located in: appp-camera.php

Hooks right after the form for the photo upload has displayed

### appp_after_process_uploads
Located in: appp-uploads.php

Hooks right after the photo upload handling is done. Passes in the affected post ID and the new attachment ID from the upload. This allows you to do more processing on either post or attachment afterwards.

### appp_before_camera_buttons
Located in: appp-camera.php

Hooks right before the form for the photo upload is displayed

## Filter Hooks

### active_plugins
Located in: appp-camera.php

Allows you to intercept get_option( 'active_plugins' ) before our WooCommerce extension determines if WooCommerce is presently active.

Default value:

	get_option( 'active_plugins' )
	
### appp_camera_description
Located in: appp-camera.php

Allows you to intercept and modify, if needed, the text to display as the description. This value is originally set in the Camera section of the AppPresser Settings page.

Default value:

	'Upload your photos!'
	
### appp_camera_not_logged_in_text
Located in: appp-camera.php

Allows you to intercept and modify, if needed, the text to display to not logged in users. This value is originally set in the Camera section of the AppPresser Settings page.

Default value:

	'Upload your own customer image!'
	
### appp_camera_post_title_label
Located in: appp-camera.php

Allows you to intercept and modify the label for the post_title field that is displayed via the `[ap-camera]` shortcode.

Default value:
	
	'<label>' . __( 'Title:', 'appp' ) . '</label>'

### appp_upload_email_message
Located in: appp-upload.php

Allows you to intercept and modify the default message that gets set with the email notifications for new uploads.

Default value:
	
	An image tag with the newly uploaded image. Approve/Deny links if moderation is enabled.

### appp_upload_email_subject
Located in: appp-upload.php

Allows you to intercept and modify the default subject that gets set with the email notifications for new uploads.

Default value:
	
	'A new photo was uploaded.'

### appp_upload_email_to
Located in: appp-upload.php

Allows you to intercept and modify the default email recipient who will get the email notifications for new uploads.

Default value:
	
	get_settings( 'admin_email' )