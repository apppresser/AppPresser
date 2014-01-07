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
	public static $instance       = null;
	public static $page_slug      = 'apppresser_settings';
	public static $extensions_slug       = 'apppresser_sub_extensions';
	public static $help_slug      = 'apppresser_sub_help_support';
	public static $menu_slug      = '';
	public static $extensions_menu_slug  = '';
	public static $help_menu_slug = '';
	public static $image_inputs   = array();
	public static $all_fields     = array();
	public static $admin_tabs     = array();
	public static $license_keys   = array();

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
		add_action( 'admin_menu', array( $this, 'plugin_menu' ), 9 );
		add_filter( 'sanitize_option_'. AppPresser::SETTINGS_NAME, array( $this, 'maybe_reset_license_statuses' ), 99 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'apppresser_add_settings', array( $this, 'add_settings' ), 6 ); // Higher priority
		add_filter( 'apppresser_field_markup_text', array( $this, 'ajax_container' ), 10, 2 );
		add_action( 'wp_ajax_appp_search_post_handler', array( $this, 'ajax_post_results' ) );
		$this->themes = wp_get_themes();
		$this->nav_menus = wp_get_nav_menus();

		// Check for the 'Use different theme for app?' option
		if ( $appp_theme = self::settings( 'appp_theme' ) ) {
			// If admin only theme object exists
			if ( isset( $this->themes[ $appp_theme ] ) && is_callable( array( $this->themes[ $appp_theme ], 'get_template_directory' ) ) ) {
				// Check child theme directory first
				$dir = $this->themes[ $appp_theme ]->get_stylesheet_directory();
				// If there is a 'appp-settings.php' file,
				if ( file_exists( $dir .'/appp-settings.php' ) ) {
					// include it
					include( $dir .'/appp-settings.php' );
				}
				// Ok, check parent theme directory
				$dir = $this->themes[ $appp_theme ]->get_template_directory();
				// If there is a 'appp-settings.php' file,
				if ( file_exists( $dir .'/appp-settings.php' ) ) {
					// include it
					include( $dir .'/appp-settings.php' );
				}
			}
		}
		// Otherwise if there is a 'appp-settings.php' file in the current theme,
		elseif ( file_exists( get_stylesheet_directory_uri() .'/appp-settings.php' ) ) {
			// include it
			include( get_stylesheet_directory_uri() .'/appp-settings.php' );
		}

	}

	/**
	 * Create AppPresser Settings menus
	 * @since  1.0.0
	 */
	function plugin_menu() {
		// Create main menu and settings page
		self::$menu_slug = add_menu_page( __( 'AppPresser', 'apppresser' ), __( 'AppPresser', 'apppresser' ), 'manage_options', self::$page_slug, array( $this, 'settings_page' ) );
		// Create submenu items

		// Extensions page
		self::$extensions_menu_slug = add_submenu_page( self::$page_slug, __( 'Extensions', 'apppresser' ), __( 'Extensions', 'apppresser' ), 'manage_options', self::$extensions_slug, array( $this, 'extensions_page' ) );
		// Help page
		self::$help_menu_slug = add_submenu_page( self::$page_slug, __( 'Help / Support', 'apppresser' ), __( 'Help / Support', 'apppresser' ), 'manage_options', self::$help_slug, array( $this, 'help_support_page' ) );

		add_action( 'admin_head-' . self::$menu_slug, array( $this, 'admin_head' ) );

		// enqueue
		foreach ( array( self::$menu_slug, self::$extensions_menu_slug, self::$help_menu_slug ) as $slug )
			add_action( 'admin_print_scripts-' . $slug, array( $this, 'admin_scripts' ) );

	}

	/**
	 * Admin scripts and styles
	 * @since  1.0.0
	 */
	function admin_scripts() {
		// admin scripts and styles
		wp_enqueue_script( 'appp-admin', self::$js_url . 'appp-admin.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-tooltip' ), self::VERSION );
		wp_enqueue_style( 'jquery-ui-smoothness', self::$css_url . 'smoothness/smoothness.custom.min.css' );
		wp_enqueue_style( 'appp-admin-styles', self::$css_url . 'appp-admin-styles.css', null, self::VERSION.time() );
	}

	/**
	 * Easy hook for adding to the admin_head on the AppPresser settings page
	 * @since  1.0.0
	 */
	function admin_head() {
		do_action( 'appp_admin_settings_head', self::run() );
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
	 * @param  array $inputs The input array we want to validate
	 * @return array         Our sanitized inputs
	 */
	function settings_validate( $inputs ) {

		// sanitize the settings data submitted
		foreach ( $inputs as $key => $value ) {
			switch ( $key ) {
				case 'menu':
					$cleaninput[ $key ] = absint( $value );
					break;
				case 'mobile_browser_theme_switch':
					// Clear cookie
					setcookie( 'AppPresser_Appp', 'true', time() - DAY_IN_SECONDS );
					$cleaninput[ $key ] = isset( $inputs[ $key ] ) && $inputs[ $key ] == 'on' ? 'on' : '';
				case 'admin_theme_switch':
					$cleaninput[ $key ] = isset( $inputs[ $key ] ) && $inputs[ $key ] == 'on' ? 'on' : '';
					break;
				default:
					$cleaninput[ $key ] = apply_filters( 'apppresser_sanitize_setting', sanitize_text_field( $value ), $key, $value, $inputs, self::run() ); ;
					break;
			}

			// Check for registered license option keys
			if ( array_key_exists( $key, self::license_keys() ) ) {
				// Get old value for comparison
				$old = appp_get_setting( $key );
				if ( ! $old || $old != $value ) {
					// if updated, trigger a status check
					$this->reset_status[] = $key;
				}
			}

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

		// Add settings tabs/inputs via this hook. The AppPresser_Admin_Settings instance is passed in.
		do_action( 'apppresser_add_settings', self::run() );

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

					echo '<table class="appp-tabs form-table tab-'. $tab . $current_class .'">';
						if ( isset( self::$all_fields[ $tab ] ) ) {
							echo implode( '', self::$all_fields[ $tab ] );
						}
						// A hook for adding additional data to the bottom of each tabbed area
						do_action( "apppresser_tab_bottom_$tab", self::run(), self::settings() );
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
						do_action( "apppresser_tab_buttons_$tab", self::run(), self::settings() );
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
		self::add_setting_label( __( 'AppPresser Core Settings', 'apppresser' ) );

		// For now...
		if ( appp_get_setting( 'mobile_browser_theme_switch' ) ) {
			self::add_setting( 'mobile_browser_theme_switch', __( 'Load AppPresser for mobile browsers', 'apppresser' ), array( 'type' => 'checkbox', 'helptext' => __( 'Display AppPresser in mobile browsers such as Safari and Chrome, instead of your normal theme.', 'apppresser' ) ) );
		}

		self::add_setting( 'admin_theme_switch', __( 'Load AppPresser for Admins Only', 'apppresser' ), array( 'type' => 'checkbox', 'helptext' => __( 'Check this if you want to test your AppPresser app without loading it for visitors to your site.', 'apppresser' ), 'description' => __( '(for testing purposes)', 'apppresser' ) ) );
		self::add_setting( 'appp_theme', __( 'App only theme', 'apppresser' ), array( 'type' => 'select', 'options' => $this->themes, 'helptext' => __( 'Select which theme you want to be loaded inside the app, such as the AppPresser theme.', 'apppresser' ), 'description' => __( 'must be enabled above', 'apppresser' ) ) );
		self::add_setting( 'appp_home_page', __( 'Use a unique homepage for your app.', 'apppresser' ), array( 'helptext' => __( 'Allows you to specify which page users will see first when they load up you AppPresser app.', 'apppresser' ), 'description' => __( 'Start typing to search for a page', 'apppresser' ) ) );

		$menus = array( 'option-none' => __( '-- select --', 'apppresser' ) );
		foreach ( (array) $this->nav_menus as $menu ) {
			$menus[ $menu->term_id ] = $menu->name;
		}

		// Register main menu setting
		self::add_setting( 'menu', __( 'Main App Menu', 'apppresser' ), array( 'type' => 'select', 'options' => $menus, 'helptext' => __( 'Use a custom main menu inside your app, different from your main site.', '' ) ) );
		// Register secondary  menu setting
		self::add_setting( 'secondary_menu', __( 'Secondary App Menu', 'apppresser' ), array( 'type' => 'select', 'options' => $menus, 'helptext' => __( 'Use a custom secondary menu inside your app (the top right dropdown in the header).', 'apppresser' ) ) );

		add_action( 'apppresser_tab_buttons_general', array( $this, 'help_link' ) );

	}

	/**
	 * Add a link to the help page in the main settings tab
	 * @since  1.0.0
	 */
	public function help_link() {
		echo '<a class="button-secondary" href="'. add_query_arg( 'page', self::$help_slug, admin_url( 'admin.php' ) ) .'">'. __( 'Help/Support', 'apppresser' ) .'</a>';
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
	 * @param  string  $type    Type of option: checkbox, select, or text
	 * @param  array   $options Array of possible options for select
	 */
	public static function add_setting( $key, $label, $args = array() ) {

		$keys = array_keys( self::$admin_tabs );
		$defaults = array(
			'type'        => 'text',
			'helptext'    => '',
			'description' => '',
			'options'     => array(),
			'tab'         => array_shift( $keys ),
			'echo'        => false,
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
				$_field = '<th colspan="2" scope="row"  class="appp-section-title"><h3 id="apppresser--'. $key .'">'. $label . $help .'</h3>';
				$_field .= ! empty( $args['description'] ) ? '<p>'. trim( $args['description'] ) .'</p>' : '';
				$_field .= '</th>';
			} else {
				$_field  = '<th scope="row"><label for="apppresser-'. $key .'">'. $label .'</label>'. $help . '</th><td>';
			}
		switch ( $type ) {
			case 'checkbox':
				$field .= '<input type="checkbox" id="apppresser--'. $key .'" name="appp_settings['. $key .']" '. checked( self::settings( $key ), 'on', false ) .' />'."\n";
				if ( $args['description'] )
					$field .= '&nbsp; <span class="description">'. $args['description'] .'</span>';
				break;

			case 'select':
				$field .= '
				<select id="apppresser--'. $key .'" name="appp_settings['. $key .']" >'."\n";
				// load all themes
				if ( ! empty( $options ) ) {
					$current = self::settings( $key );
					$opts = array();
					foreach ( $options as $value => $name ) {
						$value = $value == 'option-none' ? '' : esc_attr( $value );
						$opts[ $value ] = '<option value="'. $value .'" '. selected( $value, $current, false ) .'>'. esc_html( $name ) .'</option>'."\n";
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
					$current = self::settings( $key );
					$opts = array();
					foreach ( $options as $value => $name ) {
						$value = $value == 'option-none' ? '' : esc_attr( $value );
						$opts[ $value ] = '<p><label><input type="radio" name="appp_settings['. $key .']" value="'. $value .'" '. checked( $value, $current, false ) .'>&nbsp;&nbsp;'. esc_html( $name ) .'</label></p>'."\n";
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

				$field .= sprintf( '<input class="regular-text" type="text" id="apppresser--%1$s" name="appp_settings[%2$s]" value="%3$s" />'."\n", $key, $key, self::settings( $key ) );
				$field .= sprintf( '<p class="description license_key"><span class="regular-text %1$s">%2$s</span></p>', $class, $notice );

				break;

			case 'h3':
				break;

			default:
				// Filter allows devs to add their own field types
				$field .= sprintf( '<input class="regular-text" type="text" id="apppresser--%1$s" name="appp_settings[%2$s]" value="%3$s" />'."\n", $key, $key, self::settings( $key ) );
				break;
		}
		if ( trim( $args['description'] ) && ! in_array( $type, array( 'h3', 'checkbox' ) ) ) {
			$field .= '<p class="description">'. trim( $args['description'] ) .'</p>';
		}
		// Filter allows devs to add their own field types
		$field = apply_filters( "apppresser_field_markup_$type", $field, $key, self::settings( $key ), $args, self::run() );

		if ( $type !== 'h3' )
			$field .= '</td>';

		$_field = $_field . $field .'
		</tr>
		';
		if ( $args['echo'] ) {
			echo $_field;
			self::$all_fields['echoed'][ $key ] = $_field;
		} else {
			self::$all_fields[ $args['tab'] ][ $key ] = $_field;
		}

		return $_field;
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
	 * @param  string  $label   Title
	 * @param  array   $options Array of possible options
	 */
	public static function add_setting_label( $title, $args = array() ) {
		self::add_setting( sanitize_title( $title ), $title, wp_parse_args( $args, array( 'type' => 'h3' ) ) );
	}

	public static function license_keys() {
		if ( empty( self::$license_keys ) )
			self::$license_keys = apply_filters( 'apppresser_license_keys_to_check', self::$license_keys, self::run() );
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
			<p><strong><?php _e( 'Resources', 'apppresser' ); ?>:</strong> <a href="https://github.com/WebDevStudios/AppPresser/" target="_blank">AppPresser <?php _e( 'Core on Github', 'apppresser' ); ?></a> | <a href="http://wordpress.org/support/plugin/apppresser" target="_blank"><?php _e( 'Support Forums', 'apppresser' ); ?></a> | <a href="http://apppresser.com/docs/" target="_blank">AppPresser <?php _e( 'Documentation', 'apppresser' ); ?></a></p>
			<p><strong>AppPresser <?php _e( 'Online', 'apppresser' ); ?>:</strong> <a href="http://twitter.com/apppresser" target="_blank"><?php _e( 'Twitter', 'apppresser' ); ?></a> | <a href="http://facebook.com/apppresser" target="_blank"><?php _e( 'Facebook', 'apppresser' ); ?></a> | <a href="http://youtube.com/user/apppresser" target="_blank"><?php _e( 'YouTube', 'apppresser' ); ?></a></p>
			<h3><?php _e( 'About AppPresser', 'apppresser' ); ?></h3>
			<p><?php _e( 'AppPresser was created by Scott Bolinger, Brad Williams, Brian Messenlehner, and Lisa Sabin-Wilson', 'apppresser' ); ?>.</p>
		</div>
		<?php
	}

	/**
	 * AppPresser extensions page output
	 * @since  1.0.4
	 */
	function extensions_page() {
		$class = self::$page_slug;
		$class .= self::is_mp6() ? ' mp6' : '';
		?>
		<div class="wrap <?php echo $class; ?>">
			<h2><?php printf( 'AppPresser ' .__( 'Extensions &nbsp;&mdash;&nbsp; %s', 'apppresser' ), '<a href="http://apppresser.com/extensions/?ref=appp" class="button-primary" target="_blank">' . __( 'Browse All Extensions', 'apppresser' ) . '</a>' ); ?></h2>
			<p><?php _e( 'These extensions extend the functionality of AppPresser.', 'apppresser' ); ?></p>

         <?php
			// Attempt to pull back our cached feed
			$feed = get_transient( 'appp_extensions_feed' );
			$fallback = '<div class="error"><p>' . __( 'There was an error retrieving the extensions list. Please try again later.', 'apppresser' ) . '</div>';

			// If we don't have a cached feed, pull back fresh data
			if ( empty( $feed ) ) {
				// Retrieve and parse our feed
				$feed = wp_remote_get( 'http://apppresser.com/?feed=addons', array( 'sslverify' => false ) );
				if ( ! is_wp_error( $feed ) ) {
					if ( isset( $feed['body'] ) && strlen( $feed['body'] ) > 0 ) {
						$feed = wp_remote_retrieve_body( $feed );

						$feed = str_ireplace( array( '<html>', '<body>', '</html>', '</body>'), '', $feed );
						// Cache our feed for 1 hour
						set_transient( 'appp_extensions_feed', $feed, HOUR_IN_SECONDS );
					}
				}
			}

			// display the feed or error message
			echo $feed && ! is_wp_error( $feed ) ? $feed : $fallback;
			?>
     </div>
     <?php

	}

	/**
	 * Returns url to the AppPresser settings page
	 * @since  1.0.4
	 * @return string  AppPresser settings page url
	 */
	public static function url() {
		return add_query_arg( 'page', self::$page_slug, admin_url( 'admin.php' ) );
	}

}
AppPresser_Admin_Settings::run();

/**
 * Function helper. Adds a setting section to AppPresser's settings.
 * @since  1.0.0
 * @param  string  $key     Option key
 * @param  string  $label   Option label
 * @param  string  $type    Type of option: checkbox, select, or text
 * @param  array   $options Array of possible options for select
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
