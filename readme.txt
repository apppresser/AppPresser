=== AppPresser - Mobile App Framework ===
Contributors: apppresser, webdevstudios, williamsba1, scottopolis, jtsternberg, Messenlehner, LisaSabinWilson, tw2113, modemlooper
Donate link: http://apppresser.com/
Tags: mobile, app, ios, android, application, phonegap, iphone app, android app, mobile app, native app, wordpress mobile, ipad app, iOS app
Requires at least: 3.5
Tested up to: 4.2
Stable tag: 1.1.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Your WordPress site in an App.

== Description ==

AppPresser is a mobile app development framework for WordPress.

[AppPresser](http://apppresser.com/ "AppPresser mobile apps with WordPress") allows you to use a WordPress site as an app, including access to device features such as the camera, contacts, and more. You can create your app completely in WordPress, using themes, plugins, and all the stuff you already know.

Developers can use this plugin to make custom apps, or custom extensions for AppPresser. If you are not a developer, please see our website for more information about creating an app with WordPress.

This plugin is not an app-creator in itself, it serves as the core for all app development with AppPresser.

[youtube http://www.youtube.com/watch?v=i8Deew6pif4]

Want to contribute to AppPresser? Awesome! Visit our [GitHub site for the project](https://github.com/WebDevStudios/AppPresser "AppPresser on GitHub").

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


== Screenshots ==

1. AppPresser admin options page.


== Changelog ==
= 1.1.5 =
security fixes

= 1.1.3 =
* Roll back script optimization to fix push notifications and other bugs

= 1.1.2 =
* Fix for splashscreen hide

= 1.1.1 =
* Enhancement: optimize cordova scripts to only load when needed
* Moved app menu settings to theme customizer exclusively
* Hide app splashscreen on load
* Misc bug fixes

= 1.1.0 =
* Fixed annoyance of settings page not returning to the tab you were on when you clicked 'save.'
* Enhancement: New filter, "apppresser_sanitize_setting_$key" for registering your own sanitization callback to override AppPresser's.
* Enhancement: New filter, "apppresser_field_override_$type" for registering your own field type view callback to override AppPresser's.
* Enhancement: Added [CMB](https://github.com/WebDevStudios/Custom-Metaboxes-and-Fields-for-WordPress). Settings API will be re-worked in next versions to use CMB.

= 1.0.9 =
* Bug Fix: App-theme settings were not getting displayed if the theme was not active (despite being set as the App-only theme)

= 1.0.8 =
* Bug Fix: Theme\_mod settings would get the non-theme_mod setting warning asterisk if no value had been saved to them yet.
* Bug Fix: If the "Load AppPresser for Admins Only" setting was not checked, the theme customizer would try to activate the app theme from the customizer.

= 1.0.7 =
* Enable theme customizer for the App-only theme while theme is not active. There is now a link to customize the theme below the select dropdown.

= 1.0.6 =
* Enhancement: New filter `apppresser_theme_settings_file` that allows you to set the location of your theme's AppPresser settings registration (so your settings show when the theme is not active). Will fallback to looking for `appp-settings.php` file in the theme root.
* Enhancement: New filter `apppresser_notifications`, allows other plugins/themes to add their own notification count.

= 1.0.5 =
* Enhancement/Bug Fix: Don't delete license keys and other options if a particular plugin is deactivated at the time of saving.
* Enhancement/Bug Fix: AppPresser "App only theme" option now works with child themes.
* Enhancement: Add a `apppresser_tab_top_$tab` hook to match the `apppresser_tab_bottom_$tab` hook.

= 1.0.4.1 =
* Remove "App only theme?" front-end error.

= 1.0.4 =
* Extensions submenu highlighting available for AppPresser add-ons.
* Addressed some pre-PHP 5.3 notices.
* Bug Fix: White-screen on the front end if selecting a theme in the "App only theme?" setting that does not support AppPresser. An error will now be shown.
* Improvement: `appp_get_setting()` now accepts a fallback option like `get_option()`.

= 1.0.3 =
* Bug Fix: `plugins_loaded` firing too early causing conflicts with other plugins.
* Improvement: Check child theme for `app-settings.php` file as well as parent theme.
* Improvement: Added method for loading AppPresser theme despite aggresively cached web hosts.

= 1.0.2 =
* Bug Fix: Conflict causing other themes to appear to need an update.

= 1.0.1 =
* Bug Fixes
* Add theme updater and updater API
* Better styling for "MP6"

= 1.0.0 =
* Release into the wild!


== Upgrade Notice ==

= 1.1.3 =
* Roll back script optimization to fix push notifications and other bugs

= 1.1.2 =
* Fix for splashscreen hide

= 1.1.1 =
* Enhancement: optimize cordova scripts to only load when needed
* Moved app menu settings to theme customizer exclusively
* Hide app splashscreen on load
* Misc bug fixes

= 1.1.0 =
* Fixed annoyance of settings page not returning to the tab you were on when you clicked 'save.'
* Enhancement: New filter, "apppresser_sanitize_setting_$key" for registering your own sanitization callback to override AppPresser's.
* Enhancement: New filter, "apppresser_field_override_$type" for registering your own field type view callback to override AppPresser's.
* Enhancement: Added [CMB](https://github.com/WebDevStudios/Custom-Metaboxes-and-Fields-for-WordPress). Settings API will be re-worked in next versions to use CMB.

= 1.0.9 =
* Bug Fix: App-theme settings were not getting displayed if the theme was not active (despite being set as the App-only theme)

= 1.0.8 =
* Bug Fix: Theme\_mod settings would get the non-theme_mod setting warning asterisk if no value had been saved to them yet.
* Bug Fix: If the "Load AppPresser for Admins Only" setting was not checked, the theme customizer would try to activate the app theme from the customizer.

= 1.0.7 =
* Enable theme customizer for the App-only theme while theme is not active. There is now a link to customize the theme below the select dropdown.

= 1.0.6 =
* Enhancement: New filter `apppresser_theme_settings_file` that allows you to set the location of your theme's AppPresser settings registration (so your settings show when the theme is not active). Will fallback to looking for `appp-settings.php` file in the theme root.
* Enhancement: New filter `apppresser_notifications`, allows other plugins/themes to add their own notification count.

= 1.0.5 =
* Enhancement/Bug Fix: Don't delete license keys and other options if a particular plugin is deactivated at the time of saving.
* Enhancement/Bug Fix: AppPresser "App only theme" option now works with child themes.
* Enhancement: Add a `apppresser_tab_top_$tab` hook to match the `apppresser_tab_bottom_$tab` hook.

= 1.0.4.1 =
* Remove "App only theme?" front-end error.

= 1.0.4 =
* Extensions submenu highlighting available for AppPresser add-ons.
* Addressed some pre-PHP 5.3 notices.
* Bug Fix: White-screen on the front end if selecting a theme in the "App only theme?" setting that does not support AppPresser. An error will now be shown.
* Improvement: `appp_get_setting()` now accepts a fallback option like `get_option()`.

= 1.0.3 =
* Bug Fix: `plugins_loaded` firing too early causing conflicts with other plugins.
* Improvement: Check child theme for `app-settings.php` file as well as parent theme.
* Improvement: Added method for loading AppPresser theme despite aggresively cached web hosts.

= 1.0.2 =
* Bug Fix: Conflict causing other themes to appear to need an update.

= 1.0.1 =
* Bug Fixes
* Add theme updater and updater API
* Better styling for "MP6"

= 1.0.0 =
* Release into the wild!
