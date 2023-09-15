<?php
class AppPresser_API_Limit
{
    public static function get_user_route()
    {
        $current_url = $_SERVER['REQUEST_URI'];

        // Check if the URL matches a REST API route
        $api_base = rest_get_url_prefix();

        if (empty($api_base)) {
            // Fallback to a default API base if rest_get_url_prefix() is empty
            $api_base = 'wp-json';
        }

        // Remove the API base from the URL to get the endpoint
        $current_endpoint = str_replace($api_base, '', $current_url);

        // Remove any leading double slashes (if present)
        $current_endpoint = ltrim($current_endpoint, '/');

        return $current_endpoint;
    }

    public static function appresser_api_limit()
    {
        // Get the user's IP address
        $user_ip = $_SERVER['REMOTE_ADDR'];
        // The amount of time the user is locked out
        $limited_time = 150;
        // How many requests per X seconds
        $requests_per_second = 3;
        $current_route = self::get_user_route();
        // Define an array of limited routes
        $limited_routes = array('appp/v1/reset-password', 'appp/v1/login', 'appp/v1/verify-resend');

        // Check if the current route is in the array of limited routes
        if (in_array($current_route, $limited_routes)) {
            // Get the existing wait time transient
            $wait_time = get_transient($user_ip . '_wait_time');

            if ($wait_time === false) {
                // Get the existing countdown transient or set it to the allowed request limit if it doesn't exist
                $transient_data = get_transient($user_ip . '_count');

                if (false === $transient_data || $transient_data <= 0) {
                    $transient_data = intval($requests_per_second);
                    set_transient($user_ip . '_count', $transient_data, 20);
                }

                // Check if $transient_data is greater than zero before decrementing
                if ($transient_data > 0) {
                    // Decrement the countdown transient data by 1
                    $transient_data--;
                }

                // Update the countdown transient with the decremented data
                set_transient($user_ip . '_count', $transient_data, 20);

                // Check if the countdown transient data is zero
                if ($transient_data === 0) {
                    // Set the wait time transient to $limited_time seconds
                    set_transient($user_ip . '_wait_time', 'wait_time', $limited_time);

                    // Send a JSON response with HTTP status code 429 (Too Many Requests)
                    $response = array(
                        'clientIp' => $user_ip,
                        'message' => 'Slow down your API calls',
                        'route' => $current_route
                    );
                    wp_send_json($response, 429);
                }
            } else {
                // HTTP Response 429 => "Too many requests"
                // A JSON message to send back to the client.
                $response = array(
                    'clientIp' => $user_ip,
                    'message' => 'Slow down your API calls',
                    'route' => $current_route
                );
                wp_send_json($response, 429);
            }
        }
    }
}

// Add action hook for appresser_api_limit method
add_action('rest_api_init', array('AppPresser_API_Limit', 'appresser_api_limit'));