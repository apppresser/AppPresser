/*
Title: Install AppWoo
*/

# AppWoo Extension

In this tutorial, we will discuss installing the AppWoo extension and what it does. AppWoo, at this point, has been developed specifically for [AppTheme](../apptheme/) and has not been tested in other themes. AppWoo also requires WooCommerce to be active before you can use it. If you don't have WooCommerce yet, you can acquire it from [WooThemes](http://www.woothemes.com/woocommerce/).

## How to install AppWoo

1. Install and activate the AppWoo plugin from the zip file that you were given when you purchased AppWoo. You can upload that through your WordPress admin and let WordPress extract it into the right folder for you. If you would prefer SSH/FTP, extract the contents of the zip file and upload the contents to your wp-content/plugins/ directory on your server.

## Using AppWoo

AppWoo is fairly self contained and does not have any user-configurable settings. However, it does a lot of things underneath the hood to help optimize AppTheme with WooCommerce for your native app.

* It shapes the page layout for single products to fit nicely with app-like structure.

It accomplishes this by removing various WooCommerce hook callbacks as well as registering some new ones for AppTheme to achieve the desired layout.

* It provides integration with AppSwiper, and aids with ajax and sliders for product images.

This way you can more easily display multiple product images and variety for your customers.

* It provides a user profile in the left panel menu for your returning customers. When the toggle the menu, they'll see their name and profile picture.
* It provides cart information and totals in the left panel menu for your customer.

Provide easy access to your customer's information and orders, right from the menu.

## AppWoo Video tutorial

[AppWoo VIdeo demo](http://apppresser.com/wp-content/uploads/2013/12/appp-woocommerce-setup.mp4)
