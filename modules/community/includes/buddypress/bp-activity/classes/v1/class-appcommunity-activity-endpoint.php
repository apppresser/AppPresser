<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * BP REST: AppCommunity_Activity_Endpoint_V1 class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity endpoints.
 *
 * @since 0.1.0
 */
class AppCommunity_Activity_Endpoint_V1 extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'ap-bp/v1';
		$this->rest_base = buddypress()->activity->id;

		add_filter( 'appcommunity_activity_content', array( $this, 'filter_activity_content' ), 10, 2 );
		add_filter( 'appcommunity_activity_content', array( $this, 'strip_the_text_slashes' ), 20, 2 );
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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( true ),
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
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( false ),
				),
				// array(
				// 'methods'             => WP_REST_Server::DELETABLE,
				// 'callback'            => array( $this, 'delete_item' ),
				// 'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				// ),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve activities.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of activities object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'exclude'           => $request['exclude'],
			'in'                => $request['include'],
			'page'              => $request['page'],
			'per_page'          => $request['per_page'],
			'search_terms'      => $request['search'],
			'sort'              => $request['order'],
			'spam'              => 'spam' === $request['status'] ? 'spam_only' : 'ham_only',
			'display_comments'  => $request['display_comments'],
			'count_total'       => true,
			'fields'            => 'all',
			'show_hidden'       => false,
			'update_meta_cache' => true,
			'filter'            => false,
		);

		if ( isset( $request['after'] ) ) {
			$args['since'] = $request['after'];
		}

		if ( isset( $request['user'] ) ) {
			$args['filter']['user_id'] = $request['user'];
		}

		if ( isset( $request['component'] ) ) {
			$args['filter']['object'] = $request['component'];
		}

		if ( isset( $request['type'] ) ) {
			$args['filter']['action'] = $request['type'];
		}

		if ( isset( $request['primary_id'] ) ) {
			$args['filter']['primary_id'] = $request['primary_id'];

			// this handles showing activity in private groups
			if ( $request['user_id'] ) {

				$user_id  = intval( $request['user_id'] );
				$group_id = intval( $request['primary_id'] );

				if ( bp_is_active( 'groups' ) ) {
					// see if they have access to group
					if ( groups_is_user_admin( $user_id, $group_id ) || groups_is_user_mod( $user_id, $group_id ) || groups_is_user_member( $user_id, $group_id ) || groups_is_user_creator( $user_id, $group_id ) || user_can( $user_id, 'activate_plugins' ) ) {
						$args['show_hidden'] = true;
					}
				} else {
					return new WP_Error(
						'bp_rest_invalid_activity',
						__( 'You have to enable user groups in BuddyPress settings.', 'appcommunity' ),
						array(
							'status' => 500,
						)
					);
				}
			}
		}

		if ( isset( $request['secondary_id'] ) ) {
			$args['filter']['secondary_id'] = $request['secondary_id'];
		}

		if ( $args['in'] ) {
			$args['count_total'] = false;
		}

		if ( isset( $request['scope'] ) ) {
			$args['scope'] = $request['scope'];

			// for some reason API breaks without this
			if ( isset( $request['user_id'] ) ) {
				$args['filter']['user_id'] = $request['user_id'];
			}
		}

		$args = apply_filters( 'appcommunity_activity_get_items_args', $args, $request );

		$activities = bp_activity_get( $args );

		$retval = array();
		foreach ( $activities['activities'] as $activity ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			);
		}

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after a list of activities is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activities Fetched activities.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'appcommunity_activity_get_items', $activities, $retval, $request );

		return $retval;
	}

	/**
	 * Retrieve an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {

		$args = array(
			'display_comments' => true,
			'activity_ids'     => $request['id'],
		);

		$args = apply_filters( 'appcommunity_activity_get_item_args', $args, $request );

		$act_item = bp_activity_get_specific( $args );

		// Prevent non-members from seeing hidden activity.
		/*
		if ( ! $this->show_hidden( $request, 'activity', $request['id'] ) ) {
			return new WP_Error( 'bp_rest_invalid_activity',
				__( 'Sorry, you are not allowed to see that.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}
		*/

		$retval = rest_ensure_response( $act_item );

		/**
		 * Fires after an activity is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity Fetched activity.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'appcommunity_activity_get_item', $act_item, $retval, $request );

		return $retval;
	}

	/**
	 * Check if a given request has access to get information about a specific activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! $this->can_see( $request ) ) {
			return new WP_Error(
				'rest_user_cannot_view_activity',
				__( 'Sorry, you cannot view the activities.', 'appcommunity' ),
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
	 * Check if a given request has access to activity items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		// Bail early.
		/*
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to see the activities.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}*/

		return true;
	}

	/**
	 * Create an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function create_item( $request ) {

		if ( ! empty( $_FILES['activity_image'] ) ) {
			return $this->create_item_with_image( $request );
		} else {

			$prepared_activity = $this->prepare_item_for_database( $request );
			$prime             = $request['primary_id'];
			$id                = $request['id'];
			$parent            = $request['parent'];
			$type              = $request['type'];
			$activity_id       = 0;

			if ( ( 'activity_update' === $type ) && bp_is_active( 'groups' ) && ! is_null( $prime ) ) {
				$activity_id = groups_post_update( $prepared_activity );
			} elseif ( ( 'activity_comment' === $type ) && ! is_null( $parent ) ) {

				$activity_id = bp_activity_new_comment( $prepared_activity );
			} else {
				$activity_id = bp_activity_post_update( $prepared_activity );
			}

			if ( ! is_numeric( $activity_id ) ) {
				return new WP_Error(
					'rest_activity_invalid_id',
					__( 'Sorry, there was a problem.', 'appcommunity' ),
					array(
						'status' => 500,
					)
				);
			}

			$activity = bp_activity_get(
				array(
					'in'               => $activity_id,
					'display_comments' => 'stream',
					'show_hidden'      => true,
				)
			);

			$retval = array(
				$this->prepare_response_for_collection(
					$this->prepare_item_for_response( $activity['activities'][0], $request )
				),
			);

			$retval = rest_ensure_response( $retval );

			/**
			 * Fires after an activity is created via the REST API.
			 *
			 * @since 0.1.0
			 *
			 * @param object           $activity The created activity.
			 * @param WP_REST_Response $retval   The response data.
			 * @param WP_REST_Request  $request  The request sent to the API.
			 */
			do_action( 'appcommunity_activity_create_item_image', $activity, $retval, $request );

			return $retval;

		}
	}

	public function create_item_with_image( $request ) {

		$content  = $request['content'];
		$user_ID  = $request['user_id'];
		$group_id = $request['primary_id'];

		if ( $group_id && groups_is_user_member( $user_ID, $group_id ) === false ) {

			return new WP_Error(
				'rest_create_activity_group_permission',
				__( 'Sorry, only group members can post here.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);

		}

		if ( ! empty( $_FILES['activity_image'] ) ) {
			$uploadedfile = $_FILES['activity_image'];

			// make sure to include the media uploader
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$uploaded_file = wp_handle_upload( $uploadedfile, array( 'test_form' => false ) );

		}

		if ( $uploaded_file ) {

			// generate the image thumbnail
			$wp_filetype   = wp_check_filetype( basename( $uploaded_file['file'] ), null );
			$filename      = $uploaded_file['file'];
			$wp_upload_dir = wp_upload_dir();

			$post_id = 0;

			// add to media library

			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
				'post_author'    => 1,
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			);

			$attachment_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$attach_data = wp_generate_attachment_metadata( absint( $attachment_id ), $uploaded_file['file'] );

			// update the attachment metadata
			wp_update_attachment_metadata( absint( $attachment_id ), $attach_data );

			$content .= '<img src="' . $uploaded_file['url'] . '">';

		}

		if ( bp_is_active( 'groups' ) && ! empty( $group_id ) ) {

			$activity_id = groups_post_update(
				array(
					'content'  => $content,
					'user_id'  => $user_ID,
					'group_id' => $group_id,
				)
			);

		} elseif ( function_exists( 'bp_activity_add' ) || function_exists( 'bp_core_get_userlink' ) ) {

			$userlink = bp_core_get_userlink( $user_ID );

			$activity_id = bp_activity_add(
				array(
					'action'    => apply_filters( 'xprofile_new_avatar_action', sprintf( __( '%s posted an update', 'appcommunity' ), $userlink ), $user_ID ),
					'content'   => $content,
					'component' => 'activity',
					'type'      => 'activity_update',
					'user_id'   => $user_ID,
				)
			);
		}

		if ( ! is_numeric( $activity_id ) ) {
			return new WP_Error(
				'rest_create_activity_invalid_id',
				__( 'There was a problem on your site.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity = bp_activity_get(
			array(
				'in'          => $activity_id,
				'show_hidden' => true,
			)
		);

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity['activities'][0], $request )
			),
		);

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity The created activity.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'appcommunity_activity_create_item_with_image', $activity, $retval, $request );

		return $retval;
	}

	/**
	 * Checks if a given request has access to create an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {

		$AP_JWT = new AP_JWT();

		$validation = $AP_JWT->validate_token( $request );

		// Bail early.
		if ( $validation !== true ) {

			if ( ! empty( $validation['error'] ) ) {
				$msg = $validation['error'];
			}
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to create activities. ' . $msg, 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		// Bail early.
		if ( empty( $request['content'] ) && empty( $_FILES['activity_image'] ) ) {
			return new WP_Error(
				'rest_create_activity_empty_content',
				__( 'Please, enter some content.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$group_id = $request['primary_id'];
		// check for group access before posting
		if ( ( 'activity_update' === $request['type'] ) && bp_is_active( 'groups' ) && ! empty( $group_id ) ) {

			if ( ! bp_user_can( $request['user_id'], 'bp_moderate' ) && ! groups_is_user_member( $request['user_id'], $group_id ) ) {
				return new WP_Error(
					'rest_user_cannot_create_activity_in_group',
					__( 'Sorry, you are not allowed to create activity in this group.', 'appcommunity' ),
					array(
						'status' => 500,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Update an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {

		// Bail early.
		if ( empty( $request['content'] ) && empty( $request['action'] ) ) {
			return new WP_Error(
				'rest_update_activity_empty_content',
				__( 'Please, enter some content.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$user_id = $request['user_id'];

		if ( ( 'activity_favorite' === $request['action'] ) && ! is_null( $user_id ) ) {

			$bool = bp_activity_add_user_favorite( $request['id'], $user_id );
			return $bool;

		} elseif ( ( 'activity_remove_favorite' === $request['action'] ) && ! is_null( $user_id ) ) {

			$bool = bp_activity_remove_user_favorite( $request['id'], $user_id );
			return $bool;

		} elseif ( ( 'activity_flag' === $request['action'] ) ) {

			$bool = bp_activity_update_meta( $request['id'], 'is_flagged', true );
			return $bool;

		}

		$activity_id = bp_activity_add( $this->prepare_item_for_database( $request ) );

		if ( ! is_numeric( $activity_id ) ) {
			return new WP_Error(
				'rest_user_cannot_update_activity',
				__( 'Cannot update existing activity.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity = $this->get_activity_object( $activity_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity The updated activity.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'appcommunity_activity_update_item', $activity, $retval, $request );

		return $retval;
	}

	/**
	 * Check if a given request has access to update an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {

		// Bail early.
		$AP_JWT = new AP_JWT();

		$validation = $AP_JWT->validate_token( $request );

		// Bail early.
		if ( $validation != true ) {

			if ( ! empty( $validation['error'] ) ) {
				$msg = $validation['error'];
			}
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to create activities. ' . $msg, 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$activity = $this->get_activity_object( $request );

		if ( empty( $activity->id ) ) {
			return new WP_Error(
				'rest_update_activity_invalid_id',
				__( 'Invalid activity id.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		// If activity author does not match logged_in user, block access.
		// if ( $request['user_id'] !== strval( $activity->user_id ) ) {
		// return new WP_Error( 'rest_activity_cannot_update',
		// __( 'You are not allowed to update this activity.', 'appcommunity' ),
		// array(
		// 'status' => 500,
		// )
		// );
		// }

		return true;
	}

	/**
	 * Delete an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$activity = $this->get_activity_object( $request );

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $activity, $request );

		if ( 'activity_comment' === $activity->type ) {
			$retval = bp_activity_delete_comment( $activity->item_id, $activity->id );
		} else {
			$retval = bp_activity_delete(
				array(
					'id' => $activity->id,
				)
			);
		}

		if ( ! $retval ) {
			return new WP_Error(
				'rest_activity_cannot_delete',
				__( 'Could not delete the activity.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Fires after an activity is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity The deleted activity.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'appcommunity_activity_delete_item', $activity, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {

		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this activity.', 'appcommunity' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$activity = $this->get_activity_object( $request );

		if ( empty( $activity->id ) ) {
			return new WP_Error(
				'rest_activity_invalid_id',
				__( 'Invalid activity id.', 'appcommunity' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( false === $this->can_see( $request, true ) ) {
			return new WP_Error(
				'rest_user_cannot_delete_activity',
				__( 'Sorry, you cannot delete the activity.', 'appcommunity' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Prepares activity data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $activity Activity data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param boolean         $is_raw Optional, not used. Defaults to false.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $activity, $request, $is_raw = false ) {

		global $wpdb;
		$comment_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$wpdb->prefix}bp_activity WHERE type = 'activity_comment' AND item_id = %d", $activity->id ) );

		$data = array(
			'user'                  => $activity->user_id,
			'display_name'          => bp_core_get_user_displayname( $activity->user_id ),
			'component'             => $activity->component,
			'content'               => apply_filters( 'appcommunity_activity_content', $activity->content, $activity->id ),
			'date'                  => $this->prepare_date_response( $activity->date_recorded ),
			'id'                    => $activity->id,
			'link'                  => bp_activity_get_permalink( $activity->id ),
			'parent'                => 'activity_comment' === $activity->type ? $activity->item_id : 0,
			'prime_association'     => $activity->item_id,
			'secondary_association' => $activity->secondary_item_id,
			'status'                => $activity->is_spam ? 'spam' : 'published',
			'title'                 => strip_tags( $activity->action ),
			'type'                  => $activity->type,
			'favorites'             => bp_activity_get_meta( $activity->id, 'favorite_count' ),
			'comment_count'         => $comment_count,
		);

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['comments'] ) && 'threaded' === $request['display_comments'] ) {
			$data['comments'] = $this->prepare_activity_comments( $activity->children, $request );
		}

		if ( ! empty( $schema['properties']['user_avatar'] ) ) {
			$data['user_avatar'] = array(
				'full'  => bp_core_fetch_avatar(
					array(
						'item_id' => $activity->user_id,
						'html'    => false,
						'type'    => 'full',
					)
				),

				'thumb' => bp_core_fetch_avatar(
					array(
						'item_id' => $activity->user_id,
						'html'    => false,
					)
				),
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $activity ) );

		/**
		 * Filter an activity value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'appcommunity_prepare_buddypress_activity_value', $response, $request );
	}

	/*
	Do oembeds, etc on activity
	*/
	public function filter_activity_content( $content, $id ) {

		$content = apply_filters( 'the_content', $content );
		return $content;
	}

	public function strip_the_text_slashes( $text, $id ) {
		return stripslashes( $text );
	}

	/**
	 * Prepare activity comments.
	 *
	 * @since 0.1.0
	 *
	 * @param  object          $comments Comments.
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return array           An array of activity comments.
	 */
	protected function prepare_activity_comments( $comments, $request ) {
		$data = array();

		if ( empty( $comments ) ) {
			return $data;
		}

		foreach ( $comments as $comment ) {
			$data[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $comment, $request )
			);
		}

		return $data;
	}

	/**
	 * Prepare an activity for create or update.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_activity = new stdClass();
		$schema            = $this->get_item_schema();
		$activity          = $this->get_activity_object( $request );

		if ( ! empty( $schema['properties']['id'] ) && ! empty( $activity->id ) ) {
			if ( 'activity_comment' === $request['type'] ) {
				$prepared_activity->activity_id = $activity->id;
			} else {
				$prepared_activity->id         = $activity->id;
				$prepared_activity->component  = buddypress()->activity->id;
				$prepared_activity->error_type = 'wp_error';
			}
		}

		// Comment parent.
		if ( ! empty( $schema['properties']['parent'] ) && ( 'activity_comment' === $request['type'] ) && isset( $request['parent'] ) ) {
			$prepared_activity->parent_id = $request['parent'];
		}

		// Group ID.
		if ( ! empty( $schema['properties']['primary_id'] ) && isset( $request['primary_id'] ) && ( 'activity_update' === $request['type'] ) ) {
			$prepared_activity->group_id = (int) $request['primary_id'];
		}

		// Activity type.
		if ( ! empty( $schema['properties']['type'] ) && isset( $request['type'] ) ) {
			$prepared_activity->type = $request['type'];
		}

		// Activity content.
		if ( ! empty( $schema['properties']['content'] ) && isset( $request['content'] ) ) {
			$prepared_activity->content = stripslashes( $request['content'] );
		}

		// Activity content.
		if ( ! empty( $schema['properties']['user'] ) && isset( $request['user_id'] ) ) {
			$prepared_activity->user_id = intval( $request['user_id'] );
		}

		/**
		 * Filters an activity before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_activity An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'appcommunity_pre_insert_buddypress_activity_value', $prepared_activity, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array $activity Activity.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $activity ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $activity->id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href' => rest_url( sprintf( '/wp/v2/users/%d', $activity->user_id ) ),
			),
		);

		if ( 'activity_comment' === $activity->type ) {
			$links['up'] = array(
				'href' => rest_url( $url ),
			);
		}

		return $links;
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
		$user_id  = $request['user_id'];
		$activity = $this->get_activity_object( $request );
		$retval   = true;

		// Fix for edit content.
		if ( $edit && 'edit' === $request['context'] && ! user_can( $user_id, 'bp_moderate' ) ) {
			return false;
		}

		// If activity is from a group, do an extra cap check.
		if ( bp_is_active( 'groups' ) && ! empty( $request['primary_id'] ) ) {

			$group_id = $request['primary_id'];

			// User is a member of the group.
			if ( ! groups_is_user_member( $user_id, $group_id ) ) {
				$retval = false;
			}

			// Group admins and mods have access as well.
			if ( groups_is_user_admin( $user_id, $group_id ) || groups_is_user_mod( $user_id, $group_id ) ) {
				$retval = true;
			}
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool                 $retval   Return value.
		 * @param int                  $user_id  User ID.
		 * @param BP_Activity_Activity $activity Activity object.
		 */
		return apply_filters( 'appcommunity_activity_endpoint_can_see', $retval, $user_id, $activity );
	}

	/**
	 * Show hidden activity?
	 *
	 * @since 0.1.0
	 *
	 * @param  string $component Group component.
	 * @param  int    $group_id  Group ID.
	 * @return boolean
	 */
	protected function show_hidden( $request, $component, $group_id ) {
		$user_id = $request['user_id'];
		$retval  = false;

		// Moderators as well.
		if ( bp_user_can( $user_id, 'bp_moderate' ) ) {
			$retval = true;
		}

		// If activity is from a group, do an extra cap check.
		if ( bp_is_active( 'groups' ) && ! empty( $request['primary_id'] ) ) {

			$group_id = $request['primary_id'];

			// User is a member of the group.
			if ( (bool) groups_is_user_member( $user_id, $group_id ) ) {
				$retval = true;
			}

			// Group admins and mods have access as well.
			if ( groups_is_user_admin( $user_id, $group_id ) || groups_is_user_mod( $user_id, $group_id ) ) {
				$retval = true;
			}
		}

		return (bool) $retval;
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
	public function prepare_date_response( $date_gmt, $date = null ) {
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Get activity object.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return object An activity object.
	 */
	public function get_activity_object( $request ) {
		$activity_id = is_numeric( $request ) ? $request : (int) $request['id'];

		$activity = bp_activity_get_specific(
			array(
				'activity_ids' => array( $activity_id ),
			)
		);

		if ( is_array( $activity ) && isset( $activity['activities'][0] ) ) {

			/*
			 * FYI you can get whole tree with comments like this
			 * $comments = BP_Activity_Activity::get_child_comments( $activity_id );
			 */

			return $activity['activities'][0];
		}

		return '';
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
			'title'      => 'activity',
			'type'       => 'object',

			'properties' => array(
				'id'                    => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'appcommunity' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'primary_id'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object primarily associated with this one.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'secondary_association' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'user'                  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID for the creator of the object.', 'appcommunity' ),
					'type'        => 'integer',
				),

				'link'                  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink to this object on the site.', 'appcommunity' ),
					'format'      => 'url',
					'type'        => 'string',
				),

				'component'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The BuddyPress component the object relates to.', 'appcommunity' ),
					'type'        => 'string',
					'enum'        => array_keys( bp_core_get_components() ),
				),

				'type'                  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The activity type of the object.', 'appcommunity' ),
					'type'        => 'string',
					'enum'        => array_keys( bp_activity_get_types() ),
				),

				'title'                 => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML title of the object.', 'appcommunity' ),
					'type'        => 'string',
				),

				'content'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML content of the object.', 'appcommunity' ),
					'type'        => 'string',
				),

				'date'                  => array(
					'description' => __( "The date the object was published, in the site's timezone.", 'appcommunity' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),

				'status'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the object has been marked as spam or not.', 'appcommunity' ),
					'type'        => 'string',
					'enum'        => array( 'published', 'spam' ),
				),

				'parent'                => array(
					'description' => __( 'The ID of the parent of the object.', 'appcommunity' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),

				'comments'              => array(
					'description' => __( 'The ID for the parent of the object.', 'appcommunity' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

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

			$schema['properties']['user_avatar'] = array(
				'description' => __( 'Avatar URLs for the member.', 'appcommunity' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		return $schema;
	}

	/**
	 * Get the query params for collections of plugins.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'appcommunity' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes specific IDs.', 'appcommunity' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'appcommunity' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['after'] = array(
			'description'       => __( 'Limit result set to items published after a given ISO8601 compliant date.', 'appcommunity' ),
			'type'              => 'string',
			'format'            => 'date-time',
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

		$params['user'] = array(
			'description'       => __( 'Limit result set to items created by specific users.', 'appcommunity' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/*
		$params['user_id'] = array(
			'description'       => __( 'The user ID.', 'appcommunity' ),
			'type'              => 'integer',
			'default'           => '',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);
		*/

		$params['status'] = array(
			'default'           => 'published',
			'description'       => __( 'Limit result set to items with a specific status.', 'appcommunity' ),
			'type'              => 'string',
			'enum'              => array( 'published', 'spam' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/*
		$params['primary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific prime assocation.', 'appcommunity' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);
		*/

		$params['secondary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific secondary assocation.', 'appcommunity' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['component'] = array(
			'description'       => __( 'Limit result set to items with a specific BuddyPress component.', 'appcommunity' ),
			'type'              => 'string',
			'enum'              => array_keys( bp_core_get_components() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Limit result set to items with a specific activity type.', 'appcommunity' ),
			'type'              => 'string',
			'enum'              => array_keys( bp_activity_get_types() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['search'] = array(
			'description'       => __( 'Limit result set to items that match this search query.', 'appcommunity' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['display_comments'] = array(
			'description'       => __( 'False for no comments. stream for within stream display, threaded for below each activity item..', 'appcommunity' ),
			'default'           => '',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
