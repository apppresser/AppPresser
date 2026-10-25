<?php
/** Purchase integration settings, rendered inside the existing settings form. */
defined( 'ABSPATH' ) || exit;
$integrations = array(
	'memberpress' => array( 'wpMemberPressID', __( 'Membership', 'apppresser' ) ),
	'pmp'        => array( 'membershipLevelID', __( 'Membership level', 'apppresser' ) ),
	'armember'   => array( 'planID', __( 'Membership plan', 'apppresser' ) ),
	'learndash'  => array( 'ldCoursePostId', __( 'Course', 'apppresser' ) ),
	'rcp'        => array( 'rcpSubscriptionID', __( 'Membership level', 'apppresser' ) ),
	'woomp'      => array( 'wooMembershipPlanID', __( 'Membership plan', 'apppresser' ) ),
);
?>
<tr class="apppresser-iap-settings-row"><td colspan="2" class="apppresser-iap-settings-cell">
	<div class="apppresser-iap-settings">
		<p class="description apppresser-iap-intro"><?php esc_html_e( 'Connect your Apple or Google in-app purchase IDs to memberships, plans or courses. Enable an integration, then add a mapping for each purchase.', 'apppresser' ); ?></p>
		<?php if ( ! self::$plugins ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No supported integrations are active. Activate MemberPress, Paid Memberships Pro, ARMember, LearnDash, Restrict Content Pro or WooCommerce Memberships to add purchase mappings.', 'apppresser' ); ?></p></div>
		<?php endif; ?>
		<?php foreach ( self::$plugins as $plugin_slug => $plugin_name ) :
			if ( ! isset( $integrations[ $plugin_slug ] ) ) { continue; }
			list( $keyname, $membership_label ) = $integrations[ $plugin_slug ];
			$membership_options = $this->get_membership_options_for_plugin( $plugin_slug );
			$saved = apppresser_iap_get_option( 'IAP_' . strtoupper( $plugin_slug ) . '_KEYS', array() );
			$saved = is_array( $saved ) ? array_values( $saved ) : array();
			$active = 'Yes' === apppresser_iap_get_option( 'IAP_' . strtoupper( $plugin_slug ) . '_ACTIVE' );
			$prefix = 'apppresser-iap-' . $plugin_slug;
			?>
			<section class="apppresser-iap-integration" aria-labelledby="<?php echo esc_attr( $prefix ); ?>-title" data-iap-integration data-next-index="<?php echo esc_attr( count( $saved ) ); ?>">
				<header class="apppresser-iap-header">
					<h3 id="<?php echo esc_attr( $prefix ); ?>-title"><?php echo esc_html( $plugin_name ); ?></h3>
					<label class="apppresser-iap-toggle" for="iap-<?php echo esc_attr( $plugin_slug ); ?>-active">
						<input type="hidden" name="iap-<?php echo esc_attr( $plugin_slug ); ?>-active" value="">
						<input type="checkbox" id="iap-<?php echo esc_attr( $plugin_slug ); ?>-active" name="iap-<?php echo esc_attr( $plugin_slug ); ?>-active" value="Yes" data-iap-toggle aria-controls="<?php echo esc_attr( $prefix ); ?>-fields" <?php checked( $active ); ?> aria-label="<?php echo esc_attr( sprintf( __( 'Enable %s mappings', 'apppresser' ), $plugin_name ) ); ?>">
						<?php esc_html_e( 'Enable mappings', 'apppresser' ); ?>
					</label>
				</header>
				<p class="apppresser-iap-disabled" data-iap-disabled <?php echo $active ? 'hidden' : ''; ?>><?php esc_html_e( 'Mappings are disabled. Your saved mappings are kept.', 'apppresser' ); ?></p>
				<div class="apppresser-iap-fields" id="<?php echo esc_attr( $prefix ); ?>-fields" data-iap-fields>
					<div data-iap-rows>
						<?php foreach ( $saved as $index => $values ) {
							$this->mapping_row( $plugin_slug, $plugin_name, $index, $keyname, $membership_label, $membership_options, is_array( $values ) ? $values : array() );
						} ?>
					</div>
					<p class="apppresser-iap-empty" data-iap-empty <?php echo $saved ? 'hidden' : ''; ?>><?php esc_html_e( 'No mappings yet. Add a mapping to connect an in-app purchase to this integration.', 'apppresser' ); ?></p>
					<button type="button" class="button button-secondary" data-iap-add aria-label="<?php echo esc_attr( sprintf( __( 'Add %s mapping', 'apppresser' ), $plugin_name ) ); ?>"><?php esc_html_e( 'Add mapping', 'apppresser' ); ?></button>
					<template data-iap-template><?php $this->mapping_row( $plugin_slug, $plugin_name, '__index__', $keyname, $membership_label, $membership_options, array() ); ?></template>
				</div>
			</section>
		<?php endforeach; ?>
		<p class="description apppresser-iap-save-note"><?php esc_html_e( 'Changes to mappings and enabled integrations take effect when you save.', 'apppresser' ); ?></p>
		<?php submit_button( __( 'Save changes', 'apppresser' ), 'primary', 'submit-iap-settings' ); ?>
	</div>
</td></tr>
