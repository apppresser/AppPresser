<?php
/**
 * Normalized event fields, mapping source types, and value resolution for posts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Fields {

	/**
	 * Normalized fields used by the native integrations.
	 */
	public static function definitions() {
		return array(
			'title'         => array(
				'label' => __( 'Title', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'content',
			),
			'excerpt'       => array(
				'label' => __( 'Excerpt (plain text)', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'content',
				'auto'  => __( 'Post excerpt, or the first 40 words of the description', 'apppresser-events' ),
			),
			'description'   => array(
				'label' => __( 'Description (HTML)', 'apppresser-events' ),
				'type'  => 'html',
				'group' => 'content',
			),
			'image'         => array(
				'label' => __( 'Image', 'apppresser-events' ),
				'type'  => 'image',
				'group' => 'content',
			),
			'link'          => array(
				'label' => __( 'Link', 'apppresser-events' ),
				'type'  => 'url',
				'group' => 'content',
				'auto'  => __( 'Post permalink', 'apppresser-events' ),
			),
			'start_date'    => array(
				'label' => __( 'Start date (or date and time)', 'apppresser-events' ),
				'type'  => 'date',
				'group' => 'dates',
			),
			'start_time'    => array(
				'label' => __( 'Start time (only if stored apart from the date)', 'apppresser-events' ),
				'type'  => 'time',
				'group' => 'dates',
			),
			'end_date'      => array(
				'label' => __( 'End date (or date and time)', 'apppresser-events' ),
				'type'  => 'date',
				'group' => 'dates',
			),
			'end_time'      => array(
				'label' => __( 'End time (only if stored apart from the date)', 'apppresser-events' ),
				'type'  => 'time',
				'group' => 'dates',
			),
			'timezone'      => array(
				'label' => __( 'Timezone (empty means site timezone)', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'dates',
			),
			'all_day'       => array(
				'label' => __( 'All day flag', 'apppresser-events' ),
				'type'  => 'bool',
				'group' => 'dates',
				'auto'  => __( 'True when the event runs from 00:00 to 23:59', 'apppresser-events' ),
			),
			'venue_name'    => array(
				'label' => __( 'Venue name', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'venue',
			),
			'venue_text'    => array(
				'label' => __( 'Venue as one line of text (parsed into the parts below when they are empty)', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'venue',
			),
			'venue_address' => array(
				'label' => __( 'Venue street address', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'venue',
			),
			'venue_city'    => array(
				'label' => __( 'Venue city', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'venue',
			),
			'venue_state'   => array(
				'label' => __( 'Venue state or province', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'venue',
			),
			'venue_zip'     => array(
				'label' => __( 'Venue postal code', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'venue',
			),
			'venue_country' => array(
				'label' => __( 'Venue country', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'venue',
			),
			'venue_lat'     => array(
				'label' => __( 'Venue latitude', 'apppresser-events' ),
				'type'  => 'float',
				'group' => 'venue',
			),
			'venue_lng'     => array(
				'label' => __( 'Venue longitude', 'apppresser-events' ),
				'type'  => 'float',
				'group' => 'venue',
			),
			'ticket_url'    => array(
				'label' => __( 'Ticket or registration URL', 'apppresser-events' ),
				'type'  => 'url',
				'group' => 'extra',
			),
			'cost'          => array(
				'label' => __( 'Cost', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'extra',
			),
			'canceled'      => array(
				'label' => __( 'Canceled flag', 'apppresser-events' ),
				'type'  => 'bool',
				'group' => 'status',
			),
			'cancel_reason' => array(
				'label' => __( 'Cancel reason', 'apppresser-events' ),
				'type'  => 'text',
				'group' => 'status',
			),
			'featured'      => array(
				'label' => __( 'Featured flag', 'apppresser-events' ),
				'type'  => 'bool',
				'group' => 'status',
			),
			'categories'    => array(
				'label' => __( 'Categories', 'apppresser-events' ),
				'type'  => 'terms',
				'group' => 'status',
			),
		);
	}

	public static function source_types() {
		return array(
			'none'           => __( 'Not used', 'apppresser-events' ),
			'auto'           => __( 'Plugin default (computed)', 'apppresser-events' ),
			'post_field'     => __( 'Post field', 'apppresser-events' ),
			'meta'           => __( 'Post meta key', 'apppresser-events' ),
			'related_meta'   => __( 'Meta of a linked post (link_key>target_key)', 'apppresser-events' ),
			'taxonomy'       => __( 'Taxonomy', 'apppresser-events' ),
			'featured_image' => __( 'Featured image', 'apppresser-events' ),
			'static'         => __( 'Static value', 'apppresser-events' ),
		);
	}

	public static function post_fields() {
		return array( 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt', 'post_name', 'post_author', 'menu_order', 'guid' );
	}

	public static function sanitize_field( $cfg ) {
		$cfg    = is_array( $cfg ) ? $cfg : array();
		$source = isset( $cfg['source'] ) ? sanitize_key( $cfg['source'] ) : 'none';
		if ( ! array_key_exists( $source, self::source_types() ) ) {
			$source = 'none';
		}
		$out = array( 'source' => $source );
		foreach ( array( 'key', 'format', 'match' ) as $k ) {
			if ( isset( $cfg[ $k ] ) && '' !== trim( (string) $cfg[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( (string) $cfg[ $k ] );
			}
		}
		if ( isset( $out['format'] ) && 'custom' === $out['format'] ) {
			$custom = isset( $cfg['format_custom'] ) ? sanitize_text_field( (string) $cfg['format_custom'] ) : '';
			if ( '' === $custom ) {
				unset( $out['format'] );
			} else {
				$out['format'] = $custom;
			}
		}
		if ( 'static' === $source && isset( $cfg['key'] ) ) {
			$out['key'] = sanitize_textarea_field( (string) $cfg['key'] );
		}
		return $out;
	}

	/**
	 * Read a meta value. key may list fallbacks separated by "|". Arrays give their first scalar.
	 */
	public static function meta( $post_id, $key ) {
		foreach ( array_map( 'trim', explode( '|', (string) $key ) ) as $k ) {
			if ( '' === $k ) {
				continue;
			}
			$v = get_post_meta( $post_id, $k, true );
			if ( is_array( $v ) ) {
				$v = self::first_scalar( $v );
			}
			if ( null !== $v && '' !== $v && false !== $v ) {
				return $v;
			}
		}
		return null;
	}

	public static function first_scalar( $arr ) {
		foreach ( (array) $arr as $v ) {
			if ( is_array( $v ) ) {
				$v = self::first_scalar( $v );
			}
			if ( null !== $v && '' !== $v ) {
				return $v;
			}
		}
		return null;
	}

	/**
	 * Resolve one mapped field for a post. Returns the raw value, or null.
	 * Terms come back as a list of arrays; images as attachment IDs or URLs.
	 */
	public static function resolve( WP_Post $post, $cfg ) {
		$source = isset( $cfg['source'] ) ? $cfg['source'] : 'none';
		$key    = isset( $cfg['key'] ) ? (string) $cfg['key'] : '';
		switch ( $source ) {
			case 'post_field':
				if ( in_array( $key, self::post_fields(), true ) ) {
					return $post->$key;
				}
				return null;
			case 'meta':
				return self::meta( $post->ID, $key );
			case 'related_meta':
				$parts = array_map( 'trim', explode( '>', $key, 2 ) );
				if ( count( $parts ) !== 2 ) {
					return null;
				}
				$link = self::meta( $post->ID, $parts[0] );
				if ( is_string( $link ) && strpos( $link, ',' ) !== false ) {
					$link = trim( strtok( $link, ',' ) );
				}
				$link = (int) $link;
				if ( $link <= 0 || ! get_post( $link ) ) {
					return null;
				}
				if ( in_array( $parts[1], self::post_fields(), true ) ) {
					return get_post_field( $parts[1], $link, 'raw' );
				}
				return self::meta( $link, $parts[1] );
			case 'taxonomy':
				if ( '' === $key || ! taxonomy_exists( $key ) ) {
					return null;
				}
				$terms = get_the_terms( $post, $key );
				if ( ! $terms || is_wp_error( $terms ) ) {
					return array();
				}
				$out = array();
				foreach ( $terms as $t ) {
					$out[] = array(
						'id'   => (int) $t->term_id,
						'name' => Appp_Events_Util::decode( $t->name ),
						'slug' => $t->slug,
					);
				}
				return $out;
			case 'featured_image':
				$id = get_post_thumbnail_id( $post );
				return $id ? (int) $id : null;
			case 'static':
				return '' === $key ? null : $key;
		}
		return null;
	}
}
