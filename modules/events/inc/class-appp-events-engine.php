<?php
/**
 * Query engine: asks the adapter for every item, then filters by date window and
 * category, sorts, collapses series when expand is off, and paginates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Engine {

	/** Effective settings in use for this request (saved, or a preview override). */
	private static $eff = null;

	/** True while an admin preview runs with unsaved settings. */
	private static $override = false;

	/** All items for the current site, kept for the request. */
	private static $items = null;

	/** {mapping_version, source, expand} that belong to $items. */
	private static $meta = null;

	/** Use unsaved settings (admin preview). Pass null to go back to saved. */
	public static function use_settings( $override ) {
		self::$override = null !== $override;
		self::$eff      = null === $override ? null : Appp_Events_Settings::effective( $override );
		self::$items    = null;
		self::$meta     = null;
	}

	/** Forget the per request copies (tests, long running CLI jobs). */
	public static function reset_request_cache() {
		self::$eff      = null;
		self::$override = false;
		self::$items    = null;
		self::$meta     = null;
	}

	public static function eff() {
		if ( null === self::$eff ) {
			self::$eff = Appp_Events_Settings::effective();
		}
		return self::$eff;
	}

	/**
	 * The stored list is used for REST and other front end reads with saved settings.
	 * Admin screens (preview, warnings) always build fresh so their counts are live.
	 */
	private static function use_cache() {
		return ! self::$override && ! is_admin();
	}

	public static function all_items() {
		if ( null !== self::$items ) {
			return self::$items;
		}
		$cache = self::use_cache();
		$key   = '';
		if ( $cache ) {
			$key = Appp_Events_Cache::key();
			$hit = Appp_Events_Cache::get( $key );
			if ( is_array( $hit ) && isset( $hit['items'], $hit['mapping_version'], $hit['source'] ) ) {
				self::$meta  = array(
					'mapping_version' => $hit['mapping_version'],
					'source'          => $hit['source'],
					'expand'          => ! empty( $hit['expand'] ),
				);
				self::$items = $hit['items'];
				return self::$items;
			}
		}
		$eff         = self::eff();
		$items       = $eff['adapter']->items();
		self::$items = apply_filters( 'appp_events_items', $items, $eff );
		self::$meta  = array(
			'mapping_version' => $eff['mapping_version'],
			'source'          => $eff['adapter']->source_slug(),
			'expand'          => (bool) $eff['expand'],
		);
		if ( $cache ) {
			$watch = method_exists( $eff['adapter'], 'watch' ) ? $eff['adapter']->watch() : array(
				'post_types' => array(),
				'taxonomies' => array(),
			);
			Appp_Events_Cache::set( $key, self::$meta + array( 'items' => self::$items ), $watch['post_types'], $watch['taxonomies'], Appp_Events_Cache::ttl( $eff['preset'] ) );
		}
		return self::$items;
	}

	/** Mapping version, source slug and expand default for the current list. */
	public static function meta() {
		if ( null === self::$meta ) {
			self::all_items();
		}
		return self::$meta;
	}

	private static function parse_bound( $value, $end_of_day = false ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$value = (string) $value;
		$tz    = wp_timezone();
		try {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$d = new DateTimeImmutable( $value . ( $end_of_day ? ' 23:59:59' : ' 00:00:00' ), $tz );
			} else {
				$d = new DateTimeImmutable( $value, $tz );
			}
			return $d->getTimestamp();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * @param array $args page, per_page, start, end, category, upcoming, order, expand
	 * @return array|WP_Error {items, total, pages}
	 */
	public static function query( array $args ) {
		$meta     = self::meta();
		$page     = max( 1, (int) ( isset( $args['page'] ) ? $args['page'] : 1 ) );
		$per_page = min( 100, max( 1, (int) ( isset( $args['per_page'] ) ? $args['per_page'] : 10 ) ) );
		$order    = ( isset( $args['order'] ) && 'desc' === strtolower( $args['order'] ) ) ? 'desc' : 'asc';
		$expand   = isset( $args['expand'] ) && null !== $args['expand'] ? (bool) $args['expand'] : $meta['expand'];
		$upcoming = isset( $args['upcoming'] ) && null !== $args['upcoming'] ? (bool) $args['upcoming'] : true;

		$start = self::parse_bound( isset( $args['start'] ) ? $args['start'] : null );
		$end   = self::parse_bound( isset( $args['end'] ) ? $args['end'] : null, true );
		if ( false === $start || false === $end ) {
			return new WP_Error( 'appp_events_bad_date', __( 'start and end must be dates (YYYY-MM-DD) or ISO 8601 date times.', 'apppresser-events' ), array( 'status' => 400 ) );
		}
		if ( null === $start && $upcoming ) {
			$start = time();
		}

		$cats = array();
		if ( ! empty( $args['category'] ) ) {
			$cats = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $args['category'] ) ) ), 'strlen' );
		}

		$out = array();
		foreach ( self::all_items() as $it ) {
			if ( null !== $start && $it['_end_ts'] < $start ) {
				continue;
			}
			if ( null !== $end && $it['_start_ts'] > $end ) {
				continue;
			}
			if ( $cats ) {
				$hit = false;
				foreach ( $it['categories'] as $c ) {
					if ( in_array( strtolower( (string) $c['slug'] ), $cats, true ) || ( null !== $c['id'] && in_array( (string) $c['id'], $cats, true ) ) ) {
						$hit = true;
						break;
					}
				}
				if ( ! $hit ) {
					continue;
				}
			}
			$out[] = $it;
		}

		usort(
			$out,
			function ( $a, $b ) {
				if ( $a['_start_ts'] === $b['_start_ts'] ) {
					return strcmp( (string) $a['id'], (string) $b['id'] );
				}
				return $a['_start_ts'] < $b['_start_ts'] ? -1 : 1;
			}
		);

		if ( ! $expand ) {
			$seen = array();
			$keep = array();
			foreach ( $out as $it ) {
				$key = (string) $it['series_id'];
				if ( $it['is_occurrence'] && isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$keep[]       = $it;
			}
			$out = $keep;
		}

		if ( 'desc' === $order ) {
			$out = array_reverse( $out );
		}

		$total = count( $out );
		$pages = (int) ceil( $total / $per_page );
		$slice = array_slice( $out, ( $page - 1 ) * $per_page, $per_page );

		return array(
			'items' => array_map( array( __CLASS__, 'clean' ), $slice ),
			'total' => $total,
			'pages' => $pages,
		);
	}

	/**
	 * One item by id. An occurrence id ("123_202611071000") returns that occurrence; a
	 * series id returns its next upcoming occurrence (or its last one) plus the list of
	 * all occurrences.
	 */
	public static function get( $id ) {
		$id      = (string) $id;
		$all     = self::all_items();
		$matches = array();
		foreach ( $all as $it ) {
			if ( (string) $it['id'] === $id ) {
				return self::clean( $it );
			}
			if ( (string) $it['series_id'] === $id ) {
				$matches[] = $it;
			}
		}
		if ( ! $matches ) {
			return null;
		}
		usort(
			$matches,
			function ( $a, $b ) {
				return $a['_start_ts'] - $b['_start_ts'];
			}
		);
		$pick = end( $matches );
		$now  = time();
		foreach ( $matches as $m ) {
			if ( $m['_end_ts'] >= $now ) {
				$pick = $m;
				break;
			}
		}
		$item = self::clean( $pick );
		if ( count( $matches ) > 1 || $pick['is_occurrence'] ) {
			$item['occurrences'] = array();
			foreach ( $matches as $m ) {
				$item['occurrences'][] = array(
					'id'       => $m['id'],
					'start'    => $m['start'],
					'end'      => $m['end'],
					'canceled' => $m['status']['canceled'],
				);
			}
		}
		return $item;
	}

	public static function clean( $it ) {
		unset( $it['_start_ts'], $it['_end_ts'] );
		$meta                  = self::meta();
		$it['mapping_version'] = $meta['mapping_version'];
		return apply_filters( 'appp_events_item', $it );
	}
}
