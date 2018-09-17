<?php
/**
 * Ajax login, extras
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_Ajax_Extras extends AppPresser {

	public static $errorpath = '../php-error-log.php';
	public static $default_thumbnail;

	public static function run() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Party Started
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->hooks();
	}

	public function hooks() {
		add_action( 'wp_ajax_nopriv_app-lost-password', array( $this, 'appp_reset_password' ) );
		add_action('wp_ajax_nopriv_app-validate-password', array( $this, 'appp_validate_password_code') );

		add_action( 'wp_ajax_appp_load_more', array( $this, 'appp_load_more' ) );
		add_action( 'wp_ajax_nopriv_appp_load_more', array( $this, 'appp_load_more' ) );

		add_action( 'wp_ajax_nopriv_apppajaxlogin', array( $this, 'appp_ajax_login' ) );

		add_action('wp_ajax_apppajaxlogout', array( $this, 'appp_ajax_logout' ) );
		add_action('wp_ajax_nopriv_apppajaxlogout', array( $this, 'appp_ajax_logout' ) );

		add_action('wp_ajax_myappp_verify', array( $this, 'myappp_verify' ) );
		add_action('wp_ajax_nopriv_myappp_verify', array( $this, 'myappp_verify' ) );

	}

	/**
	 * Adds ajax for form#loginform modal in apptheme 2.1.3 and ion 1.0.1
	 * @since 2.0.1
	 */
	public function appp_ajax_login() {
			
		// check_ajax_referer( 'ajax-login-nonce', 'security' );

		$info = array();

		$info['user_login'] = ( $_POST['username'] ? $_POST['username'] : $_SERVER['PHP_AUTH_USER'] );
		$info['user_password'] = ( $_POST['password'] ? $_POST['password'] : $_SERVER['PHP_AUTH_PW'] );
		
		$info['remember'] = true;
		
		$user_signon = wp_signon( $info, false );

		do_action( 'appp_ajax_login_header' );
		
		if( is_wp_error( $user_signon ) ) {
		
			$return = array(
				'message' =>  apply_filters( 'appp_login_error', __('The log in you have entered is not valid.', 'apppresser'), $info['user_login'] ),
				'signon' => $info['user_login'] . $info['user_password'],
				'line' => __LINE__,
				'success' => false
			);
			wp_send_json_error( $return );
			
		} else {

			$return = array(
				'message' => apply_filters( 'appp_login_success', sprintf( __('Welcome back %s!', 'apppresser'), $user_signon->display_name), $user_signon->ID ),
				'username' => $info['user_login'],
				'avatar' => get_avatar_url( $user_signon->ID ),
				'login_redirect' => self::get_login_redirect(), // v3 only
				'success' => true
			);

			if( defined('MYAPPPRESSER_DEV_DOMAIN') ) // local development only
				@header( 'Access-Control-Allow-Origin: *' );
				
			wp_send_json_success( apply_filters( 'appp_login_data', $return, $user_signon->ID ) );
			
		}
	}

	/**
	 * Get the login redirect for the app's login modal
	 * 
	 * @since 3.2.1
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public static function get_login_redirect() {

		if( has_filter( 'appp_login_redirect' ) ) {
			$redirect_to = apply_filters( 'appp_login_redirect', '' );

			return self::add_redirect_title( $redirect_to );
			
		} else {
			return '';
		}
	}

	/**
	 * Get the login redirect for the app's login modal
	 * 
	 * @since 3.3.0
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public function get_logout_redirect() {

		if( has_filter( 'appp_logout_redirect' ) ) {
			$redirect_to = apply_filters( 'appp_logout_redirect', '' );

			return self::add_redirect_title( $redirect_to );
			
		} else {
			return '';
		}
	}

	/**
	 * Use the URL to get title or just returns a string if it is a app custom page slug
	 * 
	 * @since 3.3.0
	 * 
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public static function add_redirect_title( $redirect_to ) {
		if(is_string($redirect_to) && strpos($redirect_to, 'http') !== false) {

			// a URL

			$post_id = url_to_postid( $redirect_to );

			return array(
				'url' => $redirect_to,
				'title' => ($post_id) ? get_the_title( $post_id ) : '',
			);	
		} else {
			// An app custom page slug

			return $redirect_to;
		}
	}

	/**
	 * Logout, used via postmessage in AP3 apps
	 * @since 3.0.2
	 */
	public function appp_ajax_logout() {

		do_action( 'appp_ajax_logout_header' );

		wp_logout();

		if( defined('MYAPPPRESSER_DEV_DOMAIN') ) // local development only
				@header( 'Access-Control-Allow-Origin: *' );


		$response = array(
			'message' => __('Logout success.', 'apppresser')
		);

		$redirect = $this->get_logout_redirect();
		if($redirect) {
			$response['logout_redirect'] = $redirect;
		}

		wp_send_json_success( $response );

	}

	/**
	 * AJAX Load More
	 * @link http://www.billerickson.net/infinite-scroll-in-wordpress
	 */
	public function appp_load_more() {

		global $wp_query;

		check_ajax_referer( 'app-load-more-nonce', 'nonce' );
    
		$args = isset( $_POST['query'] ) ? array_map( 'esc_attr', $_POST['query'] ) : array();
		$args['post_type'] = isset( $args['post_type'] ) ? esc_attr( $args['post_type'] ) : 'post';
		$args['paged'] = esc_attr( $_POST['page'] );
		$args['posts_per_page'] = isset( $_POST['posts_per_page'] ) ? $_POST['posts_per_page'] : get_option( 'posts_per_page' );
		$args['post_status'] = 'publish';
		$this->parse_url_query_vars( $args );
		$data = array();
		$wp_query = new WP_Query( $args );
		$list_type = (isset( $_POST['list_type'] )) ? $_POST['list_type'] : 'medialist';
		$custom_template = $this->get_childtheme_list_template($list_type);

		/**
		 * Only AP3 Ion Theme 1.3.0+ will send $args['list_type']
		 * and will be able to handle $data['html'].
		 * 
		 * Now developers can use list type template from ion-ap3-child/content-{list_type}.php
		 */
		if( $custom_template && isset( $_POST['list_type'] )) {

			ob_start();
			if( have_posts() ): while( have_posts() ): the_post();
				include( $custom_template );
			endwhile; endif;
			$data['html'] = ob_get_contents();
			ob_end_clean();

		} else {
			if( have_posts() ): while( have_posts() ): the_post();

				$data[] = array( 
					'id' => get_the_ID(),
					'permalink' => get_the_permalink(),
					'title' => get_the_title(),
					'excerpt' => get_the_excerpt(),
					'thumbnail' => $this->get_thumbnail( get_the_ID() ),
					'full' => get_the_post_thumbnail_url( get_the_ID(), 'full' )
					);
			endwhile; endif;
		}

		wp_reset_postdata();
		wp_send_json_success( $data );
	}

	/**
	 * Get a thumbnail:
	 *   1. post thumbnail
	 *   2. child theme default thumbnail
	 *   3. parent theme default thumbnail
	 */
	public function get_thumbnail( $post_id ) {

		$thumbnail = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
		if( empty( $thumbnail ) ) {
			return $this->get_default_thumbnail();
		}

		return $thumbnail;
	}

	public static function get_default_thumbnail() {
		if( is_null( self::$default_thumbnail ) ) {
			$app_theme = self::get_app_theme();

			$stylesheet = str_replace( '%2F', '/', rawurlencode( $app_theme ) );
			$theme_root_uri = get_theme_root_uri( $stylesheet );
			$stylesheet_dir_uri = "$theme_root_uri/$stylesheet";	

			if(file_exists(get_theme_root() . '/'. $app_theme . '/images/thumbnail.jpg')) {
				// child theme
				self::$default_thumbnail = $stylesheet_dir_uri . '/images/thumbnail.jpg';
			} else {
				self::$default_thumbnail = $theme_root_uri . '/ap3-ion-theme/images/thumbnail.jpg';
			}
		}

		return self::$default_thumbnail;
	}

	/**
	 * Checks if there is a list template in the child theme
	 */
	public function get_childtheme_list_template($list_type) {

		$list_type = ($list_type == 'default') ? 'medialist' : $list_type;

		$template_name = "content-$list_type.php";
		
		$theme = self::get_app_theme();

		$template = get_theme_root() . '/'.$theme.'/'.$template_name;
		
		return (file_exists($template)) ? $template : false;
	}

	public static function get_app_theme() {
		$child_theme_slug = 'ion-ap3-child';
		$child_theme = wp_get_theme( $child_theme_slug );

		if ( $child_theme->exists() ) {
			$theme = $child_theme_slug;
		} else {
			$theme = apply_filters( 'appp_theme', 'ap3-ion-theme' );
		}

		return $theme;
	}

	/**
	 * When the URL looks similar to this: /app-list/?list_type=cardlist&cat=20&appp=3&num=1
	 * custom.js will send the entire query as 'url_query' in a $_POST var, so
	 * parse that info and add it to the existing $args
	 */
	public function parse_url_query_vars( &$args ) {
		if( isset( $_POST['url_query'] ) ) {
			$url_query = str_replace('?', '', $_POST['url_query']);
			$url_query = explode('&', $url_query);
			foreach ($url_query as $qv_pairs) {
				$kv = explode('=', $qv_pairs);
				if( isset( $kv[0] ) && !empty($kv[0]) &&
				    isset( $kv[1] ) && !empty($kv[1]) &&
				    ! in_array( $kv[0], array( 'appp', 'list_type' ) ) ) { // we can ignore these two: already handled

					if( $kv[0] == 'type' ) {
						$args['post_type'] = $kv[1];
					} else if( $kv[0] == 'num' ) {
						$args['posts_per_page'] = $kv[1];
					} else {
						$args[$kv[0]] = $kv[1];
					}
				}
			}
		}
	}

	/*
	 * Handles ajax lost password for the apptheme
	 */
	public function appp_reset_password() {

		// error_log("Reset pw...\r\n",3,self::$errorpath);

		$nonce = $_POST['nonce'];
		$email = $_POST['email'];

		if ( !wp_verify_nonce( $nonce, 'new_password' ) ) return;

		$user = get_user_by( 'email', $email );

		// error_log("User: " . $user->ID . "\r\n",3,self::$errorpath);

		if( $user ) {

			$time = current_time( 'mysql' );
			// create a unique code to use one time
			$hash = $this->get_short_reset_code();

			update_user_meta( $user->ID, 'app_hash', $hash );

			$subject = __('App Password Reset', 'apppresser');
			$message = __('Enter the code into the app to reset your password. Code: ', 'apppresser') . $hash;
			$mail = wp_mail( $email, $subject, $message );

			$return = array(
				'message' =>  __('Please check your email and enter the retrieval code below.', 'apppresser')
			);
			wp_send_json_success( $return );

		} else {

			$return = array(
				'message' =>  __('The email you have entered is not valid.', 'apppresser')
			);
			wp_send_json_error( $return );

		}
	}

	public function get_short_reset_code() {
		
		$numbers = str_split('1234567890');
		shuffle($numbers);
		$letters = str_split('abcdefghijklmnopqrstuvwxyz');
		shuffle($letters);

		$code = $numbers[1].$letters[1].$letters[2].$numbers[3];

		return $code;
	}

	/**
	 * Ajax function to reset password with code from pw reset email
	 *
	 * @access public
	 * @return void
	 */
	public function appp_validate_password_code() {
		global $wpdb;

		$nonce 		= $_POST['nonce'];
		$code 		= $_POST['code'];
		$password 	= $_POST['password'];

		if ( !wp_verify_nonce( $nonce, 'new_password' ) ) return;

		$user = get_users( array( 'meta_key' => 'app_hash', 'meta_value' => $code ) );

		if( $user ) {

			wp_update_user( array ('ID' => $user[0]->data->ID, 'user_pass' => $password ) ) ;
			// delete our one time access code
			delete_user_meta( $user[0]->data->ID, 'app_hash');

			wp_set_auth_cookie( $user[0]->data->ID );
			do_action('wp_signon', $user[0]->data->user_login);

			$return = array(
				'message' => __('Password has been changed.', 'apppresser'),
				'success' => 'true'
			);
			wp_send_json_success( $return );

		} else {

			$return = array(
				'message' =>  __('The code you have entered is not valid.', 'apppresser')
			);
			wp_send_json_error( $return );
		}
	}

	/**
	 * This allows myapppresser.com to check this plugin is here or it can verify the AppPresser 3 settings
	 */
	public function myappp_verify() {

		header('Access-Control-Allow-Origin: *');

		$response = array(
			'version' => AppPresser::VERSION,
		);

		if( isset( $_GET['ap3_site_slug'], $_GET['ap3_app_id'] ) ) {
			$site_slug = appp_get_setting('ap3_site_slug');
			$app_id    = appp_get_setting('ap3_app_id');

			if($_GET['ap3_site_slug'] == $site_slug && $_GET['ap3_app_id'] == $app_id) {
				wp_send_json_success( $response );
			} else {
				$response['error'] = 'On your WordPress site, ' . get_bloginfo( 'wpurl' ) . ', the AppPresser 3 Settings are not set correctly. Please visit the AppPresser settings page in your wp-admin to fix.';
				wp_send_json_error( $response );
			}
		} else {
			wp_send_json_success( $response );
		}
	}
}
AppPresser_Ajax_Extras::run();