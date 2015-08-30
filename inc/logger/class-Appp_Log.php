<?php

/**
 * ApppLog class
 *
 * @package AppPresser
 * @subpackage ApppLog
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class ApppLog {

	private static $instance;
	public static $logging_status_option = 'appplog';
	private $ajax_action = 'appptogglelog';
	public static $expire_logging = 'expire_logging';
	public static $logging_status;
	public static $log_filename = null;
	public static $log_dir_path = '/uploads/apppresser/';
	public static $log_filepath;
	public static $log_url = null;

	public function __construct() {

		$this->hooks();

		self::$logging_status = get_option( self::$logging_status_option, 'off' );
		self::$log_filename   = $this->get_filename();
		self::$log_filepath   = WP_CONTENT_DIR . self::$log_dir_path . self::$log_filename;
		self::$log_url        = WP_CONTENT_URL . ApppLog::$log_dir_path . ApppLog::$log_filename;
		if( !file_exists( self::$log_filepath ) ) {
			mkdir( WP_CONTENT_DIR . self::$log_dir_path );
			touch( self::$log_filepath );
		}
	}

	public function hooks() {
		add_action( 'wp_ajax_' . $this->ajax_action, array( $this, 'toggle_logging_callback' ) );
		add_action( 'wp_ajax_nopriv_' . $this->ajax_action, array( $this, 'toggle_logging_callback' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( self::$expire_logging, array($this, 'expire_logging') );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'appp-logger', AppPresser::$inc_url ."logger/appp.logger.js", array( 'jquery' ) );
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
		$logfile = fopen(self::$log_filepath, "a") or die("Unable to open file!");

		$txt = ( is_string( $var ) ) ? $var : print_r($var, true);

		$txt = '['.date('Y-m-d H:i:s').'] '.$function.'() /'.str_replace(ABSPATH, '', $file).':'.$line."\n".$title.': '.$txt."\n----\n";

		fwrite($logfile, $txt);
		fclose($logfile);
	}

	/**
	 * Turns logging off site wide from the admin setting log tab
	 * @since  1.3.0
	 */
	public function toggle_logging( $new_status = null ) {
		if( $new_status == null ) {
			$current_status = get_option( self::$logging_status_option, 'on' );
			$new_status = ( $current_status == 'on' ) ? 'off' : 'on';
		}

		update_option( self::$logging_status_option, $new_status );

		self::$logging_status = $new_status;

		if( $new_status == 'on' ) {
			$this->set_logging_cron();
		} else {
			$this->clear_logging_cron();
		}
	}

	/**
	 * Sets up the cron that will exprire the logging.  This is to avoid the log file growing out of control.
	 * @since  1.3.0
	 */
	public function set_logging_cron() {
		if ( ! wp_next_scheduled( self::$expire_logging ) ) {
			$one_day = (24 * 60 * 60);
			wp_schedule_single_event( time()+$one_day, self::$expire_logging );
		}
	}

	/**
	 * Clears the log on plugin deactivation
	 * @since 1.3.0
	 */
	public function clear_logging_cron() {
		wp_clear_scheduled_hook(self::$expire_logging);
	}

	/**
	 * Ajax call back that handles the checkbox onclick event to toggle enabling or disabling logging
	 * @since 1.3.0
	 */
	public function toggle_logging_callback() {
		if( isset( $_POST['status'] ) ) {
			$this->toggle_logging( $_POST['status'] );
			echo json_encode( array( 'status' => $_POST['status'], 'admin_email' => get_bloginfo('admin_email'), 'expire_logging' => wp_next_scheduled( self::$expire_logging ) ) );
		}

		die();
	}

	/**
	 * Turns off logging and email the admin to let them know
	 * @since 1.3.0
	 */
	public function expire_logging() {
		$this->toggle_logging( 'off' );
		//wp_clear_scheduled_hook(self::$expire_logging);
		wp_mail( get_bloginfo('admin_email'), 'ApppPresser Logging', 'AppPresser logging has been turned off.');
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
}

ApppLog::get_instance();

/**
 * A utility function to add a message to the log
 * @since 1.3.0
 */
function appp_debug_log( $title, $var, $file = 'file', $function = 'function', $line = 'line' ) {
	if( ApppLog::$logging_status == 'on' ) {
		ApppLog::log( $title, $var, $file, $function, $line );
	}
} add_action( 'appp_debug_log', 'appp_debug_log', 10, 5 );

