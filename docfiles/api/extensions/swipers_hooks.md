/*
Title: WooCommerce Extension Hooks
*/

# WooCommerce Hooks

## Action hooks

### woocommerce_after_shop_loop_item
Located in: /plugins/apppresser-swipers/inc/woo-loop.php

Hooks right at the end of the product item container, after the permalink for the product.

### woocommerce_after_shop_loop_item_title
Located in: /plugins/apppresser-swipers/inc/woo-loop.php

Hooks right right the product title, inside the link anchor tag.

### woocommerce_before_shop_loop_item
Located in: /plugins/apppresser-swipers/inc/woo-loop.php

Hooks right inside the the product item container, above the permalink for the product.

### woocommerce_before_shop_loop_item_title
Located in: /plugins/apppresser-swipers/inc/woo-loop.php

Hooks right before the product title, inside the link anchor tag.


## Filter Hooks
	
### loop_shop_columns
Located in: /inc/woo-loop.php

Allows you to modify the amount of columns to render in the woocommerce loop, related to grid display. Defaults to 4.

Provided value:

	4