<?php
/**
 * Shared implementation for supported post-based events integrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Appp_Events_Adapter_Posts extends Appp_Events_Adapter {

	/**
	 * Computed value for a field whose source is "auto". Child adapters add their own.
	 */
	protected function auto( WP_Post $post, $key ) {
		switch ( $key ) {
			case 'excerpt':
				if ( '' !== trim( $post->post_excerpt ) ) {
					return Appp_Events_Util::plain( $post->post_excerpt );
				}
				return Appp_Events_Util::excerpt_from( $post->post_content );
			case 'link':
				return get_permalink( $post );
		}
		return null;
	}

	/**
	 * Occurrences for a post. Default: the one start/end from the mapping.
	 * Each entry: start, end (DateTimeImmutable) and optional overrides (canceled, all_day).
	 */
	protected function occurrences( WP_Post $post, $start, $end, $tz ) {
		return $start ? array(
			array(
				'start' => $start,
				'end'   => $end,
			),
		) : array();
	}

	protected function query_posts() {
		if ( '' === $this->post_type || ! post_type_exists( $this->post_type ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'        => $this->post_type,
				'post_status'      => 'publish',
				'posts_per_page'   => (int) apply_filters( 'appp_events_max_posts', 500 ),
				'orderby'          => 'ID',
				'order'            => 'DESC', // newest posts first, so a capped build keeps current events
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
	}

	/** Post types of linked posts (venues, organizers) read while building. */
	protected $related_types = array();

	/**
	 * Load linked posts and featured images for all posts in a few queries instead of
	 * one or two per post. Results are the same; only the number of queries changes.
	 */
	protected function prime( array $posts ) {
		if ( ! $posts ) {
			return;
		}
		$ids = array();
		foreach ( (array) $this->fields as $cfg ) {
			$src = isset( $cfg['source'] ) ? $cfg['source'] : '';
			if ( 'related_meta' === $src ) {
				$parts = array_map( 'trim', explode( '>', (string) $cfg['key'], 2 ) );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				foreach ( $posts as $post ) {
					$link = Appp_Events_Fields::meta( $post->ID, $parts[0] );
					if ( is_string( $link ) && false !== strpos( $link, ',' ) ) {
						$link = trim( strtok( $link, ',' ) );
					}
					if ( (int) $link > 0 ) {
						$ids[ (int) $link ] = 'related';
					}
				}
			} elseif ( 'featured_image' === $src ) {
				foreach ( $posts as $post ) {
					$tid = (int) get_post_meta( $post->ID, '_thumbnail_id', true );
					if ( $tid > 0 && ! isset( $ids[ $tid ] ) ) {
						$ids[ $tid ] = 'image';
					}
				}
			}
		}
		if ( ! $ids ) {
			return;
		}
		_prime_post_caches( array_keys( $ids ), false, true );
		foreach ( $ids as $id => $kind ) {
			if ( 'related' === $kind ) {
				$pt = get_post_type( $id );
				if ( $pt ) {
					$this->related_types[ $pt ] = true;
				}
			}
		}
	}

	/**
	 * Post types and taxonomies whose changes must refresh the feed cache.
	 *
	 * @return array {post_types, taxonomies}
	 */
	public function watch() {
		$pts = array_merge( array( $this->post_type ), array_keys( $this->related_types ), $this->watch_post_types() );
		$tax = array();
		foreach ( (array) $this->fields as $cfg ) {
			if ( isset( $cfg['source'] ) && 'taxonomy' === $cfg['source'] && ! empty( $cfg['key'] ) ) {
				$tax[] = (string) $cfg['key'];
			}
		}
		return array(
			'post_types' => $pts,
			'taxonomies' => $tax,
		);
	}

	/** Extra post types an adapter reads (venues, organizers, locations). */
	protected function watch_post_types() {
		return array();
	}

	public function items() {
		$items       = array();
		$this->stats = array(
			'records'           => 0,
			'missing_start'     => 0,
			'missing_start_ids' => array(),
		);
		$posts       = $this->query_posts();
		$this->prime( $posts );
		foreach ( $posts as $post ) {
			++$this->stats['records'];
			foreach ( $this->post_items( $post ) as $item ) {
				$item = $this->filter_event( $item, $post );
				if ( null !== $item ) {
					$items[] = $item;
				}
			}
		}
		return $items;
	}

	protected function value( WP_Post $post, $key ) {
		$cfg = $this->cfg( $key );
		if ( 'auto' === $cfg['source'] ) {
			return $this->auto( $post, $key );
		}
		return Appp_Events_Fields::resolve( $post, $cfg );
	}

	protected function fmt( $key ) {
		$c = $this->cfg( $key );
		return isset( $c['format'] ) ? $c['format'] : '';
	}

	protected function flag( WP_Post $post, $key ) {
		$cfg = $this->cfg( $key );
		if ( 'none' === $cfg['source'] ) {
			return false;
		}
		$raw = $this->value( $post, $key );
		if ( 'auto' === $cfg['source'] ) {
			return is_bool( $raw ) ? $raw : null;
		}
		if ( is_array( $raw ) ) {
			return ! empty( $raw );
		}
		return Appp_Events_Util::to_bool( $raw, isset( $cfg['match'] ) ? $cfg['match'] : '' );
	}

	public function post_items( WP_Post $post ) {
		$tz = Appp_Events_Util::tz( (string) $this->value( $post, 'timezone' ) );

		$start = Appp_Events_Util::combine(
			Appp_Events_Util::parse_dt( $this->value( $post, 'start_date' ), $this->fmt( 'start_date' ), $tz ),
			Appp_Events_Util::parse_dt( $this->value( $post, 'start_time' ), $this->fmt( 'start_time' ), $tz )
		);
		$end_d = Appp_Events_Util::parse_dt( $this->value( $post, 'end_date' ), $this->fmt( 'end_date' ), $tz );
		$end_t = Appp_Events_Util::parse_dt( $this->value( $post, 'end_time' ), $this->fmt( 'end_time' ), $tz );
		if ( ! $end_d && $end_t && $start ) {
			$end_d = $start;
		}
		$end = Appp_Events_Util::combine( $end_d, $end_t );

		$occ = $this->occurrences( $post, $start, $end, $tz );
		if ( ! $occ ) {
			if ( ! $start && isset( $this->stats['missing_start'] ) ) {
				++$this->stats['missing_start'];
				$this->stats['missing_start_ids'][] = $post->ID;
			}
			return array();
		}

		$all_day = $this->flag( $post, 'all_day' );

		// Venue: mapped parts first, then fill blanks from the one line text.
		$venue = array();
		foreach ( array( 'name', 'address', 'city', 'state', 'zip', 'country', 'lat', 'lng' ) as $part ) {
			$v = $this->value( $post, 'venue_' . $part );
			if ( is_array( $v ) ) {
				$v = implode( ', ', wp_list_pluck( $v, 'name' ) );
			}
			$venue[ $part ] = $v;
		}
		$text = $this->value( $post, 'venue_text' );
		if ( is_string( $text ) && '' !== trim( $text ) ) {
			foreach ( Appp_Events_Util::parse_address( $text ) as $part => $v ) {
				if ( ( ! isset( $venue[ $part ] ) || '' === (string) $venue[ $part ] ) && '' !== $v ) {
					$venue[ $part ] = $v;
				}
			}
		}

		$image_raw = $this->value( $post, 'image' );
		$desc_raw  = $this->value( $post, 'description' );
		$exc_raw   = $this->value( $post, 'excerpt' );
		if ( 'auto' !== $this->source_of( 'excerpt' ) && null !== $exc_raw ) {
			$exc_raw = Appp_Events_Util::plain( $exc_raw );
		}
		$link = $this->value( $post, 'link' );

		$base = array(
			'title'         => (string) $this->value( $post, 'title' ),
			'link'          => is_string( $link ) ? $link : null,
			'image'         => null !== $image_raw ? Appp_Events_Util::image_from_value( $image_raw ) : null,
			'excerpt'       => null !== $exc_raw ? (string) $exc_raw : '',
			'description'   => null !== $desc_raw ? Appp_Events_Util::render_html( $desc_raw ) : '',
			'venue'         => $venue,
			'ticket_url'    => $this->value( $post, 'ticket_url' ),
			'cost'          => $this->value( $post, 'cost' ),
			'canceled'      => (bool) $this->flag( $post, 'canceled' ),
			'cancel_reason' => $this->value( $post, 'cancel_reason' ),
			'featured'      => (bool) $this->flag( $post, 'featured' ),
			'categories'    => $this->terms_out( $this->value( $post, 'categories' ) ),
			'protected'     => '' !== (string) $post->post_password,
		);
		if ( '' === $base['excerpt'] && ! $base['protected'] && '' !== $base['description'] && 'none' !== $this->source_of( 'excerpt' ) ) {
			$base['excerpt'] = Appp_Events_Util::excerpt_from( $desc_raw );
		}

		$items  = array();
		$series = count( $occ ) > 1 || ! empty( $occ[0]['occurrence'] );
		if ( ! $this->expand && $series ) {
			$occ = array( $this->pick_series_occurrence( $occ ) );
		}
		foreach ( $occ as $i => $o ) {
			$s                     = $o['start'];
			$e                     = isset( $o['end'] ) && $o['end'] ? $o['end'] : $s;
			$p                     = $base;
			$p['id']               = $series ? $post->ID . '_' . $s->format( 'YmdHi' ) : $post->ID;
			$p['series_id']        = $post->ID;
			$p['is_occurrence']    = $series;
			$p['occurrence_index'] = $series ? ( isset( $o['index'] ) ? $o['index'] : $i + 1 ) : null;
			$p['start']            = $s;
			$p['end']              = $e;
			$ad                    = isset( $o['all_day'] ) ? $o['all_day'] : $all_day;
			if ( null === $ad ) {
				$ad = ( '0000' === $s->format( 'Hi' ) && $e->format( 'Hi' ) >= '2359' );
			}
			$p['all_day'] = (bool) $ad;
			if ( isset( $o['canceled'] ) ) {
				$p['canceled'] = $p['canceled'] || $o['canceled'];
			}
			if ( isset( $o['link'] ) ) {
				$p['link'] = $o['link'];
			}
			$items[] = $this->build_item( $p );
		}
		return $items;
	}

	/** With expand off, keep the next upcoming occurrence (or the last one). */
	protected function pick_series_occurrence( array $occ ) {
		$now = time();
		foreach ( $occ as $i => $o ) {
			$e = isset( $o['end'] ) && $o['end'] ? $o['end'] : $o['start'];
			if ( $e->getTimestamp() >= $now ) {
				$o['index'] = $i + 1;
				return $o;
			}
		}
		$o          = end( $occ );
		$o['index'] = count( $occ );
		return $o;
	}
}
