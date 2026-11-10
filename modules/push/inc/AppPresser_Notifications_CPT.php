<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_Notifications_CPT extends AppPresser_API_Response_Viewer {

	public static $notify_url_meta_key = 'apppush_notify_url';
	public $singular;
	public $plural;
	public $post_type;
	public $labels;
	public $args;

	public function __construct() {

		$this->singular  = __( 'Notification', 'apppresser-push' );
		$this->plural    = __( 'Notifications', 'apppresser-push' );
		$this->post_type = AppPresser_Notifications::$cpt;

		$this->labels = array(
			'name'               => $this->plural,
			'singular_name'      => $this->singular,
			'add_new'            => sprintf( __( 'Add New %s' ), $this->singular ),
			'add_new_item'       => sprintf( __( 'Add New %s' ), $this->singular ),
			'edit_item'          => sprintf( __( 'Edit %s' ), $this->singular ),
			'new_item'           => sprintf( __( 'New %s' ), $this->singular ),
			'all_items'          => $this->plural,
			'view_item'          => sprintf( __( 'View %s' ), $this->singular ),
			'search_items'       => sprintf( __( 'Search %s' ), $this->plural ),
			'not_found'          => sprintf( __( 'No %s' ), $this->plural ),
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash' ), $this->plural ),
			'parent_item_colon'  => null,
			'menu_name'          => $this->plural,
		);

		$this->args = array(
			'labels'          => $this->labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'apppresser_settings',
			'query_var'       => true,
			'rewrite'         => array( 'slug' => 'notification' ),
			'capability_type' => 'post',
			'hierarchical'    => false,
			'menu_position'   => 1,
			'supports'        => array( 'title', 'excerpt' ),
			'taxonomies'      => array(),
		);
	}

	public function hooks() {

		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'admin_head', array( $this, 'conditional_hooks' ) );
		add_filter( 'post_updated_messages', array( $this, 'messages' ) );
		add_filter( 'manage_edit-' . $this->post_type . '_columns', array( $this, 'columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'columns_display' ) );
		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'metabox_replace' ) );
		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'api_response_metabox' ) );
		add_action( 'save_post', array( $this, 'save_post_meta' ), 10, 2 );
		
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_apppush_search_users', array( $this, 'ajax_search_users' ) );
		add_action( 'wp_ajax_apppush_search_pages', array( $this, 'ajax_search_pages' ) );
		add_action( 'wp_ajax_apppush_get_recipient_count', array( $this, 'ajax_get_recipient_count' ) );
		add_action( 'wp_ajax_apppush_get_app_pages', array( $this, 'ajax_get_app_pages' ) );
	}

	/**
	 * Conditional Hooks for this CPT
	 *
	 * @since  1.0.0
	 */
	public function conditional_hooks() {
		$screen = get_current_screen();
		if ( isset( $screen->post_type ) && $screen->post_type == $this->post_type ) {
			add_filter( 'enter_title_here', array( $this, 'title' ) );
			add_filter( 'gettext', array( $this, 'modify_text' ) );
			$this->excerpt_css();
		}
	}

	/**
	 * Register notifications Custom Post Type
	 *
	 * @since  1.0.0
	 */
	public function register_cpt() {
		register_post_type( $this->post_type, apply_filters( 'appp_push_cpt_args', $this->args ) );
	}

	/**
	 * Modies CPT based messages to include our CPT labels
	 *
	 * @since  0.1.0
	 * @param  array $messages Array of messages
	 * @return array            Modied messages array
	 */
	public function messages( $messages ) {
		global $post, $post_ID;

		$messages[ $this->singular ] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => sprintf( __( '%1$s updated. <a href="%2$s">View %1$s</a>' ), $this->singular, esc_url( get_permalink( $post_ID ) ) ),
			2  => __( 'Custom field updated.' ),
			3  => __( 'Custom field deleted.' ),
			4  => sprintf( __( '%1$s updated.' ), $this->singular ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( '%1$s restored to revision from %2$s' ), $this->singular, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => sprintf( __( '%1$s published. <a href="%2$s">View %1$s</a>' ), $this->singular, esc_url( get_permalink( $post_ID ) ) ),
			7  => sprintf( __( '%1$s saved.' ), $this->singular ),
			8  => sprintf( __( '%1$s submitted. <a target="_blank" href="%2$s">Preview %1$s</a>' ), $this->singular, esc_url( add_query_arg( 'preview', 'true', esc_url( get_permalink( $post_ID ) ) ) ) ),
			9  => sprintf(
				__( '%1$s scheduled for: <strong>%2$s</strong>. <a target="_blank" href="%3$s">Preview %1$s</a>' ),
				$this->singular,
				// translators: Publish box date format, see http://php.net/date
				date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ),
				esc_url( get_permalink( $post_ID ) )
			),
			10 => sprintf( __( '%1$s draft updated. <a target="_blank" href="%2$s">Preview %1$s</a>' ), $this->singular, esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);
		return $messages;
	}

	/**
	 * Filter CPT title entry placeholder text
	 *
	 * @since  1.0.0
	 * @param  string $title Original placeholder text
	 * @return string        Modifed placeholder text
	 */
	public function title( $title ) {
		return sprintf( __( '%s Title' ), $this->singular );
	}

	/**
	 * Change text for certain customizer strings four our custom version.
	 *
	 * @since  1.0.7
	 * @param  string $translated_text Input
	 * @return string                   Maybe modified text
	 */
	public function modify_text( $translated_text ) {
		switch ( $translated_text ) {
			case 'Excerpt':
				return sprintf( __( '%s Text' ), $this->singular );
			case 'Publish':
				return __( 'Send Notification', 'apppresser-push' );
		}
		return $translated_text;
	}

	/**
	 * Registers admin columns to display.
	 *
	 * @since  0.1.0
	 * @param  array $columns Array of registered column names/labels
	 * @return array           Modified array
	 */
	public function columns( $columns ) {

		$date = $columns['date'];
		unset( $columns['date'] );
		$columns[ $this->post_type . '_excerpt' ] = __( 'Excerpt' );
		$columns['date']                          = $date;

		return $columns;
	}

	/**
	 * Handles admin column excerpt display.
	 *
	 * @since  0.1.0
	 * @param  array $column Array of registered column names
	 */
	public function columns_display( $column ) {
		global $post;

		if ( $this->post_type . '_excerpt' === $column && ! empty( $post->post_excerpt ) ) {
			echo wpautop( $post->post_excerpt );
		}
	}

	/**
	 * Make excerpt column wider
	 *
	 * @since  1.0.0
	 */
	public function excerpt_css() {
		?>
		<style type="text/css"> .column-<?php echo $this->post_type; ?>_excerpt { width: 65%; } </style>
		<?php
	}

	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( isset( $screen->post_type ) && $screen->post_type == $this->post_type ) {
			wp_enqueue_style( 'apppush-admin-ui', AppPresser_Notifications::$plugin_url . 'css/appp-push-admin-ui.css', array(), AppPresser_Notifications::VERSION );
			wp_enqueue_script( 'apppush-admin-js', AppPresser_Notifications::$plugin_url . 'js/appp-push-admin.js', array( 'jquery', 'jquery-ui-autocomplete' ), AppPresser_Notifications::VERSION, true );
			
			// Check if notification was already sent
			$post_id = get_the_ID();
			$notification_sent = $post_id ? get_post_meta( $post_id, '_apppush_notification_sent', true ) : '';
			
			wp_localize_script( 'apppush-admin-js', 'apppush_local', array(
				'nonce'             => wp_create_nonce( 'apppush_nonce' ),
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'notification_sent' => ( $notification_sent === '1' ) ? '1' : '0',
			) );
		}
	}

	public function ajax_search_users() {
		check_ajax_referer( 'apppush_nonce', 'nonce' );
		$term = sanitize_text_field( $_GET['term'] );
		$users = new WP_User_Query( array(
			'search'         => '*' . $term . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
			'number'         => 20,
			'fields'         => array( 'ID', 'display_name', 'user_email' ),
		) );
		$results = array();
		foreach ( $users->get_results() as $user ) {
			$results[] = array(
				'id'   => $user->ID,
				'text' => $user->display_name . ' (' . $user->user_email . ')',
			);
		}
		wp_send_json_success( $results );
	}

	public function ajax_search_pages() {
		check_ajax_referer( 'apppush_nonce', 'nonce' );
		$term = sanitize_text_field( $_GET['term'] );
		$query = new WP_Query( array(
			's'              => $term,
			'post_type'      => array( 'post', 'page' ),
			'posts_per_page' => 20,
			'post_status'    => 'publish',
		) );
		$results = array();
		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'   => $post->ID,
				'text' => $post->post_title . ' (' . $post->post_type . ')',
				'url'  => get_permalink( $post->ID ),
			);
		}
		wp_send_json_success( $results );
	}

	public function ajax_get_recipient_count() {
		check_ajax_referer( 'apppush_nonce', 'nonce' );
		$type = sanitize_text_field( $_POST['type'] );
		$data = isset( $_POST['data'] ) ? $_POST['data'] : array();
		
		$args = array(
			'fields' => 'ID',
		);

		if ( $type === 'role' && ! empty( $data ) ) {
			$args['role__in'] = $data;
		} elseif ( $type === 'specific' && ! empty( $data ) ) {
			$args['include'] = $data;
		} elseif ( $type === 'all' ) {
			// No filter needed for all
		} elseif ( $type === 'segment' ) {
			// For segments, we can't easily count WP users unless we query the API or map them.
			// For now, return N/A or 0 for WP count.
			wp_send_json_success( array( 'total' => 'N/A (Segment)', 'valid' => 'N/A' ) );
			return;
		} elseif ( $type === 'memberpress' && ! empty( $data ) ) {
			// Get users with active MemberPress membership
			$membership_id = absint( $data );
			$user_ids = self::get_memberpress_membership_users( $membership_id );
			
			if ( empty( $user_ids ) ) {
				wp_send_json_success( array( 'total' => 0, 'valid' => 0 ) );
				return;
			}

			$total_count = count( $user_ids );

			// Valid (with device ARNs)
			$args['include'] = $user_ids;
			$args['meta_key'] = 'ap3_endpoint_arns';
			$args['meta_compare'] = 'EXISTS';
			$user_query_valid = new WP_User_Query( $args );
			$valid_count = $user_query_valid->get_total();

			wp_send_json_success( array( 'total' => $total_count, 'valid' => $valid_count ) );
			return;
		} elseif ( $type === 'rcp' && ! empty( $data ) ) {
			// Get users with active RCP membership level
			$level_id = absint( $data );
			$user_ids = self::get_rcp_membership_level_users( $level_id );
			
			if ( empty( $user_ids ) ) {
				wp_send_json_success( array( 'total' => 0, 'valid' => 0 ) );
				return;
			}

			$total_count = count( $user_ids );

			// Valid (with AP3 ARN)
			$args['include'] = $user_ids;
			$args['meta_key'] = 'ap3_endpoint_arns';
			$args['meta_compare'] = 'EXISTS';
			$user_query_valid = new WP_User_Query( $args );
			$valid_count = $user_query_valid->get_total();

			wp_send_json_success( array( 'total' => $total_count, 'valid' => $valid_count ) );
			return;
		}

		// Total Selected
		$user_query_total = new WP_User_Query( $args );
		$total_count = $user_query_total->get_total();

		// Valid (with AP3 ARN)
		$args['meta_key'] = 'ap3_endpoint_arns';
		$args['meta_compare'] = 'EXISTS';
		$user_query_valid = new WP_User_Query( $args );
		$valid_count = $user_query_valid->get_total();

		wp_send_json_success( array( 'total' => $total_count, 'valid' => $valid_count ) );
	}

	/**
	 * AJAX handler to get app pages from the App Config API
	 */
	public function ajax_get_app_pages() {
		check_ajax_referer( 'apppush_nonce', 'nonce' );

		$site_slug = appp_get_setting( 'ap3_site_slug' );
		$app_id    = appp_get_setting( 'ap3_app_id' );

		if ( empty( $site_slug ) || empty( $app_id ) ) {
			wp_send_json_error( array( 'message' => 'Missing site slug or app ID configuration.' ) );
			return;
		}

		// Build the API URL
		$api_domain = ( defined( 'MYAPPPRESSER_DEV_DOMAIN' ) ) ? MYAPPPRESSER_DEV_DOMAIN : 'https://myapppresser.com/';
		$api_url    = $api_domain . $site_slug . '/wp-json/ap4/apps/' . $app_id . '?auth=@ppp';

		$response = wp_remote_get( esc_url_raw( $api_url ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Failed to connect to the API: ' . $response->get_error_message() ) );
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['menus'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid API response or no menus found.' ) );
			return;
		}

		$pages = array();

		// Extract pages from side_menu, tab_menu, and aux_menu
		$menu_types = array( 'side_menu', 'tab_menu', 'aux_menu' );

		foreach ( $menu_types as $menu_type ) {
			if ( isset( $data['menus'][ $menu_type ]['items'] ) && is_array( $data['menus'][ $menu_type ]['items'] ) ) {
				foreach ( $data['menus'][ $menu_type ]['items'] as $item ) {
					if ( ! empty( $item['title'] ) && ! empty( $item['slug'] ) ) {
						$pages[] = array(
							'title'     => $item['title'],
							'slug'      => $item['slug'],
							'menu_type' => $menu_type,
						);
					}
				}
			}
		}

		if ( empty( $pages ) ) {
			wp_send_json_error( array( 'message' => 'No pages found in app menus.' ) );
			return;
		}

		wp_send_json_success( $pages );
	}

	/**
	 * Replace excerpt metabox
	 *
	 * @since  1.0.0
	 * @param  object $post Post object
	 */
	public function metabox_replace() {
		remove_meta_box( 'postexcerpt', $this->post_type, 'normal' );
		
		// 1. Content
		add_meta_box( 'apppush_content', __( 'Content', 'apppresser-push' ), array( $this, 'render_content_meta_box' ), $this->post_type, 'normal', 'high' );
		
		// 2. Click Action
		add_meta_box( 'apppush_action', __( 'Click Action', 'apppresser-push' ), array( $this, 'render_action_meta_box' ), $this->post_type, 'normal', 'high' );
		
		// 3. Audience
		add_meta_box( 'apppush_audience', __( 'Audience', 'apppresser-push' ), array( $this, 'render_audience_meta_box' ), $this->post_type, 'normal', 'high' );

		// 4. Preview (Side)
		add_meta_box( 'apppush_preview', __( 'Preview', 'apppresser-push' ), array( $this, 'render_preview_meta_box' ), $this->post_type, 'side', 'default' );
	}

	/**
	 * Rename Publish button to "Send Notification"
	 */


	/**
	 * Render Content Meta Box
	 */
	public function render_content_meta_box( $post ) {
		wp_nonce_field( 'push_text_description', 'apppush_nonce' );
		
		if ( ! appp_get_setting( 'notifications_ap3_key' ) ) {
			echo '<p><strong style="color:red">Warning!</strong> If you are using myapppresser.com to send push notifications, you are missing an <a href="' . admin_url( 'admin.php?page=apppresser_settings&tab=tab-appp-notifications' ) . '">API key</a>.</p>';
		}
		?>
		<div class="apppush-section">
			<p>
				<label for="apppush_notification_title" class="apppush-label"><strong>Notification Title <span class="required">(required)</span></strong></label>
				<input type="text" name="apppush_notification_title" id="apppush_notification_title" class="widefat apppush-input" value="<?php echo esc_attr( $post->post_title ); ?>" placeholder="Enter title..." required>
				<small class="apppush-help">Changes to this field will update the main post title.</small>
			</p>
			<p>
				<label for="excerpt" class="apppush-label"><strong>Notification Text <span class="required">(required)</span></strong></label>
				<textarea rows="3" cols="40" name="excerpt" id="excerpt" class="widefat apppush-input" placeholder="Enter notification message..." required><?php echo $post->post_excerpt; ?></textarea>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Click Action Meta Box
	 */
	public function render_action_meta_box( $post ) {
		$nofify_url_meta = get_post_meta( $post->ID, self::$notify_url_meta_key, true );
		?>
		<div class="apppush-section apppush-click-target">
			<div class="apppush-radio-group">
				<label><input type="radio" name="apppush_action" value="app" checked> Open App</label>
				<label><input type="radio" name="apppush_action" value="app_page"> Open App Page</label>
				<label><input type="radio" name="apppush_action" value="page"> Open to WordPress Post/Page</label>
				<label><input type="radio" name="apppush_action" value="url"> Open to Custom URL</label>
			</div>

			<div id="apppush-action-app-page" class="apppush-action-option" style="display:none;">
				<p class="apppush-subtitle">Select an App Page:</p>
				<select name="apppush_app_page" id="apppush_app_page" class="widefat apppush-input">
					<option value="">-- Loading app pages... --</option>
				</select>
				<p id="apppush-app-page-status" class="apppush-help"></p>
			</div>

			<div id="apppush-action-page" class="apppush-action-option" style="display:none;">
				<input type="text" id="apppush-page-search" class="widefat apppush-input" placeholder="Search for a page or post...">
				<input type="hidden" name="apppush_page_id" id="apppush_page_id">
				<p id="apppush-selected-page"></p>
			</div>

			<div id="apppush-action-url" class="apppush-action-option" style="display:none;">
				<input type="text" name="apppush_custom_url" id="apppush_custom_url" class="widefat apppush-input" value="<?php echo esc_attr( $nofify_url_meta ); ?>" placeholder="https://...">
			</div>
			<!-- Hidden field for the final URL to be saved/used -->
			<input type="hidden" name="notify_url" id="notify_url" value="<?php echo esc_attr( $nofify_url_meta ); ?>">
		</div>
		<?php
	}

	/**
	 * Render Audience Meta Box
	 */
	public function render_audience_meta_box( $post ) {
		$memberpress_active = self::is_memberpress_active();
		$rcp_active = self::is_rcp_active();
		?>
		<div class="apppush-section apppush-audience-selection">
			<div class="apppush-radio-group">
				<label><input type="radio" name="apppush_audience" value="all" checked> Everyone</label>
				<label><input type="radio" name="apppush_audience" value="role"> By Role</label>
				<label><input type="radio" name="apppush_audience" value="specific"> Specific Users</label>
				<?php if ( $memberpress_active ) : ?>
				<label><input type="radio" name="apppush_audience" value="memberpress"> By MemberPress Membership</label>
				<?php endif; ?>
				<?php if ( $rcp_active ) : ?>
				<label><input type="radio" name="apppush_audience" value="rcp"> By RCP Membership Level</label>
				<?php endif; ?>
				<label><input type="radio" name="apppush_audience" value="segment"> Segment</label>
			</div>

			<div id="apppush-audience-role" class="apppush-audience-option" style="display:none;">
				<p class="apppush-subtitle">Select Roles:</p>
				<div class="apppush-roles-list">
					<?php
					global $wp_roles;
					foreach ( $wp_roles->roles as $role_key => $role_data ) {
						echo '<label><input type="checkbox" name="apppush_roles[]" value="' . esc_attr( $role_key ) . '"> ' . esc_html( $role_data['name'] ) . '</label>';
					}
					?>
				</div>
			</div>

			<div id="apppush-audience-specific" class="apppush-audience-option" style="display:none;">
				<p class="apppush-subtitle">Search Users:</p>
				<input type="text" id="apppush-user-search" class="widefat apppush-input" placeholder="Type to search users...">
				<div id="apppush-selected-users"></div>
				<input type="hidden" name="apppush_specific_users" id="apppush_specific_users" value="">
			</div>

			<?php if ( $memberpress_active ) : ?>
			<div id="apppush-audience-memberpress" class="apppush-audience-option" style="display:none;">
				<p class="apppush-subtitle">Select Membership:</p>
				<select name="apppush_memberpress_membership" id="apppush_memberpress_membership" class="widefat apppush-input">
					<option value="">-- Select a Membership --</option>
					<?php
					$memberships = self::get_memberpress_memberships();
					foreach ( $memberships as $membership ) {
						echo '<option value="' . esc_attr( $membership->ID ) . '">' . esc_html( $membership->post_title ) . '</option>';
					}
					?>
				</select>
			</div>
			<?php endif; ?>

			<?php if ( $rcp_active ) : ?>
			<div id="apppush-audience-rcp" class="apppush-audience-option" style="display:none;">
				<p class="apppush-subtitle">Select Membership Level:</p>
				<select name="apppush_rcp_level" id="apppush_rcp_level" class="widefat apppush-input">
					<option value="">-- Select a Membership Level --</option>
					<?php
					$levels = self::get_rcp_membership_levels();
					foreach ( $levels as $level ) {
						echo '<option value="' . esc_attr( $level->id ) . '">' . esc_html( $level->name ) . '</option>';
					}
					?>
				</select>
			</div>
			<?php endif; ?>

			<div id="apppush-audience-segment" class="apppush-audience-option" style="display:none;">
				<?php self::get_segments_dropdown(); ?>
			</div>

			<div id="apppush-counts" class="apppush-counts">
				<p><strong>Total Selected Users: <span id="apppush-total-count">Calculating...</span></strong></p>
				<p><strong>Estimated Recipients (with App): <span id="apppush-valid-count">Calculating...</span></strong></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Check if MemberPress plugin is active
	 *
	 * @return bool
	 */
	public static function is_memberpress_active() {
		return class_exists( 'MeprProduct' ) || defined( 'MEPR_VERSION' );
	}

	/**
	 * Get all MemberPress memberships
	 *
	 * @return array Array of membership posts
	 */
	public static function get_memberpress_memberships() {
		$memberships = get_posts( array(
			'post_type'      => 'memberpressproduct',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		return $memberships ? $memberships : array();
	}

	/**
	 * Get users with active subscriptions to a MemberPress membership
	 *
	 * @param int $membership_id The membership product ID
	 * @return array Array of user IDs
	 */
	public static function get_memberpress_membership_users( $membership_id ) {
		global $wpdb;

		if ( empty( $membership_id ) ) {
			return array();
		}

		$membership_id = absint( $membership_id );

		// Query the MemberPress transactions table for active subscriptions
		$table_name = $wpdb->prefix . 'mepr_transactions';
		
		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			return array();
		}

		// Get users with active (complete) transactions for this membership
		// MemberPress statuses: 'complete', 'pending', 'failed', 'refunded'
		$sql = $wpdb->prepare(
			"SELECT DISTINCT user_id 
			FROM $table_name 
			WHERE product_id = %d 
			AND status = 'complete' 
			AND (expires_at = '0000-00-00 00:00:00' OR expires_at > NOW())",
			$membership_id
		);

		$user_ids = $wpdb->get_col( $sql );

		return array_map( 'absint', $user_ids );
	}

	/**
	 * Check if Restrict Content Pro plugin is active
	 *
	 * @return bool
	 */
	public static function is_rcp_active() {
		return class_exists( 'RCP_Memberships' ) || defined( 'RCP_PLUGIN_VERSION' ) || function_exists( 'rcp_get_membership_levels' );
	}

	/**
	 * Get all RCP membership levels
	 *
	 * @return array Array of membership level objects
	 */
	public static function get_rcp_membership_levels() {
		if ( ! self::is_rcp_active() ) {
			return array();
		}

		// Use RCP function if available
		if ( function_exists( 'rcp_get_membership_levels' ) ) {
			return rcp_get_membership_levels( array( 'status' => 'active' ) );
		}

		// Fallback to direct database query
		global $wpdb;
		$table_name = $wpdb->prefix . 'rcp_membership_levels';
		
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			return array();
		}

		$levels = $wpdb->get_results( "SELECT id, name FROM $table_name WHERE status = 'active' ORDER BY name ASC" );

		return $levels ? $levels : array();
	}

	/**
	 * Get users with active memberships at a specific RCP level
	 *
	 * @param int $level_id The membership level ID
	 * @return array Array of user IDs
	 */
	public static function get_rcp_membership_level_users( $level_id ) {
		global $wpdb;

		if ( empty( $level_id ) ) {
			return array();
		}

		$level_id = absint( $level_id );

		// Query the RCP memberships table for active memberships
		$table_name = $wpdb->prefix . 'rcp_memberships';
		
		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			return array();
		}

		// Get users with active memberships for this level
		// RCP statuses: 'active', 'pending', 'expired', 'cancelled'
		$sql = $wpdb->prepare(
			"SELECT DISTINCT user_id 
			FROM $table_name 
			WHERE object_id = %d 
			AND status = 'active'",
			$level_id
		);

		$user_ids = $wpdb->get_col( $sql );

		return array_map( 'absint', $user_ids );
	}

	/**
	 * Render Preview Meta Box (Side)
	 */
	public function render_preview_meta_box( $post ) {
		?>
		<div class="apppush-preview-container">
			<div class="phone-preview">
				<div class="phone-screen lockscreen">
					<div class="lockscreen-status-bar">
						<span class="status-time">9:41</span>
						<span class="status-icons">
							<span class="status-signal">
								<svg width="17" height="12" viewBox="0 0 17 12" fill="none">
									<rect x="0" y="8" width="3" height="4" rx="1" fill="white"/>
									<rect x="4.5" y="5" width="3" height="7" rx="1" fill="white"/>
									<rect x="9" y="2" width="3" height="10" rx="1" fill="white"/>
									<rect x="13.5" y="0" width="3" height="12" rx="1" fill="white"/>
								</svg>
							</span>
							<span class="status-wifi">
								<svg width="16" height="12" viewBox="0 0 16 12" fill="none">
									<path d="M8 3.5C10.5 3.5 12.7 4.5 14.3 6.1L15.7 4.7C13.7 2.7 11 1.5 8 1.5C5 1.5 2.3 2.7 0.3 4.7L1.7 6.1C3.3 4.5 5.5 3.5 8 3.5Z" fill="white"/>
									<path d="M8 6.5C9.7 6.5 11.2 7.2 12.3 8.3L13.7 6.9C12.2 5.4 10.2 4.5 8 4.5C5.8 4.5 3.8 5.4 2.3 6.9L3.7 8.3C4.8 7.2 6.3 6.5 8 6.5Z" fill="white"/>
									<circle cx="8" cy="10.5" r="1.5" fill="white"/>
								</svg>
							</span>
							<span class="status-battery">
								<span class="battery-icon"><span class="battery-level"></span></span>
							</span>
						</span>
					</div>
					<div class="lockscreen-header">Tuesday</div>
					<div class="lockscreen-time">9:41</div>
					<div class="lockscreen-date">September 12</div>
					<div class="notification-banner">
						<div class="notification-icon">
							<img src="<?php echo esc_url( AppPresser_Notifications::$plugin_url . '/img/favicon.ico' ); ?>" alt="App Icon">
						</div>
						<div class="notification-body">
							<div class="notification-header">
								<span class="notification-title">Notification Title</span>
								<span class="notification-time">now</span>
							</div>
							<div class="notification-message">Notification message will appear here...</div>
						</div>
					</div>
					<div class="lockscreen-footer">
						<div class="lockscreen-flashlight"></div>
						<div class="lockscreen-camera"></div>
					</div>
					<div class="lockscreen-home-indicator"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Deprecated: Old single meta box
	 */
	function post_excerpt_meta_box( $post ) {
		// Replaced by separate meta boxes above
	}

	public static function get_segments_dropdown( $segments = array(), $selected = '' ) {

		if ( empty( $segments ) ) {
			$segments = self::get_segments();
		}

		if ( ! empty( $segments ) ) {
			echo '<label>Send to segment: </label><br/>';
			echo '<select name="segments">';
			echo '<option value="">All subscribers</option>';
			foreach ( $segments as $key => $value ) {
				echo '<option value="' . $segments[ $key ]['arn'] . '"' . selected( $selected, $segments[ $key ]['arn'], false ) . '>' . $segments[ $key ]['name'] . '</option>';
			}
			echo '</select>';
		}
	}

	/**
	 * Get Push segments from API, if they exist
	 */
	public static function get_segments() {

		$segments = array();

		$notifications_api_key = appp_get_setting( 'notifications_ap3_key' );

		// Get Push segments from API, if they exist
		if ( ! empty( $notifications_api_key ) ) {

			$site_slug = appp_get_setting( 'ap3_site_slug' );
			$app_id    = appp_get_setting( 'ap3_app_id' );

			$my_server = ( defined( 'MYAPPPRESSER_DEV_DOMAIN' ) ) ? MYAPPPRESSER_DEV_DOMAIN : 'https://myapppresser.com/';

			$api = $my_server . $site_slug . '/wp-json/ap3/v1/app/' . $app_id . '?auth=@ppp';

			$response = wp_remote_get( esc_url_raw( $api ) );

			if ( ! is_wp_error( $response ) ) {

				$api_response = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( is_array( $api_response ) && isset( $api_response['segments'] ) ) {
					// v3 app
					$segments = $api_response['segments'];
				} elseif ( is_array( $api_response ) && isset( $api_response['config']['push']['segments'] ) ) {
					// v4 app
					$segments = $api_response['config']['push']['segments'];
				} else {
					$segments = array();
				}
			}
		}

		return $segments;
	}

	function save_post_meta( $post_id, $post ) {
		/* Verify the nonce before proceeding. */
		if ( ! isset( $_POST['apppush_nonce'] ) || ! wp_verify_nonce( $_POST['apppush_nonce'], 'push_text_description' ) ) {
			return $post_id;
		}

		/* Get the posted data and sanitize it for use as an HTML class. */
		$new_meta_value = ( isset( $_POST['notify_url'] ) ) ? $_POST['notify_url'] : '';


		/* Get the meta key. */
		$meta_key = self::$notify_url_meta_key;

		/* Get the meta value of the custom field key. */
		$meta_value = get_post_meta( $post_id, $meta_key, true );

		/* If a new meta value was added and there was no previous value, add it. */
		if ( $new_meta_value && $meta_value == '' ) {
			add_post_meta( $post_id, $meta_key, $new_meta_value, true );
		}

		/* If the new meta value does not match the old value, update it. */
		elseif ( $new_meta_value && $new_meta_value != $meta_value ) {
			update_post_meta( $post_id, $meta_key, $new_meta_value );
		}

		/* If there is no new meta value but an old value exists, delete it. */
		elseif ( $new_meta_value == '' && $meta_value ) {
			delete_post_meta( $post_id, $meta_key, $meta_value );
		}

		// Save Audience Selection
		if ( isset( $_POST['apppush_audience'] ) ) {
			update_post_meta( $post_id, 'apppush_audience', sanitize_text_field( $_POST['apppush_audience'] ) );
		}

		if ( isset( $_POST['apppush_roles'] ) ) {
			// Sanitize array of roles
			$roles = array_map( 'sanitize_text_field', $_POST['apppush_roles'] );
			update_post_meta( $post_id, 'apppush_roles', $roles );
		} else {
			delete_post_meta( $post_id, 'apppush_roles' );
		}

		if ( isset( $_POST['apppush_specific_users'] ) ) {
			update_post_meta( $post_id, 'apppush_specific_users', sanitize_text_field( $_POST['apppush_specific_users'] ) );
		} else {
			delete_post_meta( $post_id, 'apppush_specific_users' );
		}

		if ( isset( $_POST['apppush_memberpress_membership'] ) ) {
			update_post_meta( $post_id, 'apppush_memberpress_membership', absint( $_POST['apppush_memberpress_membership'] ) );
		} else {
			delete_post_meta( $post_id, 'apppush_memberpress_membership' );
		}

		if ( isset( $_POST['apppush_rcp_level'] ) ) {
			update_post_meta( $post_id, 'apppush_rcp_level', absint( $_POST['apppush_rcp_level'] ) );
		} else {
			delete_post_meta( $post_id, 'apppush_rcp_level' );
		}
	}
}
