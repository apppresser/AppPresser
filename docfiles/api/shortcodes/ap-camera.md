/*
Title: ap-camera
 */

# [ap-camera]

## Available shortcode attributes

* **action**
	* Specifies where to post the photo to. "This" is the post or page the form is on, "new" is a new post or page, and "library" posts to the media library unattached to a specific post or page. ***Note: if the action is set to "new", uploaded image will be set as the "featured image" for the post.***
	* Default: this
	* Available: this, new, library
	* Example: `[ap-camera action="new"]`
* **post_type**
	* Allows you to specify a post type to post the photo to. Note: this parameter can only be use when action is "new".
	* Default: post
	* Available: any post types that are registered. ***Note: use the post type slug.***
	* Example: `[ap-camera post_type="page"]`
* **post_title**
	* Sets whether to display a "title" field for the form. If set to true, the form will display a "Title" field that you can use to name the created post.
	* Default: false
	* Available: false, true
	* Example: `[ap-camera post_title="true"]`

>General Notes: the not_logged_in text and description text are controlled by the AppPresser Camera settings and can be changed there.
>
>See our [FAQs](../../../faq/) for more information regarding testing the camera functionality.
