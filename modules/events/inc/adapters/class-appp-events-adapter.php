<?php
/**
 * Base adapter. An adapter knows one events plugin: how to detect it, its preset
 * mapping, and how to turn its records into normalized event items.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Appp_Events_Adapter {

	/** Effective mapping (field key => config). */
	protected $fields = array();

	/** Post type for post based adapters. */
	protected $post_type = '';

	/** Counters from the last items() run, for preview warnings. */
	protected $stats = array();

	/** Expand recurring events into one item per occurrence. */
	protected $expand = true;

	abstract public function id();

	abstract public function label();

	/** True when the plugin is active on the current site. */
	abstract public function detect();

	/** Preset mapping for this plugin. */
	abstract public function preset_fields();

	/** Build all normalized items (unfiltered, unsorted). */
	abstract public function items();

	public function stats() {
		return $this->stats;
	}

	/** Value of the "source" field in the REST output. */
	public function source_slug() {
		return $this->id();
	}

	public function version() {
		return '';
	}

	public function default_post_type() {
		return '';
	}

	/** Source types the mapping screen offers for this adapter. Null means all. */
	public function allowed_sources() {
		return null;
	}

	/** Short notes for the settings screen about what the adapter handles itself. */
	public function notes() {
		return array();
	}

	/**
	 * Run appp_events_event on one built item. Return false (or anything that is not an
	 * array) from the filter to drop the item. Dates changed by the filter are re-read.
	 *
	 * @param array        $item Normalized item (with internal _start_ts and _end_ts).
	 * @param WP_Post|null $post Source post, null for table based plugins (Amelia).
	 * @return array|null
	 */
	protected function filter_event( array $item, $post = null ) {
		$before = array( $item['start'], $item['end'] );
		$item   = apply_filters( 'appp_events_event', $item, $post, $this->id() );
		if ( ! is_array( $item ) || empty( $item['start']['utc'] ) ) {
			return null;
		}
		if ( array( $item['start'], $item['end'] ) !== $before ) {
			$item['_start_ts'] = strtotime( $item['start']['utc'] );
			$item['_end_ts']   = ! empty( $item['end']['utc'] ) ? strtotime( $item['end']['utc'] ) : $item['_start_ts'];
		}
		return $item;
	}

	public function configure( array $fields, $post_type, $expand ) {
		$this->fields    = $fields;
		$this->post_type = $post_type ? $post_type : $this->default_post_type();
		$this->expand    = (bool) $expand;
	}

	public function get_post_type() {
		return $this->post_type;
	}

	protected function cfg( $key ) {
		return isset( $this->fields[ $key ] ) ? $this->fields[ $key ] : array( 'source' => 'none' );
	}

	protected function source_of( $key ) {
		$c = $this->cfg( $key );
		return isset( $c['source'] ) ? $c['source'] : 'none';
	}

	/**
	 * For adapters whose values are computed (Amelia), let the site owner still force a
	 * static value or switch a field off.
	 */
	protected function override( $key, $computed ) {
		$c = $this->cfg( $key );
		$s = isset( $c['source'] ) ? $c['source'] : 'auto';
		if ( 'none' === $s ) {
			return null;
		}
		if ( 'static' === $s ) {
			return isset( $c['key'] ) && '' !== $c['key'] ? $c['key'] : null;
		}
		return $computed;
	}

	/**
	 * Assemble one item in the public shape. $p holds plain values; DateTimes for start/end.
	 */
	protected function build_item( array $p ) {
		$start = $p['start'];
		$end   = isset( $p['end'] ) && $p['end'] ? $p['end'] : $start;
		if ( $end < $start ) {
			$end = $start;
		}
		$all_day = ! empty( $p['all_day'] );
		if ( $all_day ) {
			$start = $start->setTime( 0, 0, 0 );
			$end   = $end->setTime( 23, 59, 59 );
		}
		$protected = ! empty( $p['protected'] );
		$venue     = $this->venue_out( isset( $p['venue'] ) ? $p['venue'] : array() );
		$ticket    = isset( $p['ticket_url'] ) ? trim( (string) $p['ticket_url'] ) : '';
		if ( '' !== $ticket && ! preg_match( '#^https?://#i', $ticket ) ) {
			$ticket = '';
		}
		$cost = isset( $p['cost'] ) && null !== $p['cost'] ? trim( Appp_Events_Util::decode( (string) $p['cost'] ) ) : '';

		$item = array(
			'id'               => $p['id'],
			'series_id'        => isset( $p['series_id'] ) ? $p['series_id'] : $p['id'],
			'is_occurrence'    => ! empty( $p['is_occurrence'] ),
			'occurrence_index' => isset( $p['occurrence_index'] ) ? $p['occurrence_index'] : null,
			'title'            => trim( Appp_Events_Util::decode( wp_strip_all_tags( (string) $p['title'] ) ) ),
			'link'             => ! empty( $p['link'] ) ? esc_url_raw( $p['link'] ) : null,
			'image'            => isset( $p['image'] ) ? $p['image'] : null,
			'excerpt'          => $protected ? '' : ( isset( $p['excerpt'] ) ? (string) $p['excerpt'] : '' ),
			'description'      => $protected ? '' : ( isset( $p['description'] ) ? (string) $p['description'] : '' ),
			'start'            => Appp_Events_Util::date_out( $start ),
			'end'              => Appp_Events_Util::date_out( $end ),
			'all_day'          => $all_day,
			'venue'            => $venue,
			'ticket_url'       => ( $protected || '' === $ticket ) ? null : esc_url_raw( $ticket ),
			'cost'             => '' === $cost ? null : $cost,
			'status'           => array(
				'canceled' => ! empty( $p['canceled'] ),
				'featured' => ! empty( $p['featured'] ),
				'reason'   => ! empty( $p['canceled'] ) && ! empty( $p['cancel_reason'] ) ? (string) $p['cancel_reason'] : null,
			),
			'categories'       => isset( $p['categories'] ) ? array_values( $p['categories'] ) : array(),
			'source'           => $this->source_slug(),
			'protected'        => $protected,
			'_start_ts'        => $start->getTimestamp(),
			'_end_ts'          => $end->getTimestamp(),
		);
		return $item;
	}

	protected function venue_out( array $v ) {
		$keys = array( 'name', 'address', 'city', 'state', 'zip', 'country', 'lat', 'lng' );
		$out  = array();
		$any  = false;
		foreach ( $keys as $k ) {
			$val = isset( $v[ $k ] ) ? $v[ $k ] : null;
			if ( in_array( $k, array( 'lat', 'lng' ), true ) ) {
				$val = ( null === $val || '' === $val || ! is_numeric( $val ) || 0.0 === (float) $val ) ? null : (float) $val;
			} else {
				$val = ( null === $val ) ? null : trim( Appp_Events_Util::decode( wp_strip_all_tags( (string) $val ) ) );
				if ( '' === $val ) {
					$val = null;
				}
			}
			if ( null !== $val ) {
				$any = true;
			}
			$out[ $k ] = $val;
		}
		return $any ? $out : null;
	}

	/** Turn categories from a mapped value into a list of {id,name,slug}. */
	protected function terms_out( $raw ) {
		if ( is_array( $raw ) && ( empty( $raw ) || is_array( reset( $raw ) ) ) ) {
			return $raw;
		}
		if ( null === $raw || '' === $raw ) {
			return array();
		}
		$out = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $raw ) ), 'strlen' ) as $name ) {
			$name  = Appp_Events_Util::decode( $name );
			$out[] = array(
				'id'   => null,
				'name' => $name,
				'slug' => sanitize_title( $name ),
			);
		}
		return $out;
	}
}
