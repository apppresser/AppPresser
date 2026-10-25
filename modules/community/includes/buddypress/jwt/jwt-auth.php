<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


/*
 * Adds a token to the login data, which is used for authenticating API requests.
 * Forked from https://github.com/Tmeister/wp-api-jwt-auth/blob/develop/includes/vendor/firebase/php-jwt/src/JWT.php
 */

if ( ! class_exists( 'AP_JWT' ) ) {

	class AP_JWT {
		/**
		 * @var         AP_JWT $instance The one true AP_JWT
		 * @since       0.1.0
		 */
		private static $instance;

		/**
		 * When checking nbf, iat or expiration times,
		 * we want to provide some extra leeway time to
		 * account for clock skew.
		 */
		public static $leeway = 0;
		/**
		 * Allow the current timestamp to be specified.
		 * Useful for fixing a value within unit testing.
		 *
		 * Will default to PHP time() value if null.
		 */
		public static $timestamp = null;

		public static $supported_algs = array(
			'HS256' => array( 'hash_hmac', 'SHA256' ),
			'HS512' => array( 'hash_hmac', 'SHA512' ),
			'HS384' => array( 'hash_hmac', 'SHA384' ),
			'RS256' => array( 'openssl', 'SHA256' ),
			'RS384' => array( 'openssl', 'SHA384' ),
			'RS512' => array( 'openssl', 'SHA512' ),
		);

		/**
		 * Get active instance
		 *
		 * @access      public
		 * @since       0.1.0
		 * @return      self The one true AP_JWT
		 */
		public static function instance() {

			if ( ! self::$instance ) {
				self::$instance = new AP_JWT();
				self::$instance->hooks();
			}

			return self::$instance;
		}

		public function hooks() {

			add_action( 'admin_init', array( $this, 'set_default_key' ) );

			add_action( 'apppresser_add_settings', array( $this, 'jwt_settings' ), 10 );
		}

		public function set_default_key() {
			$settings = AppPresser_Modules::instance()->settings;
			$values = $settings->values( 'community' );
			if ( empty( $values['ap_bp_jwt_key'] ) ) {
				$values['ap_bp_jwt_key'] = wp_generate_password( 64, false );
				update_option( $settings->option_name( 'community' ), $values, false );
			}
		}

		public function jwt_settings( $appp ) {

			$appp->add_setting(
				'ap_bp_jwt_key',
				__( 'API Secret', 'appcommunity' ),
				array(
					'type'        => 'text',
					'description' => __( 'AppPresser API secret key.', 'appcommunity' ),
					'helptext'    => __( 'Used to authenticate API POST requests (like BuddyPress activity). If you change this key it will void all API requests and force users to log in again.', 'appcommunity' ),
				)
			);
		}

		public static function get_secret_key() {
			return appp_get_setting( 'ap_bp_jwt_key' );
		}

		/**
		 * Validates a token in an http request
		 * inputs: $request with token and user_id
		 * return: error (array), or true for success
		 */
		public function validate_token( $request ) {

			if ( empty( $request['token'] ) || empty( $request['user_id'] ) || ! class_exists( 'AP_JWT' ) ) {
				return array( 'error' => __( 'Missing token.', 'appcommunity' ) );
			}

			$key = $this->get_secret_key();

			$decoded = $this->decode( $request['token'], $key, array( 'HS256' ) );

			// return the error message if there is one
			if ( is_array( $decoded ) && ! empty( $decoded['error'] ) ) {
				return $decoded;
			}

			if ( strval( $decoded->user_id ) != strval( $request['user_id'] ) ) {
				return array( 'error' => __( 'User ID does not match.', 'appcommunity' ) );
			}

			if ( trailingslashit( $decoded->url ) != home_url( '/', 'https' ) ) {
				return array( 'error' => __( 'Parameter 2 does not match.', 'appcommunity' ) );
			}

			return true;
		}

		/**
		 * Decodes a JWT string into a PHP object.
		 *
		 * @param string       $jwt            The JWT
		 * @param string|array $key            The key, or map of keys.
		 *                                     If the algorithm used is asymmetric, this is the public key
		 * @param array        $allowed_algs   List of supported verification algorithms
		 *                                     Supported algorithms are 'HS256', 'HS384', 'HS512' and 'RS256'
		 *
		 * @return object The JWT's payload as a PHP object
		 *
		 * @throws UnexpectedValueException     Provided JWT was invalid
		 * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
		 * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
		 * @throws BeforeValidException         Provided JWT is trying to be used before it's been created as defined by 'iat'
		 * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
		 *
		 * @uses jsonDecode
		 * @uses urlsafeB64Decode
		 */
		public static function decode( $jwt, $key, array $allowed_algs = array() ) {
			$timestamp = is_null( static::$timestamp ) ? time() : static::$timestamp;
			if ( empty( $key ) ) {
				return array( 'error' => __( 'Missing secret key in AppPresser settings.', 'appcommunity' ) );
			}
			$tks = explode( '.', $jwt );
			if ( count( $tks ) != 3 ) {
				return array( 'error' => __( 'Wrong number of segments', 'appcommunity' ) );
			}
			list($headb64, $bodyb64, $cryptob64) = $tks;
			if ( null === ( $header = static::jsonDecode( static::urlsafeB64Decode( $headb64 ) ) ) ) {
				return array( 'error' => __( 'Invalid header encoding', 'appcommunity' ) );
			}
			if ( null === $payload = static::jsonDecode( static::urlsafeB64Decode( $bodyb64 ) ) ) {
				return array( 'error' => __( 'Invalid claims encoding', 'appcommunity' ) );
			}
			if ( false === ( $sig = static::urlsafeB64Decode( $cryptob64 ) ) ) {
				return array( 'error' => __( 'Invalid signature encoding', 'appcommunity' ) );
			}
			if ( empty( $header->alg ) ) {
				return array( 'error' => __( 'Empty algorithm', 'appcommunity' ) );
			}
			if ( empty( static::$supported_algs[ $header->alg ] ) ) {
				return array( 'error' => __( 'Algorithm not supported', 'appcommunity' ) );
			}
			if ( ! in_array( $header->alg, $allowed_algs ) ) {
				return array( 'error' => __( 'Algorithm not allowed', 'appcommunity' ) );
			}
			if ( is_array( $key ) || $key instanceof \ArrayAccess ) {
				if ( isset( $header->kid ) ) {
					if ( ! isset( $key[ $header->kid ] ) ) {
						return array( 'error' => __( '"kid" invalid, unable to lookup correct key', 'appcommunity' ) );
					}
					$key = $key[ $header->kid ];
				} else {
					return array( 'error' => __( '"kid" empty, unable to lookup correct key', 'appcommunity' ) );
				}
			}
			// Check the signature
			if ( ! static::verify( "$headb64.$bodyb64", $sig, $key, $header->alg ) ) {
				return array( 'error' => __( 'Signature verification failed', 'appcommunity' ) );
			}
			// Check if the nbf if it is defined. This is the time that the
			// token can actually be used. If it's not yet that time, abort.
			if ( isset( $payload->nbf ) && $payload->nbf > ( $timestamp + static::$leeway ) ) {
				return array(
					'error' =>
											__( 'Cannot handle token prior to', 'appcommunity' ) . ' ' . date( DateTime::ISO8601, $payload->nbf ),
				);
			}
			// Check that this token has been created before 'now'. This prevents
			// using tokens that have been created for later use (and haven't
			// correctly used the nbf claim).
			if ( isset( $payload->iat ) && $payload->iat > ( $timestamp + static::$leeway ) ) {
				return array(
					'error' =>
											__( 'Cannot handle token prior to', 'appcommunity' ) . ' ' . date( DateTime::ISO8601, $payload->iat ),
				);
			}
			// Check if this token has expired.
			if ( isset( $payload->exp ) && ( $timestamp - static::$leeway ) >= $payload->exp ) {
				return array( 'error' => __( 'Expired token', 'appcommunity' ) );
			}
			return $payload;
		}
		/**
		 * Converts and signs a PHP object or array into a JWT string.
		 *
		 * @param object|array $payload    PHP object or array
		 * @param string       $key        The secret key.
		 *                                 If the algorithm used is asymmetric, this is the private key
		 * @param string       $alg        The signing algorithm.
		 *                                 Supported algorithms are 'HS256', 'HS384', 'HS512' and 'RS256'
		 * @param mixed        $keyId
		 * @param array        $head       An array with header elements to attach
		 *
		 * @return string A signed JWT
		 *
		 * @uses jsonEncode
		 * @uses urlsafeB64Encode
		 */
		public static function encode( $payload, $key, $alg = 'HS256', $keyId = null, $head = null ) {
			$header = array(
				'typ' => 'JWT',
				'alg' => $alg,
			);
			if ( $keyId !== null ) {
				$header['kid'] = $keyId;
			}
			if ( isset( $head ) && is_array( $head ) ) {
				$header = array_merge( $head, $header );
			}
			$segments      = array();
			$segments[]    = static::urlsafeB64Encode( static::jsonEncode( $header ) );
			$segments[]    = static::urlsafeB64Encode( static::jsonEncode( $payload ) );
			$signing_input = implode( '.', $segments );
			$signature     = static::sign( $signing_input, $key, $alg );
			$segments[]    = static::urlsafeB64Encode( $signature );
			return implode( '.', $segments );
		}

		/**
		 * Sign a string with a given key and algorithm.
		 *
		 * @param string          $msg    The message to sign
		 * @param string|resource $key    The secret key
		 * @param string          $alg    The signing algorithm.
		 *                                Supported algorithms are 'HS256', 'HS384', 'HS512' and 'RS256'
		 *
		 * @return string An encrypted message
		 *
		 * @throws DomainException Unsupported algorithm was specified
		 */
		public static function sign( $msg, $key, $alg = 'HS256' ) {
			if ( empty( static::$supported_algs[ $alg ] ) ) {
				throw new DomainException( __( 'Algorithm not supported', 'appcommunity' ) );
			}
			list($function, $algorithm) = static::$supported_algs[ $alg ];
			switch ( $function ) {
				case 'hash_hmac':
					return hash_hmac( $algorithm, $msg, $key, true );
				case 'openssl':
					$signature = '';
					$success   = openssl_sign( $msg, $signature, $key, $algorithm );
					if ( ! $success ) {
						throw new DomainException( __( 'OpenSSL unable to sign data', 'appcommunity' ) );
					} else {
						return $signature;
					}
			}
		}
		/**
		 * Verify a signature with the message, key and method. Not all methods
		 * are symmetric, so we must have a separate verify and sign method.
		 *
		 * @param string          $msg        The original message (header and body)
		 * @param string          $signature  The original signature
		 * @param string|resource $key        For HS*, a string key works. for RS*, must be a resource of an openssl public key
		 * @param string          $alg        The algorithm
		 *
		 * @return bool
		 *
		 * @throws DomainException Invalid Algorithm or OpenSSL failure
		 */
		private static function verify( $msg, $signature, $key, $alg ) {
			if ( empty( static::$supported_algs[ $alg ] ) ) {
				throw new DomainException( __( 'Algorithm not supported', 'appcommunity' ) );
			}
			list($function, $algorithm) = static::$supported_algs[ $alg ];
			switch ( $function ) {
				case 'openssl':
					$success = openssl_verify( $msg, $signature, $key, $algorithm );
					if ( $success === 1 ) {
						return true;
					} elseif ( $success === 0 ) {
						return false;
					}
					// returns 1 on success, 0 on failure, -1 on error.
					throw new DomainException(
						__( 'OpenSSL error:', 'appcommunity' ) . ' ' . openssl_error_string()
					);
				case 'hash_hmac':
				default:
					$hash = hash_hmac( $algorithm, $msg, $key, true );
					if ( function_exists( 'hash_equals' ) ) {
						return hash_equals( $signature, $hash );
					}
					$len    = min( static::safeStrlen( $signature ), static::safeStrlen( $hash ) );
					$status = 0;
					for ( $i = 0; $i < $len; $i++ ) {
						$status |= ( ord( $signature[ $i ] ) ^ ord( $hash[ $i ] ) );
					}
					$status |= ( static::safeStrlen( $signature ) ^ static::safeStrlen( $hash ) );
					return ( $status === 0 );
			}
		}
		/**
		 * Decode a JSON string into a PHP object.
		 *
		 * @param string $input JSON string
		 *
		 * @return object Object representation of JSON string
		 *
		 * @throws DomainException Provided string was invalid JSON
		 */
		public static function jsonDecode( $input ) {
			if ( version_compare( PHP_VERSION, '5.4.0', '>=' ) && ! ( defined( 'JSON_C_VERSION' ) && PHP_INT_SIZE > 4 ) ) {
				/** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
				 * to specify that large ints (like Steam Transaction IDs) should be treated as
				 * strings, rather than the PHP default behaviour of converting them to floats.
				 */
				$obj = json_decode( $input, false, 512, JSON_BIGINT_AS_STRING );
			} else {
				/** Not all servers will support that, however, so for older versions we must
				 * manually detect large ints in the JSON string and quote them (thus converting
				 *them to strings) before decoding, hence the preg_replace() call.
				 */
				$max_int_length       = strlen( (string) PHP_INT_MAX ) - 1;
				$json_without_bigints = preg_replace( '/:\s*(-?\d{' . $max_int_length . ',})/', ': "$1"', $input );
				$obj                  = json_decode( $json_without_bigints );
			}
			if ( function_exists( 'json_last_error' ) && $errno = json_last_error() ) {
				static::handleJsonError( $errno );
			} elseif ( $obj === null && $input !== 'null' ) {
				throw new DomainException( __( 'Null result with non-null input', 'appcommunity' ) );
			}
			return $obj;
		}
		/**
		 * Encode a PHP object into a JSON string.
		 *
		 * @param object|array $input A PHP object or array
		 *
		 * @return string JSON representation of the PHP object or array
		 *
		 * @throws DomainException Provided object could not be encoded to valid JSON
		 */
		public static function jsonEncode( $input ) {
			$json = json_encode( $input );
			if ( function_exists( 'json_last_error' ) && $errno = json_last_error() ) {
				static::handleJsonError( $errno );
			} elseif ( $json === 'null' && $input !== null ) {
				throw new DomainException( __( 'Null result with non-null input', 'appcommunity' ) );
			}
			return $json;
		}
		/**
		 * Decode a string with URL-safe Base64.
		 *
		 * @param string $input A Base64 encoded string
		 *
		 * @return string A decoded string
		 */
		public static function urlsafeB64Decode( $input ) {
			$remainder = strlen( $input ) % 4;
			if ( $remainder ) {
				$padlen = 4 - $remainder;
				$input .= str_repeat( '=', $padlen );
			}
			return base64_decode( strtr( $input, '-_', '+/' ) );
		}
		/**
		 * Encode a string with URL-safe Base64.
		 *
		 * @param string $input The string you want encoded
		 *
		 * @return string The base64 encode of what you passed in
		 */
		public static function urlsafeB64Encode( $input ) {
			return str_replace( '=', '', strtr( base64_encode( $input ), '+/', '-_' ) );
		}
		/**
		 * Helper method to create a JSON error.
		 *
		 * @param int $errno An error number from json_last_error()
		 *
		 * @return void
		 */
		private static function handleJsonError( $errno ) {
			$messages = array(
				JSON_ERROR_DEPTH          => __( 'Maximum stack depth exceeded', 'appcommunity' ),
				JSON_ERROR_STATE_MISMATCH => __( 'Invalid or malformed JSON', 'appcommunity' ),
				JSON_ERROR_CTRL_CHAR      => __( 'Unexpected control character found', 'appcommunity' ),
				JSON_ERROR_SYNTAX         => __( 'Syntax error, malformed JSON', 'appcommunity' ),
				JSON_ERROR_UTF8           => __( 'Malformed UTF-8 characters', 'appcommunity' ), // PHP >= 5.3.3
			);
			throw new DomainException(
				isset( $messages[ $errno ] )
				? $messages[ $errno ]
				: __( 'Unknown JSON error:', 'appcommunity' ) . ' ' . $errno
			);
		}
		/**
		 * Get the number of bytes in cryptographic strings.
		 *
		 * @param string
		 *
		 * @return int
		 */
		private static function safeStrlen( $str ) {
			if ( function_exists( 'mb_strlen' ) ) {
				return mb_strlen( $str, '8bit' );
			}
			return strlen( $str );
		}
	}

	AP_JWT::instance();

}
