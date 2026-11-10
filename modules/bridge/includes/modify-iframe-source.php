<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Modify the content.rendered field for BuddyPress activity in the REST API.
 */
function apppresser_bridge_modify_bp_activity_content( $response, $request, $activity ) {
	$data                        = $response->get_data();
	if ( ! isset( $data['content']['rendered'] ) || ! is_string( $data['content']['rendered'] ) ) { return $response; }
	$data['content']['rendered'] = apppresser_bridge_modify_iframe_src( $data['content']['rendered'] );
	$response->set_data( $data );

	return $response;
}

/**
 * Modify the content.rendered field for posts in the REST API.
 */
function apppresser_bridge_modify_rest_post_content( $response, $post, $request ) {
	$data                        = $response->get_data();
	if ( ! isset( $data['content']['rendered'] ) || ! is_string( $data['content']['rendered'] ) ) { return $response; }
	$data['content']['rendered'] = apppresser_bridge_modify_iframe_src( $data['content']['rendered'] );
	$response->set_data( $data );

	return $response;
}

/**
 * Attach REST prepare filters only for post types enabled in YouTube support settings.
 */
function apppresser_bridge_register_rest_post_content_filters() {
	$enabled_post_types = appp_get_setting( 'youtube_post_types' );

	if ( empty( $enabled_post_types ) || ! is_array( $enabled_post_types ) ) {
		return;
	}

	foreach ( $enabled_post_types as $post_type ) {
		if ( 'bp_activity' === $post_type ) {
			add_filter( 'bp_rest_activity_prepare_value', 'apppresser_bridge_modify_bp_activity_content', 10, 3 );
		} else {
			add_filter( 'rest_prepare_' . $post_type, 'apppresser_bridge_modify_rest_post_content', 10, 3 );
		}
	}
}
add_action( 'init', 'apppresser_bridge_register_rest_post_content_filters', 21 );

/**
 * Replace the video url with our new video template url.
 */
function apppresser_bridge_is_youtube_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! isset( $parts['host'], $parts['path'] ) || ! in_array( isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '', array( 'http', 'https' ), true ) ) { return false; }
	$host = strtolower( $parts['host'] );
	return ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com' ), true ) && 0 === strpos( $parts['path'], '/embed/' ) ) ||
		( in_array( $host, array( 'youtu.be', 'www.youtu.be' ), true ) && strlen( trim( $parts['path'], '/' ) ) > 0 );
}

function apppresser_bridge_modify_iframe_src( $content ) {
	if ( '' === $content || ! class_exists( 'DOMDocument' ) || false === stripos( $content, '<iframe' ) ) { return $content; }
	$dom = new DOMDocument();
	$previous_errors = libxml_use_internal_errors( true );
	try {
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content, LIBXML_NONET );
	} finally {
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );
	}
	if ( ! $loaded ) { return $content; }
	// Handle iframes
	$site_url = get_site_url();
	$iframes  = $dom->getElementsByTagName( 'iframe' );
	foreach ( $iframes as $iframe ) {
		$original_src = $iframe->getAttribute( 'src' );
		// Modify only YouTube URLs
		if ( apppresser_bridge_is_youtube_url( $original_src ) ) {
			$new_src = $site_url . '/appp-video/?url=' . urlencode( $original_src );
			$iframe->setAttribute( 'src', $new_src );
		}
	}
	// Save modified HTML back to content
	$body             = $dom->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body ) { return $content; }
	$modified_content = '';
	foreach ( $body->childNodes as $child ) {
		$modified_content .= $dom->saveHTML( $child );
	}

	return $modified_content;
}
