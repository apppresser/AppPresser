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
				'login_redirect' => $this->get_login_redirect(), // v3 only
				'success' => true
			);
			wp_send_json_success( $return );
			
		}
	}

	/**
	 * Get the login redirect for the app's login modal
	 * 
	 * @since 3.2.1
	 * @return string | array( 'url' => '', 'title' => '' )
	 */
	public function get_login_redirect() {

		if( has_filter( 'appp_login_redirect' ) ) {
			$redirect_to = apply_filters( 'appp_login_redirect', '' );
			$post_id = url_to_postid( $redirect_to );

			$redirect = array(
				'url' => $redirect_to,
				'title' => ($post_id) ? get_the_title( $post_id ) : '',
			);	
			
		} else {
			$redirect = '';
		}

		return $redirect;
	}

	/**
	 * Logout, used via postmessage in AP3 apps
	 * @since 3.0.2
	 */
	public function appp_ajax_logout() {

		wp_logout();

		wp_send_json_success( __('Logout success.', 'apppresser') );

	}

	/**
	 * AJAX Load More
	 * @link http://www.billerickson.net/infinite-scroll-in-wordpress
	 */
	public function appp_load_more() {

		check_ajax_referer( 'app-load-more-nonce', 'nonce' );
    
		$args = isset( $_POST['query'] ) ? array_map( 'esc_attr', $_POST['query'] ) : array();
		$args['post_type'] = isset( $args['post_type'] ) ? esc_attr( $args['post_type'] ) : 'post';
		$args['paged'] = esc_attr( $_POST['page'] );
		$args['posts_per_page'] = isset( $_POST['posts_per_page'] ) ? $_POST['posts_per_page'] : 10;
		$args['post_status'] = 'publish';
		$this->parse_url_query_vars( $args );
		$data = array();
		$loop = new WP_Query( $args );
		if( $loop->have_posts() ): while( $loop->have_posts() ): $loop->the_post();
			$data[] = array( 
				'permalink' => get_the_permalink(),
				'title' => get_the_title(),
				'excerpt' => get_the_excerpt(),
				'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ),
				'full' => get_the_post_thumbnail_url( get_the_ID(), 'full' )
				);
		endwhile; endif; wp_reset_postdata();
		wp_send_json_success( $data );		
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

					if( $kv[0] == 'num' ) {
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
		
		$symbols = str_split('!@#$%^&*');
		shuffle($symbols);
		$numbers = str_split('1234567890');
		shuffle($numbers);
		$letters = str_split('abcdefghijklmnopqrstuvwxyz');
		shuffle($letters);

		$code = $numbers[1].$numbers[1].strtoupper($letters[1]).$letters[1].$letters[1].$symbols[1].$symbols[1];

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
}
AppPresser_Ajax_Extras::run();