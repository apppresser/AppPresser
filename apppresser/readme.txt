=== AppPresser - Mobile App Framework ===
Contributors: apppresser
Donate link: http://apppresser.com/
Tags: mobile, app, ios, android, application, phonegap
Requires at least: 3.5
Tested up to: 3.7.1
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Build native iOS and Android mobile apps with the AppPresser framework.

== Description ==

AppPresser is the first native mobile app development framework for WordPress.

[AppPresser](http://apppresser.com/ "AppPresser mobile apps with WordPress") allows you to use a WordPress site as an app, including access to device features such as the camera, contacts, and more. You can create your app completely in WordPress, using themes, plugins, and all the stuff you already know.

Developers can use this plugin to make custom apps, or custom extensions for AppPresser. If you are not a developer, please see our website for more information about creating an app with WordPress.

This plugin is not an app-creator in itself, it serves as the core for all app development with AppPresser.

<iframe width="530" height="298" src="//www.youtube.com/embed/i8Deew6pif4" frameborder="0" allowfullscreen></iframe>

### What this plugin does:

*   Integrates Phonegap with WordPress, which exposes the [Phonegap API](http://docs.phonegap.com/en/3.2.0/index.html "Phonegap docs")
*   Allows you to use javascript (using the Phonegap API) to use native device features
*   Allows you to use other AppPresser plugins and themes to create an app
*   Adds a settings page with app-only homepage, menus, and theme settings

#### What this plugin DOES NOT do:

*   It does not automatically create an app for you, or give you a WYSIWYG app creator
*   It does not change your site aesthetically
*   It does not allow you to test any app features in the browser, (you need Xcode or Eclipse for that)
*   It does not build the app for you

#### How do I use it?

*   Install and activate the plugin
*   Add AppPresser themes or extensions to create your app
*   Build your app yourself with Phonegap, or with our build service
*   Distribute to the iOS/Android app stores

== Installation ==

1. Upload AppPresser to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to AppPresser settings page to configure

After installing, optionally add AppPresser theme/extensions, or use the [Phonegap API](http://docs.phonegap.com/en/3.2.0/index.html "Phonegap docs") to create custom apps.

== Frequently Asked Questions ==

Please see our [FAQ page](http://apppresser.com/faq/ "FAQ") for the newest information.

= I activated the plugin and nothing happened. =

This plugin does not do a whole lot by itself, you need to use our extensions and themes to actually create the app.

This plugin is the core development framework for developers to create their own apps or extensions, it is not meant to be an end in itself.

= Do I need coding skills to use AppPresser? =

Using only this plugin by itself, you will need to add your own code to create the app.

If you use one of our pre-made app bundles, like the ecommerce app, you are not required to code anything.

= How do I test the app features like camera, contacts, etc.? =

Native device features only work on native devices, so they won’t work in your browser.

To test this, you need to setup a local testing environment with Xcode (iOS) or Eclipse (Android). You then need a Phonegap project that you can load into your emulator. To test on a real iOS device, you need an Apple developer account and a provisioned device. (Android does not have the same requirements)

Please see our website for a tutorial.

= Can I make any type of app I want? =

Technically you can do anything with an AppPresser app that you can do with Phonegap. That means if you are handy with javascript, the sky is the limit!