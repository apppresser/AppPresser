<?php
/**
 * Small helpers: dates, images, text, address parsing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Util {

	/**
	 * Turn a stored timezone value into a DateTimeZone. Falls back to the site timezone.
	 */
	public static function tz( $value = '' ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' !== $value ) {
			if ( preg_match( '/^UTC([+-])(\d{1,2})(?:([.:])(\d{1,2}))?$/i', $value, $m ) ) {
				$mins = 0;
				if ( isset( $m[4] ) ) {
					$mins = ( '.' === $m[3] ) ? (int) round( (float) ( '0.' . $m[4] ) * 60 ) : (int) $m[4];
				}
				$value = sprintf( '%s%02d:%02d', $m[1], (int) $m[2], $mins );
			}
			try {
				return new DateTimeZone( $value );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// fall through to site timezone.
			}
		}
		return wp_timezone();
	}

	/**
	 * Parse a stored date or time value.
	 *
	 * Formats: empty (auto parse), any PHP date format, "U" (Unix timestamp, true UTC),
	 * "U_local" (Unix timestamp holding local wall clock time, as some plugins store it).
	 */
	public static function parse_dt( $value, $format, DateTimeZone $tz ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( null === $value || '' === $value || false === $value ) {
			return null;
		}
		$value  = trim( (string) $value );
		$format = trim( (string) $format );
		if ( '' === $value || '0000-00-00' === substr( $value, 0, 10 ) ) {
			return null;
		}
		try {
			if ( 'U' === $format || ( '' === $format && preg_match( '/^\d{9,11}$/', $value ) ) ) {
				$d = new DateTimeImmutable( '@' . (int) $value );
				return $d->setTimezone( $tz );
			}
			if ( 'U_local' === $format ) {
				return new DateTimeImmutable( gmdate( 'Y-m-d H:i:s', (int) $value ), $tz );
			}
			if ( '' !== $format ) {
				$d = DateTimeImmutable::createFromFormat( '!' . $format, $value, $tz );
				if ( $d instanceof DateTimeImmutable ) {
					return $d;
				}
			}
			return new DateTimeImmutable( $value, $tz );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Put the clock time of $time on the calendar day of $date.
	 */
	public static function combine( $date, $time ) {
		if ( ! $date ) {
			return null;
		}
		if ( ! $time ) {
			return $date;
		}
		return $date->setTime( (int) $time->format( 'H' ), (int) $time->format( 'i' ), (int) $time->format( 's' ) );
	}

	/**
	 * Date object for the API: local ISO with offset, UTC ISO, timezone name.
	 */
	public static function date_out( $dt ) {
		if ( ! $dt ) {
			return null;
		}
		$utc = $dt->setTimezone( new DateTimeZone( 'UTC' ) );
		return array(
			'local'    => $dt->format( 'Y-m-d\TH:i:sP' ),
			'utc'      => $utc->format( 'Y-m-d\TH:i:s\Z' ),
			'timezone' => $dt->getTimezone()->getName(),
		);
	}

	public static function to_bool( $value, $expected = '' ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		$value    = is_bool( $value ) ? ( $value ? '1' : '' ) : trim( (string) $value );
		$expected = (string) $expected;
		if ( '' !== $expected ) {
			if ( strlen( $expected ) > 2 && '/' === $expected[0] && preg_match( '#^/.*/[imsux]*$#s', $expected ) ) {
				$r = @preg_match( $expected, $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return 1 === $r;
			}
			return strtolower( $value ) === strtolower( $expected );
		}
		$v = strtolower( $value );
		if ( in_array( $v, array( '', '0', 'no', 'false', 'off', 'null' ), true ) ) {
			return false;
		}
		return true;
	}

	public static function plain( $html ) {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $html ), true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	public static function excerpt_from( $html, $words = 40 ) {
		$text = self::plain( excerpt_remove_blocks( (string) $html ) );
		return wp_trim_words( $text, $words, '...' );
	}

	/**
	 * HTML for the description without running the full the_content filter, so other
	 * plugins (Events Manager adds its own event block there) do not inject markup.
	 */
	public static function render_html( $content ) {
		$content = (string) $content;
		if ( function_exists( 'do_blocks' ) ) {
			$content = do_blocks( $content );
		}
		$content = wptexturize( $content );
		$content = wpautop( $content );
		$content = shortcode_unautop( $content );
		$content = do_shortcode( $content );
		if ( function_exists( 'wp_filter_content_tags' ) ) {
			$content = wp_filter_content_tags( $content );
		}
		return trim( $content );
	}

	public static function image_from_attachment( $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return null;
		}
		$sizes = array();
		foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ) as $size ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( $src ) {
				$sizes[ $size ] = array(
					'url'    => $src[0],
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
				);
			}
		}
		if ( ! $sizes ) {
			return null;
		}
		return array(
			'id'    => $id,
			'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'sizes' => $sizes,
		);
	}

	/**
	 * Image from an attachment ID, a URL, or an array holding either.
	 */
	public static function image_from_value( $value ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( is_numeric( $value ) ) {
			return self::image_from_attachment( (int) $value );
		}
		$url = esc_url_raw( (string) $value );
		if ( '' === $url ) {
			return null;
		}
		$id = attachment_url_to_postid( $url );
		if ( ! $id ) {
			$id = attachment_url_to_postid( preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $url ) );
		}
		if ( $id ) {
			$img = self::image_from_attachment( $id );
			if ( $img ) {
				return $img;
			}
		}
		return array(
			'id'    => null,
			'alt'   => '',
			'sizes' => array(
				'full' => array(
					'url'    => $url,
					'width'  => null,
					'height' => null,
				),
			),
		);
	}

	/**
	 * Parse a one line address such as
	 * "Riverside Community Hall, 120 Water Street, Lancaster, PA 17602".
	 */
	public static function parse_address( $text ) {
		$out  = array(
			'name'    => '',
			'address' => '',
			'city'    => '',
			'state'   => '',
			'zip'     => '',
			'country' => '',
		);
		$text = trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $text ) {
			return $out;
		}
		// Multi line addresses (one part per line) are treated like comma separated ones.
		$text  = preg_replace( '/\s*[\r\n]+\s*/', ', ', $text );
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $text ) ), 'strlen' ) );
		$last  = end( $parts );
		if ( count( $parts ) > 1 && preg_match( '/^(united states|usa|us|canada|united kingdom|uk)$/i', $last ) ) {
			$out['country'] = $last;
			array_pop( $parts );
			$last = end( $parts );
		}
		if ( count( $parts ) > 1 && preg_match( '/^([A-Za-z][A-Za-z .]{1,30}?)\s+([A-Za-z0-9]{3,5}(?:[ -][A-Za-z0-9]{3,4})?)$/', $last, $m ) && preg_match( '/\d/', $m[2] ) ) {
			$out['state'] = trim( $m[1] );
			$out['zip']   = trim( $m[2] );
			array_pop( $parts );
		} elseif ( count( $parts ) > 1 && preg_match( '/^\d{4,6}$/', $last ) ) {
			$out['zip'] = $last;
			array_pop( $parts );
		}
		$addr_i = null;
		foreach ( $parts as $i => $p ) {
			if ( preg_match( '/^\d/', $p ) ) {
				$addr_i = $i;
				break;
			}
		}
		if ( null !== $addr_i ) {
			$out['name']    = implode( ', ', array_slice( $parts, 0, $addr_i ) );
			$out['address'] = $parts[ $addr_i ];
			$out['city']    = implode( ', ', array_slice( $parts, $addr_i + 1 ) );
		} elseif ( count( $parts ) >= 3 ) {
			$out['name']    = $parts[0];
			$out['address'] = $parts[1];
			$out['city']    = implode( ', ', array_slice( $parts, 2 ) );
		} elseif ( count( $parts ) === 2 ) {
			$out['name'] = $parts[0];
			$out['city'] = $parts[1];
		} else {
			$out['name'] = $parts[0];
		}
		return $out;
	}

	public static function decode( $s ) {
		return html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	public static function table_exists( $table ) {
		global $wpdb;
		static $cache = array();
		if ( ! isset( $cache[ $table ] ) ) {
			$cache[ $table ] = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table );
		}
		return $cache[ $table ];
	}
}
