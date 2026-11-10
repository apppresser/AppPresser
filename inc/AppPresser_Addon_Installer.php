<?php
/**
 * AppPresser Addon Installer
 *
 * Fetches available plugins and themes from the AppPresser API
 * and registers them with TGM Plugin Activation.
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

/**
 * Addon Installer Class
 */
class AppPresser_Addon_Installer {

	/**
	 * Transient key for caching API response
	 */
	const CACHE_KEY = 'apppresser_addons_cache';

	/**
	 * Cache duration in seconds (12 hours)
	 */
	const CACHE_DURATION = 43200;

	/**
	 * Single instance of this class
	 *
	 * @var AppPresser_Addon_Installer
	 */
	private static $instance = null;

	/**
	 * Cached addons data
	 *
	 * @var array|null
	 */
	private $addons = null;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since 4.6.0
	 * @return AppPresser_Addon_Installer A single instance of this class.
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Add theme installer messages at the top
		add_action( 'admin_notices', array( $this, 'theme_installer_messages' ) );
		// Add theme installer section at the bottom via footer (will be repositioned with JS)
		add_action( 'admin_footer', array( $this, 'theme_installer_section' ) );
		add_action( 'admin_init', array( $this, 'handle_theme_install' ) );

		// Add MU-plugin installer messages and section
		add_action( 'admin_notices', array( $this, 'mu_plugin_installer_messages' ) );
		add_action( 'admin_footer', array( $this, 'mu_plugin_installer_section' ) );
		add_action( 'admin_init', array( $this, 'handle_mu_plugin_install' ) );

		// Override the Type column text to show Optional instead of Recommended
		add_filter( 'tgmpa_table_data_item', array( $this, 'modify_type_column' ), 10, 2 );

