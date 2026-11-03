<?php
/**
 * Per site settings: which adapter, which post type, and the field mapping.
 *
 * Stored inside the core AppPresser option (appp_settings), Events tab:
 *   events_source     auto | adapter or preset id | guess:<post type> | plain | manual
 *   events_post_type  post type for plain (guessed mapping) and manual (blank mapping)
 *   events_collapse   "on" to list one item per recurring series by default
 *   events_field_map  array: _target => adapter id or manual, plus
 *                     field key => {source, key, format, match} overrides
 * With nothing saved, the detected plugin's preset is used as is. If core AppPresser
 * is not active the presets still work; only the settings screen is missing.
 */

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
			// Data presets: built in (Very Simple Event List) and appp_events_presets.
			foreach ( Appp_Events_Presets::all( array_keys( $list ) ) as $id => $p ) {
				$list[ $id ] = new Appp_Events_Adapter_Preset( $p );
			}
			$list = apply_filters( 'appp_events_adapters', $list );
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

	/** Internal shape from an appp_settings style array (saved, or posted for preview). */
	public static function from_array( array $arr ) {
		$out    = array();
		$source = isset( $arr['events_source'] ) ? (string) $arr['events_source'] : '';
		if ( 'manual' === $source ) {
			$out['mode'] = 'manual';
		} elseif ( 'plain' === $source ) {
			$out['mode'] = 'plain';
		} elseif ( 0 === strpos( $source, 'guess:' ) ) {
			$out['mode']  = 'guess';
			$out['guess'] = sanitize_key( substr( $source, 6 ) );
		} elseif ( '' !== $source && 'auto' !== $source ) {
			$out['mode']   = 'preset';
			$out['preset'] = sanitize_key( $source );
		}
		$pt = isset( $arr['events_post_type'] ) ? (string) $arr['events_post_type'] : '';
		if ( '' !== $pt && 'auto' !== $pt ) {
			$out['post_type'] = sanitize_key( $pt );
		}
		$out['expand'] = ! ( isset( $arr['events_collapse'] ) && 'on' === $arr['events_collapse'] );
		$map           = isset( $arr['events_field_map'] ) ? $arr['events_field_map'] : null;
		if ( is_array( $map ) && ! empty( $map['_target'] ) ) {
			$out['map_target'] = (string) $map['_target'];
			unset( $map['_target'] );
			$out['fields'] = $map;
		}
		// 0.1 saved a hand finished guess for post types a preset now covers (for example
		// Very Simple Event List). Read that as the preset, keeping any overrides.
		if ( isset( $out['mode'] ) && 'guess' === $out['mode'] ) {
			foreach ( self::adapters() as $a ) {
				if ( ! ( $a instanceof Appp_Events_Adapter_Guess ) && $a->default_post_type() === $out['guess'] && $a->detect() ) {
					$out['mode']   = 'preset';
					$out['preset'] = $a->id();
					if ( isset( $out['map_target'] ) && 'guess:' . $out['guess'] === $out['map_target'] ) {
						$out['map_target'] = $a->id();
					}
					$out['migrated_from'] = 'guess:' . $out['guess'];
					unset( $out['guess'] );
					break;
				}
			}
		}
		return $out;
	}

	/** Saved events_source as the screen should show it (after the 0.1 migration). */
	public static function saved_source() {
		$s = self::stored();
		if ( empty( $s['mode'] ) ) {
			return 'auto';
		}
		switch ( $s['mode'] ) {
			case 'manual':
			case 'plain':
				return $s['mode'];
			case 'guess':
				return 'guess:' . $s['guess'];
		}
		return $s['preset'];
	}

	/** Post types offered for plain and manual mode: public ones plus any with an admin UI. */
	public static function selectable_post_types() {
		$skip = array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_family', 'wp_font_face', 'wp_global_styles' );
		$out  = array();
		foreach ( array_merge( get_post_types( array( 'public' => true ), 'objects' ), get_post_types( array( 'show_ui' => true ), 'objects' ) ) as $obj ) {
			if ( ! in_array( $obj->name, $skip, true ) ) {
				$out[ $obj->name ] = $obj;
			}
		}
		return $out;
	}

	/** Normalize an events_source or map target value (keeps the guess: and plain: prefixes). */
	public static function clean_source( $v ) {
		$v = (string) $v;
		foreach ( array( 'guess:', 'plain:', 'manual:' ) as $pre ) {
			if ( 0 === strpos( $v, $pre ) ) {
				return $pre . sanitize_key( substr( $v, strlen( $pre ) ) );
			}
		}
		return sanitize_key( $v );
	}

	/**
	 * The adapter to use, configured with the effective mapping. Null if nothing is set up.
	 *
	 * @return array {adapter, mode, preset, fields, post_type, expand, preset_fields}
	 */
	public static function effective( $override = null ) {
		$s        = null === $override ? self::stored() : $override;
		$adapters = self::adapters();
		$mode     = isset( $s['mode'] ) && in_array( $s['mode'], array( 'manual', 'guess', 'plain' ), true ) ? $s['mode'] : 'preset';
		$expand   = isset( $s['expand'] ) ? (bool) $s['expand'] : true;
		$adapter  = null;
		$auto     = empty( $s['mode'] );

		if ( 'plain' === $mode ) {
			if ( ! empty( $s['post_type'] ) && post_type_exists( $s['post_type'] ) ) {
				$adapter = new Appp_Events_Adapter_Plain( $s['post_type'] );
			} else {
				$mode = 'manual';
			}
		}
		if ( 'guess' === $mode ) {
			if ( ! empty( $s['guess'] ) && post_type_exists( $s['guess'] ) ) {
				$adapter = new Appp_Events_Adapter_Guess( $s['guess'] );
			} else {
				$mode = 'manual';
			}
		}
		if ( 'preset' === $mode ) {
			if ( ! empty( $s['preset'] ) && isset( $adapters[ $s['preset'] ] ) ) {
				$adapter = $adapters[ $s['preset'] ];
			} else {
				$adapter = self::detected();
			}
			if ( ! $adapter && $auto ) {
				// Nothing known: use the best unknown candidate, if any.
				$cands = Appp_Events_Heuristic::candidates();
				if ( $cands ) {
					$adapter = new Appp_Events_Adapter_Guess( key( $cands ) );
					$mode    = 'guess';
				}
			}
			if ( ! $adapter ) {
				$mode = 'manual';
			}
		}
		if ( 'manual' === $mode ) {
			$adapter = new Appp_Events_Adapter_Posts();
		}

		$preset_fields = $adapter->preset_fields();
		$fields        = $preset_fields;
		$stored_fields = isset( $s['fields'] ) && is_array( $s['fields'] ) ? $s['fields'] : array();
		if ( isset( $s['override_fields'] ) ) {
			$stored_fields = $s['override_fields'];
		}
		$target = 'manual' === $mode ? 'manual' : $adapter->id();
		if ( isset( $s['override_fields'] ) ) {
			$s['fields']     = $s['override_fields'];
			$s['map_target'] = $target;
		}
		$same_target = isset( $s['map_target'] ) && $s['map_target'] === $target;
		if ( $same_target ) {
			foreach ( $stored_fields as $k => $cfg ) {
				if ( isset( $fields[ $k ] ) ) {
					$fields[ $k ] = Appp_Events_Fields::sanitize_field( $cfg );
				}
			}
		}
		$post_type = ! empty( $s['post_type'] ) && in_array( $mode, array( 'manual', 'plain' ), true ) ? sanitize_key( $s['post_type'] ) : $adapter->default_post_type();
		$adapter->configure( $fields, $post_type, true );
		$version = substr( md5( wp_json_encode( array( $target, $post_type, $fields ) ) ), 0, 10 );

		return array(
			'mapping_version' => $version,
			'adapter'         => $adapter,
			'mode'            => $mode,
			'target'          => $target,
			'preset'          => $adapter->id(),
			'fields'          => $fields,
			'preset_fields'   => $preset_fields,
			'post_type'       => $post_type,
			'expand'          => $expand,
		);
	}

	/** Keys this plugin owns inside appp_settings. */
	public static function keys() {
		return array( 'events_source', 'events_post_type', 'events_collapse', 'events_field_map' );
	}
}
