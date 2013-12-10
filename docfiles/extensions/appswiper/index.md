/*
Title: Install AppSwiper
*/

# AppSwiper Extension

## Installing the AppSwiper extension.

In this tutorail, we will discuss installing the AppSwiper extension and what it does.

## How to install AppSwiper

1. Install and activate the AppSwiper plugin from the zip file that you were given when you purchased AppSwiper. You can upload that through your WordPress admin and let WordPress extract it into the right folder for you. If you would prefer SSH/FTP, extract the contents of the zip file and upload the contents to your wp-content/plugins/ directory on your server.

## Using AppSwiper

AppSwiper is fairly self contained and does not have any user-configurable settings. However, it does a lot of things underneath the hood to help shape your site to be application-ready.

* It registers a "slides" custom post type.

This custom post type will make it easy to create slides for sliders that you'll use. Since each slide is its own post, you have potential to extend each slide using post meta and make each slide more than just an image.

* It registers some optimized image sizes for media uploads.

There are two sizes that AppSwiper registers for you: "phone" with dimensions of 500w x 300h, and "tablet" with dimensions of 1024w x 400h.

* Uses two different javascript based libraries for its functionality.

AppSwiper uses [Swiper](http://www.idangero.us/sliders/swiper/) by idangero.us and [Picturefill](https://github.com/scottjehl/picturefill) by Scott Jehl. Swiper allows for multiple ways to display your content and provide scroll-ability within the context of your smart device. Picturefill helps aid in keeping your images responsive so that they display nicely to your users.

* Provides two shortcodes

First shortcode available is `[swiper]`. This shortcode is well suited for the homepage and the slider displayed at the very top. This shortcode is limited to the 'slides' custom post type. You can find more detail on the [Swiper Shortcode](../../api/shortcodes/swiper/) page.

**Note: Category support displayed with the shortcode api docs is in the works.**

Second shortcode available is `[carousel]`. This shortcode is more flexible and able to be used with your product pages and elsewhere. When you use this shortcode with your product pages, you'll want to specify "products" for the post type, and it'll use a special loop tailored for the products. You can find more detail on the [Carousel Shortcode](../../api/shortcodes/swiper-carousel/) page.
