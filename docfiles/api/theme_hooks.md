/*
Title: Theme Hooks
*/

# Theme Hooks

## Action hooks

### appp_app_panel_menu
Located in: inc/classes/AppPresser_App_Functionality.php

Hooks into right side of the top toolbar in the app panel

### appp_before
Located in: header.php

Hooks into right after the menu, and inside the #page div

### appp_left_panel_before
Located in: inc/classes/AppPresser_App_Functionality.php

Hooks into top left panel, and is used for search bar, shopping cart, and user profile pic

### appp_page_title
Located in: header.php

Hooks into the .site-title-wrap div to display a title value.



## Filter hooks

### appp_attachment_size
Located in: inc/classes/AppPresser_Tags.php

Allows you to modify the default attachment size used when displaying an attached image and link to next image.

Default value:

	array( 1200, 1200 )

### {$tax}_archive_meta
Located in: /inc/classes/AppPresser_Tags.php

Allows you to modify the tax archive meta. Uses the tag description by default. This hook has a variable in it, meaning it can end up being one of many values for the hook name. If the taxonomy is `post_tag` then the filter will be `tag_archive_meta` else it'll be whatever the current taxonomy is. For example, if you have a `books` taxonomy, the hook will be `books_archive_meta`

Default value:

	'sprintf( '<div class="taxonomy-description">%s</div>', $description )'
