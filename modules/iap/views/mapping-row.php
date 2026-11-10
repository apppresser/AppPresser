<?php
/** One mapping, reused by saved rows and the add-row template. */
defined( 'ABSPATH' ) || exit;
$field_id = 'apppresser-iap-' . $plugin_slug . '-' . $index;
$field_name = 'iap_' . $plugin_slug . '[' . $index . ']';
$stored_id = isset( $values[ $keyname ] ) ? (string) $values[ $keyname ] : '';
$purchase_id = isset( $values['iapID'] ) ? $values['iapID'] : '';
$has_match = in_array( $stored_id, array_map( 'strval', array_column( $membership_options, 'id' ) ), true );
?>
<div class="apppresser-iap-mapping" data-iap-row>
	<div class="apppresser-iap-field">
		<label for="<?php echo esc_attr( $field_id ); ?>-membership"><?php echo esc_html( $membership_options ? $membership_label : sprintf( __( '%s ID', 'apppresser' ), $membership_label ) ); ?></label>
		<?php if ( $membership_options ) : ?>
			<select id="<?php echo esc_attr( $field_id ); ?>-membership" name="<?php echo esc_attr( $field_name . '[' . $keyname . ']' ); ?>">
				<option value=""><?php echo esc_html( sprintf( __( 'Select %s', 'apppresser' ), strtolower( $membership_label ) ) ); ?></option>
				<?php foreach ( $membership_options as $option ) : ?>
					<option value="<?php echo esc_attr( $option['id'] ); ?>" <?php selected( $stored_id, (string) $option['id'] ); ?>><?php echo esc_html( $option['label'] ); ?></option>
				<?php endforeach; ?>
				<?php if ( '' !== $stored_id && ! $has_match ) : ?>
					<option value="<?php echo esc_attr( $stored_id ); ?>" selected><?php echo esc_html( sprintf( __( 'Saved ID: %s (not found)', 'apppresser' ), $stored_id ) ); ?></option>
				<?php endif; ?>
			</select>
		<?php else : ?>
			<input type="text" id="<?php echo esc_attr( $field_id ); ?>-membership" name="<?php echo esc_attr( $field_name . '[' . $keyname . ']' ); ?>" value="<?php echo esc_attr( $stored_id ); ?>" aria-describedby="<?php echo esc_attr( $field_id ); ?>-help">
			<p class="description" id="<?php echo esc_attr( $field_id ); ?>-help"><?php esc_html_e( 'No items found. Enter an existing ID from this integration.', 'apppresser' ); ?></p>
		<?php endif; ?>
	</div>
	<div class="apppresser-iap-field">
		<label for="<?php echo esc_attr( $field_id ); ?>-purchase"><?php esc_html_e( 'In-app purchase ID', 'apppresser' ); ?></label>
		<input type="text" id="<?php echo esc_attr( $field_id ); ?>-purchase" name="<?php echo esc_attr( $field_name ); ?>[iapID]" value="<?php echo esc_attr( $purchase_id ); ?>" placeholder="<?php esc_attr_e( 'e.g. monthly_membership', 'apppresser' ); ?>" autocapitalize="none" autocomplete="off" spellcheck="false">
	</div>
	<button type="button" class="button-link button-link-delete apppresser-iap-remove" data-iap-remove aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s mapping', 'apppresser' ), $plugin_name ) ); ?>"><?php esc_html_e( 'Remove', 'apppresser' ); ?></button>
</div>
