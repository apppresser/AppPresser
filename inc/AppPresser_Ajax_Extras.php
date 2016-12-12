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
		$args['post_status'] = 'publish';
		ob_start();
		$loop = new WP_Query( $args );
		if( $loop->have_posts() ): while( $loop->have_posts() ): $loop->the_post();
			echo '<li class="post-list-item">';
			echo '<a class="item item-thumbnail-left item-text-wrap" href="' . get_the_permalink() . '">';
			if ( has_post_thumbnail() ) {
				the_post_thumbnail( 'thumbnail' );
			} else { ?>
				<img src="<?php echo get_stylesheet_directory_uri() . '/images/thumbnail.jpg'; ?>">
				<?php
			}
			echo '<div class="item-title">' . get_the_title() . '</div>';
			echo '<div class="item-text"><p>' . get_the_excerpt() . '</p></div>';
			echo '</a>';
			echo '</li>';
		endwhile; endif; wp_reset_postdata();
		$data = ob_get_clean();
		wp_send_json_success( $data );
		wp_die();
		
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
			$hash = hash( 'md5', $email . $time );

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