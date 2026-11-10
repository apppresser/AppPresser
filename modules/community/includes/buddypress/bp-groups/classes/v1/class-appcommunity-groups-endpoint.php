<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * BP REST: AppCommunity_Groups_Endpoint_V1 class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Groups endpoints.
 *
 * @since 0.1.0
 */
class AppCommunity_Groups_Endpoint_V1 extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v1';
		$this->rest_base = buddypress()->groups->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/members',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'group_get_members' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/join-group',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'join_group' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
	}

	/*
	 * Request to join a group, etc.
	 */
	public function join_group( $request ) {

		if ( empty( $request['group_id'] ) || empty( $request['user_id'] ) || empty( $request['token'] ) ) {

			return new WP_Error(
				'bp_rest_missing_required_fields',
				__( 'Missing required fields.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);

		}

		$group = groups_get_group( $request['group_id'] );

		if ( 'private' == $group->status ) {

			$request = groups_send_membership_request( $request['user_id'], $request['group_id'] );

			if ( $request ) {
				$retval = array(
					'status'  => 'pending',
					'message' => 'Request sent.',
				);
			} else {
				$retval = array(
					'status'  => 'error',
					'message' => 'Cannot send request. You already requested to join, or there was an error.',
				);
			}
		} else {
			$join = groups_join_group( $request['group_id'], $request['user_id'] );
			if ( $join ) {
				$retval = array(
					'status'  => 'joined_group',
					'message' => 'Joined group.',
				);
			} else {
				$retval = array(
					'status'  => 'error',
					'message' => 'Could not join group.',
				);
			}
		}

		$retval = rest_ensure_response( $retval );

		return $retval;
	}

	/**
	 * Retrieve groups.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of groups object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'type'               => $request['type'],
			'order'              => $request['order'],
			'fields'             => $request['fields'],
			'orderby'            => $request['orderby'],
			'user_id'            => $request['user_id'],
			'include'            => $request['include'],
			'exclude'            => $request['exclude'],
			'search_terms'       => $request['search'],
			'group_type'         => $request['group_type'],
			'group_type__in'     => $request['group_type__in'],
			'group_type__not_in' => $request['group_type__not_in'],
			'show_hidden'        => $request['show_hidden'],
			'per_page'           => $request['per_page'],
			'page'               => $request['page'],
			'populate_extras'    => false,
			'update_meta_cache'  => true,
			'search_terms'       => $request['search'],
		);

		// Admins and moderators can see it all.
		if ( user_can( $request['user_id'], 'bp_moderate' ) ) {
			$args['show_hidden'] = true;
		}

		$args = apply_filters( 'appcommunity_groups_get_items_args', $args, $request );

		$groups = groups_get_groups( $args );

		$retval = array();
		foreach ( $groups['groups'] as $group ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group, $request )
			);
		}

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after a list of groups is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $groups   Fetched groups.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'appcommunity_groups_get_items', $groups, $retval, $request );

		return $retval;
	}

	/**
	 * Retrieve a group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {
		$group_id = (int) $request['id'];

		$group = groups_get_group( $group_id );

		// Prevent non-members from seeing hidden groups.
		if ( ! $this->show_hidden( $group, $request ) ) {

			// Unset the group ID to ensure our error condition fires.
			$group->id = 0;

			if ( empty( $group ) || empty( $group->id ) ) {
				return new WP_Error(
					'bp_rest_invalid_group_id',
					__( 'Invalid group id.', 'appcommunity' ),
					array(
						'status' => 404,
					)
				);
			}
		}

		$retval = $this->prepare_response_for_collection(
			$this->prepare_item_for_response( $group, $request )
		);

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after a group is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $group    Fetched group.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'appcommunity_group_get_item', $group, $retval, $request );

		return $retval;
	}

	/**
	 * Retrieve members.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of groups object data.
	 */
	public function group_get_members( $request ) {

		if ( empty( $request['user_id'] ) || empty( $request['id'] ) ) {
			return new WP_Error(
				'bp_rest_missing_required_fields',
				__( 'Missing required fields.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		$group = groups_get_group( $request['id'] );

		if ( ! $this->show_hidden( $group, $request ) ) {
			return new WP_Error(
				'bp_rest_invalid_group_id',
				__( 'Sorry, you do not have permission to view this.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		$args = array(
			'group_id' => $request['id'],
			'per_page' => ( $request['per_page'] ? $request['per_page'] : 10 ),
			'page'     => $request['page'],
		);

		$args = apply_filters( 'appcommunity_group_get_members_args', $args, $request );

		$members = array();

		if ( bp_group_has_members( $args ) ) :

			while ( bp_group_members() ) :
				bp_group_the_member();

				$member_id = bp_get_member_user_id();

				$members[] = array(
					'name'         => bp_get_member_name(),
					'permalink'    => bp_get_member_permalink(),
					'avatar'       => bp_core_fetch_avatar(
						array(
							'item_id' => $member_id,
							'type'    => 'thumb',
							'width'   => 32,
							'height'  => 32,
							'class'   => 'friend-avatar',
							'html'    => false,
						)
					),
					'id'           => $member_id,
					'mention_name' => bp_activity_get_user_mentionname( $member_id ),
				);

			endwhile;

		endif;

		$retval = rest_ensure_response( $members );

		do_action( 'appcommunity_group_get_members', $retval, $request );

		return $retval;
	}

	/**
	 * Checks if a given request has access to create an item.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {

		if ( empty( $request['token'] ) ) {
			return new WP_Error(
				'rest_authorization_required',
				__( 'Missing required fields.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$AP_JWT = new AP_JWT();

		$validation = $AP_JWT->validate_token( $request );

		// Bail early.
		if ( $validation !== true ) {

			if ( ! empty( $validation['error'] ) ) {
				$msg = $validation['error'];
			}
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to do that. ' . $msg, 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Check if a given request has access to get information about a specific group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to group items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! $this->can_see( $request ) ) {
			return new WP_Error(
				'rest_user_cannot_view_group',
				__( 'Sorry, you cannot view hidden groups.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see( $request, true ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you cannot view this resource with edit context.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares group data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $item     Group data.
	 * @param WP_REST_Request $request  Full details about the request.
	 * @param boolean         $is_raw   Optional, not used. Defaults to false.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request, $is_raw = false ) {
		$data = array(
			'avatar_urls'        => array(),
			'creator_id'         => bp_get_group_creator_id( $item ),
			'date_created'       => $this->prepare_date_response( $item->date_created ),
			'description'        => bp_get_group_description( $item ),
			'enable_forum'       => bp_group_is_forum_enabled( $item ),
			'id'                 => $item->id,
			'link'               => bp_get_group_permalink( $item ),
			'name'               => bp_get_group_name( $item ),
			'slug'               => bp_get_group_slug( $item ),
			'status'             => bp_get_group_status( $item ),
			'admins'             => array(),
			'mods'               => array(),
			'total_member_count' => groups_get_groupmeta( $item->id, 'total_member_count' ),
			'last_activity'      => null,
			'is_user_member'     => groups_is_user_member( intval( $request['user_id'] ), $item->id ),
			'cover_image'        => bp_attachments_get_attachment(
				'url',
				array(
					'object_dir' => 'groups',
					'item_id'    => $item->id,
				)
			),
		);

		// Avatars.
		$data['avatar_urls']['thumb'] = bp_core_fetch_avatar(
			array(
				'html'    => false,
				'object'  => 'group',
				'item_id' => $item->id,
				'type'    => 'thumb',
			)
		);

		$data['avatar_urls']['full'] = bp_core_fetch_avatar(
			array(
				'html'    => false,
				'object'  => 'group',
				'item_id' => $item->id,
				'type'    => 'full',
			)
		);

		$context = ( ! empty( $request['context'] ) ) ? $request['context'] : 'view';

		// If this is the 'edit' context, fill in more details--similar to "populate_extras".
		if ( 'edit' === $context ) {
			$data['last_activity'] = $this->prepare_date_response( groups_get_groupmeta( $item->id, 'last_activity' ) );

			// Add admins and moderators to their respective arrays.
			$admin_mods = groups_get_group_members(
				array(
					'group_id'   => $item->id,
					'group_role' => array(
						'admin',
						'mod',
					),
				)
			);

			foreach ( (array) $admin_mods['members'] as $user ) {
				if ( ! empty( $user->is_admin ) ) {
					$data['admins'][] = $user;
				} else {
					$data['mods'][] = $user;
				}
			}
		}

		/**
		 * Filter a group value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'appcommunity_prepare_group', $data, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array $item Group.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $item ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $base . $item->id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
		);

		return $links;
	}

	/**
	 * Convert the input date to RFC3339 format.
	 *
	 * @since 0.1.0
	 *
	 * @param string      $date_gmt Date GMT format.
	 * @param string|null $date Optional. Date object.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Can this user see the activity?
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  boolean         $edit Edit fallback.
	 * @return boolean
	 */
	protected function can_see( $request, $edit = false ) {

		if ( $edit && 'edit' === $request['context'] && ! user_can( $request['user_id'], 'bp_moderate' ) ) {
			return false;
		}

		$retval = true;

		// Only bp_moderators and logged in users (viewing their own groups) can see hidden groups.
		// if ( ! empty( $request['show_hidden'] ) ) {
		// if ( ! empty( $request['user_id'] ) && bp_loggedin_user_id() !== $request['user_id'] ) {
		// $retval = false;
		// }

		// if ( ! user_can( $request['user_id'], 'bp_moderate' ) ) {
		// $retval = false;
		// }
		// }

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool            $retval Return value.
		 * @param WP_REST_Request $request Full details about the request.
		 */
		return apply_filters( 'appcommunity_group_endpoint_can_see', $retval, $request );
	}

	/**
	 * Show hidden group?
	 *
	 * @since 0.1.0
	 *
	 * @param  object $group Group object.
	 * @return boolean
	 */
	protected function show_hidden( $group, $request ) {
		// Bail early.
		if ( 'hidden' !== $group->status ) {
			return true;
		}

		// Moderators as well.
		if ( user_can( $request['user_id'], 'bp_moderate' ) ) {
			return true;
		}

		// User is a member of the group.
		if ( (bool) groups_is_user_member( $request['user_id'], $group->id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Clean up group_type__in input.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value Comma-separated list of group types.
	 * @return array|null
	 */
	public function sanitize_group_types( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		$types       = explode( ',', $value );
		$valid_types = array_intersect( $types, bp_groups_get_group_types() );

		return empty( $valid_types ) ? null : $valid_types;
	}

	/**
	 * Validate group_type__in input.
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed           $value mixed value.
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  string          $param string.
	 *
	 * @return WP_Error|boolean
	 */
	public function validate_group_types( $value, $request, $param ) {

		// Bail early.
		if ( empty( $value ) ) {
			return true;
		}

		$types            = explode( ',', $value );
		$registered_types = bp_groups_get_group_types();
		foreach ( $types as $type ) {
			if ( ! in_array( $type, $registered_types, true ) ) {
				/* translators: %1$s and %2$s is replaced with the registered types */
				return new WP_Error( 'rest_invalid_group_type', sprintf( __( 'The group type you provided, %1$s, is not one of %2$s.', 'appcommunity' ), $type, implode( ', ', $registered_types ) ) );
			}
		}
	}

	/**
	 * Get the group schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'group',
			'type'       => 'object',

			'properties' => array(
				'id'                 => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'appcommunity' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'creator_id'         => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the user that created the group.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'name'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the group.', 'appcommunity' ),
					'type'        => 'string',
				),

				'slug'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The URL-friendly slug for the group.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'link'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink to this object on the site.', 'appcommunity' ),
					'format'      => 'url',
					'type'        => 'string',
				),

				'description'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The description of the group.', 'appcommunity' ),
					'type'        => 'string',
				),

				'status'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The status of the group.', 'appcommunity' ),
					'type'        => 'string',
					'enum'        => array( 'public', 'private', 'hidden' ),
				),

				'enable_forum'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the group has a forum or not.', 'appcommunity' ),
					'type'        => 'boolean',
				),

				'date_created'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the group was created, in the site's timezone.", 'appcommunity' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),

				'admins'             => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group administrators.', 'appcommunity' ),
					'type'        => 'array',
				),

				'mods'               => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group administrators.', 'appcommunity' ),
					'type'        => 'array',
				),

				'total_member_count' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Count of all group members.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'last_activity'      => array(
					'context'     => array( 'edit' ),
					'description' => __( "The date the group was last active, in the site's timezone.", 'appcommunity' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),

				'avatar_urls'        => array(
					'description' => __( 'Avatar URLs for the resource.', 'appcommunity' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => array(
						'thumb' => array(
							'description' => __( 'Thumbnail-sized avatar URL.', 'appcommunity' ),
							'type'        => 'string',
							'format'      => 'uri',
							'context'     => array( 'embed', 'view', 'edit' ),
						),
						'full'  => array(
							'description' => __( 'Full-sized avatar URL.', 'appcommunity' ),
							'type'        => 'string',
							'format'      => 'uri',
							'context'     => array( 'embed', 'view', 'edit' ),
						),
					),
				),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for collections of groups.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['type'] = array(
			'description'       => __( 'Shorthand for certain orderby/order combinations', 'appcommunity' ),
			'type'              => 'string',
			'default'           => null,
			'enum'              => array( 'active', 'newest', 'alphabetical', 'random', 'popular', 'most-forum-topics', 'most-forum-posts' ),
			'sanitize_callback' => 'rest_validate_request_arg',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'appcommunity' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['orderby'] = array(
			'description'       => __( 'Order groups by which attribute.', 'appcommunity' ),
			'type'              => 'string',
			'default'           => 'date_created',
			'enum'              => array( 'date_created', 'last_activity', 'total_member_count', 'name', 'random' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Pass a user_id to limit to only groups that this user is a member of.', 'appcommunity' ),
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes groups with specific IDs.', 'appcommunity' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'appcommunity' ),
			'type'              => 'array',
			'default'           => false,
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['search'] = array(
			'description'       => __( 'Limit results set to items that match this search query.', 'appcommunity' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['group_type'] = array(
			'description'       => __( 'Limit results set to a certain type.', 'appcommunity' ),
			'default'           => '',
			'type'              => 'string',
			'enum'              => bp_groups_get_group_types(),
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['group_type__in'] = array(
			'description'       => __( 'Limit results set to groups of certain types.', 'appcommunity' ),
			'default'           => '',
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['group_type__not_in'] = array(
			'description'       => __( 'Exclude groups of certain types.', 'appcommunity' ),
			'default'           => '',
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['show_hidden'] = array(
			'description'       => __( 'Whether results should include hidden groups.', 'appcommunity' ),
			'default'           => false,
			'type'              => 'boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['per_page'] = array(
			'description'       => __( 'Maximum number of results returned per result set.', 'appcommunity' ),
			'default'           => 20,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['page'] = array(
			'description'       => __( 'Offset the result set by a specific number of pages of results.', 'appcommunity' ),
			'default'           => 1,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
