<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_Notifications_Settings {

	public $apppush_log;
	public $api_ready;

	public function __construct( $api_ready ) {
		$this->api_ready = $api_ready;
	}

	public function hooks() {

		// Add setting rows to Apppresser settings
		add_action( 'apppresser_add_settings', array( $this, 'notifications_settings' ), 30 );

		// post_type
		add_filter( 'apppresser_field_override_notifications_post_types', array( $this, 'notifications_post_types' ), 10, 4 );
		add_filter( 'apppresser_sanitize_setting_notifications_post_types', array( $this, 'sanitize_array_values' ), 10, 2 );

		// allow segmenting
		add_filter( 'apppresser_field_override_notifications_allow_segments', array( $this, 'notifications_allow_segments' ), 10, 4 );
		add_filter( 'apppresser_sanitize_setting_notifications_allow_segments', array( $this, 'sanitize_array_values' ), 10, 2 );

		// taxonomy
		add_filter( 'apppresser_field_override_notifications_taxonomy_segments', array( $this, 'notifications_taxonomy_segments' ), 10, 4 );
		add_filter( 'apppresser_sanitize_setting_notifications_taxonomy_segments', array( $this, 'sanitize_array_values' ), 10, 2 );
	}

	// Notifications settings Settings
	public function notifications_settings( $appp ) {

		$appp->add_setting_tab( __( 'Notifications', 'apppresser-push' ), 'appp-notifications' );

		$appp->add_setting(
			'notifications_ap3_key',
			__( 'AppPresser Notifications Key', 'apppresser-push' ),
			array(
				'tab'      => 'appp-notifications',
				'helptext' => __( 'Get this key on myapppresser.com under Your App => Push Notifications.', 'apppresser-push' ),
				'subtab'   => 'general',
			)
		);

		$appp->add_setting(
			'notifications_disable',
			__( 'Disable Notifications', 'appp-notifications' ),
			array(
				'tab'         => 'appp-notifications',
				'description' => __( 'Disable', 'apppresser-push' ),
				'helptext'    => __( 'Safely disable notification during development.', 'apppresser-push' ),
				'type'        => 'checkbox',
				'subtab'      => 'general',
			)
		);

		$appp->add_setting(
			'notifications_logging',
			__( 'Log Notifications', 'apppresser-push' ),
			array(
				'tab'         => 'appp-notifications',
				'description' => __( 'Logging<br>&nbsp;&nbsp;<a href="' . admin_url( 'edit.php?post_type=apppush-log' ) . '">Notification Logs</a>', 'apppresser-push' ),
				'helptext'    => __( 'Turn on logging of each notification sent.', 'apppresser-push' ),
				'type'        => 'checkbox',
				'subtab'      => 'general',
			)
		);

		$appp->add_setting(
			'notifications_post_types',
			__( 'Push Post Types', 'apppresser-push' ),
			array(
				'type'     => 'notifications_post_types',
				'tab'      => 'appp-notifications',
				'helptext' => __( 'Choose Post Types that can send Push Notifications', 'apppresser-push' ),
				'subtab'   => 'general',
			)
		);
	}

	public function notifications_post_types( $field, $key, $value, $args ) {

		$post_types          = get_post_types( array(), 'objects' );
		$exclude_types       = array( 'attachment', 'revision', 'nav_menu_item', AppPresser_Notifications::$cpt, 'apppush-log' );
		$exclude_woocommerce = array( 'product_variation', 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_webhook' );
		$exclude_buddypress  = array( 'bp-email' );

		$exclude_types = array_merge( $exclude_types, $exclude_woocommerce, $exclude_buddypress );
		$exclude_types = apply_filters( 'apppush_segment_post_types', $exclude_types );

		$saved = appp_get_setting( 'notifications_post_types' );

		foreach ( $post_types as $post_type => $object ) {
			if ( ! in_array( $post_type, $exclude_types ) ) {

				$checked = is_array( $saved ) && in_array( $post_type, $saved, true );
				$field  .= '<label><input ' . checked( $checked, 1, 0 ) . ' type="checkbox" name="appp_settings[notifications_post_types][]" value="' . esc_attr( $post_type ) . '">&nbsp;' . $object->labels->name . '</label><br>' . "\n";
			}
		}

		return $field;
	}

	public function notifications_allow_segments( $field, $key, $value, $args ) {
		$checked = appp_get_setting( 'notifications_allow_segments' );

		$field .= '<label><input ' . checked( $checked, 1, 0 ) . ' data-allow-segments="checkbox" type="checkbox" name="appp_settings[notifications_allow_segments]" value=\'1\'>&nbsp;' . __( 'Enable', 'apppush' ) . '</label><br>' . "\n";

		return $field;
	}

	public function notifications_taxonomy_segments( $field, $key, $value, $args ) {
		$post_types      = get_post_types( array(), 'objects' );
		$exclude_types   = array( 'attachment', 'revision', 'nav_menu_item', AppPresser_Notifications::$cpt );
		$exclude_tax     = array( 'post_tag', 'post_format', 'nav_menu' );
		$saved_tax       = appp_get_setting( 'notifications_taxonomy_segments' );
		$saved_post_type = appp_get_setting( 'notifications_post_types' );

		$saved_tax_setting = appp_get_setting( 'notifications_taxonomy_segments' );
		$saved_tax         = array();

		if ( $saved_tax_setting ) {
			foreach ( $saved_tax_setting as $key => $value ) {
				array_push( $saved_tax, unserialize( $value ) );
			}
		}

		if ( ! empty( $saved_post_type ) && is_array( $saved_post_type ) ) {

			$field .= '<div class="toggle-notification-segments" id="toggle-notification-segments" style="display:none">';
			$field .= '<table>';

			foreach ( $post_types as $post_type ) {
				if ( ! in_array( $post_type, $exclude_types ) && in_array( $post_type->name, $saved_post_type ) ) {

					$taxonomies = get_object_taxonomies( $post_type->name );

					// foreach post type
					// we need to loop through each taxononomy
					// and then foreach taxonomy
					// we need to loop through each term
					foreach ( $taxonomies as $tax ) {

						if ( ! in_array( $tax, $exclude_tax ) ) {

							// only display taxonomies once
							// $exclude_tax[] = $tax;

							if ( version_compare( get_bloginfo( 'version' ), '4.5.0', '<' ) ) {
								$terms = get_terms(
									'taxonomy',
									array(
										'hide_empty' => false,
									)
								);
							} else {
								$terms = get_terms(
									array(
										'taxonomy'   => $tax,
										'hide_empty' => false,
									)
								);
							}

							$tax = get_taxonomy( $tax );

							if ( ! empty( $terms ) ) {
								$field .= '<tr><th colspan="3">' . $post_type->label . ': ' . $tax->labels->singular_name . '</th></tr>';
							}

							$css       = array( 'odd', 'even' );
							$css_count = 0;

							foreach ( $terms as $term ) {

								++$css_count;

								$i = ( $css_count % 2 ) ? 1 : 0;

								$checked = 0;

								foreach ( $saved_tax as $posttype_with_taxonomy ) {
									if ( $posttype_with_taxonomy['posttype'] == $post_type->name &&
										$posttype_with_taxonomy['taxonomy'] == $tax->name &&
										$posttype_with_taxonomy['term_id'] == $term->term_id ) {
										$checked = 1;
										break;
									}
								}

								$subscriber_count = AppPresser_Notifications_Segments::get_subscription_totals( $post_type->name, $tax->name, $term->term_id );

								$field      .= '<tr class="' . $css[ $i ] . '">';
								$field_value = serialize(
									array(
										'posttype' => $post_type->name,
										'taxonomy' => $tax->name,
										'term_id'  => $term->term_id,
									)
								);
								$field      .= '<td><input ' . checked( $checked, 1, 0 ) . ' type="checkbox" id="' . $post_type->name . '-' . $tax->name . '-' . $term->term_id . '" name="appp_settings[notifications_taxonomy_segments][]" value=\'' . $field_value . '\'></td>';
								$field      .= '<td><label for="' . $post_type->name . '-' . $tax->name . '-' . $term->term_id . '">' . $term->name . '</label></td>';
								if ( $subscriber_count ) {
									$field .= '<td class="appp-subscribe-count">' . $subscriber_count . '</td>';
								} else {
									$field .= '<td class="appp-subscribe-count">&nbsp;</td>';
								}
								$field .= '</tr>';

							}
						}
					}
				}
			}

			$field .= '</table>';
			$field .= '</div>';
			return $field;
		} else {
			return '<p>No <a class="subnav-tab" data-selector="general-subtab.subtab-appp-notifications" href="?page=apppresser_settings&tab=tab-appp-notifications&subnav=general">push post types</a> are saved.</p>';
		}
	}

	public function get_app_log() {
		if ( $this->apppush_log ) {
			return $this->apppush_log;
		}

		$this->apppush_log = new AppPresser_Notifications_Log();

		return $this->apppush_log;
	}

	public function sanitize_array_values( $empty, $value ) {

		// Sanitize
		// foreach ( $value as $key => $val ) {
		// $value[ $key ] = sanitize_text_field( $val );
		// }
		return $value;
	}
}
