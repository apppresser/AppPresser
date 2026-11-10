<?php
/**
 * WP Events Manager by ThimPress (tp_event). Date and time are stored apart, there is
 * no timezone (site timezone is used), location is free text, and there is no cancel
 * status, so the preset treats a "CANCELED:" title prefix as canceled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Adapter_ThimPress extends Appp_Events_Adapter_Posts {

	public function id() {
		return 'wp-events-manager';
	}

	public function label() {
		return 'WP Events Manager (ThimPress)';
	}

	public function detect() {
		return post_type_exists( 'tp_event' ) || defined( 'WPEMS_VER' );
	}

	public function version() {
		return defined( 'WPEMS_VER' ) ? WPEMS_VER : '';
	}

	public function default_post_type() {
		return 'tp_event';
	}

	public function notes() {
		return array(
			__( 'No recurrence, featured flag, timezone or cancel status in this plugin. All day is guessed from 00:00 to 23:59.', 'apppresser-events' ),
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
				'key'    => 'tp_event_date_start',
				'format' => 'Y-m-d',
			),
			'start_time'    => array(
				'source' => 'meta',
				'key'    => 'tp_event_time_start',
				'format' => 'H:i',
			),
			'end_date'      => array(
				'source' => 'meta',
				'key'    => 'tp_event_date_end',
				'format' => 'Y-m-d',
			),
			'end_time'      => array(
				'source' => 'meta',
				'key'    => 'tp_event_time_end',
				'format' => 'H:i',
			),
			'timezone'      => array( 'source' => 'none' ),
			'all_day'       => array( 'source' => 'auto' ),
			'venue_name'    => array( 'source' => 'none' ),
			'venue_text'    => array(
				'source' => 'meta',
				'key'    => 'tp_event_location',
			),
			'venue_address' => array( 'source' => 'none' ),
			'venue_city'    => array( 'source' => 'none' ),
			'venue_state'   => array( 'source' => 'none' ),
			'venue_zip'     => array( 'source' => 'none' ),
			'venue_country' => array( 'source' => 'none' ),
			'venue_lat'     => array( 'source' => 'none' ),
			'venue_lng'     => array( 'source' => 'none' ),
			'ticket_url'    => array( 'source' => 'none' ),
			'cost'          => array(
				'source' => 'meta',
				'key'    => 'tp_event_price',
			),
			'canceled'      => array(
				'source' => 'post_field',
				'key'    => 'post_title',
				'match'  => '/^\s*cancell?ed\s*:/i',
			),
			'cancel_reason' => array( 'source' => 'none' ),
			'featured'      => array( 'source' => 'none' ),
			'categories'    => array(
				'source' => 'taxonomy',
				'key'    => 'tp_event_category',
			),
		);
	}
}
