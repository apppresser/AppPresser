<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

$attachments = self::get_photos();
if ( ! is_array( $attachments ) )
	$attachments = array();

wp_enqueue_script( 'appp-camera-admin', self::$plugin_url .'js/appp-camera-admin.js', array( 'wp-backbone' ), self::VERSION );
$appCamAdmin = array(
	'tableTemplate' => '#apppresser-moderation-form tbody',
	'rowTemplate'   => 'rowTemplate',
	'action'        => 'image_approval_handler',
	'settingsURL'   => esc_url( add_query_arg( 'page', AppPresser_Admin_Settings::$page_slug, admin_url( 'admin.php' ) ) ),
	'no_photos'     => __( 'No Photos to Moderate.', 'apppresser-camera' ),
	'redirected'    => __( ' You will be redirected shortly.', 'apppresser-camera' ),
	'attachments'   => $this->moderationJSON( $attachments ),
);
wp_localize_script( 'appp-camera-admin', 'appCamAdmin', $appCamAdmin );

?>
<div class="wrap" >
	<h2><?php _e( 'AppPresser Photo Moderation', 'apppresser-camera' ); ?></h2>
	<?php if ( ! empty( $attachments ) ) { ?>
	<div class="have-photos">
		<p><?php _e( 'Check the photos you would like to Approve or Deny.', 'apppresser-camera' ); ?></p>
		<form method="post" id="apppresser-moderation-form">
			<table class="wp-list-table widefat fixed media" cellspacing="0">
				<thead>
					<tr>
						<th scope="col" id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1"><?php _e( 'Select All', 'apppresser-camera' ); ?></label><input id="cb-select-all-1" type="checkbox"></th>
						<th scope="col"></th>
						<th scope="col"><?php _e( 'File', 'apppresser-camera' ); ?></th>
						<th scope="col"><?php _e( 'Author', 'apppresser-camera' ); ?></th>
						<th scope="col"><?php _e( 'Attached to', 'apppresser-camera' ); ?></th>
						<th scope="col"><?php _e( 'Date', 'apppresser-camera' ); ?></th>
					</tr>
				</thead>
					<tbody>
					</tbody>
			</table>
			<p>
				<input type="submit" class="button button-primary" name="appp_handle_photos" value="<?php esc_attr_e( 'Approve', 'apppresser-camera' ); ?>">
				<input type="submit" class="button button-secondary" name="appp_handle_photos" value="<?php esc_attr_e( 'Deny',  'apppresser-camera' ); ?>">
			</p>
		</form>
	</div>
	<?php } else { ?>
		<p class="no-photos"><?php echo $appCamAdmin['no_photos']; ?></p>
	<?php } ?>
</div>

<!-- Undescore Template -->
<script type="text/template" id="tmpl-rowTemplate">
	<th scope="row" class="check-column" data-attachment_id="{{{ data.post_id }}}">
		<input type="checkbox" name="appp_photos[]" id="appp_image_{{{ data.id }}}" value="{{{ data.id }}}">
	</th>
	<td>
		<a href="{{{ data.url }}}">{{{ data.thumb }}}</a>
	</td>
	<td>
		<strong class="title"><a href="{{{ data.editLink }}}">{{{ data.title }}}</a></strong>
		<p>{{{ data.mime }}}</p>
		<div class="file-error" style="display:none;"><?php _e( 'Whoops, Something went wrong!', 'apppresser-camera' ); ?></div>
		<div class="row-actions">
			<span class="edit"><a href="#"><?php _e( 'Approve', 'apppresser-camera' ); ?></a> | </span>
			<span class="delete"><a class="submitdelete" href="#"><?php _e( 'Deny', 'apppresser-camera' ); ?></a> | </span>
			<span class="view"><a href="{{{ data.url }}}" title="View {{{ data.title }}}" rel="permalink"><?php _e( 'View', 'apppresser-camera' ); ?></a></span>
		</div>
		<p style="float:left;" class="spinner"></p>
	</td>
	<td>
		<a href="{{{ data.authorURL }}}">{{{ data.authorName }}}</a>
	</td>
	<td>
		<strong><a href="{{{ data.parentEditURL }}}">{{{ data.parentTitle }}}</a></strong>
	</td>
	<td>
		{{{ data.date }}}
	</td>
</script>
<!-- Underscore Template ### END -->
<?php
