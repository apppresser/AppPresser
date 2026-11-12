<?php
/** Events settings for supported calendar integrations. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Admin {
	const TAB = 'appp-events';
	const PAGE = 'apppresser_settings';

	public static function init() {
		add_filter( 'apppresser_settings_allowed_fields', array( __CLASS__, 'allowed_fields' ), 10, 2 );
		add_action( 'apppresser_add_settings', array( __CLASS__, 'add_settings' ), 30 );
		add_action( 'apppresser_tab_top_' . self::TAB, array( __CLASS__, 'render_detection' ), 10, 2 );
		add_action( 'apppresser_tab_bottom_' . self::TAB, array( __CLASS__, 'render_preview' ), 10, 2 );
		add_action( 'apppresser_tab_buttons_' . self::TAB, array( __CLASS__, 'render_buttons' ), 10, 2 );
		add_filter( 'apppresser_sanitize_setting', array( __CLASS__, 'sanitize_setting' ), 10, 3 );
		add_filter( 'sanitize_option_appp_settings', array( __CLASS__, 'after_validate' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redetect' ) );
	}

	public static function allowed_fields( $allowed, $tab ) {
		if ( self::TAB === $tab ) {
			$allowed += array_fill_keys( Appp_Events_Settings::keys(), true );
		}
		return $allowed;
	}

	public static function tab_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => 'tab-' . self::TAB ), $args ), admin_url( 'admin.php' ) );
	}

	public static function maybe_redetect() {
		if ( empty( $_GET['appp_events_redetect'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'appp_events_redetect' );
		Appp_Events_Cache::flush_detection();
		wp_safe_redirect( self::tab_url() );
		exit;
	}

	public static function add_settings( $appp ) {
		$appp->add_setting_tab( __( 'Events', 'apppresser-events' ), self::TAB );
		$appp->add_setting( 'events_collapse', __( 'Recurring events', 'apppresser-events' ), array(
			'tab' => self::TAB,
			'type' => 'checkbox',
			'description' => __( 'List one item per series instead of one per occurrence (apps can still pass expand=true or false)', 'apppresser-events' ),
		) );
	}

	public static function render_detection( $appp_settings = null, $settings = array() ) {
		$known = Appp_Events_Settings::detected_all();
		$eff = Appp_Events_Settings::effective();
		echo '<tr><td colspan="2" style="padding-left:0"><h3>' . esc_html__( 'App Events', 'apppresser-events' ) . '</h3>';
		echo '<p>' . esc_html__( 'Test endpoint:', 'apppresser-events' ) . ' <a href="' . esc_url( rest_url( 'appp/v1/events' ) ) . '" target="_blank" rel="noopener"><code>' . esc_html( rest_url( 'appp/v1/events' ) ) . '</code></a></p>';
		echo '<h4>' . esc_html__( 'Detected events plugin', 'apppresser-events' ) . '</h4>';
		if ( ! $known ) {
			echo '<p>' . esc_html__( 'No supported events plugin is active. Activate a supported plugin to show events in your app.', 'apppresser-events' ) . '</p>';
		} elseif ( 1 === count( $known ) ) {
			echo '<p><strong>' . esc_html( $known[0]->label() ) . '</strong></p>';
		} else {
			echo '<p>' . esc_html__( 'More than one supported events plugin is active. Choose the one your app uses:', 'apppresser-events' ) . '</p>';
			foreach ( $known as $adapter ) {
				printf( '<p><label><input type="radio" name="appp_settings[events_source]" value="%s" %s> %s</label></p>', esc_attr( $adapter->id() ), checked( $eff['target'], $adapter->id(), false ), esc_html( $adapter->label() ) );
			}
		}
		if ( count( $known ) < 2 ) {
			echo '<input type="hidden" name="appp_settings[events_source]" value="auto">';
		}
		echo '<h4>' . esc_html__( 'Supported plugins', 'apppresser-events' ) . '</h4><ul>';
		foreach ( Appp_Events_Settings::adapters() as $adapter ) {
			echo '<li>' . esc_html( $adapter->label() ) . '</li>';
		}
		echo '</ul><p>' . esc_html__( 'Need another events calendar plugin? Contact AppPresser support to request a native integration.', 'apppresser-events' ) . ' <a href="https://apppresser.com/support/" target="_blank" rel="noopener">' . esc_html__( 'Contact support', 'apppresser-events' ) . '</a></p></td></tr>';
	}

	public static function render_preview( $appp_settings = null, $settings = array() ) {
		if ( ! Appp_Events_Engine::eff()['adapter'] ) {
			return;
		}
		echo '<tr><td colspan="2" style="padding-left:0"><style>.appp-ev-card{display:flex;gap:12px;border:1px solid #dcdcde;padding:10px;margin-bottom:10px;max-width:640px}.appp-ev-card img{width:96px;height:72px;object-fit:cover}.appp-ev-card h4{margin:0 0 4px}.appp-ev-warn{color:#8a2424}</style><h3>' . esc_html__( 'Preview', 'apppresser-events' ) . '</h3>';
		echo self::preview_html( self::preview_data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</td></tr>';
	}

	public static function render_buttons( $appp_settings = null, $settings = array() ) {
		echo ' <a class="button-secondary" href="' . esc_url( wp_nonce_url( self::tab_url( array( 'appp_events_redetect' => 1 ) ), 'appp_events_redetect' ) ) . '">' . esc_html__( 'Re-detect plugins', 'apppresser-events' ) . '</a>';
	}

	public static function sanitize_setting( $filtered, $key, $value ) {
		return 'events_source' === $key ? Appp_Events_Settings::clean_source( $value ) : $filtered;
	}

	/** Retired settings never affect feeds and are removed on the next settings save. */
	public static function after_validate( $value ) {
		if ( is_array( $value ) ) {
			unset( $value['events_post_type'], $value['events_field_map'], $value['events_import'], $value['events_reset'] );
			if ( isset( $value['events_source'] ) ) {
				$value['events_source'] = Appp_Events_Settings::clean_source( $value['events_source'] );
			}
		}
		return $value;
	}

	/** Items, totals and warnings for the preview, from the engine's current settings. */
	public static function preview_data() {
		$all   = Appp_Events_Engine::all_items();
		$eff   = Appp_Events_Engine::eff();
		$stats = $eff['adapter']->stats();
		$res   = Appp_Events_Engine::query( array( 'per_page' => 3 ) );
		$warn  = array();
		$recs  = isset( $stats['records'] ) ? (int) $stats['records'] : 0;
		$miss  = isset( $stats['missing_start'] ) ? (int) $stats['missing_start'] : 0;
		if ( $miss ) {
			/* translators: 1 missing 2 total */
			$warn[] = sprintf( __( 'Start date missing on %1$d of %2$d events (they are left out).', 'apppresser-events' ), $miss, $recs );
		}
		if ( ! $all ) {
			$warn[] = $recs ? __( 'No events could be read from this plugin.', 'apppresser-events' ) : __( 'No published events found for this source.', 'apppresser-events' );
		} elseif ( ! is_wp_error( $res ) && 0 === $res['total'] ) {
			$warn[] = __( 'All events are in the past.', 'apppresser-events' );
		}
		$no_title = 0;
		$no_venue = 0;
		$no_img   = 0;
		foreach ( $all as $it ) {
			$no_title += '' === $it['title'] ? 1 : 0;
			$no_venue += null === $it['venue'] ? 1 : 0;
			$no_img   += null === $it['image'] ? 1 : 0;
		}
		if ( $no_title ) {
			/* translators: 1 count 2 total */
			$warn[] = sprintf( __( 'Title empty on %1$d of %2$d items.', 'apppresser-events' ), $no_title, count( $all ) );
		}
		if ( $all && count( $all ) === $no_venue ) {
			$warn[] = __( 'No venue on any item.', 'apppresser-events' );
		}
		if ( $all && count( $all ) === $no_img ) {
			$warn[] = __( 'No image on any item.', 'apppresser-events' );
		}
		return array(
			'items'    => is_wp_error( $res ) ? array() : $res['items'],
			'upcoming' => is_wp_error( $res ) ? 0 : $res['total'],
			'all'      => count( $all ),
			'records'  => $recs,
			'warnings' => $warn,
			'source'   => $eff['adapter']->source_slug(),
		);
	}

	public static function preview_html( $d ) {
		ob_start();
		/* translators: 1: source records read, 2: items built, 3: upcoming items, 4: source id */
		echo '<p>' . esc_html( sprintf( __( '%1$d source records read, %2$d items built, %3$d upcoming. Source: %4$s.', 'apppresser-events' ), $d['records'], $d['all'], $d['upcoming'], $d['source'] ) ) . '</p>';
		foreach ( $d['warnings'] as $w ) {
			echo '<p class="appp-ev-warn">&#9888; ' . esc_html( $w ) . '</p>';
		}
		foreach ( $d['items'] as $it ) {
			$img  = ( $it['image'] && isset( $it['image']['sizes']['thumbnail'] ) ) ? $it['image']['sizes']['thumbnail']['url'] : ( $it['image'] ? $it['image']['sizes']['full']['url'] : '' );
			$when = $it['start'] ? wp_date( $it['all_day'] ? 'D M j, Y' : 'D M j, Y g:i A', strtotime( $it['start']['utc'] ), new DateTimeZone( $it['start']['timezone'] ) ) : '';
			echo '<div class="appp-ev-card">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '<img alt="">' ) . '<div>';
			echo '<h4>' . esc_html( $it['title'] ) . '</h4>';
			echo '<p>' . esc_html( $when . ( $it['all_day'] ? ' ' . __( '(all day)', 'apppresser-events' ) : '' ) ) . '</p>';
			if ( $it['venue'] ) {
				echo '<p>' . esc_html( trim( implode( ', ', array_filter( array( $it['venue']['name'], $it['venue']['city'], $it['venue']['state'] ) ) ) ) ) . '</p>';
			}
			$flags = array();
			if ( $it['status']['canceled'] ) {
				$flags[] = __( 'Canceled', 'apppresser-events' );
			}
			if ( $it['status']['featured'] ) {
				$flags[] = __( 'Featured', 'apppresser-events' );
			}
			if ( $it['protected'] ) {
				$flags[] = __( 'Password protected', 'apppresser-events' );
			}
			if ( $it['is_occurrence'] ) {
				$flags[] = __( 'Recurring', 'apppresser-events' );
			}
			if ( $flags ) {
				echo '<p><em>' . esc_html( implode( ', ', $flags ) ) . '</em></p>';
			}
			echo '</div></div>';
		}
		echo '<details><summary>' . esc_html__( 'JSON', 'apppresser-events' ) . '</summary><pre style="max-width:1200px;max-height:420px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #dcdcde;white-space:pre-wrap">' . esc_html( wp_json_encode( $d['items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre></details>';
		return ob_get_clean();
	}

}
