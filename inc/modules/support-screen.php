<?php
/** Help content inside the Settings sidebar, without a separate admin page. */
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You cannot access AppPresser support.', 'apppresser' ), '', array( 'response' => 403 ) );
}
$dashboard_slug = appp_get_setting( 'ap3_site_slug', '' );
$dashboard_slug = is_scalar( $dashboard_slug ) ? trim( (string) $dashboard_slug, " \t\n\r\0\x0B/" ) : '';
$dashboard_url = 'https://myapppresser.com/' . ( '' !== $dashboard_slug ? rawurlencode( $dashboard_slug ) . '/' : '' );
?>
<div class="apppresser-support-content">
	<p><?php esc_html_e( 'Find setup guides, troubleshoot your app, or get help from the AppPresser team.', 'apppresser' ); ?></p>
	<h3><?php esc_html_e( 'Documentation', 'apppresser' ); ?></h3>
	<p><a href="https://docs.apppresser.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Browse AppPresser documentation', 'apppresser' ); ?></a></p>
	<p><?php esc_html_e( 'Search for connection setup, feature configuration, app builds, and troubleshooting guides.', 'apppresser' ); ?></p>
	<h3><?php esc_html_e( 'Get help', 'apppresser' ); ?></h3>
	<p><a class="button button-primary" href="https://apppresser.com/support/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'AppPresser support', 'apppresser' ); ?></a> <a class="button" href="mailto:support@apppresser.com"><?php esc_html_e( 'Email support', 'apppresser' ); ?></a></p>
	<p><?php esc_html_e( 'When requesting help, include:', 'apppresser' ); ?></p>
	<ul class="ul-disc">
		<li><?php esc_html_e( 'Your WordPress site URL and the name or ID of your app.', 'apppresser' ); ?></li>
		<li><?php esc_html_e( 'Steps to reproduce the issue, what you expected, and what happened.', 'apppresser' ); ?></li>
		<li><?php esc_html_e( 'The affected device and operating system, with screenshots or error messages where useful.', 'apppresser' ); ?></li>
	</ul>
	<p><?php esc_html_e( 'Use Log to review recent messages and System Info to collect site details if support requests them. Remove passwords, API keys, and customer data before sharing logs.', 'apppresser' ); ?></p>
	<p>
		<?php foreach ( array( 'log' => __( 'Open Log', 'apppresser' ), 'systeminfo' => __( 'Open System Info', 'apppresser' ) ) as $section => $label ) : ?>
			<?php if ( isset( $tabs[ $section ] ) ) : ?>
				<a class="button" data-settings-target="<?php echo esc_attr( $section ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=apppresser_settings&tab=tab-' . $section ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</p>
	<h3><?php esc_html_e( 'Your app dashboard', 'apppresser' ); ?></h3>
	<p><a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open MyAppPresser', 'apppresser' ); ?></a></p>
</div>
