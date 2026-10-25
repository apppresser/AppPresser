<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;



/**
 * AppPresser_IAP_Settings
 *
 * These are functions are used for In App Purchase Settings
 *
 * @package     AppPresser
 * @subpackage  Settings/iapsettings
 * @copyright   Copyright (c) 2017, AppPresser
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

class AppPresser_IAP_Settings
{
	public static $plugins = array();
	public static $instance = null;
	public static $dir_path;
	public static $membership_options = array();

	public static function get()
	{
		if (self::$instance === null)
			self::$instance = new self();

		return self::$instance;
	}

	public function __construct()
	{
		self::$dir_path = trailingslashit( plugin_dir_path( __FILE__ ) );

		add_action( 'admin_init', array( $this, 'maybe_save_iap_data' ) );
		$this->hooks();
	}

	public function hooks()
	{
		add_action('apppresser_add_settings', array($this, 'IAP_tab'), 100);
		add_action('apppresser_tab_top_iapsettings', array($this, 'appp_IAP_settings'));
		add_action( 'admin_enqueue_scripts', array( $this, 'settings_assets' ) );

		// Check if memberpress plugin is active
		if (in_array(('memberpress/memberpress.php'),array_merge( (array) get_option('active_plugins', array()), array_keys( (array) get_site_option('active_sitewide_plugins', array()) ) ))) {
			self::$plugins['memberpress'] = 'MemberPress';
			if ( 'Yes' === apppresser_iap_get_option( 'IAP_MEMBERPRESS_ACTIVE' ) ) { require_once( self::$dir_path . 'inc/apppresser-iap-memberpress.php' ); }
		}

		// Check if paid memberships pro plugin is active
		if (in_array(('paid-memberships-pro/paid-memberships-pro.php'),array_merge( (array) get_option('active_plugins', array()), array_keys( (array) get_site_option('active_sitewide_plugins', array()) ) ))) {
			self::$plugins['pmp'] = 'Paid Memberships Pro';
			if ( 'Yes' === apppresser_iap_get_option( 'IAP_PMP_ACTIVE' ) ) { require_once( self::$dir_path . 'inc/apppresser-iap-pmp.php' ); }
		}

		// check if armember is active
		if (in_array(('armember/armember.php'),array_merge( (array) get_option('active_plugins', array()), array_keys( (array) get_site_option('active_sitewide_plugins', array()) ) ))) {
			self::$plugins['armember'] = 'ARMember';
			if ( 'Yes' === apppresser_iap_get_option( 'IAP_ARMEMBER_ACTIVE' ) ) { require_once( self::$dir_path . 'inc/apppresser-iap-armember.php' ); }
		}

		// check if Learndash plugin is active
		if (in_array(('sfwd-lms/sfwd_lms.php'),array_merge( (array) get_option('active_plugins', array()), array_keys( (array) get_site_option('active_sitewide_plugins', array()) ) ))) {
			self::$plugins['learndash'] = 'LearnDash';
			if ( 'Yes' === apppresser_iap_get_option( 'IAP_LEARNDASH_ACTIVE' ) ) { require_once( self::$dir_path . 'inc/apppresser-iap-learndash.php' ); }
		}

		// check if Restrict Content Pro plugin is active
		if (in_array(('restrict-content-pro/restrict-content-pro.php'),array_merge( (array) get_option('active_plugins', array()), array_keys( (array) get_site_option('active_sitewide_plugins', array()) ) ))) {
			self::$plugins['rcp'] = 'Restrict Content Pro';
			if ( 'Yes' === apppresser_iap_get_option( 'IAP_RCP_ACTIVE' ) ) { require_once( self::$dir_path . 'inc/apppresser-iap-rcp.php' ); }
		}

		// check if WooCommerce Memberships plugin is active
		if (in_array(('woocommerce-memberships/woocommerce-memberships.php'),array_merge( (array) get_option('active_plugins', array()), array_keys( (array) get_site_option('active_sitewide_plugins', array()) ) ))) {
			self::$plugins['woomp'] = 'WooCommerce Memberships';
			if ( 'Yes' === apppresser_iap_get_option( 'IAP_WOOMP_ACTIVE' ) ) { require_once( self::$dir_path . 'inc/apppresser-iap-woo.php' ); }
		}
	}

	/**
	 * Get membership objects for a supported integration so we can build
	 * human‑readable dropdowns instead of raw ID fields.
	 *
	 * Each item in the returned array has:
	 * - id    => the underlying membership/plan/course ID (string)
	 * - label => a human readable label (string)
	 *
	 * @param string $plugin_slug
	 * @return array
	 */
	protected function get_membership_options_for_plugin( $plugin_slug ) {
		if ( isset( self::$membership_options[ $plugin_slug ] ) ) {
			return self::$membership_options[ $plugin_slug ];
		}

		$options = array();

		switch ( $plugin_slug ) {
			case 'memberpress':
				// MemberPress membership products are stored as a CPT.
				if ( post_type_exists( 'memberpressproduct' ) ) {
					$products = get_posts(
						array(
							'post_type'      => 'memberpressproduct',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);

					foreach ( $products as $product ) {
						$options[] = array(
							'id'    => (string) $product->ID,
							'label' => sprintf(
								'%1$s (ID: %2$d)',
								$product->post_title,
								$product->ID
							),
						);
					}
				}
				break;

			case 'pmp':
				// Paid Memberships Pro.
				if ( function_exists( 'pmpro_getAllLevels' ) ) {
					$levels = call_user_func( 'pmpro_getAllLevels', false, true );

					if ( ! empty( $levels ) && is_array( $levels ) ) {
						foreach ( $levels as $level ) {
							$options[] = array(
								'id'    => (string) $level->id,
								'label' => sprintf(
									'%1$s (Level ID: %2$d)',
									$level->name,
									$level->id
								),
							);
						}
					}
				}
				break;

			case 'armember':
				// ARMember exposes plans via the $arm_subscription_plans object.
				global $arm_subscription_plans;

				if ( isset( $arm_subscription_plans ) ) {
					$plans = array();

					// Try common method names defensively to avoid fatals.
					if ( method_exists( $arm_subscription_plans, 'arm_get_all_plans' ) ) {
						$plans = $arm_subscription_plans->arm_get_all_plans();
					} elseif ( method_exists( $arm_subscription_plans, 'arm_get_all_membership_plans' ) ) {
						$plans = $arm_subscription_plans->arm_get_all_membership_plans();
					}

					if ( ! empty( $plans ) && is_array( $plans ) ) {
						foreach ( $plans as $plan ) {
							$id   = isset( $plan['arm_subscription_plan_id'] ) ? $plan['arm_subscription_plan_id'] : ( isset( $plan['id'] ) ? $plan['id'] : '' );
							$name = isset( $plan['arm_subscription_plan_name'] ) ? $plan['arm_subscription_plan_name'] : ( isset( $plan['name'] ) ? $plan['name'] : '' );

							if ( ! $id ) {
								continue;
							}

							$options[] = array(
								'id'    => (string) $id,
								'label' => $name ? sprintf( '%1$s (Plan ID: %2$s)', $name, $id ) : sprintf( 'Plan ID: %s', $id ),
							);
						}
					}
				}
				break;

			case 'learndash':
				// LearnDash courses.
				if ( function_exists( 'learndash_get_courses' ) ) {
					$courses = call_user_func(
						'learndash_get_courses',
						array(
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);
				} elseif ( post_type_exists( 'sfwd-courses' ) ) {
					$courses = get_posts(
						array(
							'post_type'      => 'sfwd-courses',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);
				} else {
					$courses = array();
				}

				if ( ! empty( $courses ) && is_array( $courses ) ) {
					foreach ( $courses as $course ) {
						$options[] = array(
							'id'    => (string) $course->ID,
							'label' => sprintf(
								'%1$s (Course ID: %2$d)',
								$course->post_title,
								$course->ID
							),
						);
					}
				}
				break;

			case 'rcp':
				// Restrict Content Pro – support both old and new APIs.
				if ( function_exists( 'rcp_get_membership_levels' ) ) {
					$levels = call_user_func( 'rcp_get_membership_levels' );
				} elseif ( function_exists( 'rcp_get_subscription_levels' ) ) {
					$levels = call_user_func( 'rcp_get_subscription_levels' );
				} else {
					$levels = array();
				}

				if ( ! empty( $levels ) && is_array( $levels ) ) {
					foreach ( $levels as $level ) {
						$id   = is_object( $level ) ? $level->id : ( isset( $level['id'] ) ? $level['id'] : '' );
						$name = is_object( $level ) ? $level->name : ( isset( $level['name'] ) ? $level['name'] : '' );

						if ( ! $id ) {
							continue;
						}

						$options[] = array(
							'id'    => (string) $id,
							'label' => $name ? sprintf( '%1$s (ID: %2$s)', $name, $id ) : sprintf( 'Level ID: %s', $id ),
						);
					}
				}
				break;

			case 'woomp':
				// WooCommerce Memberships.
				if ( function_exists( 'wc_memberships_get_membership_plans' ) ) {
					$plans = call_user_func( 'wc_memberships_get_membership_plans' );
				} elseif ( post_type_exists( 'wc_membership_plan' ) ) {
					$plans = get_posts(
						array(
							'post_type'      => 'wc_membership_plan',
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => 'title',
							'order'          => 'ASC',
						)
					);
				} else {
					$plans = array();
				}

				if ( ! empty( $plans ) && is_array( $plans ) ) {
					foreach ( $plans as $plan ) {
						if ( is_object( $plan ) && method_exists( $plan, 'get_id' ) ) {
							$id   = $plan->get_id();
							$name = method_exists( $plan, 'get_name' ) ? $plan->get_name() : '';
						} else {
							$id   = is_object( $plan ) ? $plan->ID : ( isset( $plan['id'] ) ? $plan['id'] : '' );
							$name = is_object( $plan ) ? $plan->post_title : ( isset( $plan['name'] ) ? $plan['name'] : '' );
						}

						if ( ! $id ) {
							continue;
						}

						$options[] = array(
							'id'    => (string) $id,
							'label' => $name ? sprintf( '%1$s (Plan ID: %2$s)', $name, $id ) : sprintf( 'Plan ID: %s', $id ),
						);
					}
				}
				break;
		}

		self::$membership_options[ $plugin_slug ] = $options;

		return $options;
	}

	public function IAP_tab($appp)
	{
		$appp->add_setting_tab(__('In App Purchases', 'apppresser'), 'iapsettings');
	}

	public function maybe_save_iap_data() {
		if ( ! isset( $_POST['submit-iap-settings'] ) ) { return; }
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage purchase settings.', 'apppresser' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'apppresser_iap_settings' );
		$this->save_iap_data();
		add_settings_error( 'apppresser_iap_settings', 'apppresser_iap_saved', __( 'Purchase mappings saved.', 'apppresser' ), 'updated' );
	}

	public function save_iap_data()
	{
		// ARMember
		if (!empty($_POST['iap-armember-active'])) {
			$iap_ARMember_active = $_POST['iap-armember-active'];
			apppresser_iap_update_option('IAP_ARMEMBER_ACTIVE', $iap_ARMember_active);

			// Sanitize and process form data
			$iap_ar = isset($_POST['iap_armember']) ? $_POST['iap_armember'] : array();
			$sanitized_ar_data = array();

			foreach ($iap_ar as $ar_single) {
				if (!empty($ar_single['iapID']) && !empty($ar_single['planID'])) {
					$sanitized_ar_data[] = array(
						'iapID' => sanitize_text_field($ar_single['iapID']),
						'planID' => sanitize_text_field($ar_single['planID']),
					);
				}
			}
			// Save tasks data into WordPress options
			apppresser_iap_update_option('IAP_ARMEMBER_KEYS', $sanitized_ar_data);
		} else {
			apppresser_iap_update_option('IAP_ARMEMBER_ACTIVE', '');
		}

		// MemberPress
		if (!empty($_POST['iap-memberpress-active'])) {
			$iap_memberpress_active = $_POST['iap-memberpress-active'];
			apppresser_iap_update_option('IAP_MEMBERPRESS_ACTIVE', $iap_memberpress_active);

			// Sanitize and process form data
			$iap_memberpress = isset($_POST['iap_memberpress']) ? $_POST['iap_memberpress'] : array();
			$sanitized_memberpress_data = array();

			foreach ($iap_memberpress as $iap_memberpress_single) {
				if (!empty($iap_memberpress_single['iapID']) && !empty($iap_memberpress_single['wpMemberPressID'])) {
					$sanitized_memberpress_data[] = array(
						'iapID' => sanitize_text_field($iap_memberpress_single['iapID']),
						'wpMemberPressID' => sanitize_text_field($iap_memberpress_single['wpMemberPressID']),
					);
				}
			}
			// Save tasks data into WordPress options
			apppresser_iap_update_option('IAP_MEMBERPRESS_KEYS', $sanitized_memberpress_data);
		} else {
			apppresser_iap_update_option('IAP_MEMBERPRESS_ACTIVE', '');
		}

		// LearnDash
		if (!empty($_POST['iap-learndash-active'])) {
			$iap_learndash_active = $_POST['iap-learndash-active'];
			apppresser_iap_update_option('IAP_LEARNDASH_ACTIVE', $iap_learndash_active);

			// Sanitize and process form data
			$iap_learndash = isset($_POST['iap_learndash']) ? $_POST['iap_learndash'] : array();
			$sanitized_learndash_data = array();

			foreach ($iap_learndash as $iap_learndash_single) {
				if (!empty($iap_learndash_single['iapID']) && !empty($iap_learndash_single['ldCoursePostId'])) {
					$sanitized_learndash_data[] = array(
						'iapID' => sanitize_text_field($iap_learndash_single['iapID']),
						'ldCoursePostId' => sanitize_text_field($iap_learndash_single['ldCoursePostId']),
					);
				}
			}
			// Save tasks data into WordPress options
			apppresser_iap_update_option('IAP_LEARNDASH_KEYS', $sanitized_learndash_data);
		} else {
			apppresser_iap_update_option('IAP_LEARNDASH_ACTIVE', '');
		}

		// Paid Membership Pro
		if (!empty($_POST['iap-pmp-active'])) {
			$iap_pmp_active = $_POST['iap-pmp-active'];
			apppresser_iap_update_option('IAP_PMP_ACTIVE', $iap_pmp_active);

			// Sanitize and process form data
			$iap_pmp = isset($_POST['iap_pmp']) ? $_POST['iap_pmp'] : array();
			$sanitized_pmp_data = array();

			foreach ($iap_pmp as $iap_pmp_single) {
				if (!empty($iap_pmp_single['iapID']) && !empty($iap_pmp_single['membershipLevelID'])) {
					$sanitized_pmp_data[] = array(
						'iapID' => sanitize_text_field($iap_pmp_single['iapID']),
						'membershipLevelID' => sanitize_text_field($iap_pmp_single['membershipLevelID']),
					);
				}
			}
			// Save tasks data into WordPress options
			apppresser_iap_update_option('IAP_PMP_KEYS', $sanitized_pmp_data);
		} else {
			apppresser_iap_update_option('IAP_PMP_ACTIVE', '');
		}

		// Restrict Content Pro
		if (!empty($_POST['iap-rcp-active'])) {
			$iap_rcp_active = $_POST['iap-rcp-active'];
			apppresser_iap_update_option('IAP_RCP_ACTIVE', $iap_rcp_active);

			// Sanitize and process form data
			$iap_rcp = isset($_POST['iap_rcp']) ? $_POST['iap_rcp'] : array();
			$sanitized_rcp_data = array();

			foreach ($iap_rcp as $iap_rcp_single) {
				if (!empty($iap_rcp_single['iapID']) && !empty($iap_rcp_single['rcpSubscriptionID'])) {
					$sanitized_rcp_data[] = array(
						'iapID' => sanitize_text_field($iap_rcp_single['iapID']),
						'rcpSubscriptionID' => sanitize_text_field($iap_rcp_single['rcpSubscriptionID']),
					);
				}
			}
			// Save tasks data into WordPress options
			apppresser_iap_update_option('IAP_RCP_KEYS', $sanitized_rcp_data);
		} else {
			apppresser_iap_update_option('IAP_RCP_ACTIVE', '');
		}

		// WooCommerce Memberships
		if (!empty($_POST['iap-woomp-active'])) {
			$iap_woomp_active = $_POST['iap-woomp-active'];
			apppresser_iap_update_option('IAP_WOOMP_ACTIVE', $iap_woomp_active);

			// Sanitize and process form data
			$iap_woomp = isset($_POST['iap_woomp']) ? $_POST['iap_woomp'] : array();
			$sanitized_woomp_data = array();

			foreach ($iap_woomp as $iap_woomp_single) {
				if (!empty($iap_woomp_single['iapID']) && !empty($iap_woomp_single['wooMembershipPlanID'])) {
					$sanitized_woomp_data[] = array(
						'iapID' => sanitize_text_field($iap_woomp_single['iapID']),
						'wooMembershipPlanID' => sanitize_text_field($iap_woomp_single['wooMembershipPlanID']),
					);
				}
			}
			// Save tasks data into WordPress options
			apppresser_iap_update_option('IAP_WOOMP_KEYS', $sanitized_woomp_data);
		} else {
			apppresser_iap_update_option('IAP_WOOMP_ACTIVE', '');
		}
	}

	public function appp_IAP_settings() {
		require __DIR__ . '/views/settings.php';
	}

	private function mapping_row( $plugin_slug, $plugin_name, $index, $keyname, $membership_label, $membership_options, $values ) {
		require __DIR__ . '/views/mapping-row.php';
	}

	public function settings_assets() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'apppresser_settings' ) ) { return; }
		wp_enqueue_style( 'apppresser-iap-settings', plugins_url( 'css/settings.css', __FILE__ ), array( 'apppresser-modules' ), (string) filemtime( __DIR__ . '/css/settings.css' ) );
		wp_enqueue_script( 'apppresser-iap-settings', plugins_url( 'js/settings.js', __FILE__ ), array( 'wp-a11y' ), (string) filemtime( __DIR__ . '/js/settings.js' ), true );
		wp_localize_script( 'apppresser-iap-settings', 'apppresserIAPSettings', array(
			'added' => __( 'Mapping added.', 'apppresser' ),
			'removed' => __( 'Mapping removed.', 'apppresser' ),
		) );
	}
}

AppPresser_IAP_Settings::get();
