<?php

/**
 * AdminLogTab class
 *
 * @package AppPresser
 * @subpackage ApppLog
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AdminLogTab {

	public static $instance = null;
	private $template = 'template.php';

	public function __construct( $dir_path = '' ) {
		$this->template = $dir_path . DIRECTORY_SEPARATOR . $this->template;
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
	 * Gets the log file name
	 * @since  1.3.0
	 * @return string|boolean A file path or false if the file does not exist
	 */
	public function get_log_file_name() {
		if( file_exists( ApppLog::$log_filepath ) ) {
			return ApppLog::$log_filepath;
		} else{
			return false;
		}
	}

	/**
	 * Reads the log file
	 * @since  1.3.0
	 * @return string The file content
	 */
	public function get_log_file_content() {
		if( $this->get_log_file_name() ) {
			return file_get_contents( $this->get_log_file_name(), false );
		}

		return '';
	}

	/**
	 * Displays the template of the log file and admin settings under the log tab
	 * @since  1.3.0
	 */
	public function display_log() {
		$file_exists = file_exists( ApppLog::$log_filepath );
		$file_writeable = is_writeable( ApppLog::$log_filepath );

		include_once $this->template;
	}
}
