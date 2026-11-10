<?php
/** App messaging for iframe pages served by a custom WordPress theme. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Custom_Theme_Support {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets() {
		if ( ! AppPresser::is_app() ) { return; }
		// get_template() respects the theme selected for this app request and
		// returns the parent template for child themes. Ion supplies its own handlers.
		if ( 'ap3-ion-theme' !== get_template() ) {
			wp_enqueue_script( 'apppresser-bridge-main-js', plugin_dir_url( __FILE__ ) . 'js/main.js', array( 'jquery' ), AppPresser::VERSION, true );
		}
	}
}

new AppPresser_Custom_Theme_Support();
