/*
Title: WooCommerce Extension Hooks
*/

# WooCommerce Hooks

## Action hooks

### appp_after_product_images
Located in: /inc/woocom.php

Hooks right after the product images gallery display.

## Filter Hooks

### active_plugins
Located in: apppresser-woocommerce.php

Allows you to intercept get_option( 'active_plugins' ) before our WooCommerce extension determines if WooCommerce is presently active.

Default value:

	get_option( 'active_plugins' )
	
### apppresser_woocom_gallery_ids
Located in: /inc/woocom.php

Allows you to intercept and modify, if needed, an array of gallery image IDs used with the gallery display.
 
Default value:

	$gallery_ids