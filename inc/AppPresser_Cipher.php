<?php

declare(strict_types=1);

final class AppPresser_Cipher
{
    /**
     * OpenSSL Cipher
     *
     * @var string
     */
    const OPENSSL_CIPHER = 'AES-128-CBC';

    /**
     * Checks to see if OpenSSL is available.
     *
     * @return bool
     */
    public static function has_curl_openssl_support(): bool
    {
        // Bail early if openssl is not installed.
        if (!function_exists('curl_version')) {
            return false;
        }

        $curl_version = curl_version();
        return stripos($curl_version['ssl_version'] ?? '', 'openssl') !== false;
    }

    public static function encrypt($value)
    {
        // Bail early if no key is set.
        if (!defined('AUTH_KEY')) {
            throw new \Exception('No AUTH_KEY set!');
        }

        $key = substr(AUTH_KEY, 2, 5);
        $iv = substr(AUTH_KEY, 0, 16);

        if (!self::has_curl_openssl_support()) {
            return base64_encode($key . $value . $iv);
        }

        return openssl_encrypt($value, self::OPENSSL_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    }

    public static function decrypt($value)
    {
        // Bail early if no key is set.
        if (!defined('AUTH_KEY')) {
            throw new \Exception('No AUTH_KEY set!');
        }

        $key = substr(AUTH_KEY, 2, 5);
        $iv = substr(AUTH_KEY, 0, 16);

        if (!self::has_curl_openssl_support()) {
            return base64_decode($key . $value . $iv);
        }

        return openssl_decrypt($value, self::OPENSSL_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    }
}
