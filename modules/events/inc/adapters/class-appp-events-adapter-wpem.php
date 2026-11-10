<?php
/**
 * WP Event Manager (event_listing). The start and end meta already hold date and time.
 * The editor defaults _event_timezone to the first zone in its list, so the preset uses
 * the site timezone instead. Venue posts have no address meta in the free plugin, so
 * the address comes from the one line _event_location text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Adapter_WPEM extends Appp_Events_Adapter_Posts {

	public function id() {
		return 'wp-event-manager';
	}

	public function label() {
		return 'WP Event Manager';
	}

	public function detect() {
		return post_type_exists( 'event_listing' ) || defined( 'EVENT_MANAGER_VERSION' );
	}

	public function version() {
		return defined( 'EVENT_MANAGER_VERSION' ) ? EVENT_MANAGER_VERSION : '';
	}

	public function default_post_type() {
		return 'event_listing';
	}

	public function notes() {
		return array(
			__( 'No recurrence or all day flag in the free plugin (recurring is a paid add-on). All day is guessed from 00:00 to 23:59.', 'apppresser-events' ),
			__( 'Timezone uses the site timezone because _event_timezone often holds the editor default. Switch it to the meta key if your events set it on purpose.', 'apppresser-events' ),
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
				'key'    => '_event_start_date',
				'format' => 'Y-m-d H:i:s',
			),
			'start_time'    => array( 'source' => 'none' ),
			'end_date'      => array(
				'source' => 'meta',
				'key'    => '_event_end_date',
				'format' => 'Y-m-d H:i:s',
			),
			'end_time'      => array( 'source' => 'none' ),
			'timezone'      => array( 'source' => 'none' ),
			'all_day'       => array( 'source' => 'auto' ),
			'venue_name'    => array(
				'source' => 'related_meta',
				'key'    => '_event_venue_ids>post_title',
			),
			'venue_text'    => array(
				'source' => 'meta',
				'key'    => '_event_location',
			),
			'venue_address' => array( 'source' => 'none' ),
			'venue_city'    => array( 'source' => 'none' ),
			'venue_state'   => array( 'source' => 'none' ),
			'venue_zip'     => array(
				'source' => 'meta',
				'key'    => '_event_pincode',
			),
			'venue_country' => array(
				'source' => 'meta',
				'key'    => '_event_country',
			),
			'venue_lat'     => array( 'source' => 'none' ),
			'venue_lng'     => array( 'source' => 'none' ),
			'ticket_url'    => array(
				'source' => 'meta',
				'key'    => '_registration',
			),
			'cost'          => array(
				'source' => 'meta',
				'key'    => '_event_ticket_price',
			),
			'canceled'      => array(
				'source' => 'meta',
				'key'    => '_cancelled',
			),
			'cancel_reason' => array( 'source' => 'none' ),
			'featured'      => array(
				'source' => 'meta',
				'key'    => '_featured',
			),
			'categories'    => array(
				'source' => 'taxonomy',
				'key'    => 'event_listing_category',
			),
		);
	}
}
