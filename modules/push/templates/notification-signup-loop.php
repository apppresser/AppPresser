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
 * FILTER/HOOK:
 * If you want to override just notification-signup-loop.php file, create your own file and place
 * it in your theme's directory, then add a filter hook to your theme's function.php file:
 * add_filter('appp-notification-signup-loop-template', $the_file_path_to_your_file );
 */

?>
<li class="item item-toggle item-toggle-<?php echo $count; ?>">
	<?php echo $term->name; ?>
	<?php if ( $term->description ) : ?>
		<p><?php echo $term->description; ?></p>
	<?php endif; ?>

	<label class="toggle <?php echo apply_filters( 'apppush_toggle_class', 'toggle-apppush' ); ?>">
		<input type="checkbox" data-count="<?php echo $count; ?>" <?php checked( $is_checked ); ?> data-post-type="<?php echo $post_type; ?>" data-taxonomy="<?php echo $term->taxonomy; ?>" data-term-id="<?php echo $term->term_id; ?>" name="<?php echo $term->name; ?>" value="1">
		<div class="track">
		<div class="handle"></div>
		</div>
	</label>

	<div class="appp-error-msg"><?php _e( 'Error!', 'apppresser-push' ); ?></div>
	</li>