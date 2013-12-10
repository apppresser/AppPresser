/*
Title: AppPresser Core Extension Hooks
*/

# AppPresser Core Hooks


## Action hooks

### apppresser_add_setting_row
Located in: /inc/admin-settings.php

Hooks right after the default settings fields so that you can add your own settings fields.

Provided value:
	
	self::settings()

## Filter hooks

### apppresser_sanitize_setting
Located in: /inc/admin-settings.php

Allows you to intercept and modify the default's sanitized value from a text field. Provides the current input key, input value, and all input values.

Provided value:

	sanitize_text_field( $value )

### apppresser_setting_default
Located in: /apppresser.php

Useful to provide an overriding value for a setting or supply a fallback. 

Provided value:

	$setting
	
Extra parameters:
	
	$key, self::settings

