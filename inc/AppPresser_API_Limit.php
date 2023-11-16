<?php
class AppPresser_API_Limit
{
    public static function get_user_route()
    {
        $current_url = $_SERVER['REQUEST_URI'];
        $api_base = rest_get_url_prefix();
        $current_endpoint = str_replace($api_base, '', $current_url);
        $current_endpoint = ltrim($current_endpoint, '/');
        return $current_endpoint;
    }
    public static function getRateLimitedData($user_ip)
    {
        return get_transient($user_ip . 'limited_data');
    }
    public static function saveRateLimitedData($currentMinute, $user_ip)
    {
        $rateLimitData = [
            'minute' => $currentMinute,
            'requests' => 1,
        ];
        set_transient($user_ip . 'limited_data', $rateLimitData, 20);
    }

    public static function updateRateLimitedData($rateLimitData, $user_ip, $currentMinute)
    {
        $rateLimitData['minute'] = $currentMinute;
        $rateLimitData['requests'] += 1;
        set_transient($user_ip . 'limited_data', $rateLimitData, 20);
    }

    public static function appresser_api_limit()
    {
        $user_ip = $_SERVER['REMOTE_ADDR'];
        $limit = 2;
        $requests_per_minute = apply_filters('limited_requests_per_minute', $limit);
        $current_route = self::get_user_route();
        $default_limited_routes = array(
            'appp/v1/reset-password',
            'appp/v1/login',
            'appp/v1/verify-resend'
        );
        $limited_routes = apply_filters('appresser_limited_routes', $default_limited_routes);
        if (in_array($current_route, $limited_routes)) {
            $rateLimitData = self::getRateLimitedData($user_ip);
            $currentMinute = floor(time() / 60);
            if ($rateLimitData) {
                if ($rateLimitData['minute'] == $currentMinute) {
                    if ($rateLimitData['requests'] >= $requests_per_minute) {
                        $response = array(
                            'clientIp' => $user_ip,
                            'message' => 'Slow down your API calls',
                            'route' => $current_route,
                        );
                        wp_send_json($response, 429);
                    } else {
                        self::updateRateLimitedData($rateLimitData, $user_ip, $currentMinute);
                    }
                } else {
                    self::saveRateLimitedData($currentMinute, $user_ip);
                }
            } else {
                self::saveRateLimitedData($currentMinute, $user_ip);
            }
        }
    }
}
add_action('rest_api_init', array('AppPresser_API_Limit', 'appresser_api_limit'));