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
	$data['content']['rendered'] = apppresser_bridge_modify_iframe_src( $data['content']['rendered'], $request );
	$response->set_data( $data );

	return $response;
}

/**
 * Modify the content.rendered field for posts in the REST API.
 */
function apppresser_bridge_modify_rest_post_content( $response, $post, $request ) {
	$data                        = $response->get_data();
	if ( ! isset( $data['content']['rendered'] ) || ! is_string( $data['content']['rendered'] ) ) { return $response; }
	$data['content']['rendered'] = apppresser_bridge_modify_iframe_src( $data['content']['rendered'], $request );
	$response->set_data( $data );

	return $response;
}

/**
 * Attach REST prepare filters only for post types enabled in video support settings.
 */
function apppresser_bridge_register_rest_post_content_filters() {
	$youtube_post_types = appp_get_setting( 'youtube_post_types' );
	$vimeo_post_types   = appp_get_setting( 'vimeo_post_types' );
	$enabled_post_types = array();

	if ( is_array( $youtube_post_types ) ) {
		$enabled_post_types = array_merge( $enabled_post_types, $youtube_post_types );
	}

	if ( is_array( $vimeo_post_types ) ) {
		$enabled_post_types = array_merge( $enabled_post_types, $vimeo_post_types );
	}

	$enabled_post_types = array_values( array_unique( $enabled_post_types ) );

	if ( empty( $enabled_post_types ) ) {
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
 * Modify iframe content for YouTube and Vimeo embeds.
 */
function apppresser_bridge_is_youtube_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! isset( $parts['host'], $parts['path'] ) || ! in_array( isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '', array( 'http', 'https' ), true ) ) { return false; }
	$host = strtolower( $parts['host'] );
	return ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com' ), true ) && 0 === strpos( $parts['path'], '/embed/' ) ) ||
		( in_array( $host, array( 'youtu.be', 'www.youtu.be' ), true ) && strlen( trim( $parts['path'], '/' ) ) > 0 );
}

function apppresser_bridge_is_vimeo_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! isset( $parts['host'], $parts['path'] ) || ! in_array( isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '', array( 'http', 'https' ), true ) ) { return false; }
	$host = strtolower( $parts['host'] );
	return ( in_array( $host, array( 'player.vimeo.com', 'www.player.vimeo.com' ), true ) && 0 === strpos( $parts['path'], '/video/' ) ) ||
		( in_array( $host, array( 'vimeo.com', 'www.vimeo.com' ), true ) && strlen( trim( $parts['path'], '/' ) ) > 0 );
}

function apppresser_bridge_modify_iframe_src( $content, $request = null ) {
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
		// Remove fullscreen from Vimeo embeds requested by the iOS app.
		$is_ios = $request instanceof WP_REST_Request && 'ios' === strtolower( (string) $request->get_header( 'X-App-Platform' ) );
		if ( $is_ios && apppresser_bridge_is_vimeo_url( $original_src ) ) {
			$allow = $iframe->getAttribute( 'allow' );
			if ( ! empty( $allow ) ) {
				$permissions = array_filter( array_map( 'trim', explode( ';', $allow ) ) );
				$permissions = array_values(
					array_filter(
						$permissions,
						function ( $permission ) {
							return 'fullscreen' !== strtolower( $permission );
						}
					)
				);
				$iframe->setAttribute( 'allow', implode( '; ', $permissions ) );
			}
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
