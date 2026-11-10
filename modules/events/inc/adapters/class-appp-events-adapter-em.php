<?php
/**
 * Events Manager (post type "event"). Dates and status come from post meta; recurring
 * series and locations are read from the em_events, em_event_recurrences and
 * em_locations tables, because recurrence rows have no posts of their own.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Adapter_EM extends Appp_Events_Adapter_Posts {

	private $loc_cache = array();

	public function id() {
		return 'events-manager';
	}

	public function label() {
		return 'Events Manager';
	}

	public function detect() {
		return defined( 'EM_VERSION' ) || class_exists( 'EM_Events' );
	}

	public function version() {
		return defined( 'EM_VERSION' ) ? EM_VERSION : '';
	}

	public function default_post_type() {
		return defined( 'EM_POST_TYPE_EVENT' ) ? EM_POST_TYPE_EVENT : 'event';
	}

	public function notes() {
		return array(
			__( 'Recurring events are expanded from the em_events table (rows linked by recurrence_set_id or recurrence_id). The recurring parent itself is not listed.', 'apppresser-events' ),
			__( 'Venue "Plugin default" reads the em_locations table through _location_id.', 'apppresser-events' ),
			__( 'Events Manager free has no featured flag.', 'apppresser-events' ),
		);
	}

	public function preset_fields() {
		return array(
			'title'         => array(
				'source' => 'post_field',
				'key'    => 'post_title',
			),
			'excerpt'       => array( 'source' => 'auto' ),
			'description'   => array(
				'source' => 'post_field',
				'key'    => 'post_content',
			),
			'image'         => array( 'source' => 'featured_image' ),
			'link'          => array( 'source' => 'auto' ),
			'start_date'    => array(
				'source' => 'meta',
				'key'    => '_event_start_local',
				'format' => 'Y-m-d H:i:s',
			),
			'start_time'    => array( 'source' => 'none' ),
			'end_date'      => array(
				'source' => 'meta',
				'key'    => '_event_end_local',
				'format' => 'Y-m-d H:i:s',
			),
			'end_time'      => array( 'source' => 'none' ),
			'timezone'      => array(
				'source' => 'meta',
				'key'    => '_event_timezone',
			),
			'all_day'       => array(
				'source' => 'meta',
				'key'    => '_event_all_day',
			),
			'venue_name'    => array( 'source' => 'auto' ),
			'venue_text'    => array( 'source' => 'none' ),
			'venue_address' => array( 'source' => 'auto' ),
			'venue_city'    => array( 'source' => 'auto' ),
			'venue_state'   => array( 'source' => 'auto' ),
			'venue_zip'     => array( 'source' => 'auto' ),
			'venue_country' => array( 'source' => 'auto' ),
			'venue_lat'     => array( 'source' => 'auto' ),
			'venue_lng'     => array( 'source' => 'auto' ),
			'ticket_url'    => array( 'source' => 'none' ),
			'cost'          => array( 'source' => 'none' ),
			'canceled'      => array(
				'source' => 'meta',
				'key'    => '_event_active_status',
				'match'  => '0',
			),
			'cancel_reason' => array( 'source' => 'none' ),
			'featured'      => array( 'source' => 'none' ),
			'categories'    => array(
				'source' => 'taxonomy',
				'key'    => 'event-categories',
			),
		);
	}

	private function table( $name ) {
		global $wpdb;
		// Events Manager uses per site tables on multisite unless global tables are on.
		$prefix = ( is_multisite() && get_site_option( 'dbem_ms_global_table' ) ) ? $wpdb->base_prefix : $wpdb->prefix;
		return $prefix . $name;
	}

	/** em_events rows (first per post) and em_locations rows, loaded in one query each. */
	private $ev_rows  = null;
	private $loc_rows = null;

	protected function watch_post_types() {
		return array( 'location' );
	}

	protected function prime( array $posts ) {
		global $wpdb;
		parent::prime( $posts );
		$this->ev_rows  = null;
		$this->loc_rows = null;
		if ( ! $posts ) {
			return;
		}
		$t = $this->table( 'em_events' );
		if ( Appp_Events_Util::table_exists( $t ) ) {
			$this->ev_rows = array();
			foreach ( array_chunk( wp_list_pluck( $posts, 'ID' ), 500 ) as $chunk ) {
				$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE post_id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ') ORDER BY post_id ASC, event_id ASC' ); // phpcs:ignore
				foreach ( (array) $rows as $r ) {
					if ( ! isset( $this->ev_rows[ (int) $r->post_id ] ) ) {
						$this->ev_rows[ (int) $r->post_id ] = $r;
					}
				}
			}
		}
		$lt = $this->table( 'em_locations' );
		if ( Appp_Events_Util::table_exists( $lt ) ) {
			$ids = array();
			foreach ( $posts as $post ) {
				$loc = (int) get_post_meta( $post->ID, '_location_id', true );
				if ( $loc ) {
					$ids[ $loc ] = true;
				}
			}
			$this->loc_rows = array();
			foreach ( array_chunk( array_keys( $ids ), 500 ) as $chunk ) {
				$rows = $wpdb->get_results( "SELECT * FROM {$lt} WHERE location_id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ')' ); // phpcs:ignore
				foreach ( (array) $rows as $r ) {
					$this->loc_rows[ (int) $r->location_id ] = $r;
				}
			}
		}
	}

	private function event_row( $post_id ) {
		global $wpdb;
		if ( null !== $this->ev_rows ) {
			return isset( $this->ev_rows[ (int) $post_id ] ) ? $this->ev_rows[ (int) $post_id ] : null;
		}
		$t = $this->table( 'em_events' );
		if ( ! Appp_Events_Util::table_exists( $t ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE post_id = %d LIMIT 1", $post_id ) ); // phpcs:ignore
	}

	private function location( WP_Post $post ) {
		global $wpdb;
		if ( array_key_exists( $post->ID, $this->loc_cache ) ) {
			return $this->loc_cache[ $post->ID ];
		}
		$loc_id = (int) get_post_meta( $post->ID, '_location_id', true );
		$row    = null;
		$t      = $this->table( 'em_locations' );
		if ( $loc_id && null !== $this->loc_rows ) {
			$row = isset( $this->loc_rows[ $loc_id ] ) ? $this->loc_rows[ $loc_id ] : null;
		} elseif ( $loc_id && Appp_Events_Util::table_exists( $t ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE location_id = %d", $loc_id ) ); // phpcs:ignore
		}
		$this->loc_cache[ $post->ID ] = $row;
		return $row;
	}

	protected function auto( WP_Post $post, $key ) {
		$map = array(
			'venue_name'    => 'location_name',
			'venue_address' => 'location_address',
			'venue_city'    => 'location_town',
			'venue_state'   => 'location_state',
			'venue_zip'     => 'location_postcode',
			'venue_country' => 'location_country',
			'venue_lat'     => 'location_latitude',
			'venue_lng'     => 'location_longitude',
		);
		if ( isset( $map[ $key ] ) ) {
			$loc = $this->location( $post );
			return ( $loc && isset( $loc->{$map[ $key ]} ) ) ? $loc->{$map[ $key ]} : null;
		}
		return parent::auto( $post, $key );
	}

	protected function occurrences( WP_Post $post, $start, $end, $tz ) {
		global $wpdb;
		$row = $this->event_row( $post->ID );
		if ( ! $row ) {
			return parent::occurrences( $post, $start, $end, $tz );
		}
		if ( ! empty( $row->event_private ) ) {
			return array();
		}
		$is_recurring = ( isset( $row->event_type ) && 'recurring' === $row->event_type ) || ( isset( $row->recurrence ) && 1 === (int) $row->recurrence );
		if ( ! $is_recurring ) {
			if ( ! $start && ! empty( $row->event_start_date ) ) {
				$etz   = Appp_Events_Util::tz( $row->event_timezone );
				$start = Appp_Events_Util::parse_dt( $row->event_start_date . ' ' . $row->event_start_time, 'Y-m-d H:i:s', $etz );
				$end   = Appp_Events_Util::parse_dt( $row->event_end_date . ' ' . $row->event_end_time, 'Y-m-d H:i:s', $etz );
			}
			return parent::occurrences( $post, $start, $end, $tz );
		}

		$t     = $this->table( 'em_events' );
		$where = array();
		$args  = array();
		$rt    = $this->table( 'em_event_recurrences' );
		if ( Appp_Events_Util::table_exists( $rt ) ) {
			$sets = $wpdb->get_col( $wpdb->prepare( "SELECT recurrence_set_id FROM {$rt} WHERE event_id = %d", $row->event_id ) ); // phpcs:ignore
			if ( $sets ) {
				$where[] = 'recurrence_set_id IN (' . implode( ',', array_map( 'intval', $sets ) ) . ')';
			}
		}
		$where[] = 'recurrence_id = %d';
		$args[]  = (int) $row->event_id;
		$sql     = "SELECT * FROM {$t} WHERE (" . implode( ' OR ', $where ) . ') AND event_id <> %d AND event_start_date IS NOT NULL ORDER BY event_start_date ASC, event_start_time ASC';
		$args[]  = (int) $row->event_id;
		$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore

		if ( ! $rows ) {
			return parent::occurrences( $post, $start, $end, $tz );
		}
		$out = array();
		foreach ( $rows as $i => $r ) {
			$etz = Appp_Events_Util::tz( $r->event_timezone ? $r->event_timezone : $tz->getName() );
			$s   = Appp_Events_Util::parse_dt( $r->event_start_date . ' ' . ( $r->event_start_time ? $r->event_start_time : '00:00:00' ), 'Y-m-d H:i:s', $etz );
			$e   = Appp_Events_Util::parse_dt( ( $r->event_end_date ? $r->event_end_date : $r->event_start_date ) . ' ' . ( $r->event_end_time ? $r->event_end_time : '23:59:59' ), 'Y-m-d H:i:s', $etz );
			if ( ! $s ) {
				continue;
			}
			$o = array(
				'start'      => $s,
				'end'        => $e,
				'occurrence' => true,
				'index'      => $i + 1,
			);
			if ( null !== $r->event_all_day && '' !== $r->event_all_day ) {
				$o['all_day'] = (bool) (int) $r->event_all_day;
			}
			if ( isset( $r->event_active_status ) && null !== $r->event_active_status && '0' === (string) $r->event_active_status ) {
				$o['canceled'] = true;
			}
			$out[] = $o;
		}
		return $out;
	}
}
