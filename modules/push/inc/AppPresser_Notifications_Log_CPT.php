<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class AppPresser_Notifications_Log_CPT {

	public $singular;
	public $plural;
	public $post_type;
	public $log;
	public $labels;
	public $args;

	public function __construct() {

		$this->log       = new AppPresser_Notifications_Log();
		$this->singular  = __( 'Notification Log', 'apppresser-push' );
		$this->plural    = __( 'Notification Logs', 'apppresser-push' );
		$this->post_type = 'apppush-log';

		$this->labels = array(
			'name'               => $this->plural,
			'singular_name'      => $this->singular,
			'add_new'            => sprintf( __( 'Add New %s' ), $this->singular ),
			'add_new_item'       => sprintf( __( 'Add New %s' ), $this->singular ),
			'edit_item'          => sprintf( __( '%s Details' ), $this->singular ),
			'new_item'           => sprintf( __( 'New %s' ), $this->singular ),
			'all_items'          => $this->plural,
			'view_item'          => sprintf( __( 'View %s' ), $this->singular ),
			'search_items'       => sprintf( __( 'Search %s' ), $this->plural ),
			'not_found'          => sprintf( __( 'No %s' ), $this->plural ),
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash' ), $this->plural ),
			'parent_item_colon'  => null,
			'menu_name'          => 'Logs',
		);

		$this->args = array(
			'labels'            => $this->labels,
			'public'            => false,
			'show_in_rest'      => false,
			'show_ui'           => true,
			'show_in_menu'      => ( $this->log->enabled ) ? 'apppresser_settings' : false,
			'show_in_nav_menus' => false,
			'show_in_admin_bar' => false,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'appppush-log' ),
			'capability_type'   => 'post',
			'hierarchical'      => false,
			'menu_position'     => 1,
			'supports'          => array( 'title', 'excerpt', 'editor' ),
		);
	}

	public function hooks() {

		add_action( 'init', array( $this, 'register_cpt' ) );
		add_filter( 'manage_edit-' . $this->post_type . '_columns', array( $this, 'columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'columns_display' ) );
		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'remove_excerpt_metabox' ) );
		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'metabox' ) );
		add_action( 'add_meta_boxes_' . $this->post_type, array( $this, 'mv_from_post_type' ) );
		add_filter( 'post_row_actions', array( $this, 'change_row_actions' ), 10, 2 );
		add_action( 'in_admin_header', array( $this, 'disable_new_posts' ) );
	}

	public function change_row_actions( $actions, $post ) {

		if ( $post->post_type == $this->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
			unset( $actions['view'] );
			if ( isset( $actions['edit'] ) ) {
				$actions['edit'] = str_replace( 'Edit', __( 'View Log Details', 'apppresser-push' ), $actions['edit'] );
			}
		}

		return $actions;
	}

	/**
	 * Hide items from the edit screen. We don't want to edit the log;
	 * we want to preserve it.
	 */
	public function mv_from_post_type() {

		// Remove the content editor
		remove_post_type_support( $this->post_type, 'editor' );

		// Remove the excerpt
		remove_post_type_support( $this->post_type, 'excerpt' );

		// Remove the title text
		remove_post_type_support( $this->post_type, 'title' );

		// Hide the publish box
		remove_meta_box( 'submitdiv', $this->post_type, 'side' );
	}

	/**
	 * Register notification log Custom Post Type
	 *
	 * @since  1.0.0
	 */
	public function register_cpt() {
		register_post_type( $this->post_type, $this->args );
	}

	public function push_log() {
	}

	public function remove_excerpt_metabox() {
		remove_meta_box( 'postexcerpt', $this->post_type, 'normal' );
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

		$columns[ $this->post_type . '_success' ] = __( 'Success', 'apppresser-push' );
		$date                                     = $columns['date'];
		unset( $columns['date'] );
		$columns[ $this->post_type . '_segment' ]  = __( 'Segment', 'apppresser-push' );
		$columns[ $this->post_type . '_posttype' ] = __( 'Post type', 'apppresser-push' );
		$columns['date']                           = $date;

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

		if ( $column == 'apppush-log_segment' ) {
			$segment = get_post_meta( $post->ID, 'segment', true );

			if ( $segment ) {
				echo $segment;
			}
		} elseif ( $column == 'apppush-log_posttype' ) {

			// post_type of the message sent on this log
			$post_type = get_post_meta( $post->ID, 'post_type', true );
			if ( $post_type ) {
				echo $post_type;
			}
		} elseif ( $column == 'apppush-log_success' ) {

			// post_type of the message sent on this log
			$success = get_post_meta( $post->ID, 'success', true );
			if ( $success ) {
				echo '<span style="color:green">yes</span>';
			} else {
				echo '<span style="color:red">no</span>';
			}
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

	/**
	 * Replace excerpt metabox
	 *
	 * @since  1.0.0
	 * @param  object $post Post object
	 */
	public function metabox() {
		add_meta_box( 'notificationexcerpt', sprintf( __( '%s Text' ), $this->singular ), array( $this, 'post_excerpt_meta_box' ), $this->post_type, 'normal', 'core' );
	}

	/**
	 * Display a table containing a logged item
	 *
	 * @since 1.0.0
	 * @param object $post
	 */
	function post_excerpt_meta_box( $post ) {

		$meta = get_post_meta( $post->ID );

		?>
		<div class="inside">
			<style type="text/css">
				#post-body-content { display: none; }
				#notification-log-metabox th { text-align: right; }
				#notification-log-metabox tr:nth-child(odd) { background-color:#DDD; }
				#notification-log-metabox th, #notification-log-metabox td { padding: 3px 6px; }
			</style>
			<script type="text/javascript">
				jQuery('.page-title-action').hide();
			</script>
			<table id="notification-log-metabox">
			<tr><th>Success</th><td><?php echo ( $this->get_meta( $meta, 'success' ) == '1' ) ? '<span style="color:green">yes' : '<span style="color:red">no'; ?></span></td></tr>
			<tr><th>Title:</th><td><?php echo $post->post_title; ?></td></tr>
			<tr><th>Message:</th><td><?php echo $post->post_content; ?></td></tr>
			<tr><th>App id:</th><td><?php $this->the_meta( $meta, 'id' ); ?></td></tr>
			<tr><th>API key:</th><td><?php $this->the_meta( $meta, 'key' ); ?></td></tr>
			<tr><th>Custom app page (slug):</th><td><?php $this->the_meta( $meta, 'page' ); ?></td></tr>
			<tr><th>Custom URL:</th><td><?php $this->the_meta( $meta, 'url' ); ?></ptd></tr>
			<?php if ( $this->get_meta( $meta, 'url' ) ) : ?>
				<tr><th>URL target:</th><td><?php $this->the_meta( $meta, 'target' ); ?></td></tr>
			<?php endif; ?>
			<tr><th>Segment:</th><td><?php $this->the_meta( $meta, 'segment' ); ?></td></tr>
			<?php if ( $this->get_meta( $meta, 'post_type' ) ) : ?>
				<tr><th>Post type:</th><td><?php $this->the_meta( $meta, 'post_type' ); ?></td></tr>
			<?php endif; ?>
			<tr><th>Device arns:</th><td>
			<?php
				$device_arns = $this->get_meta( $meta, 'device_arns' );
				echo $this->show_device_arns( $device_arns );
			?>
			</td></tr>
			<tr><th>Version:</th><td><?php $this->the_meta( $meta, 'version' ); ?></td></tr>
			<tr><th>API send endpoint:</th><td><?php $this->the_meta( $meta, 'send_endpoint' ); ?></td></tr>
			<?php
				$re  = '/s:[0-9]*:"?|Sending: "(.*)";";/';
				$str = str_replace( '""', '"', $this->get_meta( $meta, 'response' ) );

				preg_match_all( $re, $str, $matches, PREG_SET_ORDER, 0 );

			if ( isset( $matches, $matches[2], $matches[2][1] ) ) :
				?>
			<tr><th>API response:</th><td><?php echo 'Sending: "' . $matches[2][1]; ?></td></tr>
			<?php else : ?>
			<tr><th>Raw API response:</th><td><textarea rows="10" style="max-width:100%;width:100%;" readonly="readonly"><?php $this->the_meta( $meta, 'response' ); ?></textarea></td></tr>
			<?php endif; ?>
			</table>
		</div>
		<?php
	}

	public function the_meta( $meta, $field ) {
		echo $this->get_meta( $meta, $field );
	}

	public function get_meta( $meta, $field ) {
		if ( isset( $meta[ $field ], $meta[ $field ][0] ) ) {
			return $meta[ $field ][0];
		} else {
			return '';
		}
	}

	public function show_device_arns( $device_arns ) {

		$html = '';

		if ( $device_arns ) {

			$device_arns = unserialize( $device_arns );

			if ( is_array( $device_arns ) ) {

				$loop = array(
					'current' => 0,
					'max'     => 5,
				);

				foreach ( $device_arns as $arn ) {
					if ( $loop['current'] <= $loop['max'] ) {
						$html .= $arn . '<br>';
						++$loop['current'];
					} else {

						$remaining = count( $device_arns ) - $loop['max'];

						$html .= '<b>' . $remaining . ' more</b>';
						break;
					}
				}
			}
		}

		return $html;
	}

	function disable_new_posts() {
		// Hide link on listing page
		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] == $this->post_type ) {
			echo '<style type="text/css">
			a.page-title-action { display:none; }
			</style>';
		}
	}
}
