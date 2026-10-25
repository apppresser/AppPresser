<?php
/** Native admin screens and nonce-protected feature actions. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Module_Admin {
	private $modules;

	public function __construct( $modules, $register_hooks = true ) {
		$this->modules = $modules;
		if ( ! $register_hooks ) { return; }
		add_action( 'admin_menu', array( $this, 'redirect_legacy_page' ), 0 );
		add_action( 'admin_menu', array( $this, 'order_menu' ), 99 );
		add_action( 'admin_post_apppresser_module', array( $this, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function assets() {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'apppresser' ) ) {
			$root = dirname( __DIR__, 2 );
			wp_enqueue_style( 'apppresser-modules', plugins_url( 'css/appp-modules.css', $root . '/apppresser.php' ), array(), (string) filemtime( $root . '/css/appp-modules.css' ) );
			wp_enqueue_script( 'apppresser-settings-navigation', plugins_url( 'js/appp-settings.js', $root . '/apppresser.php' ), array(), (string) filemtime( $root . '/js/appp-settings.js' ), true );
		}
	}

	public function order_menu() {
		global $submenu;
		if ( ! isset( $submenu['apppresser_settings'] ) ) {
			return;
		}
		$order = array_flip( array( 'apppresser_settings' ) );
		usort( $submenu['apppresser_settings'], static function ( $left, $right ) use ( $order ) {
			return ( isset( $order[ $left[2] ] ) ? $order[ $left[2] ] : 10 ) <=> ( isset( $order[ $right[2] ] ) ? $order[ $right[2] ] : 10 );
		} );
	}

	private function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AppPresser features.', 'apppresser' ), '', array( 'response' => 403 ) );
		}
	}

	public function handle_action() {
		$this->authorize();
		$id = isset( $_POST['module'] ) && is_string( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		check_admin_referer( 'apppresser_module_' . $id );
		$action = isset( $_POST['module_action'] ) && is_string( $_POST['module_action'] ) ? sanitize_key( wp_unslash( $_POST['module_action'] ) ) : '';
		$result = new WP_Error( 'invalid_action', __( 'Unknown feature action.', 'apppresser' ) );
		if ( 'remove_legacy' === $action ) {
			$result = $this->modules->migrate_legacy( $id );
		} elseif ( in_array( $action, array( 'enable', 'disable' ), true ) ) {
			$result = $this->modules->set_enabled( $id, 'enable' === $action );
		}
		$message = is_wp_error( $result ) ? $result->get_error_message() : ( 'remove_legacy' === $action ? __( 'Legacy plugin removed. The feature is now enabled.', 'apppresser' ) : __( 'Feature updated. Your saved configuration has been retained.', 'apppresser' ) );
		set_transient( 'apppresser_module_notice_' . get_current_user_id(), array( 'error' => is_wp_error( $result ), 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=apppresser_settings&tab=tab-features' ) );
		exit;
	}

	public function notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'apppresser' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$key = 'apppresser_module_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( is_array( $notice ) && isset( $notice['message'] ) ) {
			delete_transient( $key );
			echo '<div class="notice ' . ( ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
	}

	public function legacy_notice() {
		$this->authorize();
		$names = array();
		foreach ( $this->modules->registry() as $id => $definition ) {
			foreach ( $this->modules->legacy_plugins( $id ) as $plugin ) {
				$names[] = $plugin['Name'];
			}
		}
		if ( ! $names ) { return; }
		echo '<div class="notice notice-warning apppresser-legacy-notice"><h2>' . esc_html__( 'Legacy addon migration', 'apppresser' ) . '</h2>';
		echo '<p>' . esc_html__( 'Legacy AppPresser plugins are still installed. In Features, choose Remove legacy plugin to replace each addon and enable its bundled feature.', 'apppresser' ) . '</p>';
		echo '<p>' . esc_html( sprintf( __( 'Installed: %s', 'apppresser' ), implode( ', ', array_unique( $names ) ) ) ) . '</p>';
		echo '<p><a class="button" data-settings-target="features" href="' . esc_url( admin_url( 'admin.php?page=apppresser_settings&tab=tab-features' ) ) . '">' . esc_html__( 'Review features', 'apppresser' ) . '</a></p></div>';
	}

	/** Redirect old bookmarks before WordPress checks the removed menu pages. */
	public function redirect_legacy_page() {
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$redirects = array( 'apppresser_overview' => '', 'apppresser_features' => 'features', 'apppresser_feature_settings' => 'features', 'apppresser_sub_help_support' => 'docs-support' );
		if ( ! isset( $redirects[ $page ] ) ) { return; }
		$this->authorize();
		wp_safe_redirect( admin_url( 'admin.php?page=apppresser_settings' . ( $redirects[ $page ] ? '&tab=tab-' . $redirects[ $page ] : '' ) ) );
		exit;
	}

	private function action_form( $id, $action, $label, $primary = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'apppresser_module_' . $id );
		$is_switch = in_array( $action, array( 'enable', 'disable' ), true ) && in_array( $this->modules->state( $id ), array( 'off', 'enabled' ), true );
		$switch = $is_switch ? ' role="switch" aria-checked="' . ( 'disable' === $action ? 'true' : 'false' ) . '"' : '';
		$name = $this->modules->registry()[ $id ]['name'];
		echo '<input type="hidden" name="action" value="apppresser_module"><input type="hidden" name="module" value="' . esc_attr( $id ) . '"><input type="hidden" name="module_action" value="' . esc_attr( $action ) . '"><button type="submit" class="button' . ( $primary ? ' button-primary' : '' ) . '"' . $switch . ' aria-label="' . esc_attr( $is_switch ? $name : $label . ': ' . $name ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public function features() {
		$this->authorize();
		echo '<p class="apppresser-features-intro">' . esc_html__( 'Enable the features your app uses. Disabled features keep their settings.', 'apppresser' ) . '</p>';
		$labels = array( 'off' => __( 'Off', 'apppresser' ), 'enabled' => __( 'Enabled', 'apppresser' ), 'legacy' => __( 'Legacy plugin installed', 'apppresser' ), 'dependency' => __( 'Dependency required', 'apppresser' ), 'installation' => __( 'Loader needs repair', 'apppresser' ) );
		$available = $unavailable = array();
		foreach ( $this->modules->registry() as $id => $definition ) {
			if ( $this->modules->dependency_installed( $id ) ) {
				$available[ $id ] = $definition;
			} else {
				$unavailable[ $id ] = $definition;
			}
		}
		foreach ( $available + $unavailable as $id => $definition ) {
			$state = $this->modules->state( $id );
			$missing = isset( $unavailable[ $id ] );
			echo '<section class="apppresser-feature' . ( $missing ? ' apppresser-feature-unavailable' : '' ) . '" aria-labelledby="feature-' . esc_attr( $id ) . '"><div class="apppresser-feature-description"><h3 id="feature-' . esc_attr( $id ) . '">' . esc_html( $definition['name'] ) . '</h3><p>' . esc_html( $definition['description'] ) . '</p><span class="apppresser-status apppresser-status-' . esc_attr( $state ) . '">' . esc_html( $missing ? __( 'Plugin not installed', 'apppresser' ) : $labels[ $state ] ) . '</span>';
			if ( 'legacy' === $state ) {
				$legacy = $this->modules->legacy_plugins( $id );
				foreach ( $legacy as $file => $data ) {
					echo '<p>' . esc_html( sprintf( __( 'Installed: %1$s %2$s', 'apppresser' ), $data['Name'], isset( $data['Version'] ) ? $data['Version'] : '' ) ) . '</p>';
				}
				echo '<p>' . esc_html__( 'Remove the legacy plugin to enable this feature.', 'apppresser' ) . '</p>';
				if ( is_multisite() ) {
					echo '<p>' . esc_html__( 'A network administrator must coordinate removal if other sites use this addon.', 'apppresser' ) . '</p>';
				}
			}
			if ( 'plugin-controls' === $id && is_multisite() ) {
				echo '<p>' . esc_html__( 'This switch installs or removes the shared MU loader for the entire network. Each site keeps its own plugin selections.', 'apppresser' ) . '</p>';
			}
			if ( $missing || 'dependency' === $state ) {
				echo '<p>' . esc_html( $this->modules->dependency_error( $id, true ) ) . '</p>';
			} elseif ( 'enabled' === $state && 'push' === $id && ! appp_get_setting( 'notifications_ap3_key' ) ) {
				echo '<p>' . esc_html__( 'Needs setup: add your notification service key in Settings.', 'apppresser' ) . '</p>';
			}
			echo '</div><div class="apppresser-feature-actions">';
			if ( $missing ) {
				echo '<button type="button" class="button" disabled>' . esc_html__( 'Enable', 'apppresser' ) . '</button>';
			}
			if ( 'legacy' === $state ) {
				if ( current_user_can( 'activate_plugins' ) && current_user_can( 'delete_plugins' ) ) {
					$this->action_form( $id, 'remove_legacy', __( 'Remove legacy plugin', 'apppresser' ), true );
				}
			} elseif ( 'installation' === $state ) {
				$this->action_form( $id, 'enable', __( 'Repair installation', 'apppresser' ), true );
				$this->action_form( $id, 'disable', __( 'Disable', 'apppresser' ) );
			} elseif ( 'enabled' === $state || $this->modules->requested( $id ) ) {
				$this->action_form( $id, 'disable', __( 'Disable', 'apppresser' ) );
			} elseif ( ! $missing && 'dependency' !== $state ) {
				$this->action_form( $id, 'enable', __( 'Enable', 'apppresser' ), true );
			}
			if ( 'enabled' === $state && $definition['tab'] ) {
				echo '<a class="button" data-settings-target="' . esc_attr( $definition['tab'] ) . '" href="' . esc_url( admin_url( 'admin.php?page=apppresser_settings&tab=tab-' . $definition['tab'] ) ) . '">' . esc_html__( 'Settings', 'apppresser' ) . '</a>';
			}
			echo '</div></section>';
		}
		echo '<p class="description">' . esc_html__( 'Turning off purchases stops purchase and subscription callbacks. Turning off notifications stops sends; overdue notifications are not sent automatically when you turn the feature back on. Review ongoing activity before changing these features.', 'apppresser' ) . '</p>';
	}

}
