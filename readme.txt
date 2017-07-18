=== AppPresser - Mobile App Framework ===
Contributors: apppresser, webdevstudios, williamsba1, scottopolis, jtsternberg, Messenlehner, LisaSabinWilson, modemlooper, stillatmylinux
Donate link: http://apppresser.com/
Tags: mobile, app, ios, android, application, phonegap, iphone app, android app, mobile app, native app, wordpress mobile, ipad app, iOS app
Requires at least: 3.5
Tested up to: 4.8.0
Stable tag: 3.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to a native mobile app.

== Description ==

AppPresser helps website builders make iOS/Android mobile apps out of WordPress sites simply and quickly.

[AppPresser](https://apppresser.com/ "AppPresser mobile apps with WordPress") allows you to use a WordPress site as an app, including access to device features such as the camera, contacts, and more.

This plugin is not an app-creator in itself, it helps connect your app to WordPress. You must create an app with our app builder, then install this plugin on your WordPress site.

[youtube https://www.youtube.com/watch?v=ZIzVlA8cJTw]

### What this plugin does:

*   Is the base code for integrating your AppPresser app with your WordPress site
*   Activates AppPresser code and theme when your site is viewed in an app
*	Modifies WP-API requests to add featured image urls, used in the app
*	Adds ajax functionality used in other AppPresser theme and plugins
*   Adds a settings page

#### How do I use it?

*	Purchase a plan on [AppPresser](https://apppresser.com/)
*   Install and activate this plugin on your WordPress site
*	Follow the instructions in our documentation

== Installation ==

1. Upload AppPresser to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to AppPresser settings page to configure

== Screenshots ==

1. AppPresser admin options page.


== Changelog ==

= 3.2.1 =
* Add: filter hook for login/logout strings
* Fix: properly handle appp_login_redirect filter
* Fix: URL updates to v3 docs
* Fix: add success boolean in ajax login response
* Fix: deprecate safe_mode from system info
* Fix: update language template apppressser.pot

= 3.2.0 =
* Make the BuddyPress avatar available to the app if it's set
* Add more details to the system info

= 3.1.3 =
* Fix Facebook connect removing app theme cookies
* Update language .pot template

= 3.1.2 =
* Fix PushWoosh deep linking for Android
* Add System Info to admin settings
* Misc. bug fixes

= 3.1.1 =
* Support for WMPL translations in apps
* Fix Android deep linking for AppPush
* Parse URL query from infinite scroll

= 3.1.0 =
* Improve infinite scroll
* Make logout with ajax available
* Add Avatar to ajax login response
* Use mobile friendly password reset codes
* Fix admin styling for checkboxes
* Bug fixes
* Update EDD SL

= 3.0.2 =
* Bug fixes to help activating licenses

= 3.0.1 =
* Add back javascript upload settings for v2
* Remove extensions feed page

= 3.0.0 =
* Big changes for AppPresser 3 [AppPresser 3.0](https://apppresser.com/3-announcement/)
* Many plugin settings have been deprecated in version 3. If you are still using version 2, settings have been moved, but are still the same. You do not need to make any changes.
* New settings for version 3: site slug and app ID
* Integration with WP-API for AppPresser 3

= 2.7.2 =
* Display a message when the getCurrentPosition timesout when getting GPS location
* Fix notification alert for iOS

= 2.7.1 =
* fix redirect on settings page

= 2.7.0 =
* Fix customizer compatibility issues with WordPress 4.7
* Add filters for custom login/logout redirects
* Add class for links to open In-App Browser and close it on pause to force stop audio/video on Android
* Fix custom links for PushWoosh notifications for Android
* Fix opening external media on touch events

= 2.6.2 =
* Fix bug for geolocation places
* Add l10n variables for AppPush
* Fix displaying HTML in customerizer
* Fix notification variables changes from PushWoosh plugin

= 2.6.1 =
* Send open/close keyboard events to AppAds
* Allow externally linked images to open in the In-App browser
* Send open/close keyboard events to Ion and AppTheme to help fix copy/paste issues with iOS

= 2.6.0 =
* Bug fixes
* Allow translatable text
* Show the KeyboardAccessaryBar on iOS
* Add fb_id param for AppFBConnect
* Add a custom callback for fb login

= 2.5.0 =
* Add options to IAB window.open
* Optionally allow external links to not show the app theme, forces a page reload on return
* Improve logic when using In-App browser for external URLs to decifer relative links, tel: and mail: 
* Fix click events related to the .swiper-container for AppSwiper
* Read device id whether it is a string or object
* Make AppAds check each OS separately for existing ad settings
* Fix expired license message

= 2.4.0 =
* Add the ability to use AdMob ads in the AppAds plugin
* Bug fix: improve logic of comparing domains when opening the InApp browser

= 2.3.3 =
* Enqueue jQuery to fix missing localized variable ajaxurl for AppTheme and Ion theme

= 2.3.2 =
* Fix events triggering prior to iframe not yet ready
* Clear iOS badges on app launch
* Fix iOS bug for URL target for _system when target is IMG
* Bail AdMob init when no ad codes are set in wp-admin

= 2.3.1 =
* AppGeo bug fix for empty lat and long on checkin

= 2.3.0 =
* Quick start admin settings
* Verify THEME_SLUG
* Add external-media class to open URLs using the Google Docs previewer
* Kill videos on Android pause event

= 2.2.1 =
* fix links with the 'external' class that have the same domain as the site to open in the InAppBrowser.

= 2.2.0 =
* fix go back button for Android
* add filter for Facebook graph fields
* fix AppPresser_Logger error for multisite
* remove wp_cron for AppPresser_Logger and use alternate method to turn off logging

= 2.1.0 =
* Give developers the ability to uploads custom js files for the app in the WP Admin
* Add AJAX login to #loginform modal (requires also updating AppTheme and Ion theme)
* Add js to recognize css class 'system' to set a link's target to '_system' so links open in Safari or Chrome
* Fix external links that should open in the In-App Browser
* Other bug fixes

= 2.0.0 =
* New features to enable offline app capabilities
* Moves cordova files from the website to the device
* Bug fixes

= 1.4.0 =
* Include phonegap files for 3.7.0
* Add 3.7.0 option to admin settings
* Remove logging from multisite
* Improve the logic around creating the log file
* Add an admin nag for log file which each admin can dismiss
* Fix log file URL
* Verify backbutton event before calling maybeGoBack
* Fix typos in readme files

= 1.3.3 =
* Add noGoBackFlag feature to allow any app to stop the mayGoBack function (appbuddy 0.9.9 initially)
* Fix Android back button when 'disable dynamic page loading' is enabled

= 1.3.2 =
* bug fix: Android back button

= 1.3.1 =
* Remove static homepage option from customizer
* Add option for posts on mobile homepage
* Standardize text-domains
* Add logging for debugging and customer support

= 1.2.0 =
* Stop youtube videos on app exit
* Fix undefined index error
* Add ajax functions for AppTheme

= 1.1.9 =
* Support for Facebook Connect extension
* Remove unneeded files

= 1.1.8 =
* fix license activation bug

= 1.1.7 =
* add back missing front page setting

= 1.1.6 =
* fix broken customizer link

= 1.1.5 =
* security fixes

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
* Improvement: Added method for loading AppPresser theme despite aggressively cached web hosts.

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
* Improvement: Added method for loading AppPresser theme despite aggressively cached web hosts.

= 1.0.2 =
* Bug Fix: Conflict causing other themes to appear to need an update.

= 1.0.1 =
* Bug Fixes
* Add theme updater and updater API
* Better styling for "MP6"

= 1.0.0 =
* Release into the wild!
