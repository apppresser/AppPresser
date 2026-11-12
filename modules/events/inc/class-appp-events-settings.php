<?php
/** Supported events integrations and per-site preferences. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Settings {

	/** @return Appp_Events_Adapter[] keyed by id */
	public static function adapters() {
		static $list = null;
		if ( null === $list ) {
			$list = array();
			foreach ( array( 'Appp_Events_Adapter_TEC', 'Appp_Events_Adapter_EM', 'Appp_Events_Adapter_Amelia', 'Appp_Events_Adapter_ThimPress', 'Appp_Events_Adapter_WPEM' ) as $cls ) {
				$a                = new $cls();
				$list[ $a->id() ] = $a;
			}
			// Built-in data integrations, including Very Simple Event List.
			foreach ( Appp_Events_Presets::all( array_keys( $list ) ) as $id => $p ) {
				$list[ $id ] = new Appp_Events_Adapter_Preset( $p );
			}
		}
		return $list;
	}

	/**
	 * First active events plugin on this site, or null. The result is kept in
	 * appp_events_state until a plugin is activated, deactivated or updated, the theme
	 * changes, or the active plugin list changes in any other way.
	 */
	public static function detected() {
		$list = self::adapters();
		$id   = Appp_Events_Cache::detected_id();
		if ( null !== $id ) {
			if ( '' === $id ) {
				return null;
			}
			if ( isset( $list[ $id ] ) && $list[ $id ]->detect() ) {
				return $list[ $id ];
			}
		}
		foreach ( $list as $a ) {
			if ( $a->detect() ) {
				Appp_Events_Cache::remember_detected( $a->id() );
				return $a;
			}
		}
		Appp_Events_Cache::remember_detected( '' );
		return null;
	}

	/** All active events plugins (a site could run more than one). */
	public static function detected_all() {
		$out = array();
		foreach ( self::adapters() as $a ) {
			if ( $a->detect() ) {
				$out[] = $a;
			}
		}
		return $out;
	}

	/** Read one key from the shared appp_settings option. */
	public static function core( $key ) {
		if ( function_exists( 'appp_get_setting' ) ) {
			return appp_get_setting( $key );
		}
		$all = get_option( 'appp_settings', array() );
		return is_array( $all ) && isset( $all[ $key ] ) ? $all[ $key ] : false;
	}

	/** Settings in the internal shape, built from appp_settings. */
	public static function stored() {
		$arr = array();
		foreach ( self::keys() as $k ) {
			$arr[ $k ] = self::core( $k );
		}
		return self::from_array( $arr );
	}

	/** Read only supported source selection and the recurring-events preference. */
	public static function from_array( array $arr ) {
		$source = isset( $arr['events_source'] ) ? self::clean_source( $arr['events_source'] ) : 'auto';
		$out = array( 'expand' => ! ( isset( $arr['events_collapse'] ) && 'on' === $arr['events_collapse'] ) );
		if ( 'auto' !== $source ) {
			$out['preset'] = $source;
		}
		return $out;
	}

	public static function saved_source() {
		$s = self::stored();
		return isset( $s['preset'] ) ? $s['preset'] : 'auto';
	}

	/** Unsupported and legacy sources return to automatic detection. */
	public static function clean_source( $value ) {
		return is_string( $value ) && isset( self::adapters()[ $value ] ) ? $value : 'auto';
	}

	/** Configure an active supported integration using only its built-in fields. */
	public static function effective( $override = null ) {
		$s = null === $override ? self::stored() : $override;
		$adapters = self::adapters();
		$adapter = ! empty( $s['preset'] ) && isset( $adapters[ $s['preset'] ] ) && $adapters[ $s['preset'] ]->detect()
			? $adapters[ $s['preset'] ] : self::detected();
		$fields = $adapter ? $adapter->preset_fields() : array();
		$post_type = $adapter ? $adapter->default_post_type() : '';
		$target = $adapter ? $adapter->id() : '';
		if ( $adapter ) {
			$adapter->configure( $fields, $post_type, true );
		}
		return array(
			'mapping_version' => substr( md5( wp_json_encode( array( $target, $post_type, $fields ) ) ), 0, 10 ),
			'adapter' => $adapter,
			'mode' => 'preset',
			'target' => $target,
			'preset' => $target,
			'fields' => $fields,
			'preset_fields' => $fields,
			'post_type' => $post_type,
			'expand' => isset( $s['expand'] ) ? (bool) $s['expand'] : true,
		);
	}

	/** Keys this plugin owns inside appp_settings. */
	public static function keys() {
		return array( 'events_source', 'events_collapse' );
	}
}
