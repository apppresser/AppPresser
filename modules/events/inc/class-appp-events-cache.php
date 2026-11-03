<?php
/**
 * Feed cache and cached detection.
 *
 * The full normalized item list for a site is built once and stored, then every feed
 * request (any page, window, category or expand setting) filters that list in memory.
 *
 * Storage: the object cache when a persistent one is installed (Redis, Memcached),
 * otherwise one transient per site (appp_events_items). The key holds the plugin
 * version, the saved Events settings, the active plugin list and a counter. The
 * counter lives in the small autoloaded option appp_events_state and goes up when:
 *   an event post (or a venue, organizer or location post) is saved, trashed,
 *   restored or deleted, or its meta changes;
 *   a term in a mapped taxonomy changes or is set on a post;
 *   the AppPresser settings are saved;
 *   any plugin is activated, deactivated or updated, or the theme changes.
 * Amelia keeps events in its own tables with no save hooks we can rely on, so its
 * cache also expires after 10 minutes. Everything else expires after 12 hours.
 *
 * Filters:
 *   appp_events_cache_ttl   (int seconds, string source id)  0 turns the cache off.
 *   appp_events_cache_control (string header, WP_REST_Request) Cache-Control for
 *                            logged out feed requests, default "public, max-age=60".
 * Function: appp_events_flush_cache() drops the cache for the current site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Cache {

	const OPT   = 'appp_events_state';
	const KEY   = 'appp_events_items';
	const GROUP = 'appp_events';

	/** True once the counter went up in this request and nothing was cached since. */
	private static $bumped = false;

	public static function state() {
		$s = get_option( self::OPT );
		return is_array( $s ) ? $s : array();
	}

	private static function save_state( array $s ) {
		update_option( self::OPT, $s, true );
	}

	/** Invalidate every cached feed for this site. */
	public static function bump() {
		if ( self::$bumped ) {
			return;
		}
		self::$bumped = true;
		$s            = self::state();
		$s['v']       = isset( $s['v'] ) ? (int) $s['v'] + 1 : 1;
		self::save_state( $s );
		if ( ! wp_using_ext_object_cache() ) {
			delete_transient( self::KEY );
		}
	}

	/** Plugins or theme changed: forget the detected plugin and the feed. */
	public static function flush_detection() {
		$s = self::state();
		unset( $s['det'] );
		$s['v'] = isset( $s['v'] ) ? (int) $s['v'] + 1 : 1;
		self::save_state( $s );
		self::$bumped = true;
		if ( ! wp_using_ext_object_cache() ) {
			delete_transient( self::KEY );
		}
	}

	/** Signature of everything that can change which plugin is detected. */
	public static function plugin_sig() {
		$net = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		return substr( md5( APPP_EVENTS_VERSION . '|' . wp_json_encode( (array) get_option( 'active_plugins', array() ) ) . '|' . wp_json_encode( array_keys( $net ) ) . '|' . get_stylesheet() ), 0, 12 );
	}

	/** Detected adapter id from the cache, or null when unknown or stale. */
	public static function detected_id() {
		$s = self::state();
		if ( isset( $s['det'] ) && is_array( $s['det'] ) && isset( $s['det']['sig'] ) && self::plugin_sig() === $s['det']['sig'] ) {
			return (string) $s['det']['id'];
		}
		return null;
	}

	public static function remember_detected( $id ) {
		$s   = self::state();
		$det = array(
			'sig' => self::plugin_sig(),
			'id'  => (string) $id,
		);
		if ( isset( $s['det'] ) && $s['det'] === $det ) {
			return;
		}
		$s['det'] = $det;
		self::save_state( $s );
	}

	/** Cache key for the saved settings and current plugin set. */
	public static function key() {
		$s   = self::state();
		$raw = array();
		foreach ( Appp_Events_Settings::keys() as $k ) {
			$raw[ $k ] = Appp_Events_Settings::core( $k );
		}
		return md5( wp_json_encode( array( APPP_EVENTS_VERSION, get_current_blog_id(), isset( $s['v'] ) ? (int) $s['v'] : 0, $raw, self::plugin_sig() ) ) );
	}

	/** @return array|null {items, mapping_version, source, expand} */
	public static function get( $key ) {
		if ( wp_using_ext_object_cache() ) {
			$hit = wp_cache_get( $key, self::GROUP );
			return is_array( $hit ) ? $hit : null;
		}
		$hit = get_transient( self::KEY );
		if ( is_array( $hit ) && isset( $hit['k'], $hit['d'] ) && $hit['k'] === $key ) {
			return $hit['d'];
		}
		return null;
	}

	/**
	 * Store a freshly built list, and remember which post types and taxonomies feed it
	 * so saves elsewhere on the site never touch the cache.
	 */
	public static function set( $key, array $data, array $pts, array $tax, $ttl ) {
		$s   = self::state();
		$pts = array_values( array_unique( array_filter( $pts ) ) );
		$tax = array_values( array_unique( array_filter( $tax ) ) );
		sort( $pts );
		sort( $tax );
		if ( ! isset( $s['pts'] ) || $s['pts'] !== $pts || ! isset( $s['tax'] ) || $s['tax'] !== $tax ) {
			$s['pts'] = $pts;
			$s['tax'] = $tax;
			if ( ! isset( $s['v'] ) ) {
				$s['v'] = 0;
			}
			self::save_state( $s );
		}
		self::$bumped = false;
		if ( $ttl <= 0 ) {
			return;
		}
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $data, self::GROUP, $ttl );
			return;
		}
		set_transient(
			self::KEY,
			array(
				'k' => $key,
				'd' => $data,
			),
			$ttl
		);
	}

	public static function ttl( $source ) {
		$ttl = 0 === strpos( (string) $source, 'ameliabooking' ) ? 10 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS;
		return (int) apply_filters( 'appp_events_cache_ttl', $ttl, (string) $source );
	}
}
