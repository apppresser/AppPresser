<?php

/* Create meta box for adding page classes
-----------------------------------------------*/

//Fire our meta box setup function on the post editor screen.
add_action( 'load-post.php', 'm1_bodyclass_meta_box_setup' );
add_action( 'load-post-new.php', 'm1_bodyclass_meta_box_setup' );


/* Meta box setup function. */
function m1_bodyclass_meta_box_setup() {

	/* Add meta boxes on the 'add_meta_boxes' hook. */
	add_action( 'add_meta_boxes', 'm1_add_bodyclass_meta_boxes' );

	/* Save post meta on the 'save_post' hook. */
	add_action( 'save_post', 'm1_bodyclass_save_meta', 10, 2 );
}


/* Create one or more meta boxes to be displayed on the post editor screen. */
function m1_add_bodyclass_meta_boxes() {

	add_meta_box(
		'bodyclass',			                // Unique ID
		esc_html__( 'Page Transitions' ),		// Title
		'm1_bodyclass_meta_box',		            // Callback function
		'page',					        // Admin page (or post type)
		'side',					            // Context
		'default'					        // Priority
	);
}

/* Display the post meta box. */
function m1_bodyclass_meta_box( $bodyclass ) {

	$body_class = esc_html( get_post_meta( $bodyclass->ID, 'm1_body_class', true ) );

	wp_nonce_field( basename( __FILE__ ), 'm1_bodyclass_meta_nonce' );

	?>
	<?php // make this a select menu ?>
	<p>Where should the page transition start?</p>
	<p>
		<label for="m1-body-class"><?php _e( "top or bottom, (default is right, use all lowercase)" ); ?></label>
		<br />
		<input type="text" class="widefat" placeholder="right" name="m1_body_class_name" id="m1_body_class_name" value="<?php echo $body_class; ?>" size="30" />
	</p>
<?php }


/* Save the meta box's post metadata. */
function m1_bodyclass_save_meta( $bodyclass_id ) {

	/* Verify the nonce before proceeding. */
	if ( !isset( $_POST['m1_bodyclass_meta_nonce'] ) || !wp_verify_nonce( $_POST['m1_bodyclass_meta_nonce'], basename( __FILE__ ) ) )
		return $bodyclass_id;

	/* Check if the current user has permission to edit the post. */
	if ( !current_user_can( 'edit_post', $bodyclass_id ) )
		return $bodyclass_id;

    // Store data in post meta table if present in post data
    if ( isset( $_POST['m1_body_class_name'] ) )
        update_post_meta( $bodyclass_id, 'm1_body_class', $_POST['m1_body_class_name'] );

}

/* Add Body Class to Page */
add_filter('body_class','add_custom_body_class');
function add_custom_body_class($classes) {
	global $post;

	if ( isset( $post->ID ) && ( $body_class = get_post_meta( $post->ID, 'm1_body_class', true ) ) )
		$classes[] = 'stage-' . $body_class;

	return $classes;
}