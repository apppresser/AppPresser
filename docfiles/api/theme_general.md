/*
Title: Theme Setup
*/

# Theme Setup

## General Information

The AppPresser theme is designed to be active only inside the native app, so that you still see your current theme in normal browsers.

When you install the theme, do not activate it. If you activate it, it will be visible to all of your normal website visitors.

To see the theme, you can use the checkboxes on the AppPresser settings page. Either "Load AppPresser for mobile browsers?" or "Load AppPresser for Admins Only? (for testing purposes)." Read more about these settings in the documentation for the core plugin.

## Menus

The AppPresser theme uses menus that are active only when the theme is active. That way you can have different menus for your app.

The AppPresser settings page allows you to set your app menus.

### Menu Customization

The main left panel menu is highly customizable. To create a menu like our demo app, here are a few things you can do.

### Menu Icons

The AppPresser theme includes the font awesome icon font library, and you can use any of these icons in your menu. To do this, add a new menu link, then in the "Navigation label" field, enter your icon like this:

    <i class="fa fa-home"></i> Home

You can see all the available font icons here: [http://fontawesome.io/icons/](http://fontawesome.io/icons/)

### Nav dividers

To add a section divider to your navigation, first scroll to the top of the Appearance -> Menus page, click the "Screen Options" tab, and check "CSS classes."

Next, add a custom link to your menu with # in the url field. This is just a placeholder so that it will not link to anything.  In the CSS classes field, add "nav-divider".  The nav-divider class styles that link to look like a divider instead of a menu link.

### Sub menus

Sub menus will automatically be added to make your navigation multi-tiered with arrows and back buttons.

### Top Menu

The top menu is the gear icon at the top right of your app.  You can add a top menu, then assign it to your app on the AppPresser settings page.  The top menu does not work well with sub-menus or icons, it's best to use a short list of important links.

## Theme Customizer

You can customize the colors of your app using the theme customizer when the AppPresser theme is active.

To use the theme customizer, activate the AppPresser theme (please note that all of your normal website visitors will see this, so you may want to use a maintenance mode plugin).

Click on Appearance -> Customize. You can now customize all of your theme colors, and even add your company logo.

## Ajax

The AppPresser theme relies heavily on ajax to avoid page refreshes. Many WordPress plugins are not compatible with ajax, so if you are having trouble, please disable ajax on the AppPresser settings page.

We highly recommend you keep the ajax active if possible, because page refreshes look really bad when using an app.

## Child Themes

The AppPresser theme allows for child theme customization. More information can be found on the [child themes codex page](http://codex.wordpress.org/Child_Themes)

## Translation

Translation files are located in the /languages folder.  For more information on translating your theme, please see [Translating WordPress](http://codex.wordpress.org/Translating_WordPress)
