<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Segment notifications by taxonomies
 *
 * @package AppPresser
 * @subpackage ApppSegmentNotification
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

/**
 * Segment Notification
 */
class AppPresser_Notifications_Segments {

	public $current_post_type = '';
	public $show_headers      = false;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since  1.4.0
	 * @return ApppSegmentNotification A single instance of this class.
	 */
	public static function run() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->hooks();
	}

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'styles_scripts' ) );
		add_action( 'customize_register', array( $this, 'add_theme_mod' ) );
		add_action( 'wp_ajax_segment_user_meta_update', array( $this, 'ajax_handle_user_preferences' ) );
		add_action( 'wp_ajax_nopriv_segment_user_meta_update', array( $this, 'ajax_handle_user_preferences' ) );
		add_action( 'delete_user', array( $this, 'cleanup_user_subscriptions' ) );
		add_shortcode( 'appp-notification-signup', array( $this, 'signup_shortcode' ) );
	}

	public function styles_scripts() {
		wp_register_style( 'segment-signup', AppPresser_Notifications::$plugin_url . '/css/appp-push.css', array(), AppPresser_Notifications::VERSION, 'all' );
	}

	/**
	 * add_theme_mod function.
	 *
	 * @access public
	 * @param mixed $wp_customize
	 * @return void
	 */
	public function add_theme_mod( $wp_customize ) {

		$theme_name = appp_get_setting( 'appp_theme' )
			? appp_get_setting( 'appp_theme' )
			: null;
		$theme      = wp_get_theme( $theme_name );

		$is_app_theme = 0 === strcasecmp( $theme->get_template(), 'AppPresser' ) || 0 === strcasecmp( $theme->get_template(), 'AppTheme' ) || 0 === strcasecmp( $theme->get_template(), 'ion' );

		if ( ! $is_app_theme ) {
			return;
		}

		$wp_customize->add_section(
			'apppush_section',
			array(
				'title'       => 'AppPush Settings',
				'description' => 'Customize AppPush',
				'priority'    => 99,
				'capability'  => 'edit_theme_options',
			)
		);

		// login screen background color
		$wp_customize->add_setting(
			'ap_color_mod',
			array(
				'default'    => $this->get_default_color( $theme_name ),
				'capability' => 'edit_theme_options',
			)
		);
		// Controls
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'ap_color_mod',
				array(
					'label'    => __( 'Toggle button color', 'apppush' ),
					'section'  => 'apppush_section',
					'settings' => 'ap_color_mod',
				)
			)
		);
	}

	public function get_default_color( $theme_name = '' ) {

		if ( empty( $theme_name ) ) {
			$theme_name = appp_get_setting( 'appp_theme' );
		}

		switch ( $theme_name ) {
			case 'apptheme':
				$default_color = '#00c14d';
				break;
			case 'ion':
				$default_color = '#1495cf';
				break;

			default:
				$default_color = '#11c1f3'; // ionic-calm
				break;
		}

		return $default_color;
	}

	public function signup_shortcode( $atts, $content = '' ) {
		$shortcode_atts = shortcode_atts(
			array(
				'title'        => __( 'Notification Signup', 'apppush' ),
				'show_headers' => '',
			),
			$atts
		);

		wp_enqueue_style( 'segment-signup' );

		if ( $shortcode_atts['show_headers'] ) {
			$this->show_headers = true;
		}

		ob_start();
		include_once $this->get_template( 'notification-signup' );
		return ob_get_clean();
	}

	public function get_push_segement_options() {
		$saved_tax_setting = appp_get_setting( 'notifications_taxonomy_segments' );
		$saved_tax         = array();

		if ( $saved_tax_setting ) {
			foreach ( $saved_tax_setting as $key => $value ) {
				array_push( $saved_tax, unserialize( $value ) );
			}
		}

		return $saved_tax;
	}

	public static function get_device_ids_by_post( $post ) {
		$related_terms = self::get_related_terms_by_post( $post );
		$user_ids      = self::get_user_ids_by_segment_terms( $related_terms );
		$device_ids    = self::get_appp_push_device_ids( $user_ids );

		return $device_ids;
	}

	public static function get_appp_push_device_ids( $user_ids ) {
		global $wpdb;

		if ( empty( $user_ids ) ) {
			return array();
		}

		$device_ids = array();
		$_user_ids  = implode( ',', $user_ids );

		$sql = "SELECT meta_value FROM {$wpdb->prefix}usermeta WHERE meta_key = 'appp_push_device_id' AND user_id IN ( $_user_ids )";

		$rows = $wpdb->get_results( $sql );

		foreach ( $rows as $row ) {

			$_device_ids = maybe_unserialize( $row->meta_value );
			if ( is_array( $_device_ids ) ) {
				foreach ( $_device_ids as $_device_id ) {
					$device_ids[] = $_device_id;
				}
			} else {
				// maybe a string if < v2.2.0 when only once device id per user was saved
				$device_ids[] = $_device_ids;
			}
		}

		return $device_ids;
	}

	public static function get_user_ids_by_segment_terms( $terms ) {
		global $wpdb;

		if ( empty( $terms ) ) {
			return array();
		}

		$user_ids = array();
		$where    = array();

		foreach ( $terms as $term ) {
			$post_types = array( $term['post_type'] );
			if ( $term['taxonomy'] == 'category' && ! in_array( 'post', $post_types ) ) {
				$post_types[] = 'post';
			}
			$where[] = sprintf( "( post_type IN ('%s') AND taxonomy = '%s' AND term_id = %d )", implode( "','", $post_types ), $term['taxonomy'], $term['term_id'] );
		}

		$_where = implode( ' OR ', $where );

		$sql = "SELECT user_id FROM `{$wpdb->prefix}apppush_subscribe` WHERE $_where GROUP BY user_id;";

		$rows = $wpdb->get_results( $sql );

		foreach ( $rows as $row ) {
			$user_ids[] = (int) $row->user_id;
		}

		return $user_ids;
	}

	public static function get_related_terms_by_post( $post ) {
		$related_terms = array();

		$taxonomies         = get_object_taxonomies( $post );
		$exclude_taxonomies = array( 'post_tag', 'post_format' );

		foreach ( $taxonomies as $taxonomy ) {

			if ( ! in_array( $taxonomy, $exclude_taxonomies ) ) {
				$terms = wp_get_post_terms( $post->ID, $taxonomy );

				foreach ( $terms as $term ) {
					$related_terms[] = array(
						'post_type' => $post->post_type,
						'taxonomy'  => $taxonomy,
						'term_id'   => $term->term_id,
					);
				}
			}
		}

		return $related_terms;
	}

	public function get_template( $template ) {

		// check the theme folder
		$theme_template = locate_template( array( "templates/apppush/$template.php" ) );

		if ( $theme_template ) {
			return $theme_template;
		} else {
			// use the default template in the plugin folder
			$filepath = AppPresser_Notifications::$plugin_path . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template . '.php';
			return apply_filters( 'appp-' . $template . '-template', $filepath );
		}
	}

	public function get_notification_loop() {

		$loop_template = $this->get_template( 'notification-signup-loop' );

		$push_taxonomies = $this->get_push_segement_options();

		$user_subscriptions = $this->get_user_subscriptions();

		$count = 1;
		foreach ( $push_taxonomies as $taxonomy_term ) {
			$taxonomy = $taxonomy_term['taxonomy'];
			$term     = get_term( (int) $taxonomy_term['term_id'], $taxonomy );

			if ( ! is_wp_error( $term ) ) {
				$is_checked = false;
				$post_type  = $taxonomy_term['posttype'];

				foreach ( $user_subscriptions as $subscription ) {
					if ( $subscription->taxonomy == $taxonomy_term['taxonomy'] &&
						$subscription->term_id == $taxonomy_term['term_id'] &&
						$subscription->post_type == $taxonomy_term['posttype'] ) {
						$is_checked = true;
						break;
					}
				}

				$color = get_theme_mod( 'ap_color_mod', $this->get_default_color() );

				if ( $color ) {
					echo '<style type="text/css">';
					echo '#page .ionic .toggle.toggle-apppush input:checked + .track {border-color: ' . $color . ';background-color: ' . $color . '; }';
					echo '#page .ionic .toggle-small .toggle-apppush input:checked + .track .handle { background-color: ' . $color . '; }';
					echo '</style>';
				}

				if ( $this->show_headers && $this->current_post_type != $post_type ) {
					$post_type_obj = get_post_type_object( $post_type );
					echo '<li class="header-item item">' . $post_type_obj->label . '</li>';
					$this->current_post_type = $post_type;
				} /*
				else {
					echo '<li class="header-item item"><h2>'.$post_type.'</h2></li>';
				}*/

				include $loop_template;
				++$count;
			}
		}
	}

	/**
	 * Verify the ajax variables: taxonomy, term_id, checkbox_status.
	 * If verified, save settings.
	 *
	 * @return string|array string with error message or array with valid data
	 */
	public function ajax_handle_user_preferences() {

		$data = $this->verify_user_preferences();
		if ( is_array( $data ) ) {
			$this->update_segment_settings( $data['user_id'], $data['post_type'], $data['taxonomy'], $data['term_id'], $data['status'] );
			wp_die( json_encode( $data ) );
		}

		wp_die( json_encode( array( 'status' => $data ) ) );
	}

	private function verify_user_preferences() {

		if ( isset( $_POST['term_id'] ) && is_numeric( $_POST['term_id'] ) ) {
			$term_id = $_POST['term_id'];
		} else {
			return 'missing term id';
		}

		$taxonomies = get_taxonomies();
		if ( isset( $_POST['taxonomy'] ) && array_key_exists( $_POST['taxonomy'], $taxonomies ) ) {
			$taxonomy = $_POST['taxonomy'];
		} else {
			return 'missing taxonomy';
		}

		if ( isset( $_POST['status'] ) ) {
			$ckbox_status = ( $_POST['status'] == 'on' ) ? 'on' : 'off';
		} else {
			return 'missing checkbox status';
		}

		$post_types = get_post_types();
		if ( isset( $_POST['post_type'] ) && in_array( $_POST['post_type'], $post_types ) ) {
			$post_type = $_POST['post_type'];
		}

		return array(
			'user_id'   => get_current_user_id(),
			'post_type' => $post_type,
			'taxonomy'  => $taxonomy,
			'term_id'   => $term_id,
			'status'    => $ckbox_status,
		);
	}

	public function update_segment_settings( $user_id, $post_type, $taxonomy, $term_id, $status ) {

		global $wpdb;

		if ( $status == 'on' ) {
			$this->insert_user_subscription( $user_id, $post_type, $taxonomy, $term_id );
		} else {
			$this->delete_user_subscription( $user_id, $post_type, $taxonomy, $term_id );
		}
	}

	public function get_existing_key( $user_id, $post_type, $taxonomy_id, $term_id, $status ) {
	}

	public function get_user_subscriptions() {
		global $wpdb;

		$user_id = get_current_user_id();

		if ( $user_id ) {
			$sql = $wpdb->prepare( "SELECT post_type, taxonomy, term_id FROM {$wpdb->prefix}apppush_subscribe WHERE user_id = %d", $user_id );

			return $wpdb->get_results( $sql );
		} else {
			return array();
		}
	}

	public function insert_user_subscription( $user_id, $post_type, $taxonomy, $term_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "INSERT INTO {$wpdb->prefix}apppush_subscribe (id, user_id, post_type, taxonomy, term_id) VALUES (NULL, %d, %s, %s, %d);", $user_id, $post_type, $taxonomy, $term_id );

		return $wpdb->query( $sql );
	}

	public function delete_user_subscription( $user_id, $post_type, $taxonomy, $term_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}apppush_subscribe WHERE user_id = %d AND post_type = %s AND taxonomy = %s AND term_id = %d;", $user_id, $post_type, $taxonomy, $term_id );

		return $wpdb->query( $sql );
	}

	public function cleanup_user_subscriptions( $user_id ) {
		global $wpdb;

		// delete user subscriptions

		$sql = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}apppush_subscribe WHERE user_id = %d", $user_id );

		$wpdb->query( $sql );
	}

	public static function get_subscription_totals( $post_type, $taxonomy, $term_id ) {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'apppush_subscribe';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		if ( $table_exists ) {
			$sql     = $wpdb->prepare( "SELECT COUNT(*) as count FROM $table_name WHERE post_type = %s AND taxonomy = %s AND term_id = %d;", $post_type, $taxonomy, $term_id );
			$results = $wpdb->get_row( $sql );
			return (int) $results->count;
		}

		return 0;
	}
}
