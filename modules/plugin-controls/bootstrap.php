<?php
/** Core settings for the early Plugin Controls runtime. */
defined( 'ABSPATH' ) || exit;

class AppPresser_Plugin_Controls_Admin {
	public function __construct() {
		add_action( 'apppresser_add_settings', array( $this, 'tab' ), 70 );
		add_action( 'admin_post_apppresser_plugin_controls', array( $this, 'save' ) );
	}

	public function tab( $settings ) { $settings->add_setting_tab( __( 'Plugin Controls', 'apppresser' ), 'plugin-controls' ); }

	private static function plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		unset( $plugins[ plugin_basename( dirname( __DIR__, 2 ) . '/apppresser.php' ) ] );
		foreach ( $plugins as $file => $data ) {
			if ( in_array( basename( $file ), array( 'apppresser-plugin-controls.php', 'apppresser-plugin-block.php', 'apppresser-plugin-enable.php' ), true ) ) { unset( $plugins[ $file ] ); }
		}
		return $plugins;
	}

	public static function save_values( $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'controls_permission', __( 'You do not have permission to change plugin loading.', 'apppresser' ) );
		}
		$plugins = self::plugins();
		$values = array();
		foreach ( array( 'apppresser-plugin-block', 'apppresser-plugin-enable' ) as $key ) {
			$list = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
			$values[ $key ] = array_values( array_intersect( array_filter( $list, 'is_string' ), array_keys( $plugins ) ) );
			$values[ $key . '-api' ] = empty( $input[ $key . '-api' ] ) ? '' : '1';
		}
		// Blocking takes precedence if a crafted submission includes the same plugin twice.
		$values['apppresser-plugin-enable'] = array_values( array_diff( $values['apppresser-plugin-enable'], $values['apppresser-plugin-block'] ) );
		$option = 'apppresser_module_settings_plugin-controls';
		update_option( $option, $values, false );
		return $values === get_option( $option ) ? true : new WP_Error( 'controls_save_failed', __( 'Plugin Controls settings could not be saved. Try again.', 'apppresser' ) );
	}

	public function save() {
		check_admin_referer( 'apppresser_plugin_controls' );
		$result = self::save_values( wp_unslash( $_POST ) );
		set_transient( 'apppresser_module_notice_' . get_current_user_id(), array( 'error' => is_wp_error( $result ), 'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Plugin Controls settings saved.', 'apppresser' ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=apppresser_settings&tab=tab-plugin-controls' ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to change plugin loading.', 'apppresser' ) . '</p>';
			return;
		}
		$plugins = self::plugins();
		$active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		$values = AppPresser_Modules::instance()->settings->values( 'plugin-controls' );
		echo '<p>' . esc_html__( 'Control which plugins load for app visitors. These selections do not change plugin activation for ordinary site visitors or the WordPress dashboard.', 'apppresser' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'apppresser_plugin_controls' );
		echo '<input type="hidden" name="action" value="apppresser_plugin_controls">';
		foreach ( array( 'block' => __( 'Plugins to block in the app', 'apppresser' ), 'enable' => __( 'Inactive plugins to load in the app', 'apppresser' ) ) as $mode => $label ) {
			$key = 'apppresser-plugin-' . $mode;
			$selected = isset( $values[ $key ] ) ? $values[ $key ] : array();
			for ( $i = 0; $i < 2 && is_string( $selected ) && is_serialized( $selected ); $i++ ) { $selected = maybe_unserialize( $selected ); }
			echo '<fieldset class="apppresser-plugin-controls-list"><legend><h3>' . esc_html( $label ) . '</h3></legend>';
			$count = 0;
			foreach ( $plugins as $file => $data ) {
				if ( ( 'block' === $mode ) !== in_array( $file, $active, true ) ) { continue; }
				echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $file ) . '" ' . checked( in_array( $file, is_array( $selected ) ? $selected : array(), true ), true, false ) . '> ' . esc_html( $data['Name'] ) . '</label></p>';
				$count++;
			}
			if ( ! $count ) { echo '<p class="description">' . esc_html__( 'No plugins available for this selection.', 'apppresser' ) . '</p>'; }
			$api = isset( $values[ $key . '-api' ] ) ? maybe_unserialize( $values[ $key . '-api' ] ) : '';
			echo '<p><label><input type="checkbox" name="' . esc_attr( $key . '-api' ) . '" value="1" ' . checked( '1', $api, false ) . '> ' . esc_html__( 'Also apply to WordPress REST API requests', 'apppresser' ) . '</label></p></fieldset>';
		}
		submit_button( __( 'Save changes', 'apppresser' ), 'primary', 'submit', true, array( 'id' => 'apppresser-save-plugin-controls' ) );
		echo '</form>';
	}
}

new AppPresser_Plugin_Controls_Admin();
