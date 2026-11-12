<?php
/** Adapter for built-in integrations defined as data. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Adapter_Preset extends Appp_Events_Adapter_Posts {

	/** Normalized preset (see Appp_Events_Presets::normalize). */
	private $p;

	public function __construct( array $preset ) {
		$this->p         = $preset;
		$this->post_type = $preset['post_type'];
	}

	public function preset() {
		return $this->p;
	}

	public function origin() {
		return $this->p['origin'];
	}

	public function id() {
		return $this->p['id'];
	}

	public function label() {
		return $this->p['label'];
	}

	public function detect() {
		return Appp_Events_Presets::detect( $this->p );
	}

	public function version() {
		if ( '' !== $this->p['version'] ) {
			return $this->p['version'];
		}
		$d = $this->p['detect'];
		if ( ! empty( $d['plugin'] ) ) {
			return Appp_Events_Presets::plugin_version( $d['plugin'] );
		}
		if ( ! empty( $d['constant'] ) && defined( $d['constant'] ) && is_scalar( constant( $d['constant'] ) ) ) {
			return (string) constant( $d['constant'] );
		}
		return '';
	}

	public function default_post_type() {
		return $this->p['post_type'];
	}

	public function notes() {
		return $this->p['notes'];
	}

	public function preset_fields() {
		return $this->p['fields'];
	}
}
