<?php
/**
 * Heuristics for events plugins we do not know: find candidate post types and guess a
 * field mapping from a sample of posts. Results are stored in options (not autoloaded,
 * no expiry) and cleared by the Re-detect button or when the Events tab is saved, so the
 * scan runs on demand in the admin, not on page views or warm feed requests. (Before
 * 0.2.1 they were 12 hour transients; those are still read once if present.)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Heuristic {

	const SAMPLE  = 50;
	const NAME_RE = '/(event|meetup|class|webinar|session|concert|show|gig|festival|workshop)/i';
	const T_CANDS = 'appp_events_candidates';
	const T_GUESS = 'appp_events_guess_';

	/** Post types owned by supported plugins and active presets, never offered as unknown candidates. */
	private static function known_types() {
		// Content types and helper types owned by the five supported plugin adapters.
		$known = array( 'tribe_events', 'tribe_venue', 'tribe_organizer', 'tec_calendar_embed', 'tp_event', 'event_auth_book', 'event_listing', 'event_venue', 'event_organizer', 'location', 'event-recurring', 'wpa-events' );
		if ( defined( 'EM_VERSION' ) ) {
			$known[] = 'event';
		}
		// Post types of active presets (built in or from appp_events_presets) are known too.
		foreach ( Appp_Events_Settings::adapters() as $a ) {
			if ( ! ( $a instanceof Appp_Events_Adapter_Guess ) && $a->default_post_type() && $a->detect() ) {
				$known[] = $a->default_post_type();
			}
		}
		return array_unique( $known );
	}

	public static function flush() {
		delete_option( self::T_CANDS );
		delete_transient( self::T_CANDS );
		foreach ( get_post_types() as $pt ) {
			delete_option( self::T_GUESS . $pt );
			delete_transient( self::T_GUESS . $pt );
		}
	}

	private static function stored( $name ) {
		$v = get_option( $name, null );
		if ( is_array( $v ) ) {
			return $v;
		}
		$v = get_transient( $name );
		return is_array( $v ) ? $v : null;
	}

	private static function store( $name, $value ) {
		update_option( $name, $value, false );
	}

	private static function sample_ids( $post_type ) {
		global $wpdb;
		return array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_date DESC LIMIT %d", $post_type, self::SAMPLE ) ) // phpcs:ignore
		);
	}

	/** meta key => list of sample values (first value per post). */
	private static function sample_meta( array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$rows = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN (" . implode( ',', $ids ) . ") AND meta_key NOT IN ('_edit_lock','_edit_last','_wp_old_slug','_wp_page_template','_thumbnail_id','_encloseme','_pingme')" ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $r ) {
			if ( ! isset( $out[ $r->meta_key ][ $r->post_id ] ) ) {
				$out[ $r->meta_key ][ $r->post_id ] = (string) $r->meta_value;
			}
		}
		return $out;
	}

	/** Date kind of a value: 'unix', 'datetime', 'date', 'time', or ''. */
	public static function date_kind( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return '';
		}
		if ( preg_match( '/^\d{9,11}$/', $v ) ) {
			$y = (int) gmdate( 'Y', (int) $v );
			return ( $y >= 1990 && $y <= 2100 ) ? 'unix' : '';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{1,2}:\d{2}/', $v ) ) {
			return 'datetime';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) || preg_match( '#^\d{1,2}/\d{1,2}/\d{4}$#', $v ) || preg_match( '/^\d{8}$/', $v ) ) {
			return 'date';
		}
		if ( preg_match( '/^\d{1,2}:\d{2}(:\d{2})?\s*(am|pm)?$/i', $v ) ) {
			return 'time';
		}
		return '';
	}

	private static function share( array $vals, $test ) {
		$vals = array_filter( $vals, 'strlen' );
		if ( ! $vals ) {
			return 0.0;
		}
		$hit = 0;
		foreach ( $vals as $v ) {
			if ( call_user_func( $test, $v ) ) {
				++$hit;
			}
		}
		return $hit / count( $vals );
	}

	private static function dominant_kind( array $vals ) {
		$c = array();
		foreach ( array_filter( $vals, 'strlen' ) as $v ) {
			$k = self::date_kind( $v );
			if ( $k ) {
				$c[ $k ] = isset( $c[ $k ] ) ? $c[ $k ] + 1 : 1;
			}
		}
		if ( ! $c ) {
			return array( '', 0.0 );
		}
		arsort( $c );
		$k = key( $c );
		return array( $k, $c[ $k ] / max( 1, count( array_filter( $vals, 'strlen' ) ) ) );
	}

	private static function format_for( $kind, array $vals ) {
		switch ( $kind ) {
			case 'unix':
				return 'U_local';
			case 'datetime':
				$v = trim( (string) reset( $vals ) );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v ) ) {
					return 'Y-m-d H:i:s';
				}
				if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v ) ) {
					return 'Y-m-d H:i';
				}
				return '';
			case 'date':
				$v = trim( (string) reset( $vals ) );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
					return 'Y-m-d';
				}
				if ( preg_match( '/^\d{8}$/', $v ) ) {
					return 'Ymd';
				}
				return '';
			case 'time':
				$v = trim( (string) reset( $vals ) );
				return preg_match( '/(am|pm)$/i', $v ) ? 'g:i A' : ( preg_match( '/^\d{2}:\d{2}$/', $v ) ? 'H:i' : '' );
		}
		return '';
	}

	/**
	 * Candidate post types for unknown events plugins.
	 *
	 * @return array post_type => {label, reason, score, sampled}
	 */
	public static function candidates( $force = false ) {
		if ( ! $force ) {
			$c = self::stored( self::T_CANDS );
			if ( is_array( $c ) ) {
				// A cached list can predate a preset (0.1 cache, or a preset added later).
				return array_diff_key( $c, array_flip( self::known_types() ) );
			}
		}
		$out = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $obj ) {
			if ( in_array( $obj->name, array( 'post', 'page', 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_family', 'wp_font_face', 'wp_global_styles' ), true ) ) {
				continue;
			}
			if ( in_array( $obj->name, self::known_types(), true ) ) {
				continue;
			}
			$name_hit = preg_match( self::NAME_RE, $obj->name . ' ' . $obj->label );
			$ids      = self::sample_ids( $obj->name );
			$meta     = self::sample_meta( $ids );
			$date_key = '';
			foreach ( $meta as $k => $vals ) {
				list( $kind, $share ) = self::dominant_kind( $vals );
				if ( $kind && 'time' !== $kind && $share >= 0.6 && preg_match( '/(date|start|begin|end|when|time)/i', $k ) ) {
					$date_key = $k;
					break;
				}
			}
			if ( ! $name_hit && ! $date_key ) {
				continue;
			}
			$reason = array();
			if ( $name_hit ) {
				$reason[] = __( 'name looks like events', 'apppresser-events' );
			}
			if ( $date_key ) {
				/* translators: %s meta key */
				$reason[] = sprintf( __( 'date meta %s', 'apppresser-events' ), $date_key );
			}
			$out[ $obj->name ] = array(
				'label'   => $obj->labels->name,
				'reason'  => implode( ', ', $reason ),
				'score'   => ( $name_hit ? 1 : 0 ) + ( $date_key ? 2 : 0 ),
				'sampled' => count( $ids ),
			);
		}
		uasort(
			$out,
			function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);
		self::store( self::T_CANDS, $out );
		return $out;
	}

	/**
	 * Guess a mapping for a post type.
	 *
	 * @return array {fields: field => cfg, confidence: field => high|medium|low, sampled: int, notes: []}
	 */
	public static function guess( $post_type, $force = false ) {
		if ( ! $force ) {
			$g = self::stored( self::T_GUESS . $post_type );
			if ( is_array( $g ) ) {
				return $g;
			}
		}
		$fields = Appp_Events_Fields::manual_defaults();
		$conf   = array(
			'title'       => 'high',
			'description' => 'high',
			'excerpt'     => 'high',
			'image'       => 'high',
			'link'        => 'high',
			'all_day'     => 'medium',
		);
		$notes  = array();
		$ids    = self::sample_ids( $post_type );
		$meta   = self::sample_meta( $ids );
		$used   = array();

		$is_url  = function ( $v ) {
			return (bool) preg_match( '#^https?://\S+$#i', trim( $v ) );
		};
		$is_num  = function ( $v ) {
			return is_numeric( trim( str_replace( array( '$', ',', ' ' ), '', $v ) ) );
		};
		$is_bool = function ( $v ) {
			return in_array( strtolower( trim( $v ) ), array( '0', '1', 'yes', 'no', 'true', 'false', 'on', 'off', '' ), true );
		};
		$is_text = function ( $v ) {
			return '' !== trim( $v ) && ! is_numeric( trim( $v ) ) && strlen( $v ) < 500 && false === strpos( $v, '{' );
		};

		// Rank date keys.
		$dates = array();
		foreach ( $meta as $k => $vals ) {
			list( $kind, $share ) = self::dominant_kind( $vals );
			if ( $kind && $share >= 0.6 ) {
				$dates[ $k ] = array( $kind, $share, $vals );
			}
		}
		$pick_date = function ( $role ) use ( &$dates, &$used ) {
			$best = null;
			$bs   = -1;
			foreach ( $dates as $k => $d ) {
				// Time only keys are never the date; they fill start_time and end_time below.
				if ( isset( $used[ $k ] ) || 'time' === $d[0] ) {
					continue;
				}
				$s  = 0;
				$lk = strtolower( $k );
				if ( 'start' === $role && preg_match( '/(start|begin|from)/', $lk ) ) {
					$s += 3;
				}
				if ( 'end' === $role && preg_match( '/(end|finish|until|to$|stop)/', $lk ) ) {
					$s += 3;
				}
				if ( 'start' === $role && preg_match( '/(end|finish|until|stop|expir|deadline|regist|modified|created|publish)/', $lk ) ) {
					$s -= 5;
				}
				if ( 'end' === $role && preg_match( '/(start|begin|expir|deadline|regist|modified|created|publish)/', $lk ) ) {
					$s -= 5;
				}
				if ( preg_match( '/(date|time|when|day)/', $lk ) ) {
					++$s;
				}
				if ( preg_match( '/event/', $lk ) ) {
					++$s;
				}
				if ( $s > $bs ) {
					$bs   = $s;
					$best = $k;
				}
			}
			return array( $best, $bs );
		};

		list( $sk, $ss ) = $pick_date( 'start' );
		if ( $sk && $ss >= 0 ) {
			$used[ $sk ]          = true;
			$fields['start_date'] = array(
				'source' => 'meta',
				'key'    => $sk,
				'format' => self::format_for( $dates[ $sk ][0], $dates[ $sk ][2] ),
			);
			$conf['start_date']   = $ss >= 4 && $dates[ $sk ][1] >= 0.8 ? 'high' : ( $ss >= 2 ? 'medium' : 'low' );
			if ( 'unix' === $dates[ $sk ][0] ) {
				$notes[] = sprintf( /* translators: %s key */ __( '%s holds Unix timestamps. Guessed U_local (local wall time stored as a timestamp); switch to U if times are off by the UTC offset.', 'apppresser-events' ), $sk );
				if ( 'high' === $conf['start_date'] ) {
					$conf['start_date'] = 'medium';
				}
			}
		}
		list( $ek, $es ) = $pick_date( 'end' );
		if ( $ek && $es >= 0 ) {
			$used[ $ek ]        = true;
			$fields['end_date'] = array(
				'source' => 'meta',
				'key'    => $ek,
				'format' => self::format_for( $dates[ $ek ][0], $dates[ $ek ][2] ),
			);
			$conf['end_date']   = $es >= 4 ? 'high' : ( $es >= 2 ? 'medium' : 'low' );
		}
		// Separate time keys when the dates are date only.
		foreach ( array(
			'start' => 'start_time',
			'end'   => 'end_time',
		) as $role => $field ) {
			// No end date key: an end time alone ends on the start date.
			$base = 'start' === $role ? $sk : ( $ek ? $ek : $sk );
			if ( ! $base || ! isset( $dates[ $base ] ) || 'date' !== $dates[ $base ][0] ) {
				continue;
			}
			foreach ( $dates as $k => $d ) {
				if ( 'time' === $d[0] && ! isset( $used[ $k ] ) && preg_match( 'start' === $role ? '/(start|begin|from)/i' : '/(end|finish|until)/i', $k ) ) {
					$used[ $k ]       = true;
					$fields[ $field ] = array(
						'source' => 'meta',
						'key'    => $k,
						'format' => self::format_for( 'time', $d[2] ),
					);
					$conf[ $field ]   = 'medium';
					break;
				}
			}
		}

		$rules = array(
			'all_day'       => array( '/all[-_ ]?day/i', $is_bool, 'high' ),
			'timezone'      => array( '/(timezone|time_zone|^_?tz$)/i', null, 'medium' ),
			'venue_name'    => array( '/(venue|location|place)[-_ ]?(name|title)/i', $is_text, 'high' ),
			'venue_address' => array( '/(street|address[-_ ]?1|^_?address$|venue[-_ ]?address)/i', $is_text, 'medium' ),
			'venue_city'    => array( '/(city|town)/i', $is_text, 'high' ),
			'venue_state'   => array( '/(state|province|region)/i', $is_text, 'medium' ),
			'venue_zip'     => array( '/(zip|postal|postcode|pincode)/i', null, 'high' ),
			'venue_country' => array( '/country/i', $is_text, 'high' ),
			'venue_lat'     => array( '/(^|[-_])lat(itude)?$/i', $is_num, 'high' ),
			'venue_lng'     => array( '/(^|[-_])(lng|lon|long|longitude)$/i', $is_num, 'high' ),
			'venue_text'    => array( '/(location|venue|address|place|where)/i', $is_text, 'medium' ),
			'ticket_url'    => array( '/(ticket|regist|rsvp|book|link|url)/i', $is_url, 'medium' ),
			'cost'          => array( '/(price|cost|fee)/i', $is_num, 'high' ),
			'featured'      => array( '/(featured|sticky|highlight)/i', $is_bool, 'high' ),
			'canceled'      => array( '/cancel/i', $is_bool, 'high' ),
		);
		foreach ( $rules as $field => $rule ) {
			list( $re, $check, $level ) = $rule;
			$best                       = null;
			$bsh                        = 0;
			foreach ( $meta as $k => $vals ) {
				if ( isset( $used[ $k ] ) || ! preg_match( $re, $k ) ) {
					continue;
				}
				if ( in_array( $field, array( 'venue_text', 'venue_name', 'venue_address' ), true ) && preg_match( '/(link|url|map|label|target|lat|lng|lon)/i', $k ) ) {
					continue;
				}
				if ( 'ticket_url' === $field && preg_match( '/(location|venue|map|image|video|logo)/i', $k ) ) {
					continue;
				}
				$sh = $check ? self::share( $vals, $check ) : 1.0;
				if ( $sh > $bsh ) {
					$bsh  = $sh;
					$best = $k;
				}
			}
			if ( $best && $bsh >= 0.5 ) {
				$used[ $best ]    = true;
				$fields[ $field ] = array(
					'source' => 'meta',
					'key'    => $best,
				);
				$conf[ $field ]   = $bsh >= 0.8 ? $level : 'low';
			}
		}
		// A status key whose values mention cancel.
		if ( 'none' === $fields['canceled']['source'] ) {
			foreach ( $meta as $k => $vals ) {
				if ( preg_match( '/status/i', $k ) && self::share(
					$vals,
					function ( $v ) {
						return (bool) preg_match( '/cancel/i', $v ); }
				) > 0 ) {
					$fields['canceled'] = array(
						'source' => 'meta',
						'key'    => $k,
						'match'  => '/cancel/i',
					);
					$conf['canceled']   = 'medium';
					break;
				}
			}
		}
		// Categories: a hierarchical taxonomy first, then any with cat in its name.
		$taxes = get_object_taxonomies( $post_type, 'objects' );
		$tax   = null;
		foreach ( $taxes as $t ) {
			if ( $t->hierarchical && $t->public ) {
				$tax = $t->name;
				break;
			}
		}
		if ( ! $tax ) {
			foreach ( $taxes as $t ) {
				if ( preg_match( '/cat/i', $t->name ) ) {
					$tax = $t->name;
					break;
				}
			}
		}
		if ( $tax ) {
			$fields['categories'] = array(
				'source' => 'taxonomy',
				'key'    => $tax,
			);
			$conf['categories']   = preg_match( '/cat/i', $tax ) ? 'high' : 'medium';
		}
		if ( ! $sk ) {
			$notes[] = __( 'No start date found in the sample. Map Start date by hand.', 'apppresser-events' );
		}

		$g = array(
			'fields'     => $fields,
			'confidence' => $conf,
			'sampled'    => count( $ids ),
			'meta_keys'  => array_keys( $meta ),
			'notes'      => $notes,
		);
		self::store( self::T_GUESS . $post_type, $g );
		return $g;
	}
}
