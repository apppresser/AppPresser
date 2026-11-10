<?php
/**
 * No events plugin: any public post type (for example a custom post type with ACF or
 * custom fields) used as events. The starting mapping is guessed from its meta keys,
 * the same way unknown plugins are, and every row can be changed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Adapter_Plain extends Appp_Events_Adapter_Guess {

	public function id() {
		return 'plain:' . $this->post_type;
	}

	public function label() {
		$obj = get_post_type_object( $this->post_type );
		/* translators: %s post type */
		return sprintf( __( 'Plain post type: %s', 'apppresser-events' ), ( $obj ? $obj->labels->name : $this->post_type ) . ' (' . $this->post_type . ')' );
	}

	public function notes() {
		return array_merge(
			array( __( 'No events plugin: this post type is read as events. Map at least the start date; the rest is optional.', 'apppresser-events' ) ),
			parent::notes()
		);
	}
}
