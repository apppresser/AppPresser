<?php
/**
 * Admin Settings Log Pages
 *
 * @package AppPresser
 * @subpackage AppPresser_Log_Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

/**
 * Admin Log
 */
class AppPresser_Log_Admin extends AppPresser {

	// A single instance of this class.
	public static $instance = null;

	private $template = 'template.php';

	/**
	 * Creates or returns an instance of this class.
	 * @since  1.3.0
	 * @return AppPresser_Admin_Settings A single instance of this class.
	 */
	public static function run() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Setup the AppPresser_Log_Admin Settings
	 * @since  1.3.0
	 */
	function __construct() {
		add_action( 'apppresser_add_settings', array( $this, 'log_viewer' ), 60 );
		add_action( 'apppresser_tab_bottom_log', array( $this, 'appp_log_file_info' ) );
		add_action( 'apppresser_tab_bottom_log', array( $this, 'appp_remove_settings_save_button' ) );
		add_action( 'admin_head', array( $this, 'admin_head_javascript' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer_javascript' ) );
		add_action( 'wp_ajax_appp_log', array( $this, 'ajax_log' ) );
		add_action( 'wp_ajax_nopriv_appp_log', array( $this, 'ajax_log' ) );
	}

	/**
	 * Add Log tab!
	 * @since 1.3.0
	 * @param object  $apppresser  AppPresser_Admin_Settings Instance
	 */
	public function log_viewer( $apppresser ) {

		// Create a new tab for our settings
		$apppresser->add_setting_tab( __( 'Log', 'apppresser' ), 'log' );
	}

	/**
	 * Add the log template.php to the tab
	 * @since 1.3.0
	 * @param array $arg1
	 */
	public function appp_log_file_info( $arg1 ) {

		$this->template = self::$tmpl_path . $this->template;

		echo '<tr><td>';
		$this->display_log();
		echo '</td></tr>';
	}

	/**
	 * Removes the save settings button when viewing the log tab
	 * @since 1.3.0
	 */
	public function appp_remove_settings_save_button() {
		?>
		<script type="text/javascript">
			// hide the submit button only when the log tab is active
			jQuery(document).ready(function($) {
				var $context = $('.apppresser_settings');
				$context.on( 'click', '.nav-tab', function( event ) {
					event.preventDefault();
					var $self  = $(this);
					if( $self.data('selector') == 'tab-log' ) {
						$('p.submit').hide();
					} else {
						$('p.submit').show();
					}
				});

				var $self = $('.nav-tab.nav-tab-active');
				if($self.data('selector') == 'tab-log') {
					$('p.submit').hide();
				}
			});
		</script>
		<?php
	}

	/**
	 * The server side ajax handler when sending log messages from JavaScript
	 * @since 1.3.0
	 */
	public function ajax_log() {

		$post_vars = array( 'title', 'var', 'file', 'function', 'line' );

		$log = array();

		foreach ($post_vars as $key) {
			$log[$key] = ( isset( $_POST[$key] ) ) ? $_POST[$key] : '';
		}

		do_action( 'appp_debug_log', $log['title'], $log['var'], $log['file'], $log['function'], $log['line'] );
		wp_die();
	}

	/**
	 * Initializes the appp_log_data variable for the admin_head
	 * @since 1.3.0
	 */
	public function admin_head_javascript() { ?>
		<script type="text/javascript">
		var appp_log_data = {
			'action':'appp_log',
			'title':'',
			'var':'',
			'file':'',
			'function':'',
			'line':''
		};

		</script>
	<?php
	}

	/**
	 * Adds the app_log() function for the wp admin
	 *
	 * // how to test
	 *	appp_log_data.title = 'test';
	 *	appp_log_data.var = 'my var';
	 *	appp_log_data.file = 'some.js';
	 *	appp_log_data.function = 'test()';
     *	appp_log_data.line = '139';
     *
	 *	app_log();
	 * @since 1.3.0
	 */
	public function admin_footer_javascript() { ?>
		<script type="text/javascript">
		function app_log() {
			jQuery.post(ajaxurl, appp_log_data, function(response) {
				// silence
			});
		};
		</script> <?php
	}

	/**
	 * Gets the log file name
	 * @since  1.3.0
	 * @return string|boolean A file path or false if the file does not exist
	 */
	public function get_log_file_name() {
		if( file_exists( AppPresser_Logger::$log_filepath ) ) {
			return AppPresser_Logger::$log_filepath;
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
		$file_exists = file_exists( AppPresser_Logger::$log_filepath );
		$file_writeable = is_writeable( AppPresser_Logger::$log_filepath );

		include_once $this->template;
	}
}
AppPresser_Log_Admin::run();