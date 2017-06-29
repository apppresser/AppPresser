<?php


/**
 * AppPresser_SystemInfo
 *
 * These are functions are used for supporting AppPresser
 *
 * @package     AppPresser
 * @subpackage  Settings/SystemInfo
 * @copyright   Copyright (c) 2017, AppPresser
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
*/

class AppPresser_SystemInfo {

	public function __construct() {
		
		if( isset( $_POST['appp-sysinfo'] ) ) {
			$this->generate_sysinfo_download();
		}

		add_action( 'apppresser_add_settings', array( $this, 'systeminfo_tab' ), 100 );
		add_action( 'apppresser_tab_top_systeminfo', array( $this, 'appp_system_info' ) );
		add_action( 'apppresser_tab_bottom_log', array( $this, 'appp_remove_settings_save_button' ) );

	}

	private function generate_sysinfo_download() {

		nocache_headers();

		header( "Content-type: text/plain" );
		header( 'Content-Disposition: attachment; filename="appp-system-info.txt"' );

		echo wp_strip_all_tags( $_POST['appp-sysinfo'] );

		die();
	}


	public function systeminfo_tab( $appp ) {
		$appp->add_setting_tab( __( 'System Info', 'apppresser' ), 'systeminfo' );
	}

	public function get_system_info() {
		global $wpdb;

		if ( get_bloginfo( 'version' ) < '3.4' ) {
			$theme_data = get_theme_data( get_stylesheet_directory() . '/style.css' );
			$theme      = $theme_data['Name'] . ' ' . $theme_data['Version'];
		} else {
			$theme_data = wp_get_theme();
			$theme      = $theme_data->Name . ' ' . $theme_data->Version;
		}

		// Try to identifty the hosting provider
		$host = false;
		if( defined( 'WPE_APIKEY' ) ) {
			$host = 'WP Engine';
		} elseif( defined( 'PAGELYBIN' ) ) {
			$host = 'Pagely';
		} else {
			$host = 'Unknown';
		}

		$sysinfo = array(
			'AppPresser Version' => AppPresser::VERSION,
			'Multisite' => is_multisite() ? 'Yes' . "\n" : 'No',
			'SITE_URL' => site_url(),
			'HOME_URL' => home_url(),
			'WordPress Version' => get_bloginfo( 'version' ),
			'Permalink Structure' => get_option( 'permalink_structure' ),
			'Registered Post Stati' => implode( ', ', get_post_stati() ),
			'THEMES' => array(
				'Active Theme' => $theme,
				'App 2 Theme' => appp_get_setting('appp_theme'),
				'Ap3 Ion Theme' => $this->get_appp_theme( 'ap3-ion-theme' ),
				'Ap3 Ion Child Theme' => $this->get_appp_theme( 'ion-ap3-child' ),
				'Ap3 site slug' => appp_get_setting('ap3_site_slug'),
				'ap3 app id' => appp_get_setting('ap3_app_id'),
			),
			'Host' => $host,
			'PHP INFO' => array(
				'PHP Version' => PHP_VERSION,
				'Web Server Info' => $_SERVER['SERVER_SOFTWARE'],
				'WordPress Memory Limit' => (  WP_MEMORY_LIMIT / 1024 )."MB",
				'PHP Safe Mode' => (version_compare(phpversion(), '5.3.0', '>=')===true) ? 'DEPRECATED as of PHP 5.3.0' : (ini_get( 'safe_mode' ) ? "Yes" : "No"),
				'PHP Memory Limit' => ini_get( 'memory_limit' ),
				'PHP Upload Max Size' => ini_get( 'upload_max_filesize' ),
				'PHP Post Max Size' => ini_get( 'post_max_size' ),
				'PHP Upload Max Filesize' => ini_get( 'upload_max_filesize' ),
				'PHP Time Limit' => ini_get( 'max_execution_time' ),
				'PHP Max Input Vars' => ini_get( 'max_input_vars' ),
				'PHP Arg Separator' => ini_get( 'arg_separator.output' ),
			),
			'PHP Allow URL File Open' => ini_get( 'allow_url_fopen' ) ? "Yes" : "No",
			'WP_DEBUG' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Enabled' : 'Disabled',
			'WP Table Prefix' => "Length: ". strlen( $wpdb->prefix ),
			'Status' => ( strlen( $wpdb->prefix )>16 ) ? "ERROR: Too Long" : "Acceptable",
			'Show On Front' => get_option( 'show_on_front' ),
			'Page On Front' => get_the_title( get_option( 'page_on_front' ) ) . ' (#' . get_option( 'page_on_front' ) . ')',
			'Page For Posts' => get_the_title( get_option( 'page_for_posts' ) ) . ' (#' . get_option( 'page_for_posts' ) . ')',
			'WP Remote Post' => $this->test_remote(),
			'Session' => isset( $_SESSION ) ? 'Enabled' : 'Disabled',
			'Session Name' => esc_html( ini_get( 'session.name' ) ),
			'Cookie Path' => esc_html( ini_get( 'session.cookie_path' ) ),
			'Save Path' => esc_html( ini_get( 'session.save_path' ) ),
			'Use Cookies' => ini_get( 'session.use_cookies' ) ? 'On' : 'Off',
			'Use Only Cookies' => ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off',
			'DISPLAY ERRORS' => ( ini_get( 'display_errors' ) ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A',
			'FSOCKOPEN' => ( function_exists( 'fsockopen' ) ) ? 'Your server supports fsockopen.' : 'Your server does not support fsockopen.',
			'cURL' => ( function_exists( 'curl_init' ) ) ? 'Your server supports cURL.' : 'Your server does not support cURL.',
			'SOAP Client' => ( class_exists( 'SoapClient' ) ) ? 'Your server has the SOAP Client enabled.' : 'Your server does not have the SOAP Client enabled.',
			'SUHOSIN' => ( extension_loaded( 'suhosin' ) ) ? 'Your server has SUHOSIN installed.' : 'Your server does not have SUHOSIN installed.',
		);

		$sysinfo['ACTIVE PLUGINS'] = array();

		$plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( $plugins as $plugin_path => $plugin ) {
			// If the plugin isn't active, don't show it.
			if ( ! in_array( $plugin_path, $active_plugins ) )
				continue;

			$sysinfo['ACTIVE PLUGINS'][$plugin['Name']] = $plugin['Version'];
		}

		if ( is_multisite() ) :
			
			$sysinfo['NETWORK ACTIVE PLUGINS'] = array();

			$plugins = wp_get_active_network_plugins();
			$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

			foreach ( $plugins as $plugin_path ) {
				$plugin_base = plugin_basename( $plugin_path );

				// If the plugin isn't active, don't show it.
				if ( ! array_key_exists( $plugin_base, $active_plugins ) )
					continue;

				$plugin = get_plugin_data( $plugin_path );

				$sysinfo['NETWORK ACTIVE PLUGINS'][] = array( $plugin['Name'] => $plugin['Version'] );
			}

		endif;

		if( file_exists(ABSPATH . '.htaccess') ) {
			$sysinfo['HTACCESS'] = array( '.htaccess' => file_get_contents(ABSPATH . '.htaccess') );
		} else {
			$sysinfo['htaccess'] = '.htaccess was not found.';
		}

		$sysinfo = apply_filters( 'appp_system_info', $sysinfo );
		
		return $sysinfo;
	}

	public function the_system_info($html = false) {

		$ln = ( $html ) ? "<br>\n" : "\n";

		echo '### Begin System Info ###' . $ln . $ln;

		$sysinfo = $this->get_system_info();

		foreach ($sysinfo as $key => $value) {
			if( is_array( $value ) ) {
				echo $ln . $key . $ln;
				foreach ($value as $key2 => $value2) {
					echo $key2 . ': ' . $value2 . $ln;
				}
			} else {
				echo $key . ': ' . $value . $ln;
			}
		}

		echo $ln . '### End System Info ###';
	}

	public function appp_system_info() {
		
?>
	<tr>
		<td>
			<h2><?php _e( 'System Information', 'apppresser' ); ?></h2><br/></form>
			<form action="<?php echo esc_url( admin_url( 'admin.php?page=apppresser_settings&tab=tab-systeminfo&appp-action=get_sysinfo' ) ); ?>" method="post" dir="ltr">
				<?php 

				// this takes too long to load, so only load it on (button click)

				if( isset( $_GET['tab'] ) && $_GET['tab'] == 'tab-systeminfo' ) : ?>
					<textarea readonly="readonly" onclick="this.focus();this.select()" id="appp-system-info" name="appp-sysinfo" title="<?php _e( 'To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac).', 'apppresser' ); ?>"><?php $this->the_system_info(); ?></textarea>
					<input type="hidden" name="appp-action" value="download_sysinfo" />
					<p class="sysinfo-download"><?php submit_button( 'Download System Info File', 'primary', 'appp-download-sysinfo', false ); ?></p>
				<?php else: ?>
					<?php submit_button( 'Get System Info', 'primary', 'appp-download-sysinfo', false ); ?>
				<?php endif; ?>
			</form>
		</td>
	</tr>
		
<?php
	}

	public function appp_remove_settings_save_button() {
		?>
		<script type="text/javascript">
			// hide the submit button only when the log tab is active
			jQuery(document).ready(function($) {
				var $context = $('.apppresser_settings');
				$context.on( 'click', '.nav-tab', function( event ) {
					event.preventDefault();
					var $self  = $(this);
					if( $self.data('selector') == 'tab-systeminfo' ) {
						$('p.submit').hide();
					} else {
						$('p.submit').show();
					}
				});

				var $self = $('.nav-tab.nav-tab-active');
				if($self.data('selector') == 'tab-systeminfo') {
					$('p.submit').hide();
				}
			});
		</script>
		<?php
	}

	public function get_appp_theme( $appp_theme ) {
		$appp_theme = wp_get_theme( $appp_theme  );
		if( $appp_theme->exists() ) {
			return $appp_theme->get( 'Version' );
		} else {
			return 'Not found';
		}
	}

	public function test_remote() {
		$request['cmd'] = '_notify-validate';

		$params = array(
			'sslverify'		=> false,
			'timeout'		=> 60,
			'user-agent'	=> 'APPP/' . AppPresser::VERSION,
			'body'			=> $request
		);

		$response = wp_remote_post( 'https://www.paypal.com/cgi-bin/webscr', $params );

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$WP_REMOTE_POST =  'wp_remote_post() works' . "\n";
		} else {
			$WP_REMOTE_POST =  'wp_remote_post() does not work' . "\n";
		}

		return $WP_REMOTE_POST;
	}

}

new AppPresser_SystemInfo();