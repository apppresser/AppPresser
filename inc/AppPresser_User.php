<?php

use \Firebase\JWT\JWT;

class AppPresser_User
{
    /**
     * Returns the login response for the given user
     */
    public static function getLoginResponse($user)
    {
        // Used for setting auth cookie on iframe pages. See AppPresser_Theme_Switcher->maybe_set_auth()
        $cookie_auth = self::doCookieAuth($user->ID);

        $data = array(
            'message' => apply_filters('appp_login_success', sprintf(__('Welcome back %s!', 'apppresser'), $user->display_name), $user->ID),
            'username' => $user->user_login,
            'email' => $user->user_email,
            'avatar' => get_avatar_url($user->ID),
            'cookie_auth' => $cookie_auth,
            'login_redirect' => AppPresser_Ajax_Extras::get_login_redirect(), // v3 only
            'success' => true,
            'user_id' => $user->ID
        );

        if ($token = self::generateToken($user)) {
            $data['access_token'] = $token;
        }

        $data = apply_filters('appp_login_data', $data, $user->ID);

        $retval = rest_ensure_response($data);

        return $retval;
    }

    /*
	 * Encrypts string for later decoding
	 */
    private static function doCookieAuth($userId)
    {
        if (function_exists('openssl_encrypt')) {
            $key = substr(AUTH_KEY, 2, 5);
            $iv = substr(AUTH_KEY, 0, 16);
            $cipher = "AES-128-CBC";
            $ciphertext = openssl_encrypt($userId, $cipher, $key, null, $iv);
        } else {
            // no openssl installed
            $ciphertext = $userId;
        }

        update_user_meta($userId, 'app_cookie_auth', $ciphertext);

        return $ciphertext;
    }

    private static function generateToken($user)
    {
        $secretKey = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        $issuedAt = time();
        $notBefore = apply_filters('jwt_auth_not_before', $issuedAt, $issuedAt);
        $expire = apply_filters('jwt_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);

        $token = array(
            'iss' => get_bloginfo('url'),
            'iat' => $issuedAt,
            'nbf' => $notBefore,
            'exp' => $expire,
            'data' => array(
                'user' => array(
                    'id' => $user->data->ID,
                ),
            ),
        );

        if (class_exists('Jwt_Auth')) {
            return JWT::encode($token, $secretKey);
        } else {
            return null;
        }
    }
}
