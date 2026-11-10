<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * BP REST: AppCommunity_Members_Endpoint_V1 class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Members endpoints.
 *
 * @since 0.1.0
 */
class AppCommunity_Members_Endpoint_V1 extends WP_REST_Users_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v1';
		$this->rest_base = 'members';
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
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					// 'permission_callback' => array( $this, 'get_item_permissions_check' ),
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
	}

	/**
	 * Retrieve members.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of groups object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'type'         => ( $request['type'] ? $request['type'] : 'alphabetical' ),
			'per_page'     => ( $request['per_page'] ? $request['per_page'] : 10 ),
			'page'         => $request['page'],
			// add user argument to display friends
			'user_id'      => $request['user'],
			'search_terms' => $request['search'],
		);

		$args = apply_filters( 'appcommunity_members_get_items_args', $args, $request );

		$members = array();

		if ( bp_has_members( $args ) ) :

			while ( bp_members() ) :
				bp_the_member();

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

		do_action( 'appcommunity_members_get_items', $retval, $request );

		return $retval;
	}

	/**
	 * Retrieve a member.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {

		if ( empty( $request['id'] ) ) {
			return 'Missing ID';
		}

		if ( ! is_numeric( $request['id'] ) ) {
			$user_id = bp_core_get_userid_from_nicename( $request['id'] );
		} else {
			$user_id = $request['id'];
		}

		$member = get_userdata( $user_id );

		if ( ! $member ) {
			return new WP_Error(
				'bp_rest_invalid_member',
				__( 'No member found.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		$retval = $this->prepare_item_for_response( $member, $request );

		$retval = rest_ensure_response( $retval );

		do_action( 'appcommunity_members_get_item', $retval, $request );

		return apply_filters( 'appcommunity_members_get_item_response', $retval );
	}

	/**
	 * Prepares a single user output for response.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $user User data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $user, $request ) {
		$data   = array();
		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['id'] ) ) {
			$data['id'] = $user->ID;
		}

		if ( ! empty( $schema['properties']['name'] ) ) {
			$data['name'] = $user->display_name;
		}

		if ( ! empty( $schema['properties']['email'] ) ) {
			$data['email'] = $user->user_email;
		}

		if ( ! empty( $schema['properties']['link'] ) ) {
			$data['link'] = bp_core_get_user_domain( $user->ID, $user->user_nicename, $user->user_login );
		}

		if ( ! empty( $schema['properties']['mention_name'] ) ) {
			$data['mention_name'] = bp_is_username_compatibility_mode() ? $user->user_login : $user->user_nicename;
		}

		if ( ! empty( $schema['properties']['registered_date'] ) ) {
			$data['registered_date'] = date( 'c', strtotime( $user->user_registered ) );
		}

		if ( ! empty( $schema['properties']['avatar_urls'] ) ) {
			$data['avatar_urls'] = array(
				'full'  => bp_core_fetch_avatar(
					array(
						'item_id' => $user->ID,
						'html'    => false,
						'type'    => 'full',
					)
				),

				'thumb' => bp_core_fetch_avatar(
					array(
						'item_id' => $user->ID,
						'html'    => false,
					)
				),
			);
		}

		if ( ! empty( $schema['properties']['cover_image'] ) ) {
			$data['cover_image'] = bp_attachments_get_attachment( 'url', array( 'item_id' => $user->ID ) );
		}

		// Member types.
		if ( ! empty( $schema['properties']['member_types'] ) ) {
			$data['member_types'] = bp_get_member_type( $user->ID, false );
			if ( false === $data['member_types'] ) {
				$data['member_types'] = array();
			}
		}

		// Defensively call array_values() to ensure an array is returned.
		if ( ! empty( $schema['properties']['roles'] ) ) {
			$data['roles'] = array_values( $user->roles );
		}

		if ( ! empty( $schema['properties']['capabilities'] ) ) {
			$data['capabilities'] = (object) $user->allcaps;
		}

		if ( ! empty( $schema['properties']['extra_capabilities'] ) ) {
			$data['extra_capabilities'] = (object) $user->caps;
		}

		if ( ! empty( $schema['properties']['xprofile'] ) ) {
			$data['xprofile'] = $this->xprofile_data( $user->ID );
		}

		if ( bp_is_active( 'friends' ) && ! empty( $request['user_id'] ) ) {
			$data['check_friendship'] = friends_check_friendship( intval( $request['user_id'] ), $user->ID );
		}

		if ( ! empty( $schema['properties']['last_activity'] ) ) {
			$update = bp_activity_get(
				array(
					'per_page' => 1,
					'filter'   => array(
						'user_id' => $user->ID,
						'action'  => 'activity_update',
					),
				)
			);
			if ( $update && $update['activities'] ) {
				$last_activity          = $update['activities'][0];
				$last_activity->content = stripslashes( $last_activity->content );
				$data['last_activity']  = $last_activity;
			}
		}

		if ( bp_is_active( 'friends' ) && ! empty( $schema['properties']['request_count'] ) && $user->ID == $request['user_id'] ) {
			$data['request_count'] = bp_friend_get_total_requests_count( $user->ID );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'embed';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		/**
		 * Filters user data returned from the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param object           $user     User object used to create response.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( 'appcommunity_prepare_user', $data, $request );
	}

	/**
	 * Get XProfile info from the user.
	 *
	 * @since 0.1.0
	 *
	 * @param  int $user_id User ID.
	 * @return array XProfile info.
	 */
	protected function xprofile_data( $user_id ) {

		// Get XProfile group info.
		$groups = bp_xprofile_get_groups(
			array(
				'user_id'          => $user_id,
				'fetch_fields'     => true,
				'fetch_field_data' => true,
			)
		);

		$data = array();
		foreach ( $groups as $group ) {
			$data['groups'][ $group->id ] = array(
				'name' => $group->name,
			);

			foreach ( $group->fields as $item ) {
				$data['groups'][ $group->id ]['fields'][ $item->id ] = array(
					'name'  => $item->name,
					'value' => maybe_unserialize( $item->data->value ),
				);
			}
		}

		return $data;
	}

	/**
	 * Can we see a member?
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  boolean         $edit Edit fallback.
	 * @return boolean
	 */
	protected function can_see( $request, $edit = false ) {
		$user_id = $request['user_id'];
		$retval  = false;

		$user = get_userdata( $request['id'] );
		if ( is_wp_error( $user ) || empty( $user_id ) ) {
			return false;
		}

		// Me, myself and I are always allowed access.
		if ( $user_id === strval( $user->ID ) ) {
			return true;
		}

		// Moderators as well.
		if ( user_can( $user_id, 'bp_moderate' ) ) {
			$retval = true;
		}

		if ( user_can( $user_id, 'list_users' ) ) {
			$retval = true;
		}

		// Fix for edit content.
		if ( $edit && 'edit' === $request['context'] && $retval ) {
			$retval = true;
		}

		if ( in_array( 'subscriber', get_userdata( $user_id )->roles ) ) {
			$retval = true;
		}

		return (bool) $retval;
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'member',
			'type'       => 'object',
			'appp'       => array(
				'description' => __( 'Custom data.', 'appcommunity' ),
				'type'        => 'string',
				'context'     => array( 'embed', 'view', 'edit' ),
			),

			'properties' => array(
				'id'                 => array(
					'description' => __( 'Unique identifier for the member.', 'appcommunity' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'name'               => array(
					'description' => __( 'Display name for the member.', 'appcommunity' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),

				'email'              => array(
					'description' => __( 'The email address for the member.', 'appcommunity' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'edit' ),
					'required'    => true,
				),

				'link'               => array(
					'description' => __( 'Profile URL of the member.', 'appcommunity' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),

				'mention_name'       => array(
					'description' => __( 'An alphanumeric identifier for the member.', 'appcommunity' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'sanitize_slug' ),
					),
				),

				'registered_date'    => array(
					'description' => __( 'Registration date for the member.', 'appcommunity' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

				'password'           => array(
					'description' => __( 'Password for the member (never included).', 'appcommunity' ),
					'type'        => 'string',
					'context'     => array(), // Password is never displayed.
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_user_password' ),
					),
				),

				'roles'              => array(
					'description' => __( 'Roles assigned to the member.', 'appcommunity' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'context'     => array( 'edit' ),
				),

				'capabilities'       => array(
					'description' => __( 'All capabilities assigned to the user.', 'appcommunity' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

				'extra_capabilities' => array(
					'description' => __( 'Any extra capabilities assigned to the user.', 'appcommunity' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

				'member_types'       => array(
					'description' => __( 'Member types associated with the member.', 'appcommunity' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'last_activity'      => array(
					'description' => __( 'Last member activity.', 'appcommunity' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'request_count'      => array(
					'description' => __( 'Total pending friend requests.', 'appcommunity' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'cover_image'        => array(
					'description' => __( 'Member cover image.', 'appcommunity' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

			),
		);

		if ( bp_is_active( 'xprofile' ) ) {

			$schema['properties']['xprofile'] = array(
				'description' => __( 'Member XProfile groups and its fields.', 'appcommunity' ),
				'type'        => 'array',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
			);

		}

		// Avatars.
		if ( true === buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				/* translators: Full image size for the member Avatar */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'appcommunity' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$avatar_properties['thumb'] = array(
				/* translators: Thumb imaze size for the member Avatar */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'appcommunity' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$schema['properties']['avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the member.', 'appcommunity' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Check if a given request has access to get information about a specific item.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {

		if ( ! $this->can_see( $request ) ) {
			return new WP_Error(
				'rest_user_cannot_view_user',
				__( 'Sorry, you cannot view the user.', 'appcommunity' ),
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
	 * Check if a given request has access to user items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		// permissions not required to view members
		return true;
		// return $this->get_item_permissions_check( $request );
	}
}
