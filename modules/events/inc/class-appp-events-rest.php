<?php
/**
 * Public, read only routes:
 *   GET /wp-json/appp/v1/events
 *   GET /wp-json/appp/v1/events/<id>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appp_Events_REST {

	const NS = 'appp/v1';

	public static function register_routes() {
		register_rest_route(
			self::NS,
			'/events',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_events' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 10,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'start'    => array(
						'type'              => 'string',
						'description'       => 'Window start, YYYY-MM-DD or ISO 8601, site timezone when no offset.',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'end'      => array(
						'type'              => 'string',
						'description'       => 'Window end, YYYY-MM-DD (inclusive) or ISO 8601.',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category' => array(
						'type'              => 'string',
						'description'       => 'Category slug or term ID, comma separated.',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'upcoming' => array(
						'type'        => 'boolean',
						'default'     => true,
						'description' => 'Only events that have not ended. Ignored when start is set.',
					),
					'order'    => array(
						'type'    => 'string',
						'default' => 'asc',
						'enum'    => array( 'asc', 'desc' ),
					),
					'expand'   => array(
						'type'        => 'boolean',
						'description' => 'One item per occurrence of a recurring event (default from settings, on).',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/events/(?P<id>[A-Za-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_event' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'type' => 'string' ),
				),
			)
		);
	}

	public static function list_events( WP_REST_Request $req ) {
		$res = Appp_Events_Engine::query(
			array(
				'page'     => $req['page'],
				'per_page' => $req['per_page'],
				'start'    => $req['start'],
				'end'      => $req['end'],
				'category' => $req['category'],
				'upcoming' => $req->has_param( 'start' ) ? false : $req['upcoming'],
				'order'    => $req['order'],
				'expand'   => $req->has_param( 'expand' ) ? $req['expand'] : null,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$response = rest_ensure_response( $res['items'] );
		$response->header( 'X-WP-Total', (string) $res['total'] );
		$response->header( 'X-WP-TotalPages', (string) $res['pages'] );
		$response->header( 'X-Appp-Events-Source', Appp_Events_Engine::meta()['source'] );
		return self::http_cache( $req, $response, array( $res['items'], $res['total'], $res['pages'] ) );
	}

	/**
	 * ETag, Cache-Control and 304 Not Modified. The ETag is a hash of the exact
	 * response, so it changes whenever any returned field (or the totals) changes.
	 */
	private static function http_cache( WP_REST_Request $req, WP_REST_Response $response, $body ) {
		$etag = '"' . md5( wp_json_encode( $body ) ) . '"';
		$response->header( 'ETag', $etag );
		if ( ! is_user_logged_in() ) {
			$cc = (string) apply_filters( 'appp_events_cache_control', 'public, max-age=60', $req );
			if ( '' !== $cc ) {
				$response->header( 'Cache-Control', $cc );
			}
		}
		$inm = (string) $req->get_header( 'if_none_match' );
		if ( '' !== $inm ) {
			foreach ( array_map( 'trim', explode( ',', $inm ) ) as $tag ) {
				if ( $tag === $etag || 'W/' . $etag === $tag || '*' === $tag ) {
					$response->set_status( 304 );
					$response->set_data( null );
					if ( ! has_filter( 'rest_pre_serve_request', array( __CLASS__, 'no_body_for_304' ) ) ) {
						add_filter( 'rest_pre_serve_request', array( __CLASS__, 'no_body_for_304' ), 10, 2 );
					}
					break;
				}
			}
		}
		return $response;
	}

	/** Headers are already sent at this point; skip the JSON body on a 304. */
	public static function no_body_for_304( $served, $result ) {
		if ( ! $served && $result instanceof WP_HTTP_Response && 304 === $result->get_status() ) {
			return true;
		}
		return $served;
	}

	public static function get_event( WP_REST_Request $req ) {
		$item = Appp_Events_Engine::get( $req['id'] );
		if ( ! $item ) {
			return new WP_Error( 'appp_events_not_found', __( 'Event not found.', 'apppresser-events' ), array( 'status' => 404 ) );
		}
		return self::http_cache( $req, rest_ensure_response( $item ), $item );
	}
}
