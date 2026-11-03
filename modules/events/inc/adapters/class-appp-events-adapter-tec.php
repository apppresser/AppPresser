<?php
/**
 * The Events Calendar (tribe_events). Free stores one occurrence per post; when
 * Events Calendar Pro adds more rows to tec_occurrences, they are expanded.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Adapter_TEC extends Appp_Events_Adapter_Posts {

	public function id() {
		return 'the-events-calendar';
	}

	public function label() {
		return 'The Events Calendar';
	}

	public function detect() {
		return class_exists( 'Tribe__Events__Main' ) || post_type_exists( 'tribe_events' );
	}

	public function version() {
		return class_exists( 'Tribe__Events__Main' ) ? Tribe__Events__Main::VERSION : '';
	}

	public function default_post_type() {
		return 'tribe_events';
	}

	public function notes() {
		return array( __( 'Recurring events (Events Calendar Pro) are expanded from the tec_occurrences table when a post has more than one occurrence row.', 'apppresser-events' ) );
	}

	public function preset_fields() {
		$v = '_EventVenueID>';
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
				'key'    => '_EventStartDate',
				'format' => 'Y-m-d H:i:s',
			),
			'start_time'    => array( 'source' => 'none' ),
			'end_date'      => array(
				'source' => 'meta',
				'key'    => '_EventEndDate',
				'format' => 'Y-m-d H:i:s',
			),
			'end_time'      => array( 'source' => 'none' ),
			'timezone'      => array(
				'source' => 'meta',
				'key'    => '_EventTimezone',
			),
			'all_day'       => array(
				'source' => 'meta',
				'key'    => '_EventAllDay',
			),
			'venue_name'    => array(
				'source' => 'related_meta',
				'key'    => $v . 'post_title',
			),
			'venue_text'    => array( 'source' => 'none' ),
			'venue_address' => array(
				'source' => 'related_meta',
				'key'    => $v . '_VenueAddress',
			),
			'venue_city'    => array(
				'source' => 'related_meta',
				'key'    => $v . '_VenueCity',
			),
			'venue_state'   => array(
				'source' => 'related_meta',
				'key'    => $v . '_VenueStateProvince|_VenueState|_VenueProvince',
			),
			'venue_zip'     => array(
				'source' => 'related_meta',
				'key'    => $v . '_VenueZip',
			),
			'venue_country' => array(
				'source' => 'related_meta',
				'key'    => $v . '_VenueCountry',
			),
			'venue_lat'     => array(
				'source' => 'related_meta',
				'key'    => $v . '_VenueLat',
			),
			'venue_lng'     => array(
				'source' => 'related_meta',
				'key'    => $v . '_VenueLng',
			),
			'ticket_url'    => array(
				'source' => 'meta',
				'key'    => '_EventURL',
			),
			'cost'          => array(
				'source' => 'meta',
				'key'    => '_EventCost',
			),
			'canceled'      => array(
				'source' => 'meta',
				'key'    => '_tribe_events_status',
				'match'  => 'canceled',
			),
			'cancel_reason' => array(
				'source' => 'meta',
				'key'    => '_tribe_events_status_reason',
			),
			'featured'      => array(
				'source' => 'meta',
				'key'    => '_tribe_featured',
			),
			'categories'    => array(
				'source' => 'taxonomy',
				'key'    => 'tribe_events_cat',
			),
		);
	}

	/** Occurrence rows for the posts being built, keyed by post ID (one query). */
	private $occ_rows = null;

	protected function watch_post_types() {
		return array( 'tribe_venue', 'tribe_organizer' );
	}

	protected function prime( array $posts ) {
		global $wpdb;
		parent::prime( $posts );
		$this->occ_rows = null;
		$table          = $wpdb->prefix . 'tec_occurrences';
		if ( ! $posts || ! Appp_Events_Util::table_exists( $table ) ) {
			return;
		}
		$this->occ_rows = array();
		foreach ( array_chunk( wp_list_pluck( $posts, 'ID' ), 500 ) as $chunk ) {
			$rows = $wpdb->get_results( "SELECT post_id, start_date, end_date FROM {$table} WHERE post_id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ') ORDER BY post_id ASC, start_date ASC' ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				$this->occ_rows[ (int) $r->post_id ][] = $r;
			}
		}
	}

	protected function occurrences( WP_Post $post, $start, $end, $tz ) {
		global $wpdb;
		$table = $wpdb->prefix . 'tec_occurrences';
		if ( null !== $this->occ_rows || Appp_Events_Util::table_exists( $table ) ) {
			if ( null !== $this->occ_rows ) {
				$rows = isset( $this->occ_rows[ $post->ID ] ) ? $this->occ_rows[ $post->ID ] : array();
			} else {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT start_date, end_date FROM {$table} WHERE post_id = %d ORDER BY start_date ASC", $post->ID ) ); // phpcs:ignore
			}
			if ( count( $rows ) > 1 ) {
				$out = array();
				foreach ( $rows as $i => $r ) {
					$s = Appp_Events_Util::parse_dt( $r->start_date, 'Y-m-d H:i:s', $tz );
					if ( $s ) {
						$out[] = array(
							'start'      => $s,
							'end'        => Appp_Events_Util::parse_dt( $r->end_date, 'Y-m-d H:i:s', $tz ),
							'occurrence' => true,
							'index'      => $i + 1,
						);
					}
				}
				if ( $out ) {
					return $out;
				}
			}
		}
		return parent::occurrences( $post, $start, $end, $tz );
	}
}
