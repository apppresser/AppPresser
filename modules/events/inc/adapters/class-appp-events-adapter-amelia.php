<?php
/**
 * Amelia events. Amelia keeps events in its own tables, not posts:
 * amelia_events (one row per event, recurring children point at parentId),
 * amelia_events_periods (UTC start and end, one or more per event),
 * amelia_events_tags and amelia_locations.
 * Values are computed; the mapping screen can force a static value or switch a field off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Amelia's own table columns are camelCase (eventId, parentId, locationId).
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

class Appp_Events_Adapter_Amelia extends Appp_Events_Adapter {

	public function id() {
		return 'ameliabooking';
	}

	public function label() {
		return 'Amelia';
	}

	public function detect() {
		global $wpdb;
		return defined( 'AMELIA_VERSION' ) && Appp_Events_Util::table_exists( $wpdb->prefix . 'amelia_events' );
	}

	public function version() {
		return defined( 'AMELIA_VERSION' ) ? AMELIA_VERSION : '';
	}

	public function allowed_sources() {
		return array( 'auto', 'static', 'none' );
	}

	public function notes() {
		return array(
			__( 'Amelia stores events in its own tables, so fields are read by the adapter. You can still set a static value or turn a field off.', 'apppresser-events' ),
			__( 'Recurring events: every child row (parentId) and every extra date period becomes its own item with series_id set to the parent.', 'apppresser-events' ),
			__( 'Amelia events have no public page, so Link is empty unless you set a static value (for example your events page).', 'apppresser-events' ),
			__( 'Categories come from Amelia event tags. Canceled means status canceled or rejected. Amelia has no featured flag or password protection.', 'apppresser-events' ),
		);
	}

	public function preset_fields() {
		$f = array();
		foreach ( array_keys( Appp_Events_Fields::definitions() ) as $k ) {
			$f[ $k ] = array( 'source' => 'auto' );
		}
		foreach ( array( 'start_time', 'end_time', 'venue_text', 'ticket_url', 'featured', 'cancel_reason' ) as $k ) {
			$f[ $k ] = array( 'source' => 'none' );
		}
		return $f;
	}

	private function settings_tz() {
		$s  = json_decode( (string) get_option( 'amelia_settings' ), true );
		$tz = isset( $s['general']['timeZone'] ) ? (string) $s['general']['timeZone'] : '';
		return Appp_Events_Util::tz( $tz );
	}

	public function items() {
		global $wpdb;
		$p = $wpdb->prefix . 'amelia_';
		if ( ! Appp_Events_Util::table_exists( $p . 'events' ) ) {
			return array();
		}
		$events  = $wpdb->get_results( "SELECT * FROM {$p}events WHERE `show` = 1 AND status IN ('approved','canceled','rejected') ORDER BY id ASC" ); // phpcs:ignore
		$periods = $wpdb->get_results( "SELECT * FROM {$p}events_periods ORDER BY periodStart ASC" ); // phpcs:ignore
		$tags    = Appp_Events_Util::table_exists( $p . 'events_tags' ) ? $wpdb->get_results( "SELECT * FROM {$p}events_tags" ) : array(); // phpcs:ignore
		$locs    = array();
		if ( Appp_Events_Util::table_exists( $p . 'locations' ) ) {
			foreach ( $wpdb->get_results( "SELECT * FROM {$p}locations" ) as $l ) { // phpcs:ignore
				$locs[ (int) $l->id ] = $l;
			}
		}
		$by_event = array();
		foreach ( $periods as $per ) {
			$by_event[ (int) $per->eventId ][] = $per;
		}
		$tags_by = array();
		foreach ( $tags as $t ) {
			$tags_by[ (int) $t->eventId ][] = array(
				'id'   => null,
				'name' => Appp_Events_Util::decode( $t->name ),
				'slug' => sanitize_title( $t->name ),
			);
		}
		$parents = array();
		foreach ( $events as $e ) {
			if ( ! empty( $e->parentId ) ) {
				$parents[ (int) $e->parentId ] = true;
			}
		}

		$this->stats = array(
			'records'           => count( $events ),
			'missing_start'     => 0,
			'missing_start_ids' => array(),
		);
		$tz          = $this->settings_tz();
		$utc         = new DateTimeZone( 'UTC' );
		$items       = array();
		$seen        = array();
		foreach ( $events as $e ) {
			$eid    = (int) $e->id;
			$series = ! empty( $e->parentId ) ? (int) $e->parentId : $eid;
			$pers   = isset( $by_event[ $eid ] ) ? $by_event[ $eid ] : array();
			if ( ! $pers ) {
				++$this->stats['missing_start'];
				$this->stats['missing_start_ids'][] = $eid;
				continue;
			}
			$is_series = ! empty( $e->parentId ) || isset( $parents[ $eid ] ) || count( $pers ) > 1;
			if ( ! $this->expand && $is_series && isset( $seen[ $series ] ) ) {
				continue;
			}

			$venue = array();
			if ( ! empty( $e->locationId ) && isset( $locs[ (int) $e->locationId ] ) ) {
				$l     = $locs[ (int) $e->locationId ];
				$venue = Appp_Events_Util::parse_address( $l->address );
				if ( ! empty( $l->name ) ) {
					$venue['name'] = $l->name;
				}
				$venue['lat'] = isset( $l->latitude ) ? $l->latitude : null;
				$venue['lng'] = isset( $l->longitude ) ? $l->longitude : null;
			} elseif ( ! empty( $e->customLocation ) ) {
				$venue = Appp_Events_Util::parse_address( $e->customLocation );
			}
			foreach ( array( 'name', 'address', 'city', 'state', 'zip', 'country', 'lat', 'lng' ) as $part ) {
				$venue[ $part ] = $this->override( 'venue_' . $part, isset( $venue[ $part ] ) ? $venue[ $part ] : null );
			}

			$desc  = (string) $e->description;
			$image = ! empty( $e->pictureFullPath ) ? Appp_Events_Util::image_from_value( $e->pictureFullPath ) : null;
			$img_o = $this->override( 'image', $image );
			if ( is_string( $img_o ) ) {
				$img_o = Appp_Events_Util::image_from_value( $img_o );
			}
			$price = ( null !== $e->price && '' !== $e->price ) ? ( (float) $e->price > 0 ? (string) ( 0 + $e->price ) : '0' ) : null;
			$link  = $this->override( 'link', null );
			$cats  = $this->override( 'categories', isset( $tags_by[ $eid ] ) ? $tags_by[ $eid ] : ( isset( $tags_by[ $series ] ) ? $tags_by[ $series ] : array() ) );
			$base  = array(
				'title'       => (string) $this->override( 'title', $e->name ),
				'link'        => is_string( $link ) ? $link : null,
				'image'       => $img_o,
				'excerpt'     => (string) $this->override( 'excerpt', Appp_Events_Util::excerpt_from( $desc ) ),
				'description' => (string) $this->override( 'description', '' !== trim( $desc ) ? wpautop( wp_kses_post( $desc ) ) : '' ),
				'venue'       => $venue,
				'ticket_url'  => $this->override( 'ticket_url', null ),
				'cost'        => $this->override( 'cost', $price ),
				'canceled'    => Appp_Events_Util::to_bool( $this->override( 'canceled', in_array( $e->status, array( 'canceled', 'rejected' ), true ) ? '1' : '' ) ),
				'featured'    => Appp_Events_Util::to_bool( $this->override( 'featured', '' ) ),
				'categories'  => $this->terms_out( $cats ),
				'protected'   => false,
			);

			foreach ( $pers as $i => $per ) {
				try {
					$s = ( new DateTimeImmutable( $per->periodStart, $utc ) )->setTimezone( $tz );
					$x = ( new DateTimeImmutable( $per->periodEnd, $utc ) )->setTimezone( $tz );
				} catch ( Exception $ex ) {
					continue;
				}
				$p                     = $base;
				$p['start']            = $s;
				$p['end']              = $x;
				$p['id']               = count( $pers ) > 1 ? $eid . '_' . (int) $per->id : $eid;
				$p['series_id']        = $series;
				$p['is_occurrence']    = $is_series;
				$p['occurrence_index'] = null;
				$ad                    = $this->override( 'all_day', null );
				$p['all_day']          = null === $ad ? false : Appp_Events_Util::to_bool( $ad );
				$built                 = $this->filter_event( $this->build_item( $p ), null );
				if ( null !== $built ) {
					$items[] = $built;
				}
				if ( ! $this->expand && $is_series ) {
					break;
				}
			}
			$seen[ $series ] = true;
		}

		// Number occurrences inside each series by start time.
		$groups = array();
		foreach ( $items as $k => $it ) {
			if ( $it['is_occurrence'] ) {
				$groups[ $it['series_id'] ][ $k ] = $it['_start_ts'];
			}
		}
		foreach ( $groups as $g ) {
			asort( $g );
			$n = 0;
			foreach ( array_keys( $g ) as $k ) {
				$items[ $k ]['occurrence_index'] = ++$n;
			}
		}
		return $items;
	}
}
