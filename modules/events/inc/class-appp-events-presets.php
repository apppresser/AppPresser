<?php
/** Built-in data integrations maintained by AppPresser. */

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
	 * All built-in integrations, normalized and keyed by id.
	 *
	 * @param string[] $reserved Ids already used by class adapters.
	 */
	public static function all( array $reserved = array() ) {
		if ( null !== self::$list ) {
			return self::$list;
		}
		self::$errors = array();
		$filtered = self::builtin();
		$list = array();
		foreach ( $filtered as $key => $def ) {
			if ( ! is_array( $def ) ) {
				self::$errors[ (string) $key ][] = __( 'Preset is not an array.', 'apppresser-events' );
				continue;
			}
			if ( empty( $def['id'] ) && is_string( $key ) ) {
				$def['id'] = $key;
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

	/** Forget the cached list for tests. */
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

}
