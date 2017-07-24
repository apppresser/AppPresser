<?php
/**
 * Admin Settings Pages
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

/**
 * Admin Settings
 */
class AppPresser_Admin_Settings extends AppPresser {

	// A single instance of this class.
	public static $instance        = null;
	public static $page_slug       = 'apppresser_settings';
	public static $help_slug       = 'apppresser_sub_help_support';
	public static $menu_slug       = '';
	public static $extn_menu_slug  = '';
	public static $help_menu_slug  = '';
	public static $setting_menu_slug = '';
	public static $image_inputs    = array();
	public static $all_fields      = array();
	public static $field_args      = array();
	public static $admin_tabs      = array();
	public static $license_keys    = array();
	public static $license_fields  = array();
	public static $general_fields  = array();
	public static $customize_fields = array();
	public static $color_fields     = array();
	public static $advanced_fields  = array();
	public static $v2only_fields   = array();
	public static $deprecate_ver   = 0;

	/**
	 * Creates or returns an instance of this class.
	 * @since  1.0.0
	 * @return AppPresser_Admin_Settings A single instance of this class.
	 */
	public static function run() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Setup the AppPresser Settings
	 * @since  1.0.0
	 */
	function __construct() {
		// Manually clear cookies?
		if ( isset( $_GET['clear_app_cookie'] ) && 'true' === $_GET['clear_app_cookie'] ) {
			self::clear_cookie();
		}

		$this->set_deprecate_version();

		// Get all themes
		$this->themes    = wp_get_themes();
		// Get all nav menus
		$this->nav_menus = wp_get_nav_menus();

		// Adds the license field to settings
		add_filter('apppresser_theme_settings_file', function() { return get_theme_root() . '/ap3-ion-theme/appp-settings.php'; });

		// include theme settings file if it exists
		$this->get_theme_settings_file();

		add_action( 'admin_menu', array( $this, 'plugin_menu' ), 9 );
		add_filter( 'sanitize_option_'. AppPresser::SETTINGS_NAME, array( $this, 'maybe_reset_license_statuses' ), 99 );
		add_action( 'update_option_' . AppPresser::SETTINGS_NAME, array( $this, 'save_theme_mods'), 99, 2 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'verify_apptheme_slug' ) );
		add_action( 'apppresser_add_settings', array( $this, 'add_settings' ), 6 ); // Higher priority
		add_filter( 'apppresser_field_markup_text', array( $this, 'ajax_container' ), 10, 2 );
		add_action( 'wp_ajax_appp_search_post_handler', array( $this, 'ajax_post_results' ) );
		add_action( 'admin_head', array( $this, 'icon_styles' ) );
		add_action( 'wp_ajax_appp_hide_quickstart', array( $this, 'ajax_hide_quickstart' ) );
		add_action( 'after_appp_add_settings', array( $this, 'migrate_theme_mods' ) );

	}


	/**
	 * phonegap_versions function.
	 *
	 * @access public
	 * @return array
	 */
	public function phonegap_versions() {

		return $pg_versions = array( '3.5.0' => '3.5.0', '3.6.3' => '3.6.3', '3.7.0' => '3.7.0' );

	}


	/**
	 * get_theme_settings_file function.
	 *
	 * @access public
	 * @return void
	 */
	public function get_theme_settings_file() {
		// Get saved apppresser theme
		$appp_theme = self::settings( 'appp_theme' );

		// Check for the 'Use different theme for app?' option
		if ( $appp_theme ) {

			// If admin only theme object exists
			if ( isset( $this->themes[ $appp_theme ] ) && is_callable( array( $this->themes[ $appp_theme ], 'get_template_directory' ) ) ) {

				// Let themes override the location/name of the file
				$file_override = apply_filters( 'apppresser_theme_settings_file', '' );
				if ( $file_override && file_exists( $file_override ) ) {
					require_once( $file_override );
				}
				// Check child theme directory first
				$dir = $this->themes[ $appp_theme ]->get_stylesheet_directory();

				// If there is a 'appp-settings.php' file,
				if ( file_exists( $dir .'/appp-settings.php' ) ) {
					// include it
					return require_once( $dir .'/appp-settings.php' );
				}
				// Ok, check parent theme directory
				$dir = $this->themes[ $appp_theme ]->get_template_directory();
				// If there is a 'appp-settings.php' file,
				if ( file_exists( $dir .'/appp-settings.php' ) ) {
					// include it
					return require_once( $dir .'/appp-settings.php' );
				}
			}
		}
		// Otherwise if there is a 'appp-settings.php' file in the currently active theme,
		elseif ( file_exists( get_stylesheet_directory_uri() .'/appp-settings.php' ) ) {
			// include it
			return require_once( get_stylesheet_directory_uri() .'/appp-settings.php' );
		}

		/**
		 * @since 3.0.2
		 * If not upgrading from AP2, new installs may not have 
		 * appp_theme setting so include AP3 theme settings here
		 */
		$file_override = apply_filters( 'apppresser_theme_settings_file', '' );
		if ( $file_override && file_exists( $file_override ) ) {
			require_once( $file_override );
		}
	}

	/**
	 * Create AppPresser Settings menus
	 * @since  1.0.0
	 */
	function plugin_menu() {

		$page_title = __( 'AppPresser', 'apppresser' );
		// Create main menu and settings page
		self::$menu_slug = add_menu_page( $page_title, $page_title, 'manage_options', self::$page_slug, array( $this, 'settings_page' ) );

		// Settings page submenu item
		self::$setting_menu_slug = add_submenu_page( self::$page_slug, __( 'Settings', 'apppresser' ), __( 'Settings', 'apppresser' ), 'manage_options', self::$page_slug, array( $this, 'settings_page' ) );

		// Help page submenu item
		self::$help_menu_slug = add_submenu_page( self::$page_slug, __( 'Help / Support', 'apppresser' ), __( 'Help / Support', 'apppresser' ), 'manage_options', self::$help_slug, array( $this, 'help_support_page' ) );

		add_action( 'admin_head-' . self::$menu_slug, array( $this, 'admin_head' ) );

		// enqueue
		foreach ( array( self::$menu_slug, self::$help_menu_slug, self::$setting_menu_slug ) as $slug ) {
			add_action( 'admin_print_scripts-' . $slug, array( $this, 'admin_scripts' ) );
		}

		add_action('admin_head', array( $this, 'apppush_admin_css' ) );

		// Add notification bubble if any notifications
		if ( $notifications = $this->notification_badge() ) {

			global $menu;
			// Add the notification bubble to our top level menu
			foreach ( $menu as $menu_key => $menu_item ) {
				if ( isset( $menu_item[2] ) && self::$page_slug == $menu_item[2] ) {
					$menu[ $menu_key ][0] = $menu_item[0] . $notifications;
				}
			}
		}

	}

	/**
	 * Even though we are no longer using the customizer for appp_settings, we
	 * still need the menu choices saved into the theme_mods_{theme_slug} option
	 * 
	 * hooks into the update_option_{appp_settings}
	 * 
	 * @since 2.7.0
	 */
	public function save_theme_mods($old_appp_settings, $appp_settings) {

		if( isset( $appp_settings['appp_theme'] ) ) {

			$stylesheet = $appp_settings['appp_theme'];

			// get our existing theme_mods
			$theme_mod = get_option( 'theme_mods_' . $stylesheet );

			// Menus
			if( isset( $appp_settings['menu'] ) || isset( $appp_settings['secondary_menu'] ) ) {
				// nav_menu_locations ?
				if( $theme_mod === false || ! isset( $theme_mod['nav_menu_locations'] ) ) {
					$theme_mod['nav_menu_locations'] = array();
				}

				if( isset( $appp_settings['menu'] ) ) {
					$theme_mod['nav_menu_locations']['primary-menu'] = (int)$appp_settings['menu']; // ion
					$theme_mod['nav_menu_locations']['primary'] = (int)$appp_settings['menu'];      // apptheme
				}

				if( isset( $appp_settings['secondary_menu'] ) ) {
					$theme_mod['nav_menu_locations']['footer-menu'] = (int)$appp_settings['secondary_menu'];
				}

				if( isset( $appp_settings['top_menu'] ) ) {
					$theme_mod['nav_menu_locations']['top'] = (int)$appp_settings['top_menu']; // apptheme
				}

				if( isset( $appp_settings['top_2_menu'] ) ) {
					$theme_mod['nav_menu_locations']['top2'] = (int)$appp_settings['top_2_menu']; // apptheme
				}
			}

			$settings_keys = array(
				'list_control' => '',
				'ab_color_mod' => '',
				'ab_image_mod' => '',
				'ab_text_mod' => '',
				'ap_color_mod' => '',
				'slider_control' => 'int',
				'slider_category_control' => '',
			);

			foreach ($settings_keys as $key => $type) {
				if( isset( $appp_settings[$key] ) ) {
					if($type == 'int') {
						$theme_mod[$key] = (int)$appp_settings[$key];
					} else {
						$theme_mod[$key] = $appp_settings[$key];
					}
				}
			}

			if( isset( $appp_settings['theme_mods_'.$stylesheet]) ) {
				foreach ( $appp_settings['theme_mods_'.$stylesheet] as $color_key => $color_value ) {
					$theme_mod[$color_key] = $color_value;
				}
			}

			update_option( 'theme_mods_' . $stylesheet, $theme_mod );
		}

	}

	/**
	 * This runs pretty late because we need to wait until the color settings
	 * are loaded by the theme's customer class
	 * @since 2.7.0
	 */
	public function migrate_theme_mods() {
		require_once( self::$inc_path . 'AppPresser_Settings_Migration.php' );
		$migrate = new AppPresser_Settings_Migration();
		$migrate->migrate_check();
	}

	/**
	 * Admin scripts and styles
	 * @since  1.0.0
	 */
	function admin_scripts() {
		// admin scripts and styles
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'app-color-picker', plugins_url('js/app-color-picker.js', dirname( __FILE__ ) ), array( 'wp-color-picker' ), false, true );
		wp_enqueue_script( 'appp-admin', self::$js_url . 'appp-admin.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-tooltip', 'wp-color-picker' ), self::VERSION );
		wp_enqueue_script( 'appp-admin', self::$js_url . 'appp-admin.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-tooltip' ), self::VERSION );
		wp_enqueue_style( 'jquery-ui-smoothness', self::$css_url . 'smoothness/smoothness.custom.min.css' );
		wp_enqueue_style( 'appp-admin-styles', self::$css_url . 'appp-admin-styles.css', null, self::VERSION );
		wp_enqueue_media();
	}

	function apppush_admin_css() {
		global $post_type;
		if (( isset( $_GET['post_type'] ) && $_GET['post_type'] == 'apppush') || ($post_type == 'apppush')) {		
			echo "<link type='text/css' rel='stylesheet' href='" . self::$css_url . "appp-admin-styles.css' />";
		}
	}

	/**
	 * Easy hook for adding to the admin_head on the AppPresser settings page
	 * @since  1.0.0
	 */
	function admin_head() {
		$appp_settings = self::run();
		do_action( 'appp_admin_settings_head', $appp_settings );
	}

	/**
	 * Include css for modifying menu icon
	 * @since  1.0.0
	 */
	function icon_styles() {
		require_once( self::$dir_path . 'css/icon-styles.php' );
	}

	/**
	 * Register AppPresser Settings with Settings API.
	 * @since  1.0.0
	 */
	function register_settings() {
		register_setting( 'appp_settings_group', self::SETTINGS_NAME, array( $this, 'settings_validate' ) );
	}

	/**
	 * AppPresser Settings validation
	 *
	 * @since  1.0.0
	 * @param  array $settings The input array we want to validate
	 * @return array         Our sanitized inputs
	 */
	function settings_validate( $settings ) {

		$appp_settings = self::run();
		// sanitize the settings data submitted
		foreach ( $settings as $key => $value ) {
			switch ( $key ) {
				case 'menu':
					$cleaninput[ $key ] = absint( $value );
					break;
				case 'mobile_browser_theme_switch':
					// Clear cookie
					self::clear_cookie();
					$cleaninput[ $key ] = isset( $settings[ $key ] ) && $settings[ $key ] == 'on' ? 'on' : '';
					break;
				case 'admin_theme_switch':
					$cleaninput[ $key ] = isset( $settings[ $key ] ) && $settings[ $key ] == 'on' ? 'on' : '';
					break;
				case 'theme_mods_' . appp_get_setting('appp_theme'):
					if( is_array( $value ) ) {
						$cleaninput[ $key ] = $value;
					}
					break;
				default:
					// Allow sanitization override
					$filtered_value = apply_filters( "apppresser_sanitize_setting_$key", null, $value, $settings, $appp_settings );
					// If no override, sanitize the value ourselves
					if( null === $filtered_value && is_string($value)) {
						$filtered_value = sanitize_text_field( $value );
					} else {
						$filtered_value = $value;
					}
					// And fallback sanitization hook (mostly for backwards compatibility)
					$cleaninput[ $key ] = apply_filters( 'apppresser_sanitize_setting', $filtered_value, $key, $value, $settings, $appp_settings );
					break;
			}

			// Check for registered license option keys
			if ( array_key_exists( $key, self::license_keys() ) ) {
				// Get old value for comparison
				$old = appp_get_setting( $key );

				// we'll do a license check if the old value was either:
				// empty, different, or the license status was not valid
				if ( ! $old || $old != $value 
					|| ( isset( $settings[$key.'_status'] ) && $settings[$key.'_status'] !== 'valid' ) ) {
					
					// if updated, trigger a status check
					$this->reset_status[] = $key;
				}
			}

		}

		// Don't delete license keys and other options if a particular plugin is deactivated at the time of saving.

		// Get existing options
		$existing = is_array( appp_get_setting() ) ? appp_get_setting() : array();
		// Check for keys differing keys from existing option
		$diff = array_diff_key( $existing, $cleaninput );
		// Loop through any differeing keys
		foreach ( (array) $diff as $field_id => $value ) {

			// If the field is still registered, ignore it
			if ( !! self::get_all_fields( $field_id ) )
				continue;

			// If we get here, the field is no longer registered and so the option should be preserved.
			$cleaninput[ $field_id ] = $diff[ $field_id  ];
		}

		return $cleaninput;
	}

	/**
	 * Checks for license keys and saves a _status option
	 * @since  1.0.0
	 * @param  array  $data Options array
	 * @return array        Modified array
	 */
	public function maybe_reset_license_statuses( $data ) {
		// If reset_status is flagged,
		if ( isset( $this->reset_status ) && is_array( $this->reset_status ) ) {
			// loop through them
			foreach ( $this->reset_status as $key ) {
				// And re-verify the extention's license status
				$keys = self::license_keys();
				$plugin = isset( $keys[ $key ] ) ? $keys[ $key ] : false;

				$data[ $key .'_status' ] = appp_get_license_status( $data[ $key ], $plugin );
			}
		}
		return $data;
	}

	/**
	 * AppPresser main settings page output
	 * @since  1.0.0
	 */
	public function settings_page() {


		$appp_settings = self::run();
		// Add settings tabs/inputs via this hook. The AppPresser_Admin_Settings instance is passed in.
		do_action( 'apppresser_add_settings', $appp_settings );

		$class = self::$page_slug;
		$class .= self::is_mp6() ? ' mp6' : '';

		?>
		<div class="wrap <?php echo $class; ?>">
			<?php
			$keys = array_keys( self::$admin_tabs );
			$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : array_shift( $keys );
			$current_tab = preg_replace('/tab-/', '', $current_tab, 1 );

			// Our tabs
			echo '<h2 class="nav-tab-wrapper">';
			foreach ( self::$admin_tabs as $tab => $name ) {
				$current_class = $tab == $current_tab ? ' nav-tab-active' : '';
				echo '<a class="nav-tab'. $current_class .'" data-selector="tab-'. $tab .'" href="?page='. self::$page_slug .'&tab=tab-'. $tab .'">'. $name .'</a>';
			}
			echo '</h2>';


			?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'appp_settings_group' );
				// Our tabbed areas
				foreach ( self::$admin_tabs as $tab => $name ) {

					$current_class = $tab == $current_tab ? ' nav-tab-active' : '';

					$has_gen_subtab = ( isset( self::$general_fields[ $tab ] ) );
					$has_adv_subtab = ( isset( self::$advanced_fields[ $tab ] ) );
					$has_cst_subtab = ( isset( self::$customize_fields[ $tab ] ) );
					$has_clr_subtab = ( isset( self::$color_fields[ $tab ] ) );
					$has_lic_subtab = ( $tab == 'general' && count( self::$license_fields ) );
					$has_v2_subtab  = ( isset( self::$v2only_fields[ $tab ] ) );
					$subtab_links = array();

					// If the only tab is general, don't show it
					if ( ! $has_adv_subtab && ! $has_lic_subtab && ! $has_v2_subtab ) {
						$has_gen_subtab = false;
					}

					if( $has_gen_subtab ) {
						$subtab_links[] = '<li><a href="?page=apppresser_settings&tab=tab-'.$tab.'&subnav=general" class="subnav-tab current" id="tab-'.$tab.'-subnav-general" data-selector="general-subtab.subtab-'.$tab.'">' . __('General', 'apppresser') . '</a>'
							. ( ($has_adv_subtab || $has_lic_subtab || $has_v2_subtab)?' | ':'' ) . '</li>';

					}
					if( $has_cst_subtab ) {
						$subtab_links[] = '<li><a href="?page=apppresser_settings&tab=tab-'.$tab.'&subnav=customize" class="subnav-tab" id="tab-'.$tab.'-subnav-customize"  data-selector="customize-subtab.subtab-'.$tab.'">' . __('Customize', 'apppresser') . '</a>'
							. ( ($has_clr_subtab || $has_adv_subtab || $has_lic_subtab || $has_v2_subtab)?' | ':'' ) . '</li>';
					}
					if( $has_clr_subtab ) {
						$subtab_links[] = '<li><a href="?page=apppresser_settings&tab=tab-'.$tab.'&subnav=color" class="subnav-tab" id="tab-'.$tab.'-subnav-color"  data-selector="color-subtab.subtab-'.$tab.'">' . __('Colors', 'apppresser') . '</a>'
							. ( ($has_adv_subtab || $has_lic_subtab || $has_v2_subtab)?' | ':'' ) . '</li>';
					}
					if( $has_adv_subtab ) {
						$subtab_links[] = '<li><a href="?page=apppresser_settings&tab=tab-'.$tab.'&subnav=advanced" class="subnav-tab" id="tab-'.$tab.'-subnav-advanced"  data-selector="advanced-subtab.subtab-'.$tab.'">' . __('Advanced', 'apppresser') . '</a>'
							. ( ($has_lic_subtab || $has_v2_subtab)?' | ':'' ) . '</li>';
					}
					if( $has_v2_subtab ) {
						$subtab_links[] = '<li><a href="?page=apppresser_settings&tab=tab-'.$tab.'&subnav=v2-only" class="subnav-tab" id="tab-'.$tab.'-subnav-v2-only"  data-selector="v2-only-subtab.subtab-'.$tab.'">' . __('AppPresser 2', 'apppresser') . '</a>'
							. ( ($has_lic_subtab)?' | ':'' ) . '</li>';
					}
					if( $has_lic_subtab ) {
						$subtab_links[] = '<li><a href="?page=apppresser_settings&tab=tab-'.$tab.'&subnav=license" class="subnav-tab" id="tab-'.$tab.'-subnav-license"  data-selector="license-subtab.subtab-'.$tab.'">' . __('Licenses', 'apppresser') . '</a></li>';
					}

					echo '<table class="appp-tabs form-table tab-'. $tab . $current_class .'">';
						// A hook for adding additional data to the top of each tabbed area
						do_action( "apppresser_tab_top_$tab", $appp_settings, self::settings() );

						if( $has_gen_subtab || $has_v2_subtab || $has_lic_subtab || $has_cst_subtab ) {
							echo '<tr class="subtabs-wrapper">';
							echo '<td colspan="2">';
							echo '<ul class="subsubsub">';
							echo implode("\n", $subtab_links);
							echo '</ul><div style="clear:both"></div>';
						}
						
						// Tab main content or General subtab content
						if ( isset( self::$all_fields[ $tab ] ) ) {
							if( $has_gen_subtab ) {
								echo '<table class="appp-subtab general-subtab subtab-'.$tab.' subnav-tab-active">';
								do_action( "apppresser_tab_".$tab."_subtab_general_top", $appp_settings, self::settings() );
							}
							echo implode( "\n", self::$all_fields[ $tab ] );
							if( $has_gen_subtab ) {
								do_action( "apppresser_tab_".$tab."_subtab_general_bottom", $appp_settings, self::settings() );
								echo '</table>';
							}
						}

						// Advanced Tab
						if( $has_adv_subtab ) {
							echo '<table class="appp-subtab advanced-subtab subtab-'.$tab.'">';
							do_action( "apppresser_tab_".$tab."_subtab_advanced_top", $appp_settings, self::settings() );
							echo implode( "\n", self::$advanced_fields[ $tab ] );
							do_action( "apppresser_tab_".$tab."_subtab_advanced_bottom", $appp_settings, self::settings() );
							echo '</table>';
						}

						// License Tab
						if( $has_lic_subtab ) {
							echo '<table class="appp-subtab license-subtab subtab-'.$tab.'">';
							echo '<tr valign="top" class="apppresser-apppresser-core-settings"><th colspan="2" scope="row" class="appp-section-title"><h3>'.__('AppPresser Licenses','apppresser').'</h3></th></tr>';
							do_action( "apppresser_tab_general_subtab_license_top", $appp_settings, self::settings() );
							foreach (self::$license_fields as $key => $subtab_licenses) {
								echo implode( "\n",  $subtab_licenses );
							}
							do_action( "apppresser_tab_general_subtab_license_bottom", $appp_settings, self::settings() );
							echo '</table>';
						}

						if ( isset( self::$all_fields[ $tab ] ) ) {
							// v2 Tab
							if( $has_v2_subtab ) {
								echo '<table class="appp-subtab v2-only-subtab subtab-'.$tab.'">';
								echo '<tr valign="top" class="apppresser-apppresser-core-settings"><th colspan="2" scope="row" class="appp-section-title"><h3>'.__('AppPresser 2 only','apppresser').'</h3>
								<h4>'. __('These settings are only for AppPresser 2', 'apppresser') .'</h4>
								</th></tr>';
								do_action( "apppresser_tab_".$tab."_subtab_v2-only_top", $appp_settings, self::settings() );
								echo implode( "\n", self::$v2only_fields[ $tab ] );
								do_action( "apppresser_tab_".$tab."_subtab_v2-only_bottom", $appp_settings, self::settings() );
								echo '</table>';
							}
						}


						// Customize Tab
						if( $has_cst_subtab ) {
							echo '<table class="appp-subtab customize-subtab subtab-'.$tab.'">';
							echo '<tr valign="top" class="apppresser-apppresser-core-settings"><th colspan="2" scope="row" class="appp-section-title"><h3>'.__('Customize Your App Theme (v2 only)','apppresser').'</h3></th></tr>';
							do_action( "apppresser_tab_general_subtab_customize_top", $appp_settings, self::settings() );
							foreach (self::$customize_fields as $key => $subtab_customize) {
								echo implode( "\n",  $subtab_customize );
							}
							do_action( "apppresser_tab_general_subtab_customize_bottom", $appp_settings, self::settings() );
							echo '</table>';
						}

						// Color Tab
						if( $has_clr_subtab ) {
							echo '<table class="appp-subtab color-subtab subtab-'.$tab.'">';
							echo '<tr valign="top" class="apppresser-apppresser-core-settings"><th colspan="2" scope="row" class="appp-section-title"><h3>'.__('App Colors (v2 only)','apppresser').'</h3></th></tr>';
							do_action( "apppresser_tab_general_subtab_color_top", $appp_settings, self::settings() );
							foreach (self::$color_fields as $key => $subtab_color) {
								echo implode( "\n",  $subtab_color );
							}
							do_action( "apppresser_tab_general_subtab_color_bottom", $appp_settings, self::settings() );
							echo '</table>';
						}

						if( $has_gen_subtab || $has_adv_subtab || $has_lic_subtab || $has_v2_subtab || $has_cst_subtab || $has_clr_subtab ) {
							echo '</td>';
							echo '</tr>';
						}

						// A hook for adding additional data to the bottom of each tabbed area
						do_action( "apppresser_tab_bottom_$tab", $appp_settings, self::settings() );
					echo '</table>';
				}

				foreach ( self::license_keys() as $key => $file ) {
					echo '<input type="hidden" name="appp_settings['. $key .'_status]" value="'. appp_get_setting( $key .'_status' ) .'">';
				}
				?>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Settings', 'apppresser' ); ?>" />
					<?php
					// Content hooked in here will show/hide with the tabs
					// Read: separate secondary buttons for each settings tab
					foreach ( self::$admin_tabs as $tab => $name ) {
						$current_class = $tab == $current_tab ? ' nav-tab-active' : '';
						echo '<span class="appp-tabs tab-'. $tab . $current_class .'">';
						do_action( "apppresser_tab_buttons_$tab", $appp_settings, self::settings() );
						echo '</span>';
					}
					?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Adds AppPresser Core's settings
	 * @since 1.0.0
	 */
	public function add_settings() {

		// Main tab
		self::add_setting_tab( __( 'AppPresser', 'apppresser' ), 'general' );
		
		if( self::is_deprecated( 2 ) )
			self::add_setting_label( __( 'AppPresser Core Settings', 'apppresser' ), array(
				'subtab' => 'general',
			) );

		self::add_setting( 'appp_theme', __( 'App-only theme', 'apppresser' ), array(
			'type' => 'select',
			'options' => apply_filters( 'filter_appthemes', $this->themes ),
			'helptext' => __( 'Select which theme you want to be loaded inside the app, such as the AppPresser theme.', 'apppresser' ),
			'description' => __( 'Must be enabled above.', 'apppresser' ),
			'subtab' => 'v2-only',
			'deprecated' => 2,
		) );

		// Deprecated in 2.7.0
		/*self::add_setting( 'customizer_link', __( 'App Design', 'apppresser' ), array(
			'type' => 'paragraph',
			'helptext' => __( 'Opens the customizer to customize the look of your app.', 'apppresser' ),
			'value' => __( '<span></span>', 'apppresser' ),
			'description' => __( 'Click here to customize app colors, menus, homepage & more.', 'apppresser' ),
			'subtab' => 'v2-only',
			'deprecated' => 2,
		) );*/

		self::add_setting( 'appp_show_on_front', __( 'Use a unique homepage for your app.', 'apppresser' ), array(
			'type' => 'radio',
			'options' => array('latest_posts' => 'Your latest posts', 'static_page' => 'A static page (select below)' ),
			'helptext' => __( 'Allows you to specify which page users will see first when they load up you AppPresser app.', 'apppresser' ),
			'description' => __( 'Select homepage option.', 'apppresser' ),
			'subtab' => 'v2-only',
			'deprecated' => 2,
		) );

		self::add_setting( 'appp_home_page', '', array(
			// 'helptext' => __( 'Allows you to specify which page users will see first when they load up you AppPresser app.', 'apppresser' ),
			'description' => __( 'Start typing to search for a page, or enter a page ID.', 'apppresser' ),
			'subtab' => 'v2-only',
			'deprecated' => 2,
		) );

		if( self::$deprecate_ver < 2 ) {
			self::add_setting_label( __( 'AppPresser 3 Settings', 'apppresser' ), array(
				'subtab' => 'general',
			) );
		}

		self::add_setting_label( __( 'AppPresser 3 Settings', 'apppresser' ) );

		self::add_setting( 'ap3_site_slug', __( 'Site slug', 'apppresser' ), array( 'type' => 'text', 'helptext' => __( 'Find this by logging into your myapppresser.com dashboard, choose your app, General tab => API Settings', 'apppresser' ) ) );
		self::add_setting( 'ap3_app_id', __( 'App ID', 'apppresser' ), array( 'type' => 'text', 'helptext' => __( 'Find this by logging into your myapppresser.com dashboard, choose your app, General tab => API Settings', 'apppresser' ) ) );

		self::add_setting_label( __( 'Advanced Settings', 'apppresser' ), array(
			'subtab' => 'v2-only',
			'deprecated' => 2,
		) );

		self::add_setting( 'app_offline_toggle', __( 'Disable offline toggle buttons?', 'apppresser' ), array(
			'type' => 'checkbox',
			'helptext' => __( 'When the app disconnects from the internet, the app will display buttons that allows the user to switch to a customized offline.html file located in the app or return to the WordPress site. AppPresser 2 only.', 'apppresser' ),
			'description' => __( 'Don\'t allow the user to switch between online and offline mode when connection is lost.', 'apppresser' ),
			'subtab' => 'v2-only',
			'deprecated' => 2,
		) );

		// For now...
		if ( appp_get_setting( 'mobile_browser_theme_switch' ) ) {
			self::add_setting( 'mobile_browser_theme_switch', __( 'Load AppPresser for mobile browsers', 'apppresser' ), array(
				'type' => 'checkbox',
				'helptext' => __( 'Display AppPresser in mobile browsers such as Safari and Chrome, instead of your normal theme.', 'apppresser' ),
				'subtab' => 'v2-only',
				'deprecated' => 2,
			) );
		}

		self::add_setting( 'admin_theme_switch', __( 'Load AppPresser for Admins Only', 'apppresser' ), array(
			'type' => 'checkbox',
			'helptext' => __( 'Check this if you want to test your AppPresser app without loading it for visitors to your site.', 'apppresser' ),
			'description' => __( '(for testing purposes)', 'apppresser' ),
			'subtab' => 'v2-only',
			'deprecated' => 2,
		) );

		self::add_setting( 'appp_pg_version', __( 'Phonegap Version', 'apppresser' ), array(
			'type' => 'select',
			'options' => $this->phonegap_versions(),
			'helptext' => __( 'Select the Phonegap Version of your app.', 'apppresser' ),
			'description' => __( 'Select Phonegap Version. <b>For AppPresser 1 only</b>', 'apppresser' ),
			'subtab' => 'v2-only',
			'deprecated' => 1,
		) );
		
		if( self::$deprecate_ver ) {
			self::add_setting( 'apppresser_deprecate', __( 'Version 2', 'apppresser' ), array(
				'type' => 'paragraph',
				'value' => $this->toggle_deprecated_version(),
				'helptext' => __( 'Disable/Enable all functionality for version 2', 'apppresser' ),
				'subtab' => 'general',
		) );
		}

		$menus = array( 'option-none' => __( '-- select --', 'apppresser' ) );
		foreach ( (array) $this->nav_menus as $menu ) {
			$menus[ $menu->term_id ] = $menu->name;
		}

		// Register main menu setting
		self::add_setting( 'menu', __( 'Main App Menu', 'apppresser' ), array(
			'type' => 'select',
			'options' => $menus,
			'helptext' => __( 'Use a custom main menu inside your app, different from your main site.', 'apppresser' ),
			'subtab' => 'customize',
		) );

		// Register secondary  menu setting
		self::add_setting( 'secondary_menu', __( 'Secondary App Menu', 'apppresser' ), array(
			'type' => 'select',
			'options' => $menus,
			'helptext' => __( 'Use a custom secondary menu inside your app (the top right dropdown in the header).', 'apppresser' ),
			'subtab' => 'customize',
		) );

		// Let apptheme and child themes add more menu settings here.
		// Also, be sure to hook into the options_update_{appp_settings} to
		// copy the menus appp_settings to the theme_mods_{$stylesheet} options
		do_action( 'appp_custom_menu_settings', $this, $menus );

		self::add_setting( 'appp_logo', __( 'App Logo', 'apppresser' ), array(
			'type' => 'file',
			'helptext' => '',
			'description' => 'URL to image',
			'subtab' => 'customize',
		) );

		self::add_setting( 'mobile_list_style', __( 'List Style', 'apppresser' ), array(
			'type' => 'select',
			'options' => array(
				'Thumbnail list',
				'No thumbnails',
				'Card List',
			),
			'subtab' => 'customize',
		) );

		if( class_exists( 'AppPresser_Swipers' ) ) {
			self::add_setting_label( __( 'AppSwiper Settings', 'apppresser' ), array(
				'subtab' => 'customize',
			) );

			self::add_setting('slider_control', __('Add slider to homepage?', 'apppresser'), array(
				'type' => 'checkbox',
				'subtab' => 'customize',
			) );


			// AppSwiper Category dropdown
			$categories = get_categories( array(
			    'orderby' => 'name',
			    'order'   => 'ASC'
			) );

			$_cats = array('all'=>'All');

			foreach ( $categories as $cat ) {
				$_cats[$cat->slug] = $cat->name;
			}

			self::add_setting( 'slider_category_control', __( 'What category?', 'apppresser' ), array(
				'type' => 'select',
				'options' => $_cats,
				'subtab' => 'customize',
			) );
			
		}

		if( class_exists( 'AppBuddy' ) ) {

			self::add_setting_label( __( 'AppBuddy Settings', 'apppresser' ), array(
				'subtab' => 'customize',
			) );

			self::add_setting('ab_color_mod', __('Login Screen Background Color', 'apppresser'), array(
				'type' => 'color',
				'default' => apply_filters( 'appp_ab_color_mod', '#FFFFFF' ),
				'subtab' => 'customize',
			) );

			self::add_setting('ab_image_mod', __('Login Screen Background Image', 'apppresser'), array(
				'type' => 'file',
				'subtab' => 'customize',
			) );

			self::add_setting('ab_text_mod', __('Login Screen Text', 'apppresser'), array(
				'type' => 'text',
				'subtab' => 'customize',
			) );
		}


		add_action( 'apppresser_tab_buttons_general', array( $this, 'help_link' ) );

		// Allow other plugins or themes to add more settings
		do_action( 'after_appp_add_settings', $this );
	}

	/**
	 * Add a link to the help page in the main settings tab
	 * @since  1.0.0
	 */
	public function help_link() {
		echo '<a href="'. esc_url( add_query_arg( 'page', self::$help_slug, admin_url( 'admin.php' ) ) ) .'">'. __( 'Help/Support', 'apppresser' ) .'</a>';
	}

	public function toggle_deprecated_version() {
		if( ! AppPresser::$deprecate_ver ) {
			return '<p><a href="'. esc_url( add_query_arg( 'appp_deprecate_ver', '2', add_query_arg( 'page', self::$page_slug, admin_url( 'admin.php' ) ) ) ) .'">'. __( 'Deprecate AppPresser 2', 'apppresser' ) .'</a></p>';
		} else {
			return '<p><a href="'. esc_url( add_query_arg( 'appp_deprecate_ver', '0', add_query_arg( 'page', self::$page_slug, admin_url( 'admin.php' ) ) ) ) .'">'. __( 'Enable AppPresser 2', 'apppresser' ) .'</a></p>';
		}
	}

	/**
	 * Add ajax spinner/results container to homepage selector field
	 * @since  1.0.0
	 * @param  string  $html Input html
	 * @param  string  $key  Option field key
	 * @return string        Modified input html
	 */
	public function ajax_container( $html, $key ) {
		if ( $key === 'appp_home_page' ) {
			$html .= '
			<p class="appp-spinner spinner"></p>
			<p class="appp-ajax-results-help">'. __( 'Select a page:', 'apppresser' ) .'</p>
			<div class="appp-ajax-results-posts"></div>
			';
		}
		return $html;
	}

	/**
	 * Adds a setting section to AppPresser's settings
	 * @since  1.0.0
	 * @param  string  $key     Option key
	 * @param  string  $label   Option label
	 * @param  array   $args    Array of possible options for select
	 * @return mixed   $_field  Setting.
	 */
	public static function add_setting( $key, $label, $args = array() ) {

		if( isset( $args['deprecated'] ) && $args['deprecated'] <= self::$deprecate_ver )
			return;

		$appp_settings = self::run();
		$value = self::settings( $key );

		$keys = array_keys( self::$admin_tabs );
		$defaults = array(
			'type'        => 'text',
			'helptext'    => '',
			'description' => '',
			'options'     => array(),
			'tab'         => isset( $args['echo'] ) && $args['echo'] ? 'echoed' : array_shift( $keys ),
			'echo'        => false,
			'subtab'      => '',
		);
		$args = wp_parse_args( $args, $defaults );

		// Clean values
		$key     = esc_attr( $key );
		$label   = sanitize_text_field( $label );
		$type    = esc_attr( $args['type'] );
		$options = is_array( $args['options'] ) ? $args['options'] : array();
		$help    = ! empty( $args['helptext'] ) ? '<a class="help" href="#" title="'. trim( $args['helptext'] ) .'">?</a>' : '';

		$field   = '';
		$_field  = '
		<tr valign="top" class="apppresser-'. $key .'">';
			if ( $type == 'h3' ) {
				$_field .= '<th colspan="2" scope="row"  class="appp-section-title"><h3 id="apppresser--'. $key .'">'. $label . $help .'</h3>';
				$_field .= ! empty( $args['description'] ) ? '<p>'. trim( $args['description'] ) .'</p>' : '';
				$_field .= '</th>';
			} else {
				$_field  = '<th scope="row"><label for="apppresser-'. $key .'">'. $label .'</label>'. $help . '</th><td>';
			}
		// Filter allows devs to add their own field types
		$field = apply_filters( "apppresser_field_override_$type", $field, $key, $value, $args, $appp_settings );

		if ( '' === $field ) : // No custom type added
		switch ( $type ) {
			case 'checkbox':
				$field .= ( $args['description'] ) ? '<table class="description-tbl" cellpadding="0" cellspacing="0"><tr><td>' : ''; // help align the description
				$field .= '<input type="checkbox" id="apppresser--'. $key .'" name="appp_settings['. $key .']" '. checked( $value, 'on', false ) .' />'."\n";
				$field .= ( $args['description'] ) ? '</td><td>' : '';
				$field .= ( $args['description'] ) ? '&nbsp; <span class="description">'. $args['description'] .'</span>' : '';
				$field .= ( $args['description'] ) ? '</td></tr></table>' : '';
				break;

			case 'select':
				$field .= '
				<select id="apppresser--'. $key .'" name="appp_settings['. $key .']" >'."\n";
				// load all themes
				if ( ! empty( $options ) ) {
					$current = $value;
					$opts = array();
					foreach ( $options as $opt_value => $opt_name ) {
						$opt_value = $opt_value == 'option-none' ? '' : esc_attr( $opt_value );
						$opts[ $opt_value ] = '<option value="'. $opt_value .'" '. selected( $opt_value, $current, false ) .'>'. esc_html( $opt_name ) .'</option>'."\n";
					}
					if ( isset( $opts['option-none'] ) ) {
						$field .= '<option value="">'. $opts['option-none'] .'</option>';
						unset( $opts['option-none'] );
					}
					$field .= implode( "\n", $opts );
				}
				$field .= '</select>'."\n";
				break;

			case 'radio':
				$field .= '
				<div id="apppresser--'. $key .'">'."\n";
				// load all themes
				if ( ! empty( $options ) ) {
					$current = $value;
					$opts = array();
					foreach ( $options as $opt_value => $opt_name ) {
						$opt_value = $opt_value == 'option-none' ? '' : esc_attr( $opt_value );
						$opts[ $opt_value ] = '<p><label><input type="radio" name="appp_settings['. $key .']" value="'. $opt_value .'" '. checked( $opt_value, $current, false ) .'>&nbsp;&nbsp;'. esc_html( $opt_name ) .'</label></p>'."\n";
					}
					if ( isset( $opts['option-none'] ) ) {
						$field .= '<p><label><input type="radio" name="appp_settings['. $key .']" value="">&nbsp;&nbsp;'. $opts['option-none'] .'</label></p>';
						unset( $opts['option-none'] );
					}
					$field .= implode( "\n", $opts );
				}
				$field .= '</div>'."\n";
				break;

			case 'license_key':
				$license_status = self::settings( $key .'_status' );
				$class          = 'active';
				$notice         = __( 'Active' , 'apppresser' );

				if ( $license_status != 'valid' ) {
					$class = 'inactive';
					$notice = $license_status == 'invalid' ? __( 'Invalid Key' , 'apppresser' ) : '<a href="http://apppresser.com/extensions/">'. __( 'Get a license', 'apppresser' ) .'</a>';
				}

				$field .= sprintf( '<input class="regular-text" type="text" id="apppresser--%1$s" name="appp_settings[%2$s]" value="%3$s" />'."\n", $key, $key, $value );
				$field .= sprintf( '<p class="description license_key"><span class="regular-text %1$s">%2$s</span></p>', $class, $notice );

				break;

			case 'h3':
				break;

			case 'title':
				$field .= '<h2>' . $value . '</h2>';
				break;

			case 'paragraph':
				$field .= '<p>' . $args['value'] . '</p>';
				break;

			case 'color':

				$default = ( isset( $args['default'] ) && !empty( $args['default']) ) ? str_replace('##', '#', '#'.$args['default']) : '';
				$value = appp_get_theme_mod( $key, $default ); // i.e. #000a7c
				$appp_theme = appp_get_setting( 'appp_theme' ); // i.e. 'ion'

				$field .= sprintf('<input type="text" value="%1$s" name="appp_settings[theme_mods_%2$s][%3$s]" class="app-color-field" data-default-color="%4$s" />', $value, $appp_theme, $key, $default );
				break;

			case 'file':
				$field .= sprintf( '<input class="custom_media_url" id="apppresser--%1$s" type="text" name="appp_settings[%2$s]" value="%3$s" style="margin-bottom:10px; clear:right;"><a href="#" class="button-primary custom_media_upload">Choose File</a>'."\n", $key, $key, $value );
				if ( $args['description'] )
					$field .= '&nbsp; <span class="description">'. $args['description'] .'</span>';
				break;

			default:
				// Filter allows devs to modify default field type or override it
				$field .= sprintf( '<input class="regular-text" type="text" id="apppresser--%1$s" name="appp_settings[%2$s]" value="%3$s" />'."\n", $key, $key, $value );
				break;
		}
		endif; // End check for custom type

		if ( trim( $args['description'] ) && ! in_array( $type, array( 'h3', 'checkbox' ) ) ) {
			$field .= '<p class="description">'. trim( $args['description'] ) .'</p>';
		}
		// Filter allows devs to add their own field types
		$field = apply_filters( "apppresser_field_markup_$type", $field, $key, $value, $args, $appp_settings );

		if ( $type !== 'h3' )
			$field .= '</td>';

		$_field = $_field . $field .'
		</tr>
		';

		if( $args['subtab'] == 'general' ) {
			self::$general_fields[ $args['tab'] ][ $key ] = $_field;
		}

		if( $type == 'license_key' ) {
			self::$license_fields[ $args['tab'] ][ $key ] = $_field;
		} else if( $args['subtab'] == 'advanced' ) {
			self::$advanced_fields[ $args['tab'] ][ $key ] = $_field;
		} else if( $args['subtab'] == 'customize' ) {
			self::$customize_fields[ $args['tab'] ][ $key ] = $_field;
		} else if( $args['subtab'] == 'color' ) {
			self::$color_fields[ $args['tab'] ][ $key ] = $_field;
		} else if( $args['subtab'] == 'v2-only' ) {
			self::$v2only_fields[ $args['tab'] ][ $key ] = $_field;
		} else {
			self::$all_fields[ $args['tab'] ][ $key ] = $_field;
		}

		self::$field_args[ $key ] = array( 'args' => $args );

		if ( $args['echo'] )
			echo $_field;

		return $_field;
	}

	/**
	 * Gets all registered fields arguments
	 * @since  1.0.5
	 * @param  string  $field_id Id of field to check
	 * @return mixed             False, all fields array, or singular field array
	 */
	public static function get_all_fields( $field_id = '' ) {
		if ( ! empty( self::$field_args ) ) {

			if ( ! $field_id )
				return self::$field_args;

			return isset( self::$field_args[ $field_id ] ) ? self::$field_args[ $field_id ] : false;
		}

		$appp_settings = self::run();
		ob_start();
		// Do html
		@do_action( 'apppresser_add_settings', $appp_settings );
		// grab the data from the output buffer and add it to our $content variable
		$content = ob_get_contents();
		ob_end_clean();

		if ( ! $field_id )
			return self::$field_args;

		return isset( self::$field_args[ $field_id ] ) ? self::$field_args[ $field_id ] : false;

	}

	/**
	 * Add a settings tab to the AppPresser Settings Page
	 * @since 1.0.0
	 * @param string  $label Tab label
	 * @param string  $slug  Tab slug (optional)
	 */
	public static function add_setting_tab( $label, $slug = '' ) {
		self::$admin_tabs[ $slug ? $slug : sanitize_html_class( $label ) ] = $label;
	}

	/**
	 * Adds a setting section title to AppPresser's settings.
	 * @since  1.0.0
	 * @param  string  $title   Title
	 * @param  array   $args    Array of possible options
	 */
	public static function add_setting_label( $title, $args = array() ) {

		if( isset( $args['deprecated'] ) && $args['deprecated'] <= self::$deprecate_ver )
			return;

		self::add_setting( sanitize_title( $title ), $title, wp_parse_args( $args, array( 'type' => 'h3' ) ) );
	}

	/**
	 * Retrieve registered license option keys via `apppresser_license_keys_to_check` filter
	 * @since  1.0.1
	 * @return array  All license option keys
	 */
	public static function license_keys() {
		if ( empty( self::$license_keys ) ) {
			$appp_settings = self::run();
			self::$license_keys = apply_filters( 'apppresser_license_keys_to_check', self::$license_keys, $appp_settings );
		}
		return self::$license_keys;
	}

	/**
	 * Handles our ajax page search
	 * @since  1.0.0
	 */
	public function ajax_post_results() {

		// verify our nonce
		if ( ! ( isset( $_REQUEST['nonce'], $_REQUEST['page_title'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'appp_settings_group-options' ) ) )
			wp_send_json_error( '<ul><li>'. __( 'security check failed', 'apppresser' ) .'</li></ul>' );

		// sanitize our search string
		$search_string = sanitize_text_field( $_REQUEST['page_title'] );

		// if there is no search string, bail here
		if ( empty( $search_string ) )
			wp_send_json_error( '<ul><li>'. __( 'Please Try Again', 'apppresser' ) .'</li></ul>' );

		global $wpdb;
		// Search posts by title wildcard and get IDs
		$results = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title LIKE '%%%s%%' AND post_status = 'publish' AND post_type = 'page' LIMIT 10", $search_string ) );

		if ( empty( $results ) )
			wp_send_json_error( '<ul><li>'. __('No Results Found', 'apppresser' ) .'</li></ul>' );

		// loop found ids and concatenate post links
		$return = '<ol>';
		foreach ( $results as $post_id ) {
			$return .= '<li><a href="'. get_permalink( $post_id ) .'" data-postID="'. $post_id .'">'. get_the_title( $post_id ) .'</a></li>';
		}
		$return .= '</ol>';
		wp_reset_postdata();

		// send back our encoded data
		wp_send_json_success( $return );

	}

	/**
	 * Help and Support settings page
	 * @since  1.0.0
	 */
	function help_support_page() {
		$class = self::$page_slug;
		$class .= self::is_mp6() ? ' mp6' : '';
		?>
		<div class="wrap <?php echo $class; ?>">
			<h2>AppPresser <?php _e( 'Help and Support', 'apppresser' ); ?></h2>
			<p><strong><?php _e( 'Resources', 'apppresser' ); ?>:</strong> <a href="https://github.com/apppresser/" target="_blank">AppPresser <?php _e( 'Core on Github', 'apppresser' ); ?></a> | <a href="http://docs.apppresser.com/" target="_blank">AppPresser <?php _e( 'Documentation', 'apppresser' ); ?></a></p>
			<p><strong>AppPresser <?php _e( 'Online', 'apppresser' ); ?>:</strong> <a href="http://apppresser.com" target="_blank"><?php _e( 'Web', 'apppresser' ); ?></a> |  <a href="http://twitter.com/apppresser" target="_blank"><?php _e( 'Twitter', 'apppresser' ); ?></a> | <a href="http://facebook.com/apppresser" target="_blank"><?php _e( 'Facebook', 'apppresser' ); ?></a> | <a href="http://youtube.com/user/apppresser" target="_blank"><?php _e( 'YouTube', 'apppresser' ); ?></a></p>
			<h3><?php _e( 'About', 'apppresser' ); ?> AppPresser</h3>
			<p><?php printf( __( '%s was created by %s, %s, %s, and %s', 'apppresser' ),
				'<a href="http://apppresser.com" target="_blank">AppPresser</a>',
				'<a href="http://twitter.com/scottbolinger" target="_blank">Scott Bolinger</a>',
				'<a href="http://twitter.com/williamsba" target="_blank">Brad Williams</a>',
				'<a href="http://twitter.com/bmess" target="_blank">Brian Messenlehner</a>',
				'<a href="http://twitter.com/lisasabinwilson" target="_blank">Lisa Sabin-Wilson</a>' ); ?>.</p>
			<p><?php printf( __( 'Development props to %s, %s, %s, and %s', 'apppresser' ),
				'<a href="http://twitter.com/jtsternberg" target="_blank">Justin "JT$" Sternberg</a>',
				'<a href="http://twitter.com/pmgarman" target="_blank">Patrick Garman</a>',
				'<a href="http://twitter.com/modemlooper" target="_blank">Ryan Fugate</a>',
				'<a href="http://twitter.com/tw2113" target="_blank">Michael "Venkman" Beckwith</a>' ); ?>.</p>
		</div>
		<?php
	}

	/**
	 * 'apppresser_notifications' hook allows plugins/themes to add their own notification badge counts.
	 * @since  1.0.6
	 * @return string  Badge count markup or empty string
	 */
	public function notification_badge() {

		$format = ' <span class="update-plugins count-%d"><span class="plugin-count">%s</span></span>';
		$notification_count = apply_filters( 'apppresser_notifications', 0 );

		// Send notification bubble markup if any notifications
		if ( $notification_count ) {
			return sprintf( $format, $notification_count, number_format_i18n( $notification_count ) );
		}

		return '';
	}

	/**
	 * Returns url to the AppPresser settings page
	 * @since  1.0.4
	 * @return string  AppPresser settings page url
	 */
	public static function url() {
		return esc_url( add_query_arg( 'page', self::$page_slug, admin_url( 'admin.php' ) ) );
	}

	/**
	 * Removes cookie by setting a date in the past as the expiration
	 * @since  1.0.6
	 */
	public static function clear_cookie() {
		setcookie( 'AppPresser_Appp', 'false', time() - DAY_IN_SECONDS );
	}

	public function verify_apptheme_slug() {
		if( class_exists('AppPresser_Theme_Setup') && AppPresser_Theme_Setup::THEME_SLUG == 'apppresser' ) {
			add_action( 'admin_notices', array($this, 'apptheme_update_admin_notice' ) );
		}
	}

	public function apptheme_update_admin_notice() {
		?>
		<div class="notice notice-warning">
			<p><?php echo sprintf( __( 'Your version of AppTheme has a programming error that will cause updates to fail.  Read about the simple fix in our <a href="%s" target="_blank">docs</a>.', 'apppresser' ), 'http://v2docs.apppresser.com/article/243-older-versions-of-apptheme-fail-to-update' ); ?></p>
		</div>
		<?php
	}

	public static function set_deprecate_version( $deprecate_ver = null ) {
		if( isset( $_GET['appp_settings_ver'] ) && is_numeric( $_GET['appp_settings_ver'] ) ) {
			self::$deprecate_ver = (int)$_GET['appp_settings_ver'];
			update_option( 'appp_settings_ver', self::$deprecate_ver, true );
		} else {
			self::$deprecate_ver = get_option( 'appp_settings_ver', self::$deprecate_ver );
		}
	}

	public static function is_deprecated( $deprecate_ver = 0 ) {
		return ( $deprecate_ver <= self::$deprecate_ver );
	}

}
AppPresser_Admin_Settings::run();

/**
 * Function helper. Adds a setting section to AppPresser's settings.
 * @since  1.0.0
 * @param  string  $key     Option key
 * @param  string  $label   Option label
 * @param  array   $args    Array of possible options for select
 */
function appp_add_setting( $key, $label, $args = array() ) {
	AppPresser_Admin_Settings::add_setting( $key, $label, $args );
}

/**
 * Add a settings tab to the AppPresser Settings Page
 * @since 1.0.0
 * @param string  $label Tab label
 * @param string  $slug  Tab slug (optional)
 */
function appp_add_setting_tab( $label, $slug = '' ) {
	AppPresser_Admin_Settings::add_setting_tab( $label, $slug  );
}
