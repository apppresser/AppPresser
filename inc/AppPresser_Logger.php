<?php

/**
 * AppPresser_Logger class
 *
 * @package AppPresser
 * @subpackage AppPresser_Logger
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_Logger {

	private static $instance;
	public static $logging_status_option = 'appplog';
	private $ajax_action = 'appptogglelog';
	public static $expire_logging = 'expire_logging';
	public static $logging_status;
	public static $log_filename = null;
	public static $log_dir_path = 'apppresser';
	public static $uploads_dir_path;
	public static $log_filepath;
	public static $log_url = null;
	const USER_META_LOG_NAG = 'appp_log_error_nag_ignore';

	public function __construct() {

		self::$logging_status = get_option( self::$logging_status_option, 'off' );
		self::$log_filename   = $this->get_filename();

		$upload_dir = wp_upload_dir();

		self::$log_url = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR . self::$log_dir_path . DIRECTORY_SEPARATOR . self::$log_filename;

		self::$uploads_dir_path = $upload_dir['basedir'];
		self::$log_dir_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . self::$log_dir_path;

		self::$log_filepath = self::$log_dir_path . DIRECTORY_SEPARATOR . self::$log_filename;
		
		$this->hooks();
	}

	public function hooks() {
		add_action( 'wp_ajax_' . $this->ajax_action, array( $this, 'toggle_logging_callback' ) );
		add_action( 'wp_ajax_nopriv_' . $this->ajax_action, array( $this, 'toggle_logging_callback' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_log_dismiss', array( $this, 'log_dismiss' ) );
		add_action( 'wp_ajax_nopriv_log_dismiss', array( $this, 'log_dismiss' ) );

		// Log dir and file
		add_action('admin_notices', array( $this, 'log_dir_exists' ) );
		add_action('init', array( $this, 'force_logging_off' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'appp-logger', AppPresser::$js_url ."/appp.logger.js", array( 'jquery' ) );
	}

	/**
	 * Creates or returns an instance of this class.
	 * @since  1.3.0
	 * @return AppPresser A single instance of this class.
	 */
	public static function get_instance() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Write the log message to the file
	 * @since  1.3.0
	 */
	public static function log( $title, $var, $file = 'file', $function = 'function', $line = 'line' ) {

		if( self::get_logging_timeout() < 0 ) {
			self::toggle_logging( 'off' );
		}

		$logfile = fopen(self::$log_filepath, "a") or die("Unable to open file!");

		$txt = ( is_string( $var ) ) ? $var : print_r($var, true);

		$txt = '['.date('Y-m-d H:i:s').'] '.$function.'() /'.str_replace(ABSPATH, '', $file).':'.$line."\n".$title.': '.$txt."\n----\n";

		fwrite($logfile, stripcslashes ( $txt ) );
		fclose($logfile);
	}

	public static function clear_log() {
		$logfile = fopen(self::$log_filepath, "w") or die("Unable to open file!");

		fwrite($logfile, '' );
		fclose($logfile);
	}

	/**
	 * Turns logging off site wide from the admin setting log tab
	 * @since  1.3.0
	 */
	public static function toggle_logging( $new_status = null ) {
		if( $new_status == null ) {
			$current_status = get_option( self::$logging_status_option, 'on' );
			$new_status = ( $current_status == 'on' ) ? 'off' : 'on';
		}

		update_option( self::$logging_status_option, $new_status );

		self::$logging_status = $new_status;

		if( $new_status == 'on' ) {
			self::set_logging_timeout();
		} else {
			delete_option( 'appp_logging_timeout' );
		}
	}

	public static function get_logging_timeout() {
		$timeout = get_option( 'appp_logging_timeout' );

		if( $timeout ) {

			if ( (int)$timeout - time() < 0 ) {
				// timed out
				self::expire_logging();
				return (int)$timeout - time() < 0;
			}
		}

		return 0;
	}

	public static function set_logging_timeout() {
		$one_hour = (60 * 60);
		$timeout = time() + $one_hour;

		update_option( 'appp_logging_timeout', $timeout );
	}

	/**
	 * Ajax call back that handles the checkbox onclick event to toggle enabling or disabling logging
	 * @since 1.3.0
	 */
	public function toggle_logging_callback() {
		if( isset( $_POST['status'] ) ) {
			self::toggle_logging( $_POST['status'] );
			echo json_encode( array( 'status' => $_POST['status'], 'admin_email' => get_bloginfo('admin_email'), 'expire_logging' => wp_next_scheduled( self::$expire_logging ) ) );
		}

		die();
	}

	/**
	 * Turns off logging and email the admin to let them know
	 * @since 1.3.0
	 */
	public static function expire_logging() {
		self::toggle_logging( 'off' );
		//wp_clear_scheduled_hook(self::$expire_logging);
		wp_mail( get_bloginfo('admin_email'), __('AppPresser Logging', 'apppresser'), __('AppPresser logging has been turned off.', 'apppresser' ) );
	}

	/**
	 * Since the filename is randmized this will look up its name from the wp_options table
	 * @since 1.3.0
	 */
	public function get_filename() {

		if( self::$log_filename == null ) {
			$filename = get_option( 'appplog_filename', false );
			if( ! $filename ) {
				$filename = uniqid( 'apppresser-' ).'.log';
				update_option( 'appplog_filename', $filename );
			}
			self::$log_filename = $filename;
		}
		return self::$log_filename;
	}

	/**
	 *	Create the wp-content/uploads/apppresser directory
	 *  1.3.0
	 */
	public function log_dir_exists() {

		if( ! is_admin() || ! current_user_can('manage_options') ) {
			return;
		}

		global $current_user;

		$user_id = $current_user->ID;

		if ( get_user_meta($user_id, self::USER_META_LOG_NAG, true) === 'true' ) {

			// ignore the admin notice

			return;
		}

		if( ! file_exists( self::$log_filepath ) ) {

			if( ! file_exists( self::$log_dir_path ) ) {

				if( is_writable( self::$uploads_dir_path ) ) {
				// create the directory if it doesn't exist
					wp_mkdir_p( self::$log_dir_path );	
				} else {
					// Can not create directory
					echo ' <div class="error notice is-dismissible app-new-log-error">
					<p><b>AppPresser Debugging Log File</b></p>
				        <p>' . self::$log_dir_path . ' ' . __( 'directory is not writable', 'apppresser' ) . '</p>
				    	</div>
				    <script type="text/javascript">

				    jQuery(document).ready(function() {
				    	// double layer doc ready to add these events after wp-admin doc ready stuff
						jQuery(document).ready(function() {				    	

							jQuery(".app-new-log-error .notice-dismiss").on("click",function() {
								jQuery.ajax({
									type: "POST",
									url:  "'.admin_url('admin-ajax.php').'",
									data: {
										action: "log_dismiss"
									},
									success: function(response) {
										console.log(response);
									}
								});
							});
						});

					});
				    	
				    </script>';

					return;
				}
			}
			
			// create the file if it doesn't exist
			if( ! file_exists( self::$log_filepath ) && is_writable( self::$log_dir_path ) ) {
				@touch( self::$log_filepath );
			} else {
				// directory exist but the directory is not writable
				echo '<div class="error notice is-dismissible app-new-log-error">
						<p><b>AppPresser Debugging Log File</b></p>
				        <p>' . self::$log_filepath . ' ' . __('file is not writable', 'apppresser') . '</p>
				      </div>
				    <script type="text/javascript">

				    jQuery(document).ready(function() {
				    	// double layer doc ready to add these events after wp-admin doc ready stuff
						jQuery(document).ready(function() {				    	

							jQuery(".app-new-log-error .notice-dismiss").on("click",function() {
								jQuery.ajax({
									type: "POST",
									url:  "'.admin_url('admin-ajax.php').'",
									data: {
										action: "log_dismiss"
									},
									success: function(response) {
										console.log(response);
									}
								});
							});
						});

					});
				    	
				    </script>';
			}
		}
	}

	public function log_dismiss() {
		global $current_user;

		$user_id = $current_user->ID;
		if ( ! get_user_meta($user_id, self::USER_META_LOG_NAG) ) {
			add_user_meta($user_id, self::USER_META_LOG_NAG, 'true', true);
		}

		echo $user_id;
		die();
	}

	public static function remove_usermeta() {
		global $wpdb;

		// Delete any nag user_meta
		$wpdb->query( "DELETE FROM {$wpdb->prefix}usermeta WHERE meta_key = '".self::USER_META_LOG_NAG."';" );
	}

	public function force_logging_off() {
		if( isset( $_GET['forceloggingoff']) ) {
			self::toggle_logging( 'off' );
		}

		if( isset( $_GET['apppclearlog'] ) ) {
			self::clear_log();
		}
	}
}

AppPresser_Logger::get_instance();

/**
 * A utility function to add a message to the log
 * @since 1.3.0
 */
function appp_debug_log( $title, $var, $file = 'file', $function = 'function', $line = 'line' ) {
	if( AppPresser_Logger::$logging_status == 'on' ) {
		AppPresser_Logger::log( $title, $var, $file, $function, $line );
	}
} add_action( 'appp_debug_log', 'appp_debug_log', 10, 5 );

