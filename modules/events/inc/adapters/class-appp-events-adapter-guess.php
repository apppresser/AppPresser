<?php
/**
 * Unknown events plugin: a post type found by the heuristics, with a guessed mapping
 * as its preset. Works like manual mapping once saved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Adapter_Guess extends Appp_Events_Adapter_Posts {

	private $guess = null;

	public function __construct( $post_type ) {
		$this->post_type = $post_type;
	}

	public function id() {
		return 'guess:' . $this->post_type;
	}

	public function label() {
		$obj = get_post_type_object( $this->post_type );
		/* translators: %s post type */
		return sprintf( __( 'Auto mapped: %s', 'apppresser-events' ), ( $obj ? $obj->labels->name : $this->post_type ) . ' (' . $this->post_type . ')' );
	}

	public function detect() {
		return post_type_exists( $this->post_type );
	}

	public function default_post_type() {
		return $this->post_type;
	}

	public function guess() {
		if ( null === $this->guess ) {
			$this->guess = Appp_Events_Heuristic::guess( $this->post_type );
		}
		return $this->guess;
	}

	public function preset_fields() {
		$g = $this->guess();
		return $g['fields'];
	}

	public function confidence() {
		$g = $this->guess();
		return $g['confidence'];
	}

	public function notes() {
		$g     = $this->guess();
		$notes = array(
			/* translators: %d count */
			sprintf( __( 'Mapping guessed from %d sampled posts. Check each row; blank rows found no match.', 'apppresser-events' ), $g['sampled'] ),
		);
		return array_merge( $notes, $g['notes'] );
	}
}
