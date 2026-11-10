<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Segment notifications by taxonomies
 *
 * @package AppPresser
 * @subpackage ApppSegmentNotification
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

/**
 * SHORTCODE:
 * Displays the signup template for the segmented (taxonomies) selection options in your theme.  Use
 * the shortcode [appp-notification-signup]Some example text here.[/appp-notification-signup] on the
 * post or page you wish it to appear. It allows users to choose (or filter ) which taxonomies to
 * they prefer to receive push notifications.
 *
 * FILTER/HOOK:
 * To override this template without editing this file create your own file and place it in your theme's
 * directory, then add a filter hook to your theme's function.php file:
 * add_filter('appp-notification-signup-template', $the_file_path_to_your_file );
 *
 * FILTER/HOOK:
 * If you want to override just notification-signup-loop.php file, create your own file and place
 * it in your theme's directory, then add a filter hook to your theme's function.php file:
 * add_filter('appp-notification-signup-loop-template', $the_file_path_to_your_file );
 */

if ( is_user_logged_in() ) :

	?>
<div class="ionic">
	<ul class="notification-signup list">
		<!-- <li><h2><?php _e( $shortcode_atts['title'], 'apppush' ); ?></h2></li> -->
		<?php

			echo $content; // from shortcode if it exists

			$this->get_notification_loop();

		?>
	</ul>
</div><?php else : ?>
<a href="#loginModal" class="btn btn-primary button io-modal-open">Login</a>
<?php endif; ?>