		// Handle cache clearing via URL parameter
		add_action( 'admin_init', array( $this, 'maybe_clear_cache' ) );
	}

	/**
	 * Clear cache if requested via URL parameter
	 * Usage: ?apppresser_clear_addon_cache=1
	 * Debug: ?apppresser_debug_addons=1
	 */
	public function maybe_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['apppresser_clear_addon_cache'] ) ) {
			$this->clear_cache();
			$using_local = $this->use_local_json();
			add_action( 'admin_notices', function() use ( $using_local ) {
				$message = esc_html__( 'AppPresser addon cache cleared.', 'apppresser' );
				if ( $using_local ) {
					$message .= ' ' . esc_html__( '(Using local JSON file)', 'apppresser' );
				}
				echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
			});
		}

		// Debug mode - show what's in the addons data
		if ( isset( $_GET['apppresser_debug_addons'] ) ) {
			add_action( 'admin_notices', array( $this, 'debug_addons_notice' ) );
		}
	}

	/**
	 * Display debug information about addons
	 */
	public function debug_addons_notice() {
		$addons = $this->get_addons();
		$using_local = $this->use_local_json();
		echo '<div class="notice notice-info">';
		echo '<h3>AppPresser Addon Debug</h3>';
		if ( $using_local ) {
			echo '<p style="background:#fff3cd;padding:8px;border-left:4px solid #ffc107;"><strong>⚠️ Using Local JSON:</strong> Reading from <code>plugin-updates.json</code> instead of API</p>';
		}
		echo '<p><strong>BuddyPress Active:</strong> ' . ( $this->is_dependency_active( 'buddypress' ) ? 'Yes' : 'No' ) . '</p>';
		echo '<p><strong>BuddyBoss Active:</strong> ' . ( $this->is_dependency_active( 'buddyboss-platform' ) ? 'Yes' : 'No' ) . '</p>';
		echo '<p><strong>WooCommerce Active:</strong> ' . ( $this->is_dependency_active( 'woocommerce' ) ? 'Yes' : 'No' ) . '</p>';
		echo '<p><strong>MU-Plugins Dir:</strong> ' . esc_html( WPMU_PLUGIN_DIR ) . '</p>';
		echo '<h4>Plugin Types from API:</h4>';
		echo '<pre style="background:#f5f5f5;padding:10px;max-height:300px;overflow:auto;">';
		if ( ! empty( $addons['plugins'] ) ) {
			foreach ( $addons['plugins'] as $slug => $plugin ) {
				$type        = isset( $plugin['type'] ) ? $plugin['type'] : '(not set)';
				$required_if = isset( $plugin['required_if'] ) ? $plugin['required_if'] : '(not set)';
				$is_mu       = strtolower( trim( $type ) ) === 'mu-plugin' ? ' [MU-PLUGIN]' : '';
				echo esc_html( $slug ) . ': type=' . esc_html( $type ) . ', required_if=' . esc_html( $required_if ) . esc_html( $is_mu ) . "\n";
			}
		} else {
			echo 'No plugins found in API response';
		}
		echo '</pre>';
		
		// Show MU-plugins status
		$mu_plugins = $this->get_mu_plugins();
		if ( ! empty( $mu_plugins ) ) {
			echo '<h4>MU-Plugins Status:</h4>';
			echo '<pre style="background:#f5f5f5;padding:10px;max-height:200px;overflow:auto;">';
			foreach ( $mu_plugins as $slug => $plugin ) {
				$installed = $this->is_mu_plugin_installed( $slug ) ? 'Installed' : 'Not Installed';
				echo esc_html( $slug ) . ': ' . esc_html( $installed ) . "\n";
			}
			echo '</pre>';
		}
		echo '</div>';
	}

	/**
	 * Modify the Type column to show Optional instead of Recommended for optional plugins
	 *
	 * @param array $table_data Item data for the table.
	 * @param array $plugin     Plugin configuration from TGM.
	 * @return array Modified item data.
	 */
	public function modify_type_column( $table_data, $plugin ) {
		// Get our addon data to check the original type
		$addons = $this->get_addons();
		$slug   = isset( $plugin['slug'] ) ? $plugin['slug'] : '';

		// Only modify our plugins, not the JWT plugin from WordPress.org
		if ( empty( $addons['plugins'][ $slug ] ) ) {
			return $table_data;
		}

		$addon_plugin = $addons['plugins'][ $slug ];
		$type         = isset( $addon_plugin['type'] ) ? strtolower( trim( $addon_plugin['type'] ) ) : 'optional';

		switch ( $type ) {
			case 'required':
				$table_data['type'] = __( 'Required', 'apppresser' );
				break;
			case 'recommended':
				$table_data['type'] = __( 'Recommended', 'apppresser' );
				break;
			case 'conditional':
				$required_if = isset( $addon_plugin['required_if'] ) ? $addon_plugin['required_if'] : '';
				if ( $this->is_any_dependency_active( $required_if ) ) {
					$table_data['type'] = __( 'Required', 'apppresser' );
				} else {
					$table_data['type'] = __( 'Optional', 'apppresser' );
				}
				break;
			case 'mu-plugin':
				$table_data['type'] = __( 'Optional (MU-Plugin)', 'apppresser' );
				break;
			case 'optional':
			default:
				$table_data['type'] = __( 'Optional', 'apppresser' );
				break;
		}

		return $table_data;
	}


	/**
	 * Get addons from remote API or cache
	 *
	 * @return array Array with 'plugins' and 'themes' keys
	 */
	public function get_addons() {
		if ( $this->addons !== null ) {
			return $this->addons;
		}

		// Try to get from cache first
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			$this->addons = $cached;
			return $this->addons;
		}

		// Fetch from remote API
		$this->addons = $this->fetch_remote_addons();

		// Cache the result if we got data
		if ( ! empty( $this->addons['plugins'] ) || ! empty( $this->addons['themes'] ) ) {
			set_transient( self::CACHE_KEY, $this->addons, self::CACHE_DURATION );
		}

		return $this->addons;
	}

	/**
	 * Check if local JSON file should be used for testing
	 *
	 * @return bool True if local file should be used
	 */
	public function use_local_json() {
		// Check for constant first (for development)
		if ( defined( 'APPPRESSER_USE_LOCAL_JSON' ) && APPPRESSER_USE_LOCAL_JSON ) {
			return true;
		}

		// Check for filter (allows runtime override)
		if ( apply_filters( 'apppresser_use_local_json', false ) ) {
			return true;
		}

		// Check for URL parameter (for easy testing)
		if ( isset( $_GET['apppresser_use_local_json'] ) && current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Fetch addons from local JSON file (for testing)
	 *
	 * @return array Array with 'plugins' and 'themes' keys
	 */
	private function fetch_local_addons() {
		$default = array(
			'plugins' => array(),
			'themes'  => array(),
		);

		// Get the path to the local JSON file
		$json_file = AppPresser::$dir_path . 'plugin-updates.json';

		// Check if file exists
		if ( ! file_exists( $json_file ) ) {
			return $default;
		}

		// Read the file
		$json_content = file_get_contents( $json_file );
		if ( $json_content === false ) {
			return $default;
		}

		// Decode JSON
		$data = json_decode( $json_content, true );

		if ( ! is_array( $data ) ) {
			return $default;
		}

		return array(
			'plugins' => isset( $data['plugins'] ) && is_array( $data['plugins'] ) ? $data['plugins'] : array(),
			'themes'  => isset( $data['themes'] ) && is_array( $data['themes'] ) ? $data['themes'] : array(),
		);
	}

	/**
	 * Fetch addons from remote API
	 *
	 * @return array Array with 'plugins' and 'themes' keys
	 */
	private function fetch_remote_addons() {
		// Check if we should use local JSON file instead
		if ( $this->use_local_json() ) {
			return $this->fetch_local_addons();
		}

		$default = array(
			'plugins' => array(),
			'themes'  => array(),
		);

		$email = appp_get_setting( 'ap4_account_email' );

		// Return empty if email is not configured
		if ( empty( $email ) ) {
			return $default;
		}

		$api_url = add_query_arg(
			'email',
			rawurlencode( sanitize_email( $email ) ),
			'https://myapppresser.com/wp-json/appp/plugin-update'
		);

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $default;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			return $default;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return $default;
		}

		return array(
			'plugins' => isset( $data['plugins'] ) && is_array( $data['plugins'] ) ? $data['plugins'] : array(),
			'themes'  => isset( $data['themes'] ) && is_array( $data['themes'] ) ? $data['themes'] : array(),
		);
	}

	/**
	 * Check if ANY of the dependency plugins are active (supports comma-separated list)
	 *
	 * @param string $dependencies Comma-separated list of plugin slugs.
	 * @return bool True if any dependency is active.
	 */
	private function is_any_dependency_active( $dependencies ) {
		if ( empty( $dependencies ) ) {
			return false;
		}

		// Split comma-separated dependencies and check each one
		$dependency_list = array_map( 'trim', explode( ',', $dependencies ) );

		foreach ( $dependency_list as $dependency_slug ) {
			if ( $this->is_dependency_active( $dependency_slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a single dependency plugin is active
	 *
	 * @param string $dependency_slug The plugin slug to check (e.g., 'woocommerce', 'buddypress', 'learndash').
	 * @return bool True if the dependency is active.
	 */
	private function is_dependency_active( $dependency_slug ) {
		if ( empty( $dependency_slug ) ) {
			return false;
		}

		// Ensure plugin functions are available
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Normalize the slug
		$dependency_slug = strtolower( trim( $dependency_slug ) );

		// Map common dependency names to their actual plugin checks
		$dependency_checks = array(
			'woocommerce'        => 'woocommerce/woocommerce.php',
			'buddypress'         => 'buddypress/bp-loader.php',
			'learndash'          => 'sfwd-lms/sfwd_lms.php',
			'sfwd-lms'           => 'sfwd-lms/sfwd_lms.php',
			'buddyboss-platform' => 'buddyboss-platform/bp-loader.php',
		);

		// Check if we have a mapped file path
		if ( isset( $dependency_checks[ $dependency_slug ] ) ) {
			return is_plugin_active( $dependency_checks[ $dependency_slug ] );
		}

		// Fallback: try common plugin file patterns
		$possible_paths = array(
			$dependency_slug . '/' . $dependency_slug . '.php',
			$dependency_slug . '/class-' . $dependency_slug . '.php',
			$dependency_slug . '/' . str_replace( '-', '_', $dependency_slug ) . '.php',
		);

		foreach ( $possible_paths as $path ) {
			if ( is_plugin_active( $path ) ) {
				return true;
			}
		}

		// Also check by class name for some plugins
		$class_checks = array(
			'woocommerce' => 'WooCommerce',
			'buddypress'  => 'BuddyPress',
			'learndash'   => 'SFWD_LMS',
			'sfwd-lms'    => 'SFWD_LMS',
		);

		if ( isset( $class_checks[ $dependency_slug ] ) && class_exists( $class_checks[ $dependency_slug ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the type text for display based on plugin type and dependency status
	 *
	 * @param array $plugin Plugin data from API.
	 * @return string The type text (Required, Recommended, or Optional).
	 */
	private function get_type_label( $plugin ) {
		$type = isset( $plugin['type'] ) ? strtolower( $plugin['type'] ) : 'optional';

		switch ( $type ) {
			case 'required':
				return __( 'Required', 'apppresser' );

			case 'recommended':
				return __( 'Recommended', 'apppresser' );

			case 'conditional':
				// Check if any of the dependencies are active
				$required_if = isset( $plugin['required_if'] ) ? $plugin['required_if'] : '';
				if ( ! empty( $required_if ) && $this->is_any_dependency_active( $required_if ) ) {
					return __( 'Required', 'apppresser' );
				}
				return __( 'Optional', 'apppresser' );

			case 'mu-plugin':
				return __( 'Optional', 'apppresser' );

			case 'optional':
			default:
				return __( 'Optional', 'apppresser' );
		}
	}

	/**
	 * Determine if a plugin should be marked as required in TGM
	 *
	 * @param array $plugin Plugin data from API.
	 * @return bool True if the plugin should be required.
	 */
	private function is_plugin_required( $plugin ) {
		$type = isset( $plugin['type'] ) ? strtolower( $plugin['type'] ) : 'optional';

		switch ( $type ) {
			case 'required':
				return true;

			case 'conditional':
				// Required if any of the dependency plugins are active
				$required_if = isset( $plugin['required_if'] ) ? $plugin['required_if'] : '';
				return ! empty( $required_if ) && $this->is_any_dependency_active( $required_if );

			case 'recommended':
			case 'optional':
			default:
				return false;
		}
	}

	/**
	 * Get plugins formatted for TGM Plugin Activation (plugins only, not themes or mu-plugins)
	 *
	 * @return array Array of plugin configurations for TGM
	 */
	public function get_tgm_plugins() {
		$addons      = $this->get_addons();
		$tgm_plugins = array();

		// Process plugins only (TGM doesn't support theme or mu-plugin installation)
		if ( ! empty( $addons['plugins'] ) ) {
			foreach ( $addons['plugins'] as $slug => $plugin ) {
				if ( empty( $plugin['name'] ) || empty( $plugin['download_url'] ) ) {
					continue;
				}

				// Skip mu-plugins - they are handled separately
				$type = isset( $plugin['type'] ) ? strtolower( trim( $plugin['type'] ) ) : 'optional';
				if ( $type === 'mu-plugin' ) {
					continue;
				}

				$slug        = sanitize_text_field( $slug );
				$description = isset( $plugin['description'] ) ? sanitize_text_field( $plugin['description'] ) : '';
				$is_required = $this->is_plugin_required( $plugin );

				$tgm_plugins[] = array(
					'name'        => sanitize_text_field( $plugin['name'] ),
					'slug'        => $slug,
					'source'      => esc_url_raw( $plugin['download_url'] ),
					'required'    => $is_required,
					'version'     => isset( $plugin['latest_version'] ) ? sanitize_text_field( $plugin['latest_version'] ) : '',
					'description' => $description,
				);
			}
		}

		return $tgm_plugins;
	}

	/**
	 * Get available themes
	 *
	 * @return array Array of theme data
	 */
	public function get_themes() {
		$addons = $this->get_addons();
		return isset( $addons['themes'] ) ? $addons['themes'] : array();
	}

	/**
	 * Get themes that are not yet installed
	 *
	 * @return array Array of themes not installed
	 */
	public function get_themes_to_install() {
		$themes     = $this->get_themes();
		$to_install = array();

		foreach ( $themes as $slug => $theme ) {
			$wp_theme = wp_get_theme( $slug );
			if ( ! $wp_theme->exists() ) {
				$to_install[ $slug ] = $theme;
			}
		}

		return $to_install;
	}

	/**
	 * Display theme installer success/error messages at the top of the page
	 */
	public function theme_installer_messages() {
		// Only show on the Install Addons page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'apppresser-install-addons' ) {
			return;
		}

		// Show success message if theme was just installed
		if ( isset( $_GET['apppresser_theme_installed'] ) ) {
			$theme_slug = sanitize_text_field( $_GET['apppresser_theme_installed'] );
			$themes     = $this->get_themes();
			$theme_name = isset( $themes[ $theme_slug ]['name'] ) ? $themes[ $theme_slug ]['name'] : $theme_slug;
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Theme "%s" was installed successfully.', 'apppresser' ), esc_html( $theme_name ) ) . '</p></div>';
		}

		// Show error message if theme installation failed
		if ( isset( $_GET['apppresser_theme_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( urldecode( $_GET['apppresser_theme_error'] ) ) . '</p></div>';
		}
	}

	/**
	 * Display theme installer table after the TGM plugin table
	 */
	public function theme_installer_section() {
		// Only show on the Install Addons page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'apppresser-install-addons' ) {
			return;
		}

		$themes_to_install = $this->get_themes_to_install();

		if ( empty( $themes_to_install ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'apppresser_install_theme' );
		?>
		<div id="apppresser-theme-section" class="apppresser-theme-installer-wrap" style="display:none;">
			<h3 class="apppresser-themes-heading"><?php esc_html_e( 'AppPresser Themes', 'apppresser' ); ?></h3>
			<p class="apppresser-themes-description"><?php esc_html_e( 'App themes are installed but not activated. They are only used when viewing your site through the app.', 'apppresser' ); ?></p>

			<table class="wp-list-table widefat plugins apppresser-themes-table">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Theme', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-type"><?php esc_html_e( 'Type', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-version"><?php esc_html_e( 'Version', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'apppresser' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $themes_to_install as $slug => $theme ) :
						$install_url = admin_url( 'admin.php?page=apppresser-install-addons&apppresser_install_theme=' . urlencode( $slug ) . '&_wpnonce=' . $nonce );
						$version     = isset( $theme['latest_version'] ) ? $theme['latest_version'] : '';
					?>
					<tr>
						<td class="plugin-title column-primary">
							<strong><?php echo esc_html( $theme['name'] ); ?></strong>
							<div class="row-actions visible">
								<span class="install"><a href="<?php echo esc_url( $install_url ); ?>"><?php esc_html_e( 'Install', 'apppresser' ); ?></a></span>
							</div>
						</td>
						<td class="column-source"><?php esc_html_e( 'External Source', 'apppresser' ); ?></td>
						<td class="column-type"><?php esc_html_e( 'Recommended', 'apppresser' ); ?></td>
						<td class="column-version">
							<?php if ( $version ) : ?>
								<span class="version-info">
									<?php esc_html_e( 'Available version:', 'apppresser' ); ?> <span class="version-number"><?php echo esc_html( $version ); ?></span>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-status"><span class="status-not-installed"><?php esc_html_e( 'Not Installed', 'apppresser' ); ?></span></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<script>
		(function() {
			// Move the theme section after the TGM plugins form
			var themeSection = document.getElementById('apppresser-theme-section');
			var tgmpaForm = document.getElementById('tgmpa-plugins');
			if (themeSection && tgmpaForm) {
				tgmpaForm.parentNode.insertBefore(themeSection, tgmpaForm.nextSibling);
				themeSection.style.display = 'block';
			}
		})();
		</script>

		<style>
			.apppresser-theme-installer-wrap {
				margin: 30px 0 20px 0;
			}
			.apppresser-themes-heading {
				font-size: 1.3em;
				margin: 0 0 5px 0;
				padding: 0;
			}
			.apppresser-themes-description {
				color: #646970;
				font-style: italic;
				margin: 0 0 15px 0;
			}
			.apppresser-themes-table {
				border-spacing: 0;
			}
			.apppresser-themes-table .column-primary {
				width: 25%;
			}
			.apppresser-themes-table .column-source,
			.apppresser-themes-table .column-type {
				width: 12%;
			}
			.apppresser-themes-table .column-version {
				width: 30%;
			}
			.apppresser-themes-table .column-status {
				width: 15%;
			}
			.apppresser-themes-table .row-actions {
				color: #787c82;
				font-size: 13px;
				padding: 2px 0 0;
			}
			.apppresser-themes-table .row-actions a {
				color: #2271b1;
				text-decoration: none;
			}
			.apppresser-themes-table .row-actions a:hover {
				color: #135e96;
			}
			.apppresser-themes-table .version-number {
				color: #d63638;
			}
			.apppresser-themes-table .status-not-installed {
				color: #996800;
			}
		</style>
		<?php
	}

	/**
	 * Handle theme installation request
	 */
	public function handle_theme_install() {
		if ( ! isset( $_GET['apppresser_install_theme'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'apppresser_install_theme' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'apppresser' ) );
		}

		if ( ! current_user_can( 'install_themes' ) ) {
			wp_die( esc_html__( 'You do not have permission to install themes.', 'apppresser' ) );
		}

		$theme_slug = sanitize_text_field( $_GET['apppresser_install_theme'] );
		$themes     = $this->get_themes();

		if ( ! isset( $themes[ $theme_slug ] ) || empty( $themes[ $theme_slug ]['download_url'] ) ) {
			wp_die( esc_html__( 'Invalid theme.', 'apppresser' ) );
		}

		$theme        = $themes[ $theme_slug ];
		$download_url = $theme['download_url'];

		// Include required files for theme installation
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		// Create a custom skin for the upgrader
		$skin = new WP_Ajax_Upgrader_Skin();

		// Initialize the theme upgrader
		$upgrader = new Theme_Upgrader( $skin );

		// Install the theme
		$result = $upgrader->install( $download_url );

		// Redirect back to the Install Addons page with a message
		$redirect_url = admin_url( 'admin.php?page=apppresser-install-addons' );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( 'apppresser_theme_error', urlencode( $result->get_error_message() ), $redirect_url );
		} elseif ( $result === false ) {
			$redirect_url = add_query_arg( 'apppresser_theme_error', urlencode( __( 'Theme installation failed.', 'apppresser' ) ), $redirect_url );
		} else {
			$redirect_url = add_query_arg( 'apppresser_theme_installed', $theme_slug, $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Clear the addons cache
	 *
	 * @return bool True if cache was deleted, false otherwise
	 */
	public function clear_cache() {
		$this->addons = null;
		return delete_transient( self::CACHE_KEY );
	}

	/**
	 * Get MU-plugins from the addons list
	 *
	 * @return array Array of MU-plugin data
	 */
	public function get_mu_plugins() {
		$addons     = $this->get_addons();
		$mu_plugins = array();

		if ( ! empty( $addons['plugins'] ) ) {
			foreach ( $addons['plugins'] as $slug => $plugin ) {
				$type = isset( $plugin['type'] ) ? strtolower( trim( $plugin['type'] ) ) : 'optional';
				if ( $type === 'mu-plugin' ) {
					$mu_plugins[ $slug ] = $plugin;
				}
			}
		}

		return $mu_plugins;
	}

	/**
	 * Get MU-plugins that are not yet installed
	 *
	 * @return array Array of MU-plugins not installed
	 */
	public function get_mu_plugins_to_install() {
		$mu_plugins = $this->get_mu_plugins();
		$to_install = array();

		foreach ( $mu_plugins as $slug => $plugin ) {
			if ( ! $this->is_mu_plugin_installed( $slug ) ) {
				$to_install[ $slug ] = $plugin;
			}
		}

		return $to_install;
	}

	/**
	 * Check if an MU-plugin is installed
	 *
	 * @param string $slug The plugin slug.
	 * @return bool True if installed.
	 */
	private function is_mu_plugin_installed( $slug ) {
		$mu_plugins_dir = WPMU_PLUGIN_DIR;

		// Check for loader file
		$loader_file = $mu_plugins_dir . '/appp-load-' . $slug . '.php';
		if ( file_exists( $loader_file ) ) {
			return true;
		}

		// Check for direct plugin file
		$direct_file = $mu_plugins_dir . '/' . $slug . '.php';
		if ( file_exists( $direct_file ) ) {
			return true;
		}

		// Check for plugin directory
		$plugin_dir = $mu_plugins_dir . '/' . $slug;
		if ( is_dir( $plugin_dir ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Display MU-plugin installer success/error messages
	 */
	public function mu_plugin_installer_messages() {
		// Only show on the Install Addons page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'apppresser-install-addons' ) {
			return;
		}

		// Show success message if MU-plugin was just installed
		if ( isset( $_GET['apppresser_mu_plugin_installed'] ) ) {
			$plugin_slug = sanitize_text_field( $_GET['apppresser_mu_plugin_installed'] );
			$mu_plugins  = $this->get_mu_plugins();
			$plugin_name = isset( $mu_plugins[ $plugin_slug ]['name'] ) ? $mu_plugins[ $plugin_slug ]['name'] : $plugin_slug;
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'MU-Plugin "%s" was installed successfully.', 'apppresser' ), esc_html( $plugin_name ) ) . '</p></div>';
		}

		// Show error message if MU-plugin installation failed
		if ( isset( $_GET['apppresser_mu_plugin_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( urldecode( $_GET['apppresser_mu_plugin_error'] ) ) . '</p></div>';
		}
	}

	/**
	 * Display MU-plugin installer table after the theme section
	 */
	public function mu_plugin_installer_section() {
		// Only show on the Install Addons page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'apppresser-install-addons' ) {
			return;
		}

		$mu_plugins_to_install = $this->get_mu_plugins_to_install();

		if ( empty( $mu_plugins_to_install ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'apppresser_install_mu_plugin' );
		?>
		<div id="apppresser-mu-plugin-section" class="apppresser-mu-plugin-installer-wrap" style="display:none;">
			<h3 class="apppresser-mu-plugins-heading"><?php esc_html_e( 'AppPresser MU-Plugins', 'apppresser' ); ?></h3>
			<p class="apppresser-mu-plugins-description"><?php esc_html_e( 'MU-Plugins (Must-Use plugins) are automatically loaded and cannot be deactivated. They are installed to the wp-content/mu-plugins directory.', 'apppresser' ); ?></p>

			<table class="wp-list-table widefat plugins apppresser-mu-plugins-table">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Plugin', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-source"><?php esc_html_e( 'Source', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-type"><?php esc_html_e( 'Type', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-version"><?php esc_html_e( 'Version', 'apppresser' ); ?></th>
						<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'apppresser' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $mu_plugins_to_install as $slug => $plugin ) :
						$install_url = admin_url( 'admin.php?page=apppresser-install-addons&apppresser_install_mu_plugin=' . urlencode( $slug ) . '&_wpnonce=' . $nonce );
						$version     = isset( $plugin['latest_version'] ) ? $plugin['latest_version'] : '';
						$description = isset( $plugin['description'] ) ? $plugin['description'] : '';
					?>
					<tr>
						<td class="plugin-title column-primary">
							<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
							<?php if ( $description ) : ?>
								<p class="description"><?php echo esc_html( $description ); ?></p>
							<?php endif; ?>
							<div class="row-actions visible">
								<span class="install"><a href="<?php echo esc_url( $install_url ); ?>"><?php esc_html_e( 'Install', 'apppresser' ); ?></a></span>
							</div>
						</td>
						<td class="column-source"><?php esc_html_e( 'External Source', 'apppresser' ); ?></td>
						<td class="column-type"><?php esc_html_e( 'Optional (MU-Plugin)', 'apppresser' ); ?></td>
						<td class="column-version">
							<?php if ( $version ) : ?>
								<span class="version-info">
									<?php esc_html_e( 'Available version:', 'apppresser' ); ?> <span class="version-number"><?php echo esc_html( $version ); ?></span>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-status"><span class="status-not-installed"><?php esc_html_e( 'Not Installed', 'apppresser' ); ?></span></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<script>
		(function() {
			// Move the MU-plugin section after the theme section (or TGM form if no themes)
			var muPluginSection = document.getElementById('apppresser-mu-plugin-section');
			var themeSection = document.getElementById('apppresser-theme-section');
			var tgmpaForm = document.getElementById('tgmpa-plugins');
			
			if (muPluginSection) {
				if (themeSection && themeSection.parentNode) {
					themeSection.parentNode.insertBefore(muPluginSection, themeSection.nextSibling);
				} else if (tgmpaForm && tgmpaForm.parentNode) {
					tgmpaForm.parentNode.insertBefore(muPluginSection, tgmpaForm.nextSibling);
				}
				muPluginSection.style.display = 'block';
			}
		})();
		</script>

		<style>
			.apppresser-mu-plugin-installer-wrap {
				margin: 30px 0 20px 0;
			}
			.apppresser-mu-plugins-heading {
				font-size: 1.3em;
				margin: 0 0 5px 0;
				padding: 0;
			}
			.apppresser-mu-plugins-description {
				color: #646970;
				font-style: italic;
				margin: 0 0 15px 0;
			}
			.apppresser-mu-plugins-table {
				border-spacing: 0;
			}
			.apppresser-mu-plugins-table .column-primary {
				width: 25%;
			}
			.apppresser-mu-plugins-table .column-primary .description {
				margin: 5px 0 0 0;
				color: #646970;
				font-size: 12px;
			}
			.apppresser-mu-plugins-table .column-source,
			.apppresser-mu-plugins-table .column-type {
				width: 12%;
			}
			.apppresser-mu-plugins-table .column-version {
				width: 30%;
			}
			.apppresser-mu-plugins-table .column-status {
				width: 15%;
			}
			.apppresser-mu-plugins-table .row-actions {
				color: #787c82;
				font-size: 13px;
				padding: 2px 0 0;
			}
			.apppresser-mu-plugins-table .row-actions a {
				color: #2271b1;
				text-decoration: none;
			}
			.apppresser-mu-plugins-table .row-actions a:hover {
				color: #135e96;
			}
			.apppresser-mu-plugins-table .version-number {
				color: #d63638;
			}
			.apppresser-mu-plugins-table .status-not-installed {
				color: #996800;
			}
		</style>
		<?php
	}

	/**
	 * Handle MU-plugin installation request
	 */
	public function handle_mu_plugin_install() {
		if ( ! isset( $_GET['apppresser_install_mu_plugin'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'apppresser_install_mu_plugin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'apppresser' ) );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to install plugins.', 'apppresser' ) );
		}

		$plugin_slug = sanitize_text_field( $_GET['apppresser_install_mu_plugin'] );
		$mu_plugins  = $this->get_mu_plugins();

		if ( ! isset( $mu_plugins[ $plugin_slug ] ) || empty( $mu_plugins[ $plugin_slug ]['download_url'] ) ) {
			wp_die( esc_html__( 'Invalid MU-plugin.', 'apppresser' ) );
		}

		$plugin       = $mu_plugins[ $plugin_slug ];
		$download_url = $plugin['download_url'];
		$redirect_url = admin_url( 'admin.php?page=apppresser-install-addons' );

		// Install the MU-plugin
		$result = $this->install_mu_plugin( $plugin_slug, $download_url );

		if ( is_wp_error( $result ) ) {
			$redirect_url = add_query_arg( 'apppresser_mu_plugin_error', urlencode( $result->get_error_message() ), $redirect_url );
		} else {
			$redirect_url = add_query_arg( 'apppresser_mu_plugin_installed', $plugin_slug, $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Install an MU-plugin from a remote URL
	 *
	 * @param string $slug         The plugin slug.
	 * @param string $download_url The download URL for the plugin zip.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function install_mu_plugin( $slug, $download_url ) {
		global $wp_filesystem;

		// Include required files
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		// Initialize the filesystem
		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'filesystem_error', __( 'Could not initialize filesystem.', 'apppresser' ) );
		}

		// Ensure mu-plugins directory exists
		$mu_plugins_dir = WPMU_PLUGIN_DIR;
		if ( ! $wp_filesystem->exists( $mu_plugins_dir ) ) {
			if ( ! $wp_filesystem->mkdir( $mu_plugins_dir, FS_CHMOD_DIR ) ) {
				return new WP_Error( 'mkdir_error', __( 'Could not create mu-plugins directory.', 'apppresser' ) );
			}
		}

		// Download the plugin zip
		$temp_file = download_url( $download_url );
		if ( is_wp_error( $temp_file ) ) {
			return new WP_Error( 'download_error', sprintf( __( 'Download failed: %s', 'apppresser' ), $temp_file->get_error_message() ) );
		}

		// Create a temporary directory for extraction
		$temp_dir = $mu_plugins_dir . '/' . $slug . '-temp-' . time();
		if ( ! $wp_filesystem->mkdir( $temp_dir, FS_CHMOD_DIR ) ) {
			@unlink( $temp_file );
			return new WP_Error( 'temp_dir_error', __( 'Could not create temporary directory.', 'apppresser' ) );
		}

		// Unzip the plugin
		$unzip_result = unzip_file( $temp_file, $temp_dir );
		@unlink( $temp_file );

		if ( is_wp_error( $unzip_result ) ) {
			$wp_filesystem->delete( $temp_dir, true );
			return new WP_Error( 'unzip_error', sprintf( __( 'Unzip failed: %s', 'apppresser' ), $unzip_result->get_error_message() ) );
		}

		// Find the plugin folder (handle nested directories from zip)
		$extracted_folders = $wp_filesystem->dirlist( $temp_dir );
		if ( empty( $extracted_folders ) ) {
			$wp_filesystem->delete( $temp_dir, true );
			return new WP_Error( 'empty_zip', __( 'The downloaded zip file is empty.', 'apppresser' ) );
		}

		// Get the first (and usually only) folder from the zip
		$source_folder = '';
		foreach ( $extracted_folders as $name => $data ) {
			if ( $data['type'] === 'd' ) {
				$source_folder = $temp_dir . '/' . $name;
				break;
			}
		}

		if ( empty( $source_folder ) ) {
			// No folder found, files might be at root level
			$source_folder = $temp_dir;
		}

		// Destination for the plugin
		$dest_folder = $mu_plugins_dir . '/' . $slug;

		// Remove existing installation if present
		if ( $wp_filesystem->exists( $dest_folder ) ) {
			$wp_filesystem->delete( $dest_folder, true );
		}

		// Move the plugin to mu-plugins directory
		if ( ! $wp_filesystem->move( $source_folder, $dest_folder ) ) {
			// Try copy if move fails
			if ( ! copy_dir( $source_folder, $dest_folder ) ) {
				$wp_filesystem->delete( $temp_dir, true );
				return new WP_Error( 'move_error', __( 'Could not move plugin to mu-plugins directory.', 'apppresser' ) );
			}
		}

		// Clean up temp directory
		$wp_filesystem->delete( $temp_dir, true );

		// Find the main plugin file
		$main_file = $this->find_plugin_main_file( $dest_folder );
		if ( empty( $main_file ) ) {
			return new WP_Error( 'no_main_file', __( 'Could not find the main plugin file.', 'apppresser' ) );
		}

		// Create a loader file in mu-plugins root
		$loader_content = $this->generate_loader_file_content( $slug, $main_file );
		$loader_path    = $mu_plugins_dir . '/appp-load-' . $slug . '.php';

		if ( ! $wp_filesystem->put_contents( $loader_path, $loader_content, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'loader_error', __( 'Could not create loader file.', 'apppresser' ) );
		}

		return true;
	}

	/**
	 * Find the main plugin file in a directory
	 *
	 * @param string $dir The plugin directory.
	 * @return string|false The main plugin file path relative to the directory, or false if not found.
	 */
	private function find_plugin_main_file( $dir ) {
		global $wp_filesystem;

		$files = $wp_filesystem->dirlist( $dir );
		if ( empty( $files ) ) {
			return false;
		}

		// Look for PHP files with plugin headers
		foreach ( $files as $name => $data ) {
			if ( $data['type'] !== 'f' || pathinfo( $name, PATHINFO_EXTENSION ) !== 'php' ) {
				continue;
			}

			$file_path = $dir . '/' . $name;
			$content   = $wp_filesystem->get_contents( $file_path );

			// Check for plugin header
			if ( $content && preg_match( '/Plugin Name\s*:/i', $content ) ) {
				return $name;
			}
		}

		// Fallback: look for a file matching the folder name
		$folder_name = basename( $dir );
		$expected    = $folder_name . '.php';
		if ( isset( $files[ $expected ] ) && $files[ $expected ]['type'] === 'f' ) {
			return $expected;
		}

		return false;
	}

	/**
	 * Generate the content for the loader file
	 *
	 * @param string $slug      The plugin slug.
	 * @param string $main_file The main plugin file name.
	 * @return string The loader file content.
	 */
	private function generate_loader_file_content( $slug, $main_file ) {
		$content = "<?php\n";
		$content .= "/**\n";
		$content .= " * MU-Plugin Loader: " . esc_html( $slug ) . "\n";
		$content .= " *\n";
		$content .= " * This file loads the " . esc_html( $slug ) . " MU-plugin.\n";
		$content .= " * Generated by AppPresser Addon Installer.\n";
		$content .= " *\n";
		$content .= " * @package AppPresser\n";
		$content .= " */\n\n";
		$content .= "if ( file_exists( __DIR__ . '/" . esc_html( $slug ) . "/" . esc_html( $main_file ) . "' ) ) {\n";
		$content .= "\trequire_once __DIR__ . '/" . esc_html( $slug ) . "/" . esc_html( $main_file ) . "';\n";
		$content .= "}\n";

		return $content;
	}
}
