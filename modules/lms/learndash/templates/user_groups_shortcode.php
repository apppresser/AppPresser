<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays a user group.
 *
 * @since 2.1.0
 * 
 * @package LearnDash\Groups
 */
?>

<?php if ( $has_admin_groups ) : ?>	

<div class="card">

	<div class="item card-title"><h2><?php esc_html_e( 'Group Leader in : ', 'learndash' ); ?></h2></div>
    <?php echo '<ul class="list">'; ?>
	<?php  foreach ( $admin_groups as $group_id ) : ?>
		<?php if ( ! empty( $group_id ) ) : ?>
			<?php $group = get_post( $group_id ); ?>
			<li class="item"><b><?php echo $group->post_title; ?></b> - <?php echo trim( $group->post_content ); ?></li>
		<?php endif; ?>
	<?php endforeach; ?>
	<?php echo '</ul>'; ?>
<?php endif; ?>

<?php if ( $has_user_groups ) : ?>
	<b><?php esc_html_e( 'Assigned Group(s) : ', 'learndash' ); ?></b>
    <?php echo '<ul class="list">'; ?>
	<?php foreach( $user_groups as $group_id ) : ?>
		<?php if( ! empty( $group_id ) ) : ?>
			<?php $group = get_post( $group_id ); ?>
			<li class="item"><b><?php echo $group->post_title; ?></b> - <?php echo trim( $group->post_content ); ?></li>
			<?php endif; ?>
	<?php endforeach; ?>
	<?php echo '</ul>'; ?>
	
</div>
<?php endif; ?>