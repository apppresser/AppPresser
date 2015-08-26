<?php


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

	public function get_log_file_name() {
		if( file_exists( ApppLog::$log_filepath ) ) {
			return ApppLog::$log_filepath;
		} else{
			return false;
		}
	}

	public function get_log_file_content() {
		if( $this->get_log_file_name() ) {
			return file_get_contents( $this->get_log_file_name(), false );
		}

		return '';
	}

	public function display_log() {
		$file_exists = file_exists( ApppLog::$log_filepath );
		$file_writeable = is_writeable( ApppLog::$log_filepath );
		$file_log_url = WP_CONTENT_URL . ApppLog::$log_dir_path . ApppLog::$log_filename;

		include_once $this->template;
	}

}
