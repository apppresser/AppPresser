<?php
/** Settings sections render once so switching sections preserves unsaved edits. */
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You cannot manage AppPresser settings.', 'apppresser' ), '', array( 'response' => 403 ) );
}
$appp_settings = AppPresser_Admin_Settings::run();
do_action( 'apppresser_add_settings', $appp_settings );
$registered_tabs = AppPresser_Admin_Settings::$admin_tabs;
$tabs = array_intersect_key( $registered_tabs, array( 'general' => true ) ) + array( 'features' => __( 'Features', 'apppresser' ) );
foreach ( AppPresser_Modules::instance()->registry() as $definition ) {
	if ( $definition['tab'] && isset( $registered_tabs[ $definition['tab'] ] ) ) {
		$tabs[ $definition['tab'] ] = $registered_tabs[ $definition['tab'] ];
	}
}
// Keep third-party settings sections ahead of the utility sections as well.
$tabs += array_diff_key( $registered_tabs, array( 'log' => true, 'systeminfo' => true, 'docs-support' => true ) );
foreach ( array( 'log', 'systeminfo' ) as $utility_tab ) {
	if ( isset( $registered_tabs[ $utility_tab ] ) ) { $tabs[ $utility_tab ] = $registered_tabs[ $utility_tab ]; }
}
$tabs['docs-support'] = __( 'Docs & Support', 'apppresser' );
$requested_tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
$requested_tab = preg_replace( '/^tab-/', '', $requested_tab );
$current_tab = isset( $tabs[ $requested_tab ] ) ? $requested_tab : key( $tabs );
$feature_admin = new AppPresser_Module_Admin( AppPresser_Modules::instance(), false );
?>
<div class="wrap apppresser_settings apppresser-modules">
	<h1><?php esc_html_e( 'AppPresser', 'apppresser' ); ?></h1>
	<?php $feature_admin->legacy_notice(); ?>
	<?php settings_errors(); ?>
	<div class="apppresser-settings-layout">
		<nav class="apppresser-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'apppresser' ); ?>">
			<?php foreach ( $tabs as $tab => $name ) : ?>
				<a id="apppresser-section-link-<?php echo esc_attr( $tab ); ?>" data-settings-section="<?php echo esc_attr( $tab ); ?>" aria-controls="apppresser-section-<?php echo esc_attr( $tab ); ?>" <?php echo $tab === $current_tab ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=apppresser_settings&tab=tab-' . $tab ) ); ?>"><?php echo esc_html( $name ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="apppresser-settings-panels">
			<?php foreach ( $tabs as $tab => $name ) : ?>
				<section id="apppresser-section-<?php echo esc_attr( $tab ); ?>" class="apppresser-settings-content" data-settings-panel="<?php echo esc_attr( $tab ); ?>" aria-labelledby="apppresser-section-link-<?php echo esc_attr( $tab ); ?>" <?php echo $tab !== $current_tab ? 'hidden' : ''; ?>>
					<h2 tabindex="-1"><?php echo esc_html( $name ); ?></h2>
					<?php if ( 'features' === $tab ) : ?>
						<?php $feature_admin->features(); ?>
					<?php elseif ( 'plugin-controls' === $tab ) : ?>
						<?php AppPresser_Plugin_Controls_Admin::render(); ?>
					<?php elseif ( 'docs-support' === $tab ) : ?>
						<?php require __DIR__ . '/support-screen.php'; ?>
					<?php else : ?>
						<form method="post" action="<?php echo in_array( $tab, array( 'iapsettings', 'systeminfo' ), true ) ? esc_url( admin_url( 'admin.php?page=apppresser_settings&tab=tab-' . $tab ) ) : esc_url( admin_url( 'options.php' ) ); ?>">
							<?php if ( 'iapsettings' === $tab ) { wp_nonce_field( 'apppresser_iap_settings' ); } elseif ( 'systeminfo' === $tab ) { wp_nonce_field( 'apppresser_system_info' ); } else { settings_fields( 'appp_settings_group' ); } ?>
							<input type="hidden" name="appp_settings_tab" value="<?php echo esc_attr( $tab ); ?>">
							<table class="form-table apppresser-current-settings" role="presentation">
								<?php
								if ( 'iapsettings' === $tab && AppPresser_Modules::instance()->legacy_plugins( 'iap' ) ) {
									// Old IAP renderers close and reopen the shared settings form.
									// Keep their fields inside the form owned by this screen instead.
									ob_start();
									do_action( 'apppresser_tab_top_' . $tab, $appp_settings, AppPresser::settings() );
									echo preg_replace( '#</?form\b[^>]*>#i', '', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								} else {
									do_action( 'apppresser_tab_top_' . $tab, $appp_settings, AppPresser::settings() );
								}
								if ( isset( AppPresser_Admin_Settings::$all_fields[ $tab ] ) ) {
									foreach ( AppPresser_Admin_Settings::$all_fields[ $tab ] as $field ) {
										if ( trim( wp_strip_all_tags( $field ) ) !== $name ) {
											echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
									}
								}
								do_action( 'apppresser_tab_bottom_' . $tab, $appp_settings, AppPresser::settings() );
								?>
							</table>
							<?php
							if ( ! in_array( $tab, array( 'iapsettings', 'systeminfo', 'log' ), true ) ) {
								submit_button( __( 'Save changes', 'apppresser' ), 'primary', 'submit', true, array( 'id' => 'apppresser-save-' . $tab ) );
							}
							do_action( 'apppresser_tab_buttons_' . $tab, $appp_settings, AppPresser::settings() );
							?>
						</form>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
</div>
