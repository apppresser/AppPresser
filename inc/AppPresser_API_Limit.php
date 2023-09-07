<?php

class AppPresser_API_Limit {
    const SECONDS_BETWEEN_GUEST_API_CALLS = 10;

    public static function get_user_ip() {
        // Get the user's IP address
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $user_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $user_ip = $_SERVER['REMOTE_ADDR'];
        }
        return $user_ip;
    }

    public static function get_user_route() {
        $current_url = $_SERVER['REQUEST_URI'];

        // Check if the URL matches a REST API route
        $api_base = rest_get_url_prefix();

        // Remove the API base from the URL to get the endpoint
        $current_endpoint = str_replace($api_base, '', $current_url);

        return $current_endpoint;
    }

    public static function client_ip() {
        global $client_ip;

        if (is_null($client_ip)) {
            $server_vars = array(
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'REMOTE_ADDR',
            );

            foreach ($server_vars as $server_var) {
                if (!array_key_exists($server_var, $_SERVER)) {
                    // The server variable isn't set - do nothing.
                } elseif (empty($client_ip = filter_var($_SERVER[$server_var], FILTER_VALIDATE_IP))) {
                    // The IP address is not valid - do nothing.
                } else {
                    break;
                }
            }

            // Make sure we don't leave something like an empty string or "false"
            // in $client_ip
            if (empty($client_ip)) {
                $client_ip = null;
            }
        }

        return $client_ip;
    }

    public static function rest_api_init(WP_REST_Server $wp_rest_server) {
        $custom_ip = array(self::get_user_ip());
        $is_client_rate_limited = false;
        $transient_key = null;

        if (!empty($client_ip = self::client_ip())) {
            $transient_key = 'apppresser_' . $client_ip;
            $rate_limited_ips = apply_filters('apppresser_rate_limited_ips', $custom_ip);

            if (!empty($rate_limited_ips)) {
                $is_client_rate_limited = in_array($client_ip, $rate_limited_ips);
            } else {
                $is_client_rate_limited = !is_user_logged_in();
            }

            $is_client_rate_limited = apply_filters('apppresser_is_client_rate_limited', $is_client_rate_limited);
        }

        if (!$is_client_rate_limited) {
            // The client is not rate-limited - do nothing
        } elseif (empty($transient_key)) {
            // If we couldn't figure out the transient key - do nothing
        } elseif (empty(get_transient($transient_key))) {
            // This client IP does not have a transient record, so it has not made
            // an API call recently - let the API call execute normally.

            // Create a transient record that will expire after a certain number of seconds (e.g., 60 seconds).
            $seconds_between_api_calls = intval(apply_filters('apppresser_seconds_between_api_calls', self::SECONDS_BETWEEN_GUEST_API_CALLS, $client_ip));
            if ($seconds_between_api_calls > 0) {
                set_transient(
                    $transient_key,
                    '1',
                    $seconds_between_api_calls
                );
            }
        } else {
            // Calculate the time remaining until the transient expires
            $time_remaining = get_option('_transient_timeout_' . $transient_key) - time();

            // A JSON message to send back to the client.
            $response = array(
                'clientIp' => $client_ip,
                'message' => 'Slow down your API calls',
                'waitTime' => max(0, $time_remaining), // Ensure wait time is not negative
            );

            // HTTP Response 429 => "Too many requests"
            wp_send_json(
                $response,
                429
            );
        }
    }
}

add_action('rest_api_init', array('AppPresser_API_Limit', 'rest_api_init'), 10, 1);
