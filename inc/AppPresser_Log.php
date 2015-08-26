<?php
/**
 * Admin Settings Log Pages
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

/**
 * Admin Log
 */
class AppPresser_Log extends AppPresser {

	// A single instance of this class.
	public static $instance        = null;

	/**
	 * Creates or returns an instance of this class.
	 * @since  1.1.9
	 * @return AppPresser_Admin_Settings A single instance of this class.
	 */
	public static function run() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Setup the AppPresser Settings
	 * @since  1.1.9
	 */
	function __construct() {

		require_once( self::$inc_path . 'logger/class-Appp_Log.php' );
		require_once( self::$inc_path . 'logger/class-Admin_Log_Tab.php' );

		add_action( 'apppresser_add_settings', array( $this, 'log_viewer' ), 60 );
		add_action( 'apppresser_tab_bottom_log', array( $this, 'appp_log_file_info' ) );
		add_action( 'apppresser_tab_bottom_log', array( $this, 'appp_remove_settings_save_button' ) );
		add_action( 'plugins_loaded', array( 'AdminLogTab', 'get_instance' ) );
		add_action( 'admin_head', array( $this, 'admin_head_javascript' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer_javascript' ) );
		add_action( 'wp_ajax_appp_log', array( $this, 'ajax_log' ) );
		add_action( 'wp_ajax_nopriv_appp_log', array( $this, 'ajax_log' ) );
	}

	/**
	 * Add Log tab!
	 * @param object  $apppresser  AppPresser_Admin_Settings Instance
	 */
	public function log_viewer( $apppresser ) {

		// Create a new tab for our settings
		$apppresser->add_setting_tab( __( 'Log', 'appp' ), 'log' );
	}

	/**
	 *
	 */
	public function appp_log_file_info( $arg1 ) {
		$view_log = new AdminLogTab(self::$inc_path.'logger');
		echo '<tr><td>';
		$view_log->display_log();
		echo '</td></tr>';
	}

	/**
	 * Add an arbitrary row to an options tab in the AppPresser Settings API.
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

	public function ajax_log() {

		$post_vars = array( 'title', 'var', 'file', 'function', 'line' );

		$log = array();

		foreach ($post_vars as $key) {
			$log[$key] = ( isset( $_POST[$key] ) ) ? $_POST[$key] : '';
		}

		do_action( 'appp_debug_log', $log['title'], $log['var'], $log['file'], $log['function'], $log['line'] );
		wp_die();
	}

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
	 * // test
	 *	appp_log_data.title = 'test';
	 *	appp_log_data.var = 'my var';
	 *	appp_log_data.file = 'some.js';
	 *	appp_log_data.function = 'test()';
     *	appp_log_data.line = '139';
     *
	 *	app_log();
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
}
AppPresser_Log::run();