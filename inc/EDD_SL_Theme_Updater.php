<?php

/**
 * Theme updater class. This code does not update any plugins or themes hosted on wordpress.org, it is for extensions that are hosted on apppresser.com.
 *
 * @package EDD Sample Theme
 * @version 1.0.3
 */

class Appp_EDD_Theme_Updater {

	private $remote_api_url;
	private $request_data;
	private $response_key;
	private $theme_slug;
	private $license_key;
	private $version;
	private $author;
	protected $strings = null;


	/**
	 * Initiate the Theme updater
	 *
	 * @param array $args    Array of arguments from the theme requesting an update check
	 * @param array $strings Strings for the update process
	 */
	function __construct($args = array(), $strings = array()) {

		$defaults = array(
			'remote_api_url' => 'http://easydigitaldownloads.com',
			'request_data'   => array(),
			'theme_slug'     => get_template(), // use get_stylesheet() for child theme updates
			'item_name'      => '',
			'license'        => '',
			'version'        => '',
			'author'         => '',
			'beta'           => false,
		);

		$args = wp_parse_args($args, $defaults);

		$this->license        = $args['license'];
		$this->item_name      = $args['item_name'];
		$this->version        = $args['version'];
		$this->theme_slug     = sanitize_key($args['theme_slug']);
		$this->author         = $args['author'];
		$this->beta           = $args['beta'];
		$this->remote_api_url = $args['remote_api_url'];
		$this->response_key   = $this->theme_slug . '-' . $this->beta . '-update-response';
		$this->strings        = $strings;

		add_filter('site_transient_update_themes',        array($this, 'theme_update_transient'));
		add_filter('delete_site_transient_update_themes', array($this, 'delete_theme_update_transient'));
		add_action('load-update-core.php',                array($this, 'delete_theme_update_transient'));
		add_action('load-themes.php',                     array($this, 'delete_theme_update_transient'));
		add_action('load-themes.php',                     array($this, 'load_themes_screen'));
	}

	/**
	 * Show the update notification when neecessary
	 *
	 * @return void
	 */
	function load_themes_screen() {
		add_thickbox();
		add_action('admin_notices', array($this, 'update_nag'));
	}

	/**
	 * Display the update notifications
	 *
	 * @return void
	 */
	function update_nag() {

		$strings      = $this->strings;
		$theme        = wp_get_theme($this->theme_slug);
		$api_response = get_transient($this->response_key);

		if (false === $api_response) {
			return;
		}

		$update_url     = wp_nonce_url('update.php?action=upgrade-theme&amp;theme=' . urlencode($this->theme_slug), 'upgrade-theme_' . $this->theme_slug);
		$update_onclick = ' onclick="if ( confirm(\'' . esc_js($strings['update-notice']) . '\') ) {return true;}return false;"';

		if (version_compare($this->version, $api_response->new_version, '<')) {

			echo '<div id="update-nag">';
			printf(
				$strings['update-available'],
				$theme->get('Name'),
				$api_response->new_version,
				'#TB_inline?width=640&amp;inlineId=' . $this->theme_slug . '_changelog',
				$theme->get('Name'),
				$update_url,
				$update_onclick
			);
			echo '</div>';
			echo '<div id="' . $this->theme_slug . '_' . 'changelog" style="display:none;">';
			echo wpautop($api_response->sections['changelog']);
			echo '</div>';
		}
	}

	/**
	 * Update the theme update transient with the response from the version check
	 *
	 * @param  array $value   The default update values.
	 * @return array|boolean  If an update is available, returns the update parameters, if no update is needed returns false, if
	 *                        the request fails returns false.
	 */
	public function theme_update_transient($value) {
		$update_data = $this->check_for_update();
		if ($update_data) {

			if (!is_object($value)) {
				$value = new stdClass;
			}

			// Make sure the theme property is set. See issue 1463 on Github in the Software Licensing Repo.
			$update_data['theme'] = $this->theme_slug;

			if (version_compare($this->version, $update_data['new_version'], '<')) {
				$value->response[$this->theme_slug] = $update_data;
			} else {
				$value->no_update[$this->theme_slug] = $update_data;
			}
		}

		return $value;
	}

	/**
	 * Remove the update data for the theme
	 *
	 * @return void
	 */
	function delete_theme_update_transient() {
		delete_transient($this->response_key);
	}

	/**
	 * Call the EDD SL API (using the URL in the construct) to get the latest version information
	 *
	 * @return array|boolean  If an update is available, returns the update parameters, if no update is needed returns false, if
	 *                        the request fails returns false.
	 */
	function check_for_update() {

		$update_data = get_transient($this->response_key);

		if (false === $update_data) {
			$failed = false;

			$api_params = array(
				'edd_action' => 'get_version',
				'license'    => $this->license,
				'name'       => $this->item_name,
				'slug'       => $this->theme_slug,
				'version'    => $this->version,
				'author'     => $this->author,
				'beta'       => $this->beta
			);

			$response = wp_remote_post($this->remote_api_url, array('timeout' => 15, 'body' => $api_params));

			// Make sure the response was successful
			if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response)) {
				$failed = true;
			}

			$update_data = json_decode(wp_remote_retrieve_body($response));

			if (!is_object($update_data)) {
				$failed = true;
			}

			// If the response failed, try again in 60 minutes
			if ($failed) {
				$data = new stdClass;
				$data->new_version = $this->version;
				set_transient($this->response_key, $data, strtotime('+60 minutes', current_time('timestamp')));
				return false;
			}

			// If the status is 'ok', return the update arguments
			if (!$failed) {
				if (isset($update_data->sections)) {
					$update_data->sections = maybe_unserialize($update_data->sections);
				}
				set_transient($this->response_key, $update_data, strtotime('+4 days', current_time('timestamp')));
			}
		}

		if (!isset($update_data->new_version) || version_compare($this->version, $update_data->new_version, '>=')) {
			return false;
		}

		return (array) $update_data;
	}
}
