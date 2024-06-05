<?php
/**
 * Modifications to the WP-API
 *
 * @package AppPresser
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_User_Roles {

	/**
	 * Party Started
	 * @since 3.9.0
	 */
	public function __construct() {
		
	}

	public function hooks() {
		add_filter( 'appp_login_data', array( $this, 'appp_login_data_add_role' ), 10, 2 );
	}

	/**
	 * Adds a role to the AppPresser login data which gets sent back to the app
	 * @since 3.9.0
	 * 
	 * @param $login_data array The existing login data just prior to being sent to the app
	 * @param $user_id integer The current user's ID
	 * 
	 * @return $login_data array
	 */
	function appp_login_data_add_role( $login_data, $user_id ) {

		$roles = $this->get_app_user_roles( $user_id );

		if( $roles ) {
			$login_data['roles'] = $roles;
		}

		return $login_data;
	}

	/**
	 * Get all the roles for the current user
	 * @since 3.9.0
	 * 
	 * @param $user_id integer The current user's ID
	 * 
	 * @return $role array All roles assigned to this user
	 */
	function get_app_user_roles( $user_id ) {

		$roles = array();

		if( $user_id ) {
			$user = new WP_User( $user_id );
			$roles = ( array ) $user->roles;
		}

		$roles = apply_filters( 'apppresser_user_roles', $roles, $user_id );

		return $roles;
	}

}
global $appp_user_roles;
$appp_user_roles = new AppPresser_User_Roles();
$appp_user_roles->hooks();