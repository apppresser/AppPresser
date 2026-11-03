<?php
/**
 * Preset registry: built in presets written as data (Very Simple Event List) plus
 * presets that site code registers with the appp_events_presets filter.
 *
 * Preset format (the same one "Export as preset" writes):
 *
 *   array(
 *     'id'        => 'my-events',            // unique, lowercase, a-z 0-9 - _
 *     'label'     => 'My Events',            // shown in the Events tab
 *     'post_type' => 'my_event',             // post type that holds the events
 *     'detect'    => array(                  // when is this preset "active"?
 *       'post_type' => 'my_event',           //   this post type must exist, and
 *       'plugin'    => 'my-events/my-events.php', // any one of these signals must
 *       'class'     => 'My_Events',          //   match when at least one is given
 *       'function'  => 'my_events_init',
 *       'constant'  => 'MY_EVENTS_VERSION',
 *     ),
 *     'version'   => '1.2',                  // optional; else read from 'plugin' or 'constant'
 *     'notes'     => array( 'Shown under the mapping table.' ),
 *     'fields'    => array(                  // field key => {source, key, format, match}
 *       'start_date' => array( 'source' => 'meta', 'key' => 'my_start', 'format' => 'Y-m-d H:i' ),
 *       ...                                  // fields left out are "Not used"
 *     ),
 *   )
 *
 * Register with:
 *   add_filter( 'appp_events_presets', function ( $presets ) { $presets['my-events'] = array( ... ); return $presets; } );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Presets {

	const FORMAT = 1;

	/** Problems found while loading presets: id => list of messages. */
	private static $errors = array();

	private static $list = null;

	/** Built in data presets, keyed by id. */
	public static function builtin() {
		$out = array();
		foreach ( glob( APPP_EVENTS_DIR . 'inc/presets/*.php' ) as $file ) {
			$def = include $file;
			if ( is_array( $def ) && ! empty( $def['id'] ) ) {
				$def['origin']     = 'builtin';
				$out[ $def['id'] ] = $def;
			}
		}
		return $out;
	}

	/**
	 * All valid presets (built in first, then the filter), normalized and keyed by id.
	 *
	 * @param string[] $reserved Ids already used by class adapters.
	 */
	public static function all( array $reserved = array() ) {
		if ( null !== self::$list ) {
			return self::$list;
		}
		self::$errors = array();
		$builtin      = self::builtin();
		$filtered     = apply_filters( 'appp_events_presets', $builtin );
		$list         = array();
		if ( ! is_array( $filtered ) ) {
			self::$errors['appp_events_presets'][] = __( 'The filter did not return an array. Built in presets used.', 'apppresser-events' );
			$filtered                              = $builtin;
		}
		foreach ( $filtered as $key => $def ) {
			if ( ! is_array( $def ) ) {
				self::$errors[ (string) $key ][] = __( 'Preset is not an array.', 'apppresser-events' );
				continue;
			}
			if ( empty( $def['id'] ) && is_string( $key ) ) {
				$def['id'] = $key;
			}
			if ( empty( $def['origin'] ) || ! isset( $builtin[ $key ] ) || $builtin[ $key ] !== $def ) {
				$def['origin'] = isset( $builtin[ $key ] ) && $builtin[ $key ] === $def ? 'builtin' : 'filter';
			}
			$errs = array();
			$norm = self::normalize( $def, $errs );
			if ( $norm && in_array( $norm['id'], $reserved, true ) ) {
				$errs[] = __( 'This id belongs to a supported plugin adapter and cannot be replaced by a preset.', 'apppresser-events' );
				$norm   = null;
			}
			if ( $errs ) {
				self::$errors[ $norm ? $norm['id'] : (string) $key ] = $errs;
			}
			if ( $norm ) {
				$list[ $norm['id'] ] = $norm;
			}
		}
		self::$list = $list;
		return $list;
	}

	public static function errors() {
		return self::$errors;
	}

	/** Forget the cached list (tests, or after a filter is added late). */
	public static function reset() {
		self::$list = null;
	}

	/**
	 * Check and clean one preset. Returns null when it cannot be used; $errs gets the
	 * reasons (and warnings for fixed up values).
	 */
	public static function normalize( array $def, array &$errs = array() ) {
		$id = isset( $def['id'] ) ? sanitize_key( (string) $def['id'] ) : '';
		if ( '' === $id || 0 === strpos( $id, 'guess' ) || in_array( $id, array( 'auto', 'manual', 'plain' ), true ) ) {
			$errs[] = __( 'Missing or reserved id.', 'apppresser-events' );
			return null;
		}
		$detect = isset( $def['detect'] ) && is_array( $def['detect'] ) ? $def['detect'] : array();
		$pt     = isset( $def['post_type'] ) ? sanitize_key( (string) $def['post_type'] ) : '';
		if ( '' === $pt && ! empty( $detect['post_type'] ) ) {
			$pt = sanitize_key( (string) $detect['post_type'] );
		}
		if ( '' === $pt ) {
			$errs[] = __( 'Missing post_type.', 'apppresser-events' );
			return null;
		}
		$clean_detect = array( 'post_type' => isset( $detect['post_type'] ) ? sanitize_key( (string) $detect['post_type'] ) : $pt );
		foreach ( array( 'plugin', 'class', 'function', 'constant' ) as $sig ) {
			if ( ! empty( $detect[ $sig ] ) && is_string( $detect[ $sig ] ) ) {
				$clean_detect[ $sig ] = preg_replace( '#[^A-Za-z0-9_\-./\\\\]#', '', $detect[ $sig ] );
			}
		}
		if ( ! isset( $def['fields'] ) || ! is_array( $def['fields'] ) ) {
			$errs[] = __( 'Missing fields.', 'apppresser-events' );
			return null;
		}
		$defs   = Appp_Events_Fields::definitions();
		$fields = array();
		foreach ( array_keys( $defs ) as $k ) {
			$fields[ $k ] = isset( $def['fields'][ $k ] ) ? Appp_Events_Fields::sanitize_field( $def['fields'][ $k ] ) : array( 'source' => 'none' );
		}
		foreach ( array_keys( $def['fields'] ) as $k ) {
			if ( ! isset( $defs[ $k ] ) ) {
				/* translators: %s field key */
				$errs[] = sprintf( __( 'Unknown field %s ignored.', 'apppresser-events' ), $k );
			}
		}
		if ( 'none' === $fields['start_date']['source'] ) {
			$errs[] = __( 'start_date must be mapped.', 'apppresser-events' );
			return null;
		}
		$notes = array();
		if ( ! empty( $def['notes'] ) && is_array( $def['notes'] ) ) {
			foreach ( $def['notes'] as $n ) {
				if ( is_string( $n ) && '' !== trim( $n ) ) {
					$notes[] = sanitize_text_field( $n );
				}
			}
		}
		return array(
			'id'        => $id,
			'label'     => isset( $def['label'] ) && '' !== trim( (string) $def['label'] ) ? sanitize_text_field( (string) $def['label'] ) : $id,
			'post_type' => $pt,
			'detect'    => $clean_detect,
			'version'   => isset( $def['version'] ) ? sanitize_text_field( (string) $def['version'] ) : '',
			'notes'     => $notes,
			'fields'    => $fields,
			'origin'    => isset( $def['origin'] ) && 'builtin' === $def['origin'] ? 'builtin' : 'filter',
		);
	}

	/** True when a plugin file (folder/file.php) is active on this site or network wide. */
	public static function plugin_active( $file ) {
		$file = (string) $file;
		if ( '' === $file ) {
			return false;
		}
		if ( in_array( $file, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}
		if ( is_multisite() ) {
			$net = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $net[ $file ] );
		}
		return false;
	}

	/** Version from a plugin header, cached per request. */
	public static function plugin_version( $file ) {
		static $cache = array();
		if ( ! isset( $cache[ $file ] ) ) {
			$cache[ $file ] = '';
			$path           = WP_PLUGIN_DIR . '/' . $file;
			if ( '' !== $file && false === strpos( $file, '..' ) && is_readable( $path ) ) {
				$data           = get_file_data( $path, array( 'Version' => 'Version' ) );
				$cache[ $file ] = isset( $data['Version'] ) ? (string) $data['Version'] : '';
			}
		}
		return $cache[ $file ];
	}

	/** Detection rule check. */
	public static function detect( array $p ) {
		$d = $p['detect'];
		if ( ! post_type_exists( $d['post_type'] ) ) {
			return false;
		}
		$signals = array_intersect_key( $d, array_flip( array( 'plugin', 'class', 'function', 'constant' ) ) );
		if ( ! $signals ) {
			return true;
		}
		if ( ! empty( $signals['plugin'] ) && self::plugin_active( $signals['plugin'] ) ) {
			return true;
		}
		if ( ! empty( $signals['class'] ) && class_exists( $signals['class'], false ) ) {
			return true;
		}
		if ( ! empty( $signals['function'] ) && function_exists( $signals['function'] ) ) {
			return true;
		}
		if ( ! empty( $signals['constant'] ) && defined( $signals['constant'] ) ) {
			return true;
		}
		return false;
	}

	/** Human readable detection rule. */
	public static function describe_detect( array $d ) {
		/* translators: %s post type */
		$out  = array( sprintf( __( 'post type %s exists', 'apppresser-events' ), $d['post_type'] ) );
		$sigs = array();
		foreach ( array( 'plugin', 'class', 'function', 'constant' ) as $s ) {
			if ( ! empty( $d[ $s ] ) ) {
				$sigs[] = $s . ' ' . $d[ $s ];
			}
		}
		if ( $sigs ) {
			/* translators: %s list of signals */
			$out[] = sprintf( __( 'and one of: %s', 'apppresser-events' ), implode( '; ', $sigs ) );
		}
		return implode( ' ', $out );
	}

	/**
	 * A preset array built from the mapping now in effect, ready to be shipped as a
	 * built in preset or registered with the filter.
	 */
	public static function from_effective( array $eff ) {
		$adapter = $eff['adapter'];
		$pt      = $eff['post_type'];
		$obj     = $pt ? get_post_type_object( $pt ) : null;
		$detect  = array( 'post_type' => $pt );
		$id      = '';
		$label   = '';
		$notes   = array();
		if ( $adapter instanceof Appp_Events_Adapter_Preset ) {
			$p      = $adapter->preset();
			$id     = $p['id'];
			$label  = $p['label'];
			$detect = $p['detect'];
			$notes  = $p['notes'];
		}
		if ( '' === $id ) {
			$id    = sanitize_key( str_replace( '_', '-', $pt ) );
			$label = $obj ? $obj->labels->name : $pt;
		}
		$fields = array();
		foreach ( $eff['fields'] as $k => $cfg ) {
			$fields[ $k ] = Appp_Events_Fields::sanitize_field( $cfg );
		}
		$plugins = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $f ) {
			$plugins[] = $f . ( self::plugin_version( $f ) ? ' ' . self::plugin_version( $f ) : '' );
		}
		return array(
			'appp_events_preset' => self::FORMAT,
			'id'                 => $id,
			'label'              => $label,
			'post_type'          => $pt,
			'detect'             => $detect,
			'notes'              => $notes,
			'fields'             => $fields,
			'_info'              => array(
				'exported_from'   => home_url( '/' ),
				'exported_at'     => gmdate( 'c' ),
				'appp_events'     => APPP_EVENTS_VERSION,
				'source'          => $adapter->source_slug(),
				'mapping_version' => $eff['mapping_version'],
				'active_plugins'  => $plugins,
			),
		);
	}

	public static function to_json( array $preset ) {
		return wp_json_encode( $preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/** A small mu-plugin that registers the preset with the filter. */
	public static function to_php( array $preset ) {
		$info = isset( $preset['_info'] ) ? $preset['_info'] : array();
		unset( $preset['_info'], $preset['appp_events_preset'] );
		$hint = '';
		if ( empty( $preset['detect']['plugin'] ) && empty( $preset['detect']['class'] ) && empty( $preset['detect']['function'] ) && empty( $preset['detect']['constant'] ) ) {
			$hint = " * Detection: only the post type is checked. Add 'plugin', 'class', 'function' or\n * 'constant' to 'detect' so the preset only turns on with the right plugin.\n";
			if ( ! empty( $info['active_plugins'] ) ) {
				$hint .= " * Active plugins on the exporting site:\n";
				foreach ( $info['active_plugins'] as $p ) {
					$hint .= ' *   ' . str_replace( '*/', '', $p ) . "\n";
				}
			}
		}
		$var = self::php_value( $preset, 2 );
		$id  = var_export( (string) $preset['id'], true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return "<?php\n/**\n * Plugin Name: AppEvents preset: " . str_replace( '*/', '', $preset['label'] ) . "\n"
			. ' * Description: Registers the ' . str_replace( '*/', '', $preset['id'] ) . " preset with the appp_events_presets filter.\n"
			. ( $info ? ' * Exported from ' . str_replace( '*/', '', (string) $info['exported_from'] ) . ' on ' . $info['exported_at'] . ' (AppEvents ' . $info['appp_events'] . ', mapping_version ' . $info['mapping_version'] . ").\n" : '' )
			. $hint
			. " */\n\nadd_filter(\n\t'appp_events_presets',\n\tfunction ( \$presets ) {\n\t\t\$presets[" . $id . '] = ' . $var . ";\n\t\treturn \$presets;\n\t}\n);\n";
	}

	private static function php_value( $v, $depth ) {
		if ( is_array( $v ) ) {
			if ( ! $v ) {
				return 'array()';
			}
			$pad   = str_repeat( "\t", $depth + 1 );
			$list  = array_keys( $v ) === range( 0, count( $v ) - 1 );
			$short = true;
			foreach ( $v as $x ) {
				if ( is_array( $x ) ) {
					$short = false;
				}
			}
			if ( $short && ! $list && count( $v ) <= 3 ) {
				$parts = array();
				foreach ( $v as $k => $x ) {
					$parts[] = var_export( (string) $k, true ) . ' => ' . self::php_value( $x, $depth + 1 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				}
				return 'array( ' . implode( ', ', $parts ) . ' )';
			}
			$out = "array(\n";
			foreach ( $v as $k => $x ) {
				$out .= $pad . ( $list ? '' : var_export( (string) $k, true ) . ' => ' ) . self::php_value( $x, $depth + 1 ) . ",\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			return $out . str_repeat( "\t", $depth ) . ')';
		}
		return var_export( $v, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
