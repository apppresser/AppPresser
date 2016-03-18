<?php
/**
 * Template to display the contents of the log file
 *
 * @since 1.3.0
 */
?><div class="wrap">
	<h3><?php _e('Debug log', 'apppresser'); ?></h3>
	<div id="tab-admin-page" class="wrap">
		<?php if( !$file_exists ) : ?>
			<div id="message" class="error inline">
				<p><?php echo sprintf( __( 'The log file does not exist %s', 'apppresser' ), $this->get_log_file_name() ); ?></p>
			</div>
		<?php elseif( !$file_writeable ) : ?>
			<div id="message" class="error inline">
				<p><?php echo sprintf( __( 'The log file is not writeable %s', 'apppresser'), $this->get_log_file_name() ); ?></p>
			</div>
		<?php endif; ?>
		<div>
			<input type="checkbox" name="enable_log" id="enable_log" <?php checked( 'on', AppPresser_Logger::$logging_status ); ?>/> <label><?php _e( 'Enable logging', 'apppresser'); ?></label>
			<script type="text/javascript">
				jQuery( document ).on( 'click', '#enable_log', function(event){
					apppLogger.adminToggleLogging(jQuery('#enable_log'));
				});
			</script>
		</div>
		<div>
			<?php if( is_file( $this->get_log_file_name() ) ) : ?>
				<textarea id="log_file" name="logfile" rows="30" cols="100" readonly="readonly"><?php echo $this->get_log_file_content(); ?></textarea>
			<?php endif; ?>
		</div>
		<?php if( $file_exists ) : ?>
				<p><a href="<?php echo AppPresser_Logger::$log_url ?>" target="_blank"><?php echo AppPresser_Logger::$log_url ?></a></p>
				<p><a class="button" href="<?php echo admin_url( 'admin.php?page=apppresser_settings&tab=tab-log&apppclearlog=1' ); ?>">Clear Log</a></p>
		<?php endif; ?>
	</div>
</div>