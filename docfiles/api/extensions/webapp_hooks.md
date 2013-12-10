/*
Title: WebApp Extension Hooks
*/

# WebApp Hooks


## Action hooks

### apppresser_add_setting_row
Located in: /inc/admin-settings.php

Hooks right after the default settings fields so that you can add your own settings fields.

### apppresser_add_webapp_setting_row
Located in: /inc/admin-settings.php

Hooks right after the default settings fields in the Web App Options section so that you can add your own settings fields.

## Filter hooks

### apppresser_sanitize_setting
Located in: /inc/admin-settings.php

Allows you to intercept and modify the default's sanitized value from a text field. Provides the current input key, input value, and all input values.

Provided value:

	sanitize_text_field( $value )
	
Extra parameters:
	
	$key, $value, $inputs.