<?php
/**
 * Template to display the contents of the log file
 *
 * @since 1.3.0
 */
?><div class="wrap">
	<h3><?php _e('Debug log'); ?></h3>
	<div id="tab-admin-page" class="wrap">
		<?php if( !$file_exists ) : ?>
			<div id="message" class="error inline">
				<p><?php _e( 'The log file does not exist ' . $this->get_log_file_name() ); ?></p>
			</div>
		<?php elseif( !$file_writeable ) : ?>
			<div id="message" class="error inline">
				<p><?php _e( 'The log file is not writeable ' . $this->get_log_file_name() ); ?></p>
			</div>
		<?php endif; ?>
		<div>
			<input type="checkbox" name="enable_log" id="enable_log" <?php checked( 'on', ApppLog::$logging_status ); ?>/> <label>Enable logging</label>
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
				<p><a href="<?php echo ApppLog::$log_url ?>" target="_blank"><?php echo ApppLog::$log_url ?></a></p>
		<?php endif; ?>
	</div>
</div>