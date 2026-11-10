<?php
/**
 * Events tab on the core AppPresser settings page. Settings live in appp_settings and
 * are saved by the core form, like the AppPush and Bridge tabs. No menu page of its own.
 *
 * Layout: detection (top hook), the Recurring events checkbox (core field), then the
 * field mapping, preview, export and import (bottom hook), and the reset and re-detect
 * buttons (buttons hook).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_Admin {

	const TAB  = 'appp-events';
	const PAGE = 'apppresser_settings';

	public static function init() {
		add_filter( 'apppresser_settings_allowed_fields', array( __CLASS__, 'allowed_fields' ), 10, 2 );
		add_action( 'apppresser_add_settings', array( __CLASS__, 'add_settings' ), 30 );
		add_action( 'apppresser_tab_top_' . self::TAB, array( __CLASS__, 'render_detection' ), 10, 2 );
		add_action( 'apppresser_tab_bottom_' . self::TAB, array( __CLASS__, 'render_mapping' ), 10, 2 );
		add_action( 'apppresser_tab_buttons_' . self::TAB, array( __CLASS__, 'render_buttons' ), 10, 2 );
		// Core 4.5.3 ignores a non null return from apppresser_sanitize_setting_{key} (its
		// else branch puts the raw value back), so the generic fallback filter is used.
		add_filter( 'apppresser_sanitize_setting', array( __CLASS__, 'sanitize_setting' ), 10, 3 );
		// Runs after core settings_validate (priority 10): reset, import, stale maps.
		add_filter( 'sanitize_option_appp_settings', array( __CLASS__, 'after_validate' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redetect' ) );
		add_action( 'wp_ajax_appp_events_preview', array( __CLASS__, 'ajax_preview' ) );
	}

	/** Include the custom mapping controls in core's scoped form validation. */
	public static function allowed_fields( $allowed, $tab ) {
		if ( self::TAB === $tab ) {
			$allowed += array_fill_keys( array_merge( Appp_Events_Settings::keys(), array( 'events_reset', 'events_import' ) ), true );
		}
		return $allowed;
	}

	public static function core_active() {
		return function_exists( 'appp_get_setting' ) && class_exists( 'AppPresser_Admin_Settings' );
	}

	public static function tab_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE,
					'tab'  => 'tab-' . self::TAB,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! self::core_active() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'AppEvents needs the AppPresser plugin for its settings (the Events tab). The events route still works with the detected preset.', 'apppresser-events' ) . '</p></div>';
			return;
		}
		$src = (string) Appp_Events_Settings::core( 'events_source' );
		if ( '' === $src || 'auto' === $src || 'manual' === $src ) {
			return;
		}
		$missing = '';
		if ( 0 === strpos( $src, 'guess:' ) ) {
			if ( ! post_type_exists( substr( $src, 6 ) ) ) {
				$missing = substr( $src, 6 );
			}
		} else {
			// Checking a preset loads every adapter, so only do it where a plugin change
			// is noticed: the Dashboard, the Plugins screen and the AppPresser settings.
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || ! ( in_array( $screen->base, array( 'dashboard', 'plugins' ), true ) || false !== strpos( (string) $screen->id, self::PAGE ) ) ) {
				return;
			}
			$adapters = Appp_Events_Settings::adapters();
			if ( isset( $adapters[ $src ] ) && ! $adapters[ $src ]->detect() ) {
				$missing = $adapters[ $src ]->label();
			}
		}
		if ( $missing ) {
			$eff = Appp_Events_Settings::effective();
			echo '<div class="notice notice-error"><p>' . esc_html(
				sprintf(
					/* translators: 1 missing plugin, 2 fallback */
					__( 'AppEvents: the selected event source (%1$s) is no longer active. The app events route now uses: %2$s.', 'apppresser-events' ),
					$missing,
					$eff['adapter']->label()
				)
			) . ' <a href="' . esc_url( self::tab_url() ) . '">' . esc_html__( 'Open the Events tab', 'apppresser-events' ) . '</a></p></div>';
		}
	}

	public static function maybe_redetect() {
		if ( empty( $_GET['appp_events_redetect'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'appp_events_redetect' );
		Appp_Events_Heuristic::flush();
		Appp_Events_Cache::flush_detection();
		wp_safe_redirect( self::tab_url( array( 'appp_events_msg' => 'redetected' ) ) );
		exit;
	}

	/** Tab, section title and the one simple core field. $appp is AppPresser_Admin_Settings. */
	public static function add_settings( $appp ) {
		$appp->add_setting_tab( __( 'Events', 'apppresser-events' ), self::TAB );
		$appp->add_setting(
			'events_collapse',
			__( 'Recurring events', 'apppresser-events' ),
			array(
				'tab'         => self::TAB,
				'type'        => 'checkbox',
				'description' => __( 'List one item per series instead of one per occurrence (apps can still pass expand=true or false)', 'apppresser-events' ),
			)
		);
	}

	private static function label_for_cand( $pt, $c ) {
		/* translators: 1 label 2 post type 3 reason 4 sample count */
		return sprintf( __( 'Unknown plugin: %1$s (%2$s), %3$s, %4$d posts sampled', 'apppresser-events' ), $c['label'], $pt, $c['reason'], $c['sampled'] );
	}

	/** "Built in preset" or "Developer preset" badge for a known adapter. */
	private static function origin_badge( $a ) {
		if ( $a instanceof Appp_Events_Adapter_Preset ) {
			return 'builtin' === $a->origin()
				? array( 'preset', __( 'built in preset', 'apppresser-events' ) )
				: array( 'dev', __( 'developer preset (appp_events_presets)', 'apppresser-events' ) );
		}
		return array( 'preset', __( 'supported plugin', 'apppresser-events' ) );
	}

	private static function known_label( $a ) {
		return $a->label() . ( $a->version() ? ' ' . $a->version() : '' );
	}

	/** Detection block at the top of the tab. */
	public static function render_detection( $appp_settings = null, $settings = array() ) {
		$known  = Appp_Events_Settings::detected_all();
		$cands  = Appp_Events_Heuristic::candidates();
		$saved  = Appp_Events_Settings::saved_source();
		$eff    = Appp_Events_Settings::effective();
		$cur    = in_array( $eff['mode'], array( 'manual', 'plain' ), true ) ? $eff['mode'] : $eff['target'];
		$name   = 'appp_settings[events_source]';
		$msg    = isset( $_GET['appp_events_msg'] ) ? sanitize_key( $_GET['appp_events_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$radio  = function ( $value, $label, $checked, $badge = null ) use ( $name ) {
			printf(
				'<p><label><input type="radio" class="appp-ev-src" name="%s" value="%s" %s> %s</label>%s</p>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( $checked, true, false ),
				esc_html( $label ),
				$badge ? ' <span class="appp-ev-badge ' . esc_attr( $badge[0] ) . '">' . esc_html( $badge[1] ) . '</span>' : ''
			);
		};
		$others = function ( $is_cur ) use ( $radio, $cands ) {
			foreach ( $cands as $pt => $c ) {
				$radio( 'guess:' . $pt, self::label_for_cand( $pt, $c ), $is_cur( 'guess:' . $pt ), array( 'low', __( 'guess', 'apppresser-events' ) ) );
			}
			$radio( 'plain', __( 'No events plugin: use a post type (fields guessed from its meta keys, for example a custom post type with ACF fields)', 'apppresser-events' ), $is_cur( 'plain' ) );
			$radio( 'manual', __( 'Manual: a post type with a blank mapping', 'apppresser-events' ), $is_cur( 'manual' ) );
		};
		?>
		<tr valign="top"><td colspan="2" style="padding-left:0">
		<style>
			.appp-ev-badge{display:inline-block;padding:1px 7px;border-radius:9px;font-size:11px;line-height:18px;background:#dcdcde;color:#1d2327}
			.appp-ev-badge.preset{background:#d1e4f6}.appp-ev-badge.dev{background:#e2d6f3}.appp-ev-badge.custom{background:#f5e6ab}.appp-ev-badge.high{background:#c6e1c6}.appp-ev-badge.medium{background:#f2e3b3}.appp-ev-badge.low{background:#f6d4d4}.appp-ev-badge.none{background:#f0f0f1;color:#646970}
		</style>
		<?php if ( 'redetected' === $msg ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Detection refreshed.', 'apppresser-events' ); ?></p></div>
		<?php endif; ?>
		<h3 style="margin-top:0"><?php esc_html_e( 'App Events', 'apppresser-events' ); ?></h3>
		<p>
			<?php esc_html_e( 'Test endpoint:', 'apppresser-events' ); ?>
			<a href="<?php echo esc_url( rest_url( 'appp/v1/events' ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( rest_url( 'appp/v1/events' ) ); ?></code></a>
		</p>
		<h4><?php esc_html_e( 'Detected events plugin', 'apppresser-events' ); ?></h4>
		<?php
		if ( 1 === count( $known ) ) {
			$k = $known[0];
			$b = self::origin_badge( $k );
			echo '<p><strong>' . esc_html( self::known_label( $k ) ) . '</strong> <span class="appp-ev-badge ' . esc_attr( $b[0] ) . '">' . esc_html( $b[1] ) . '</span></p>';
			// No choice needed. A hidden value keeps auto detect unless an option below is picked.
			$keep = ( 'auto' === $saved || $saved === $k->id() ) ? 'auto' : $saved;
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $keep ) );
			echo '<details' . ( 'auto' === $keep ? '' : ' open' ) . '><summary>' . esc_html__( 'Use a different source', 'apppresser-events' ) . '</summary>';
			$others(
				function ( $v ) use ( $saved ) {
					return $saved === $v;
				}
			);
			echo '</details>';
		} elseif ( count( $known ) > 1 ) {
			echo '<p>' . esc_html__( 'More than one events plugin is active. Pick the one the app uses:', 'apppresser-events' ) . '</p>';
			foreach ( $known as $k ) {
				$radio( $k->id(), self::known_label( $k ), $cur === $k->id(), self::origin_badge( $k ) );
			}
			$others(
				function ( $v ) use ( $cur ) {
					return $cur === $v;
				}
			);
		} else {
			if ( $cands ) {
				echo '<p>' . esc_html__( 'No supported plugin or preset is active. These post types look like events:', 'apppresser-events' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'No events plugin or event like post type found. Pick a post type below.', 'apppresser-events' ) . '</p>';
			}
			$others(
				function ( $v ) use ( $cur ) {
					return $cur === $v;
				}
			);
		}
		$pt_sel = in_array( $eff['mode'], array( 'manual', 'plain' ), true ) ? $eff['post_type'] : '';
		?>
		<p id="appp-ev-pt-row" style="<?php echo in_array( $cur, array( 'manual', 'plain' ), true ) ? '' : 'display:none'; ?>">
			<label><?php esc_html_e( 'Post type:', 'apppresser-events' ); ?>
			<select name="appp_settings[events_post_type]">
				<option value="auto"><?php esc_html_e( 'Select', 'apppresser-events' ); ?></option>
				<?php foreach ( Appp_Events_Settings::selectable_post_types() as $obj ) : ?>
					<option value="<?php echo esc_attr( $obj->name ); ?>" <?php selected( $pt_sel, $obj->name ); ?>><?php echo esc_html( $obj->labels->singular_name . ' (' . $obj->name . ')' ); ?></option>
				<?php endforeach; ?>
			</select></label>
		</p>
		<p class="description"><?php esc_html_e( 'After changing the source, click Save Settings to load its mapping below.', 'apppresser-events' ); ?></p>
		<?php self::render_preset_list(); ?>
		</td></tr>
		<?php
	}

	/** Presets registered as data (built in and appp_events_presets), with their detection state. */
	private static function render_preset_list() {
		$rows = array();
		foreach ( Appp_Events_Settings::adapters() as $a ) {
			if ( $a instanceof Appp_Events_Adapter_Preset ) {
				$rows[] = $a;
			}
		}
		$errors = Appp_Events_Presets::errors();
		if ( ! $rows && ! $errors ) {
			return;
		}
		echo '<details style="margin-top:8px"><summary>' . esc_html(
			sprintf(
				/* translators: 1 preset count 2 problem count */
				__( 'Data presets: %1$d registered, %2$d with problems', 'apppresser-events' ),
				count( $rows ),
				count( $errors )
			)
		) . '</summary>';
		echo '<table class="widefat striped" style="max-width:1100px;margin-top:6px"><thead><tr><th>' . esc_html__( 'Preset', 'apppresser-events' ) . '</th><th>' . esc_html__( 'From', 'apppresser-events' ) . '</th><th>' . esc_html__( 'Detection rule', 'apppresser-events' ) . '</th><th>' . esc_html__( 'Active here', 'apppresser-events' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $a ) {
			$p = $a->preset();
			$b = self::origin_badge( $a );
			echo '<tr><td><strong>' . esc_html( $p['label'] ) . '</strong><br><code>' . esc_html( $p['id'] ) . '</code></td><td><span class="appp-ev-badge ' . esc_attr( $b[0] ) . '">' . esc_html( $b[1] ) . '</span></td><td>' . esc_html( Appp_Events_Presets::describe_detect( $p['detect'] ) ) . '</td><td>' . ( $a->detect() ? esc_html__( 'yes', 'apppresser-events' ) : esc_html__( 'no', 'apppresser-events' ) ) . '</td></tr>';
		}
		foreach ( $errors as $id => $list ) {
			echo '<tr><td><code>' . esc_html( $id ) . '</code></td><td colspan="3" class="appp-ev-warn">' . esc_html( implode( ' ', $list ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Developers can add presets in code with the appp_events_presets filter. "Export as preset" below writes one in that format.', 'apppresser-events' ) . '</p></details>';
	}

	private static function meta_keys( $post_type ) {
		global $wpdb;
		if ( ! $post_type ) {
			return array();
		}
		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s ORDER BY pm.meta_key LIMIT 300", $post_type ) ); // phpcs:ignore
	}

	/** Field mapping, preview, export and import. */
	public static function render_mapping( $appp_settings = null, $settings = array() ) {
		$eff     = Appp_Events_Settings::effective();
		$adapter = $eff['adapter'];
		$defs    = Appp_Events_Fields::definitions();
		$sources = Appp_Events_Fields::source_types();
		$formats = Appp_Events_Fields::formats();
		$allowed = $adapter->allowed_sources();
		$is_post = ! ( $adapter instanceof Appp_Events_Adapter_Amelia );
		$is_gs   = $adapter instanceof Appp_Events_Adapter_Guess;
		$conf    = $is_gs ? $adapter->confidence() : array();
		$pt      = $eff['post_type'];
		$keys    = $is_post ? self::meta_keys( $pt ) : array();
		$taxes   = $is_post && $pt ? get_object_taxonomies( $pt, 'names' ) : array();
		$name    = 'appp_settings[events_field_map]';
		if ( 'manual' === $eff['mode'] ) {
			$basis = __( 'blank manual mapping', 'apppresser-events' );
		} elseif ( $is_gs ) {
			$basis = __( 'auto mapped guess', 'apppresser-events' );
		} else {
			$basis = __( 'preset', 'apppresser-events' );
		}
		?>
		<tr valign="top"><td colspan="2" style="padding-left:0">
			<style>
				.appp-ev-badge{display:inline-block;padding:1px 7px;border-radius:9px;font-size:11px;line-height:18px;background:#dcdcde;color:#1d2327}
				.appp-ev-badge.preset{background:#d1e4f6}.appp-ev-badge.dev{background:#e2d6f3}.appp-ev-badge.custom{background:#f5e6ab}.appp-ev-badge.high{background:#c6e1c6}.appp-ev-badge.medium{background:#f2e3b3}.appp-ev-badge.low{background:#f6d4d4}.appp-ev-badge.none{background:#f0f0f1;color:#646970}
				.appp-ev-card{display:flex;gap:12px;border:1px solid #dcdcde;background:#fff;padding:10px;margin:0 0 10px;max-width:640px}
				.appp-ev-card img{width:96px;height:72px;object-fit:cover;background:#f0f0f1}
				.appp-ev-card h4{margin:0 0 4px}.appp-ev-card p{margin:2px 0;color:#50575e}
				.appp-ev-warn{color:#8a2424}
				#appp-ev-map td{vertical-align:top}
			</style>
			<h3><?php esc_html_e( 'Field mapping', 'apppresser-events' ); ?></h3>
			<p><strong><?php esc_html_e( 'In use:', 'apppresser-events' ); ?></strong>
				<?php
				echo esc_html(
					'manual' === $eff['mode']
						? sprintf( /* translators: %s post type */ __( 'Manual mapping of post type %s', 'apppresser-events' ), $pt ? $pt : __( '(none chosen yet)', 'apppresser-events' ) )
						: $adapter->label() . ( $adapter->version() ? ' ' . $adapter->version() : '' )
				);
				?>
				&nbsp; <span class="description"><?php echo esc_html( sprintf( /* translators: %s version */ __( 'mapping_version %s', 'apppresser-events' ), $eff['mapping_version'] ) ); ?></span>
			</p>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_target]" value="<?php echo esc_attr( $eff['target'] ); ?>" />
			<?php if ( $adapter->notes() ) : ?>
				<ul style="list-style:disc;margin-left:20px;max-width:1000px">
					<?php foreach ( $adapter->notes() as $n ) : ?>
						<li><?php echo esc_html( $n ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="description" style="max-width:1000px"><?php esc_html_e( 'Key: a post field name, a meta key (pick from the list or type one; use | for fallbacks), a taxonomy, link_key>target_key for meta of a linked post, or the static value. Match (flags): the value that means true, or a /regex/; empty means any value other than empty, 0, no or false.', 'apppresser-events' ); ?></p>
			<div style="max-width:100%;overflow-x:auto">
			<table class="widefat striped" id="appp-ev-map" style="min-width:780px;max-width:1300px">
				<thead><tr>
					<th style="width:19%"><?php esc_html_e( 'Event field', 'apppresser-events' ); ?></th>
					<th style="width:15%"><?php esc_html_e( 'Source', 'apppresser-events' ); ?></th>
					<th><?php esc_html_e( 'Key or value', 'apppresser-events' ); ?></th>
					<th style="width:15%"><?php esc_html_e( 'Format', 'apppresser-events' ); ?></th>
					<th style="width:10%"><?php esc_html_e( 'Match', 'apppresser-events' ); ?></th>
					<th style="width:15%"><?php esc_html_e( 'Status', 'apppresser-events' ); ?></th>
					<th style="width:6%"></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $defs as $k => $def ) : ?>
					<?php
					$cfg   = isset( $eff['fields'][ $k ] ) ? $eff['fields'][ $k ] : array( 'source' => 'none' );
					$pre   = isset( $eff['preset_fields'][ $k ] ) ? $eff['preset_fields'][ $k ] : array( 'source' => 'none' );
					$is_dt = in_array( $def['type'], array( 'date', 'time' ), true );
					$is_b  = 'bool' === $def['type'];
					$fname = $name . '[' . $k . ']';
					$fmt   = isset( $cfg['format'] ) ? $cfg['format'] : '';
					$fsel  = array_key_exists( $fmt, $formats ) ? $fmt : 'custom';
					if ( $is_gs ) {
						$pbadge = 'none' === $pre['source'] ? array( 'none', __( 'not matched', 'apppresser-events' ) ) : array( isset( $conf[ $k ] ) ? $conf[ $k ] : 'low', sprintf( /* translators: %s level */ __( 'guess: %s', 'apppresser-events' ), isset( $conf[ $k ] ) ? $conf[ $k ] : 'low' ) );
					} elseif ( 'manual' === $eff['mode'] ) {
						$pbadge = 'none' === $pre['source'] ? array( 'none', __( 'not mapped', 'apppresser-events' ) ) : array( 'preset', __( 'default', 'apppresser-events' ) );
					} elseif ( $adapter instanceof Appp_Events_Adapter_Preset && 'filter' === $adapter->origin() ) {
						$pbadge = array( 'dev', __( 'developer preset', 'apppresser-events' ) );
					} else {
						$pbadge = array( 'preset', __( 'preset', 'apppresser-events' ) );
					}
					$same = ( Appp_Events_Fields::sanitize_field( $cfg ) == Appp_Events_Fields::sanitize_field( $pre ) ); // phpcs:ignore Universal.Operators.StrictComparisons
					?>
					<tr data-preset="<?php echo esc_attr( wp_json_encode( $pre ) ); ?>" data-badge="<?php echo esc_attr( wp_json_encode( $pbadge ) ); ?>">
						<td><strong><?php echo esc_html( $def['label'] ); ?></strong><br><code style="font-size:11px"><?php echo esc_html( $k ); ?></code>
							<?php
							if ( 'auto' === $pre['source'] && ! empty( $def['auto'] ) ) :
								?>
								<br><span class="description" style="font-size:11px"><?php echo esc_html( $def['auto'] ); ?></span><?php endif; ?>
						</td>
						<td>
							<select class="appp-ev-in" data-f="source" name="<?php echo esc_attr( $fname ); ?>[source]">
								<?php foreach ( $sources as $sk => $sl ) : ?>
									<?php
									if ( null !== $allowed && ! in_array( $sk, $allowed, true ) ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $cfg['source'], $sk ); ?>><?php echo esc_html( $sl ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="text" class="appp-ev-in" data-f="key" style="width:100%" list="appp-events-keys" name="<?php echo esc_attr( $fname ); ?>[key]" value="<?php echo esc_attr( isset( $cfg['key'] ) ? $cfg['key'] : '' ); ?>" /></td>
						<td>
							<?php if ( $is_dt ) : ?>
								<select class="appp-ev-in" data-f="format" name="<?php echo esc_attr( $fname ); ?>[format]">
									<?php foreach ( $formats as $fv => $fl ) : ?>
										<option value="<?php echo esc_attr( $fv ); ?>" <?php selected( $fsel, $fv ); ?>><?php echo esc_html( $fl ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="text" class="appp-ev-in" data-f="format_custom" style="width:100%;margin-top:3px" placeholder="<?php esc_attr_e( 'custom PHP format', 'apppresser-events' ); ?>" name="<?php echo esc_attr( $fname ); ?>[format_custom]" value="<?php echo esc_attr( 'custom' === $fsel ? $fmt : '' ); ?>" />
							<?php endif; ?>
						</td>
						<td>
						<?php
						if ( $is_b ) :
							?>
							<input type="text" class="appp-ev-in" data-f="match" style="width:100%" name="<?php echo esc_attr( $fname ); ?>[match]" value="<?php echo esc_attr( isset( $cfg['match'] ) ? $cfg['match'] : '' ); ?>" placeholder="any" /><?php endif; ?></td>
						<td class="appp-ev-status">
							<?php if ( $same ) : ?>
								<span class="appp-ev-badge <?php echo esc_attr( $pbadge[0] ); ?>"><?php echo esc_html( $pbadge[1] ); ?></span>
							<?php else : ?>
								<span class="appp-ev-badge custom"><?php esc_html_e( 'custom', 'apppresser-events' ); ?></span>
							<?php endif; ?>
						</td>
						<td><button type="button" class="button-link appp-ev-reset-row" title="<?php echo esc_attr( sprintf( /* translators: %s basis */ __( 'Reset this row to the %s', 'apppresser-events' ), $basis ) ); ?>"><?php esc_html_e( 'Reset', 'apppresser-events' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<datalist id="appp-events-keys">
				<?php
				$opts = array_merge( Appp_Events_Fields::post_fields(), $taxes, $keys );
				if ( $is_gs ) {
					$g    = $adapter->guess();
					$opts = array_merge( $opts, $g['meta_keys'] );
				}
				foreach ( array_unique( $opts ) as $opt ) :
					?>
					<option value="<?php echo esc_attr( $opt ); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<h3><?php esc_html_e( 'Preview', 'apppresser-events' ); ?></h3>
			<p>
				<button type="button" class="button" id="appp-ev-preview-btn"><?php esc_html_e( 'Preview with current changes', 'apppresser-events' ); ?></button>
				<span class="description"><?php esc_html_e( 'Uses the rows above without saving. Below: saved settings.', 'apppresser-events' ); ?></span>
			</p>
			<div id="appp-ev-preview"><?php echo self::preview_html( self::preview_data() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>

			<h3><?php esc_html_e( 'Export and import mapping', 'apppresser-events' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Copy the export to another site, paste it into Import there, and click Save Settings.', 'apppresser-events' ); ?></p>
			<p><label><strong><?php esc_html_e( 'Export (saved settings)', 'apppresser-events' ); ?></strong><br>
				<textarea readonly rows="5" style="width:100%;max-width:1000px;font-family:monospace;font-size:11px" onclick="this.select()"><?php echo esc_textarea( self::export_json() ); ?></textarea></label></p>
			<p><label><strong><?php esc_html_e( 'Import', 'apppresser-events' ); ?></strong><br>
				<textarea name="appp_settings[events_import]" rows="4" style="width:100%;max-width:1000px;font-family:monospace;font-size:11px" placeholder="<?php esc_attr_e( 'Paste an exported mapping or an exported preset (JSON) here, then Save Settings', 'apppresser-events' ); ?>"></textarea></label></p>
			<?php self::render_preset_export( $eff ); ?>
			<?php self::print_js(); ?>
		</td></tr>
		<?php
	}

	/** True when the mapping in effect can be written as a data preset. */
	public static function can_export_preset( $eff ) {
		$a = $eff['adapter'];
		if ( ! $eff['post_type'] || ! post_type_exists( $eff['post_type'] ) ) {
			return false;
		}
		return $a instanceof Appp_Events_Adapter_Preset || $a instanceof Appp_Events_Adapter_Guess || 'manual' === $eff['mode'];
	}

	/** Export as preset: the saved mapping as JSON and as a PHP mu-plugin for appp_events_presets. */
	private static function render_preset_export( $eff ) {
		?>
		<h3><?php esc_html_e( 'Export as preset', 'apppresser-events' ); ?></h3>
		<?php if ( ! self::can_export_preset( $eff ) ) : ?>
			<p class="description"><?php esc_html_e( 'This source is a supported plugin with its own adapter, so it already ships with the add-on. Export as preset is for plugins and post types mapped here (guessed, plain post type, manual, or a data preset).', 'apppresser-events' ); ?></p>
			<?php
			return;
		endif;
		$preset = Appp_Events_Presets::from_effective( $eff );
		?>
		<p class="description" style="max-width:1000px"><?php esc_html_e( 'Turns the saved mapping into a preset. Send the JSON to AppPresser so it can ship as a built in preset, or drop the PHP into wp-content/mu-plugins to register it on your sites with the appp_events_presets filter. Check the detection rule first: add the plugin file, a class, a function or a constant so the preset only turns on when that plugin is active.', 'apppresser-events' ); ?></p>
		<p><label><strong><?php esc_html_e( 'Preset (JSON)', 'apppresser-events' ); ?></strong><br>
			<textarea readonly id="appp-ev-preset-json" rows="8" style="width:100%;max-width:1000px;font-family:monospace;font-size:11px" onclick="this.select()"><?php echo esc_textarea( Appp_Events_Presets::to_json( $preset ) ); ?></textarea></label></p>
		<p><label><strong><?php esc_html_e( 'Preset (PHP mu-plugin)', 'apppresser-events' ); ?></strong><br>
			<textarea readonly id="appp-ev-preset-php" rows="8" style="width:100%;max-width:1000px;font-family:monospace;font-size:11px" onclick="this.select()"><?php echo esc_textarea( Appp_Events_Presets::to_php( $preset ) ); ?></textarea></label></p>
		<p>
			<a class="button-secondary" download="<?php echo esc_attr( 'appp-events-preset-' . $preset['id'] . '.json' ); ?>" href="<?php echo esc_attr( 'data:application/json;charset=utf-8,' . rawurlencode( Appp_Events_Presets::to_json( $preset ) ) ); ?>"><?php esc_html_e( 'Download JSON', 'apppresser-events' ); ?></a>
			<a class="button-secondary" download="<?php echo esc_attr( 'appp-events-preset-' . $preset['id'] . '.php' ); ?>" href="<?php echo esc_attr( 'data:text/plain;charset=utf-8,' . rawurlencode( Appp_Events_Presets::to_php( $preset ) ) ); ?>"><?php esc_html_e( 'Download PHP', 'apppresser-events' ); ?></a>
		</p>
		<?php
	}

	public static function export_json() {
		$eff = Appp_Events_Settings::effective();
		$map = $eff['fields'];
		$map = array( '_target' => $eff['target'] ) + $map;
		return wp_json_encode(
			array(
				'appp_events'      => APPP_EVENTS_VERSION,
				'mapping_version'  => $eff['mapping_version'],
				'events_source'    => in_array( $eff['mode'], array( 'manual', 'plain' ), true ) ? $eff['mode'] : $eff['target'],
				'events_post_type' => in_array( $eff['mode'], array( 'manual', 'plain' ), true ) ? $eff['post_type'] : 'auto',
				'events_collapse'  => $eff['expand'] ? '' : 'on',
				'events_field_map' => $map,
			),
			JSON_UNESCAPED_SLASHES
		);
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
			$warn[] = $recs ? __( 'No events could be built from this mapping.', 'apppresser-events' ) : __( 'No published events found for this source.', 'apppresser-events' );
		} elseif ( ! is_wp_error( $res ) && 0 === $res['total'] ) {
			$warn[] = __( 'Dates in the past only: nothing upcoming. Check the date format and timezone.', 'apppresser-events' );
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
			$warn[] = __( 'No venue on any item. Map a venue field if your events have one.', 'apppresser-events' );
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

	/** Preview with unsaved form values. */
	public static function ajax_preview() {
		check_ajax_referer( 'appp_events_preview' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$raw = isset( $_POST['appp_settings'] ) && is_array( $_POST['appp_settings'] ) ? wp_unslash( $_POST['appp_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$arr = array(
			'events_source'    => isset( $raw['events_source'] ) ? Appp_Events_Settings::clean_source( $raw['events_source'] ) : 'auto',
			'events_post_type' => isset( $raw['events_post_type'] ) ? sanitize_key( $raw['events_post_type'] ) : '',
			'events_collapse'  => ! empty( $raw['events_collapse'] ) ? 'on' : '',
			'events_field_map' => isset( $raw['events_field_map'] ) ? self::sanitize_map( $raw['events_field_map'] ) : array(),
		);
		Appp_Events_Engine::use_settings( Appp_Events_Settings::from_array( $arr ) );
		$d = self::preview_data();
		Appp_Events_Engine::use_settings( null );
		wp_send_json_success( array( 'html' => self::preview_html( $d ) ) );
	}

	private static function print_js() {
		$cfg = array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'appp_events_preview' ),
			'custom' => __( 'custom', 'apppresser-events' ),
			'wait'   => __( 'Building preview...', 'apppresser-events' ),
			'failed' => __( 'Preview failed.', 'apppresser-events' ),
		);
		?>
		<script>
		(function(){
			var C = <?php echo wp_json_encode( $cfg ); ?>;
			var table = document.getElementById('appp-ev-map');
			if (!table) { return; }
			function badge(tr, cls, text){ var s = tr.querySelector('.appp-ev-status'); s.innerHTML=''; var b=document.createElement('span'); b.className='appp-ev-badge '+cls; b.textContent=text; s.appendChild(b); }
			function rowVal(tr){ var o={}; tr.querySelectorAll('.appp-ev-in').forEach(function(el){ o[el.getAttribute('data-f')]=el.value; }); return o; }
			function sameAsPreset(tr){
				var p = JSON.parse(tr.getAttribute('data-preset')), v = rowVal(tr);
				var fmt = v.format === 'custom' ? (v.format_custom||'') : (v.format||'');
				return (p.source||'none')===(v.source||'none') && (p.key||'')===(v.key||'') && (p.format||'')===fmt && (p.match||'')===(v.match||'');
			}
			function refresh(tr){ if (sameAsPreset(tr)) { var b = JSON.parse(tr.getAttribute('data-badge')); badge(tr,b[0],b[1]); } else { badge(tr,'custom',C.custom); } }
			table.querySelectorAll('tr[data-preset]').forEach(function(tr){
				tr.querySelectorAll('.appp-ev-in').forEach(function(el){ el.addEventListener('change', function(){ refresh(tr); }); el.addEventListener('input', function(){ refresh(tr); }); });
				tr.querySelector('.appp-ev-reset-row').addEventListener('click', function(){
					var p = JSON.parse(tr.getAttribute('data-preset'));
					tr.querySelectorAll('.appp-ev-in').forEach(function(el){
						var f = el.getAttribute('data-f');
						if (f==='format') { var has=false; for (var i=0;i<el.options.length;i++){ if (el.options[i].value===(p.format||'')) { has=true; } } el.value = has ? (p.format||'') : 'custom'; }
						else if (f==='format_custom') { var sel = tr.querySelector('[data-f=format]'); el.value = (sel && sel.value==='custom') ? (p.format||'') : ''; }
						else { el.value = p[f]||''; if (f==='source' && !el.value) { el.value='none'; } }
					});
					refresh(tr);
				});
			});
			document.querySelectorAll('.appp-ev-src').forEach(function(r){ r.addEventListener('change', function(){ var row=document.getElementById('appp-ev-pt-row'); if (row) { if (r.checked) { row.style.display = (r.value==='manual' || r.value==='plain') ? '' : 'none'; } } }); });
			var btn = document.getElementById('appp-ev-preview-btn');
			btn.addEventListener('click', function(){
				var form = btn.closest('form'), fd = new FormData();
				fd.append('action','appp_events_preview'); fd.append('_ajax_nonce', C.nonce);
				new FormData(form).forEach(function(v,k){ if (k.indexOf('appp_settings[events_')===0) { fd.append(k,v); } });
				var out = document.getElementById('appp-ev-preview'); out.innerHTML = ''; var w = document.createElement('p'); w.textContent = C.wait; out.appendChild(w);
				function fail(){ out.innerHTML=''; var p=document.createElement('p'); p.className='appp-ev-warn'; p.textContent=C.failed; out.appendChild(p); }
				fetch(C.ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){ if (j && j.success) { out.innerHTML = j.data.html; } else { fail(); } }).catch(fail);
			});
		})();
		</script>
		<?php
	}

	public static function render_buttons( $appp_settings = null, $settings = array() ) {
		printf(
			' <button type="submit" class="button-secondary" name="appp_settings[events_reset]" value="1" onclick="return confirm(\'%s\');">%s</button>',
			esc_js( __( 'Remove all Events settings and go back to auto detect with the preset mapping? Other AppPresser settings on this form are saved too.', 'apppresser-events' ) ),
			esc_html__( 'Reset all Events rows to preset', 'apppresser-events' )
		);
		printf(
			' <a class="button-secondary" href="%s">%s</a>',
			esc_url( wp_nonce_url( self::tab_url( array( 'appp_events_redetect' => 1 ) ), 'appp_events_redetect' ) ),
			esc_html__( 'Re-detect plugins', 'apppresser-events' )
		);
	}

	public static function sanitize_setting( $filtered, $key, $value ) {
		if ( 'events_field_map' === $key ) {
			return self::sanitize_map( $value );
		}
		if ( 'events_source' === $key ) {
			return Appp_Events_Settings::clean_source( $value );
		}
		if ( 'events_post_type' === $key ) {
			return sanitize_key( (string) $value );
		}
		return $filtered;
	}

	/** Core passes arrays through untouched; clean the nested map here. */
	public static function sanitize_map( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$t    = isset( $value['_target'] ) ? (string) $value['_target'] : '';
		$out  = array( '_target' => Appp_Events_Settings::clean_source( $t ) );
		$defs = Appp_Events_Fields::definitions();
		foreach ( $value as $k => $cfg ) {
			if ( isset( $defs[ $k ] ) ) {
				$out[ $k ] = Appp_Events_Fields::sanitize_field( $cfg );
			}
		}
		return $out;
	}

	/**
	 * An imported preset (Export as preset JSON) as settings: the registered preset with
	 * the same id when there is one, otherwise plain post type mode with its fields.
	 */
	public static function preset_to_settings( array $preset ) {
		$errs = array();
		$norm = Appp_Events_Presets::normalize( $preset, $errs );
		if ( ! $norm ) {
			return null;
		}
		$adapters = Appp_Events_Settings::adapters();
		if ( isset( $adapters[ $norm['id'] ] ) ) {
			return array(
				'events_source'    => $norm['id'],
				'events_post_type' => 'auto',
				'events_field_map' => array( '_target' => $norm['id'] ) + $norm['fields'],
			);
		}
		return array(
			'events_source'    => 'plain',
			'events_post_type' => $norm['post_type'],
			'events_field_map' => array( '_target' => 'plain:' . $norm['post_type'] ) + $norm['fields'],
		);
	}

	/**
	 * After core validation: Reset drops every Events key; Import replaces them; a map
	 * saved for a different source than the one now in effect is dropped so the new
	 * source starts from its preset or guess.
	 */
	public static function after_validate( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( ! empty( $value['events_reset'] ) ) {
			foreach ( Appp_Events_Settings::keys() as $k ) {
				unset( $value[ $k ] );
			}
		} elseif ( ! empty( $value['events_import'] ) ) {
			$in = json_decode( (string) $value['events_import'], true );
			if ( is_array( $in ) && isset( $in['appp_events_preset'] ) ) {
				$in = self::preset_to_settings( $in );
			}
			if ( is_array( $in ) && isset( $in['events_field_map'] ) ) {
				$value['events_source']    = self::sanitize_setting( null, 'events_source', isset( $in['events_source'] ) ? $in['events_source'] : 'auto' );
				$value['events_post_type'] = sanitize_key( isset( $in['events_post_type'] ) ? $in['events_post_type'] : 'auto' );
				$value['events_collapse']  = ( isset( $in['events_collapse'] ) && 'on' === $in['events_collapse'] ) ? 'on' : '';
				$value['events_field_map'] = self::sanitize_map( $in['events_field_map'] );
			}
		}
		unset( $value['events_reset'], $value['events_import'] );
		if ( isset( $value['events_field_map']['_target'] ) ) {
			$eff = Appp_Events_Settings::effective(
				Appp_Events_Settings::from_array(
					array(
						'events_source'    => isset( $value['events_source'] ) ? $value['events_source'] : 'auto',
						'events_post_type' => isset( $value['events_post_type'] ) ? $value['events_post_type'] : '',
					)
				)
			);
			if ( $eff['target'] !== $value['events_field_map']['_target'] ) {
				unset( $value['events_field_map'] );
			}
		}
		// Only an Events change needs a new scan; saving another AppPresser tab keeps it.
		$old     = get_option( 'appp_settings', array() );
		$changed = false;
		foreach ( Appp_Events_Settings::keys() as $k ) {
			$a = is_array( $old ) && isset( $old[ $k ] ) ? $old[ $k ] : null;
			$b = is_array( $value ) && isset( $value[ $k ] ) ? $value[ $k ] : null;
			if ( wp_json_encode( $a ) !== wp_json_encode( $b ) ) {
				$changed = true;
				break;
			}
		}
		if ( $changed ) {
			Appp_Events_Heuristic::flush();
		}
		return $value;
	}
}
