<?php
/**
 * Built in preset: Very Simple Event List (VS Event List) by Guido, tested with 20.4.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'        => 'very-simple-event-list',
	'label'     => 'Very Simple Event List',
	'post_type' => 'event',
	'detect'    => array(
		'post_type' => 'event',
		'plugin'    => 'very-simple-event-list/vsel.php',
		'function'  => 'vsel_ical_feed',
	),
	'notes'     => array(
		__( 'Dates are Unix timestamps of the local wall time (U_local). event-date is the end date.', 'apppresser-events' ),
		__( 'No canceled flag in the plugin: a title that starts with "CANCELED:" (or "Cancelled:") marks the event canceled.', 'apppresser-events' ),
		__( 'The location is free text over several lines and is split into name, street, city, state and zip.', 'apppresser-events' ),
		__( 'No cost, featured, timezone or map coordinates in the plugin.', 'apppresser-events' ),
	),
	'fields'    => array(
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
			'key'    => 'event-start-date',
			'format' => 'U_local',
		),
		'start_time'    => array( 'source' => 'none' ),
		'end_date'      => array(
			'source' => 'meta',
			'key'    => 'event-date',
			'format' => 'U_local',
		),
		'end_time'      => array( 'source' => 'none' ),
		'timezone'      => array( 'source' => 'none' ),
		'all_day'       => array(
			'source' => 'meta',
			'key'    => 'event-all-day',
		),
		'venue_name'    => array( 'source' => 'none' ),
		'venue_text'    => array(
			'source' => 'meta',
			'key'    => 'event-location',
		),
		'venue_address' => array( 'source' => 'none' ),
		'venue_city'    => array( 'source' => 'none' ),
		'venue_state'   => array( 'source' => 'none' ),
		'venue_zip'     => array( 'source' => 'none' ),
		'venue_country' => array( 'source' => 'none' ),
		'venue_lat'     => array( 'source' => 'none' ),
		'venue_lng'     => array( 'source' => 'none' ),
		'ticket_url'    => array(
			'source' => 'meta',
			'key'    => 'event-link',
		),
		'cost'          => array( 'source' => 'none' ),
		'canceled'      => array(
			'source' => 'post_field',
			'key'    => 'post_title',
			'match'  => '/^\s*cancell?ed\s*:/i',
		),
		'cancel_reason' => array( 'source' => 'none' ),
		'featured'      => array( 'source' => 'none' ),
		'categories'    => array(
			'source' => 'taxonomy',
			'key'    => 'event_cat',
		),
	),
);
