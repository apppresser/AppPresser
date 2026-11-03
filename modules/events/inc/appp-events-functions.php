<?php
/**
 * Hooks and small public functions. Loaded from appevents.php after the duplicate copy
 * check, so a second copy of the plugin never declares these functions twice.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', array( 'Appp_Events_REST', 'register_routes' ) );
add_action( 'init', 'appp_events_load_textdomain' );

/** Translations from the plugin's languages folder (same pattern as AppPush). */
function appp_events_load_textdomain() {
	load_plugin_textdomain( 'apppresser-events', false, dirname( plugin_basename( APPP_EVENTS_FILE ) ) . '/languages' );
}

// Cache invalidation. Each callback is a few array checks; nothing loads until a
// watched post type, taxonomy, plugin or setting actually changes.
add_action( 'save_post', 'appp_events_on_post_change', 10, 2 );
add_action( 'deleted_post', 'appp_events_on_post_change', 10, 2 );
add_action( 'trashed_post', 'appp_events_on_post_change' );
add_action( 'untrashed_post', 'appp_events_on_post_change' );
add_action( 'added_post_meta', 'appp_events_on_meta_change', 10, 2 );
add_action( 'updated_post_meta', 'appp_events_on_meta_change', 10, 2 );
add_action( 'deleted_post_meta', 'appp_events_on_meta_change', 10, 2 );
add_action( 'set_object_terms', 'appp_events_on_object_terms', 10, 4 );
add_action( 'created_term', 'appp_events_on_term_change', 10, 3 );
add_action( 'edited_term', 'appp_events_on_term_change', 10, 3 );
add_action( 'delete_term', 'appp_events_on_term_change', 10, 3 );
add_action( 'update_option_appp_settings', 'appp_events_flush_cache' );
add_action( 'activated_plugin', 'appp_events_flush_detection' );
add_action( 'deactivated_plugin', 'appp_events_flush_detection' );
add_action( 'upgrader_process_complete', 'appp_events_flush_detection' );
add_action( 'switch_theme', 'appp_events_flush_detection' );

/** Drop every cached feed for this site (the next request rebuilds it). */
function appp_events_flush_cache() {
	Appp_Events_Cache::bump();
}

/** Plugins changed: detect again and drop cached feeds. */
function appp_events_flush_detection() {
	Appp_Events_Cache::flush_detection();
}

function appp_events_on_post_change( $post_id, $post = null ) {
	$state = get_option( 'appp_events_state' );
	if ( empty( $state['pts'] ) ) {
		return;
	}
	$pt = ( $post instanceof WP_Post ) ? $post->post_type : get_post_type( $post_id );
	if ( $pt && in_array( $pt, $state['pts'], true ) ) {
		Appp_Events_Cache::bump();
	}
}

function appp_events_on_meta_change( $meta_ids, $object_id ) {
	$state = get_option( 'appp_events_state' );
	if ( empty( $state['pts'] ) ) {
		return;
	}
	$pt = get_post_type( $object_id );
	if ( $pt && in_array( $pt, $state['pts'], true ) ) {
		Appp_Events_Cache::bump();
	}
}

function appp_events_on_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
	$state = get_option( 'appp_events_state' );
	if ( ! empty( $state['tax'] ) && in_array( $taxonomy, $state['tax'], true ) ) {
		Appp_Events_Cache::bump();
	}
}

function appp_events_on_term_change( $term_id, $tt_id, $taxonomy ) {
	appp_events_on_object_terms( 0, array(), array(), $taxonomy );
}
