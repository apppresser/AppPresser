<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * AppLMS Functions
 *
 * @since       0.1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require the JWT library
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if ( ! class_exists( 'AppLMS_Functions' ) ) {

	/**
	 * AppLMS_Functions class
	 *
	 * @since       0.2.0
	 */
	class AppLMS_Functions {

		/**
		 * @var         AppLMS_Functions $instance The one true AppLMS_Functions
		 * @since       0.2.0
		 */
		private static $instance;
		public static $errorpath = '../php-error-log.php';
		public static $active    = array();
		// sample: error_log("meta: " . $meta . "\r\n",3,self::$errorpath);

		/**
		 * Get active instance
		 *
		 * @access      public
		 * @since       0.2.0
		 * @return      object self::$instance The one true AppLMS_Functions
		 */
		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance = new AppLMS_Functions();

				self::$instance->hooks();

			}

			return self::$instance;
		}


		/**
		 * Include necessary files
		 *
		 * @access      private
		 * @since       0.2.0
		 * @return      void
		 */
		private function hooks() {

			// only do this stuff in the app so we don't affect the desktop site
			if ( AppPresser::is_min_ver( 3 ) ) {

				add_action( 'wp_enqueue_scripts', array( $this, 'scripts_styles' ) );
				// add_filter( 'learndash_content_access', array( $this, 'no_access_message' ), 5, 2 );
				add_filter( 'learndash_payment_button', array( $this, 'payment_button' ), 5, 2 );

				add_filter( 'learndash_previous_post_link', array( $this, 'previous_post_link' ), 10, 4 );
				add_filter( 'learndash_next_post_link', array( $this, 'next_post_link' ), 10, 4 );

				add_filter( 'learndash_quiz_continue_link', array( $this, 'quiz_continue' ), 5, 2 );

				add_filter( 'learndash_mark_complete', array( $this, 'mark_complete_btn' ), 10, 2 );

				add_filter( 'ld_course_list_shortcode_attr_defaults', array( $this, 'course_list_shortcode_atts' ) );

				add_filter( 'learndash_login_modal', '__return_false' );

				add_filter( 'learndash-lesson-row-class', array( $this, 'add_lesson_classes' ), 99, 2 );

				add_filter( 'learndash_quiz_row_classes', array( $this, 'add_quiz_classes' ), 99, 3 );

			}

			// fix ajax authentication for quizzes
			add_action( 'admin_init', array( $this, 'ajax_set_user' ) );

			add_action( 'wp_ajax_applms_mark_complete', array( $this, 'applms_mark_complete' ) );
			add_action( 'wp_ajax_nopriv_applms_mark_complete', array( $this, 'applms_mark_complete' ) );

			add_action( 'wp_ajax_applms_course_progress', array( $this, 'get_course_progress' ) );

			// fix registration form
			if ( class_exists( 'Theme_My_Login' ) ) {
				remove_action( 'login_init', 'send_frame_options_header', 10 );
			}

			if ( defined( 'LEARNDASH_DEFAULT_THEME' ) && LEARNDASH_DEFAULT_THEME === 'ld30' ) {
				add_filter( 'learndash_30_get_template_part', array( $this, 'ld30_templates_load' ), 10, 4 );
			}
		}

		public function add_lesson_classes( $lesson_class, $lesson ) {
			$lesson_class .= ' push-page ';
			return $lesson_class;
		}

		public function add_quiz_classes( $classes, $quiz, $context ) {
			$classes['anchor'] .= ' push-page ';
			return $classes;
		}

		// load LD30 templates for push-page link classes
		public function ld30_templates_load( $filepath, $slug, $args, $echo ) {

			if ( ! strpos( $slug, 'partials/row' ) ) {
				return $filepath;
			} else {
				return AppLMS_DIR . 'learndash/ld30/templates/' . $slug . '.php';
			}
		}

		public function get_course_progress() {

			check_ajax_referer( 'applms-nonce', 'nonce' );

			if ( empty( $_POST['course_id'] ) || empty( $_POST['user_id'] ) ) {
				wp_send_json_error( 'Missing required fields.' );
			}

			$course_id = $_POST['course_id'];
			$user_id   = $_POST['user_id'];

			$course_progress = get_user_meta( $user_id, '_sfwd-course_progress', true );

			$percentage = 0;
			$message    = '';

			if ( ! empty( $course_progress ) && ! empty( $course_progress[ $course_id ] ) && ! empty( $course_progress[ $course_id ]['total'] ) ) {
				$completed = intVal( $course_progress[ $course_id ]['completed'] );
				$total     = intVal( $course_progress[ $course_id ]['total'] );

				if ( $completed == $total - 1 ) {
					learndash_update_completion( $user_id );
					$course_progress = get_user_meta( $user_id, '_sfwd-course_progress', true );
					$completed       = intVal( $course_progress[ $course_id ]['completed'] );
					$total           = intVal( $course_progress[ $course_id ]['total'] );
				}

				$percentage = intVal( $completed * 100 / $total );
				$percentage = ( $percentage > 100 ) ? 100 : $percentage;
				$message    = sprintf( esc_html_x( '%1$d out of %2$d steps completed', 'placeholder: completed steps, total steps', 'learndash' ), $completed, $total );
			} else {
				wp_send_json_error( $course_progress );
			}

			$progress = array(
				'percentage' => @$percentage,
				'completed'  => @$completed,
				'total'      => @$total,
			);

			wp_send_json_success( $progress );
		}

		// allow "cols" attribute on course list shortcode
		public function course_list_shortcode_atts( $atts ) {
			$atts['cols'] = '1';
			return $atts;
		}

		public function image_sizes( $post ) {

			$featured_id = get_post_thumbnail_id( $post['id'] );

			$sizes = wp_get_attachment_metadata( $featured_id );

			$size_data = new stdClass();

			if ( ! empty( $sizes['sizes'] ) ) {

				foreach ( $sizes['sizes'] as $key => $size ) {
					// Use the same method image_downsize() does
					$image_src = wp_get_attachment_image_src( $featured_id, $key );

					if ( ! $image_src ) {
						continue;
					}

					$size_data->$key = $image_src[0];

				}
			}

			return $size_data;
		}

		/*
		 * Deprecated in favor of native LD api at wp-json/ldlms/v1/courses
		 */
		public function learndash_to_api() {
			global $wp_post_types;

			$post_types = array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic' );

			foreach ( $post_types as $post_type ) {

				$wp_post_types[ $post_type ]->show_in_rest = true;
				// Optionally customize the rest_base or controller class
				$wp_post_types[ $post_type ]->rest_base             = $post_type;
				$wp_post_types[ $post_type ]->rest_controller_class = 'WP_REST_Posts_Controller';
			}
		}

		public function mark_complete_btn( $return, $post ) {

			$post_meta = get_post_meta( $post->ID );

			// var_dump( $post_meta );

			if ( isset( $post_meta['_sfwd-topic'] ) && $post_meta['_sfwd-topic'] && strpos( $post_meta['_sfwd-topic'][0], '"sfwd-topic_lesson_video_auto_complete";s:2:"on"' )
				||
				isset( $post_meta['_sfwd-lessons'] ) && $post_meta['_sfwd-lessons'] && strpos( $post_meta['_sfwd-lessons'][0], '"sfwd-lessons_lesson_video_auto_complete";s:2:"on"' )
			) {
				// this is a video progression, we need to show default button for auto complete to work
				return $return;
			}

			if ( $post->post_type === 'sfwd-lessons' ) {
				$show = $this->show_mark_lesson_complete( $post->ID, $course_id );
				if ( ! $show ) {
					return $return;
				}
			}

			echo '<button 
                class="button button-primary applms-mark-complete" 
                data-postid="' . $post->ID . '" 
                data-courseid="' . learndash_get_course_id( $post->ID ) . '"
                data-lessonid="' . learndash_get_lesson_id( $post->ID ) . '"
                data-userid="' . get_current_user_id() . '"
                data-posttype="' . get_post_type( $post->ID ) . '"
                >
                    ' . esc_html__( 'Mark Complete', 'applms' ) . '
                    <span class="applms-completed-checkmark"><i class="icon ion-checkmark"></i></span>
                    <ion-spinner icon="crescent" class="spinner spinner-crescent"><svg viewBox="0 0 64 64"><g><circle stroke-width="4" stroke-dasharray="128" stroke-dashoffset="82" r="26" cx="32" cy="32" fill="none" transform="rotate(74.4226 32 32)"><animateTransform values="0,32,32;360,32,32" attributeName="transform" type="rotate" repeatCount="indefinite" dur="750ms"></animateTransform></circle></g></svg></ion-spinner>
            </button>
            ';
		}

		public function applms_mark_complete() {

			check_ajax_referer( 'applms-nonce', 'nonce' );

			if ( empty( $_POST['post_id'] ) || empty( $_POST['user_id'] ) ) {
				wp_send_json_error( 'Missing required fields.' );
			}

			$post_id   = $_POST['post_id'];
			$user_id   = $_POST['user_id'];
			$post_type = $_POST['post_type'];
			$course_id = $_POST['course_id'];
			$lesson_id = $_POST['lesson_id'];

			$res = learndash_process_mark_complete( $user_id, $post_id, false, $course_id );

			if ( $res === false ) {
				wp_send_json_error( 'Unknown error.' );
			}

			$completion = $this->is_anything_completed( $post_id, $user_id, $post_type, $lesson_id, $course_id );

			wp_send_json_success( $completion );
		}

		// find out if we have just completed a course or a lesson, so we know where to redirect user in the app.
		public function is_anything_completed( $post_id, $user_id, $post_type, $lesson_id, $course_id ) {

			// figure out what lesson ID is, then check if it's complete
			if ( $post_type === 'sfwd-topic' ) {

				if ( empty( $course_id ) ) {
					$course_id = learndash_get_course_id( $post_id );
				}

				if ( empty( $lesson_id ) ) {
					$lesson_id = learndash_get_lesson_id( $post_id );
				}

				// last we check if the last topic has just been completed
				if ( $this->all_topics_complete( $post_id ) ) {
					return 'Topics completed.';
				} else {
					$message = 'Topic completed.';
				}

			} elseif ( $post_type === 'sfwd-quiz' ) {

				if ( empty( $lesson_id ) ) {
					$lesson_id = get_post_meta( $post_id, '_sfwd-quiz', 1 )['sfwd-quiz_lesson'];
				}

				if ( empty( $course_id ) ) {
					$course_id = get_post_meta( $post_id, '_sfwd-quiz', 1 )['sfwd-quiz_course'];
				}

				$message = 'Quiz completed.';

			} elseif ( $post_type === 'sfwd-lessons' ) {

				if ( empty( $lesson_id ) ) {
					$lesson_id = $post_id;
				}

				if ( empty( $course_id ) ) {
					$course_id = get_post_meta( $post_id, '_sfwd-lessons', 1 )['sfwd-lessons_course'];
				}

				$message = 'Lesson completed.';

			} else {
				$message = 'Invalid post type.';
			}


			return $message;
		}

		// if all quizzes completed, we can show mark complete button
		// from sfwd-lms/includes/class-ld-cpt-instance.php 477
		public function show_mark_lesson_complete( $post_id, $course_id ) {

			$all_quizzes_completed = false;
			$post                  = get_post( $post_id );

			$quizzes = learndash_get_lesson_quiz_list( $post, null, $course_id );
			$quizids = array();

			if ( ! empty( $quizzes ) ) {
				foreach ( $quizzes as $quiz ) {
					$quizids[ $quiz['post']->ID ] = $quiz['post']->ID;
				}
			}

			if ( ! empty( $quizids ) ) {
				$all_quizzes_completed = ! learndash_is_quiz_notcomplete( null, $quizids, false, $course_id );
			} else {
				$all_quizzes_completed = true;
			}

			return $all_quizzes_completed;
		}

		// utility to check if a course is completed
		public function is_course_completed( $course_id, $user_id ) {

			$course_progress = get_user_meta( $user_id, '_sfwd-course_progress', true );

			if ( ! empty( $course_progress ) && ! empty( $course_progress[ $course_id ] ) && ! empty( $course_progress[ $course_id ]['total'] ) ) {
				$completed = intVal( $course_progress[ $course_id ]['completed'] );
				$total     = intVal( $course_progress[ $course_id ]['total'] );

				if ( $completed === $total ) {
					return true;
				}
			}

			return false;
		}

		public function quiz_continue( $returnLink, $url ) {
			global $post;

			return '<button class="button button-primary applms-mark-complete" data-postid="' . $post->ID . '" data-courseid="' . learndash_get_course_id( $post->ID ) . '" data-lessonid="' . learndash_get_lesson_id( $post->ID ) . '" data-userid="' . get_current_user_id() . '" data-posttype="' . get_post_type( $post->ID ) . '">' . esc_html__( 'Continue', 'applms' ) . ' <span class="applms-completed-checkmark"><i class="icon ion-checkmark"></i></span><ion-spinner icon="crescent" class="spinner spinner-crescent"><svg viewBox="0 0 64 64"><g><circle stroke-width="4" stroke-dasharray="128" stroke-dashoffset="82" r="26" cx="32" cy="32" fill="none" transform="rotate(74.4226 32 32)"><animateTransform values="0,32,32;360,32,32" attributeName="transform" type="rotate" repeatCount="indefinite" dur="750ms"></animateTransform></circle></g></svg></ion-spinner></button>';
		}

		// find out if all topics have been completed
		public function all_topics_complete( $post_id ) {

			$progress = learndash_get_course_progress( null, $post_id );

			$complete = true;
			foreach ( $progress['posts'] as $key => $value ) {
				if ( ! $value->completed ) {
					$complete = false;
				}
			}

			return $complete;
		}

		public function previous_post_link( $markup, $permalink, $link_name, $post ) {
			return '<a href="' . $permalink . '" class="prev-link" rel="prev"><i class="icon ion-chevron-left"></i> ' . $link_name . '</a>';
		}

		public function next_post_link( $markup, $permalink, $link_name, $post ) {
			return '<a href="' . $permalink . '" class="next-link" rel="prev">' . $link_name . ' <i class="icon ion-chevron-right"></i></a>';
		}

		public function payment_button( $button, $params ) {

			if ( isset( $params['course_price_type'] ) && $params['course_price_type'] === 'free' || isset( $params['price'] ) && $params['price'] === 'free' ) {
				return $button;
			}
			echo '<strong>' . esc_html__( 'You do not have access to this course.', 'applms' ) . '</strong>';
			return null;
		}

		/**
		 * What happens to disallowed user
		 *
		 * @since       0.1.0
		 * @return      array
		 */
		public function no_access_message( $restriction_message, $post ) {
			return esc_html__( 'Please log in to access.', 'applms' );
		}

		/**
		 * Load scripts
		 *
		 * @since       0.1.0
		 * @return      void
		 */
		public function scripts_styles( $hook ) {

			global $post;

			// Use minified libraries if SCRIPT_DEBUG is turned off
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			wp_enqueue_script( 'applms-js', AppLMS_URL . 'assets/js/applms' . $suffix . '.js', array( 'jquery' ), AppLMS_VER, true );
			wp_enqueue_style( 'applms-css', AppLMS_URL . 'assets/css/applms-frontend' . $suffix . '.css', null, AppLMS_VER );

			if ( defined( 'LEARNDASH_DEFAULT_THEME' ) && LEARNDASH_DEFAULT_THEME === 'ld30' ) {
				// add new styling
				wp_enqueue_style( 'applms-ld30-css', AppLMS_URL . 'assets/css/applms-ld30' . $suffix . '.css', null, AppLMS_VER );
			}

			// get our redirect urls based on post type
			if ( isset( $post, $post->post_type, $post->ID ) ) {

				$type    = $post->post_type;
				$post_id = $post->ID;

				switch ( $type ) {
					case 'sfwd-topic':
						$course_id                       = learndash_get_course_id( $post_id );
						$lesson_id                       = learndash_get_lesson_id( $post_id );
						$quiz_link                       = $this->get_quiz_link( $post_id, $lesson_id );
						$next_link                       = $this->get_next_lesson_link( $post );
						$lesson_complete_topics_complete = $this->get_next_lesson_link( get_post( $lesson_id ) );
						break;
					case 'sfwd-lessons':
						$course_id = get_post_meta( $post_id, '_sfwd-lessons', 1 )['sfwd-lessons_course'];
						$next_link = $this->get_next_lesson_link( $post );
						$lesson_id = $post_id;
						break;
					case 'sfwd-quiz':
						$lesson_id = get_post_meta( $post_id, '_sfwd-quiz', 1 )['sfwd-quiz_lesson'];
						$course_id = get_post_meta( $post_id, '_sfwd-quiz', 1 )['sfwd-quiz_course'];
						$next_link = $this->get_next_lesson_link( $post );
						break;
				}

				$args = array(
					'nonce'           => wp_create_nonce( 'applms-nonce' ),
					'user_id'         => get_current_user_id(),
					'lessonCompleted' => __( 'Lesson completed!', 'applms' ),
					'courseCompleted' => __( 'Course completed!', 'applms' ),
					'topicsCompleted' => __( 'Topics completed!', 'applms' ),
				);

				if ( isset( $course_id ) ) {

					$course_link = get_permalink( $course_id );

					$args['lessonCompleteRedirectQuiz'] = apply_filters( 'applms_lesson_complete_redirect_quiz', $course_link, $course_id, $lesson_id );

					$args['courseCompleteRedirect'] = apply_filters( 'applms_course_complete_redirect', $course_link, $course_id, $lesson_id );

					if ( isset( $quiz_link ) ) {
						$args['topicsCompleteRedirect'] = apply_filters( 'applms_topics_complete_redirect', $quiz_link, $course_id, $lesson_id );
					}

					if ( isset( $next_link ) ) {
						$args['lessonCompleteRedirect'] = apply_filters( 'applms_lesson_complete_redirect', $next_link, $course_id, $lesson_id );

						$args['nextTopic'] = apply_filters( 'applms_next_topic_redirect', $next_link, $course_id, $lesson_id );

						$post_class                = get_post( $post_id );
						$next_item_id              = learndash_next_post_link( '', 'id', $post_class );
						$args['nextItemId']        = $next_item_id;
						$args['nextItemType']      = get_post_type( $args['nextItemId'] );
						$data['nextItemPermalink'] = get_permalink( $data['nextItemId'] );
					}

					if ( isset( $lesson_complete_topics_complete ) ) {
						$args['lessonCompleteRedirectTopic'] = apply_filters( 'applms_lesson_complete_redirect_topic', $lesson_complete_topics_complete, $course_id, $lesson_id );
					}
				}

				wp_localize_script( 'applms-js', 'applms', $args );

			} // end if $post...
		}

		// get link to next lesson when on lesson page
		public function get_next_lesson_link( $post ) {
			$next_link = learndash_next_post_link( '', true, $post );

			if ( $next_link ) {
				return $next_link;
			}

			return false;
		}

		// get link to quiz from topic page, used for redirects
		public function get_quiz_link( $post_id, $lesson_id ) {

			$lesson_quizzes = learndash_get_lesson_quiz_list( $lesson_id );

			if ( ! empty( $lesson_quizzes ) ) {

				// If we have some lesson quizzes we loop through these to find the first one not completed by the user.
				// This should be the first one but we don't want to assume.
				foreach ( $lesson_quizzes as $lesson_quiz ) {
					if ( $lesson_quiz['status'] == 'notcompleted' ) {
						// Once we find a non-completed quiz we set the $link to the quiz
						// permalink then break out of out loop
						$quiz_link = $lesson_quiz['permalink'];
						return $quiz_link;
						break;
					}
				}
			}

			return false;
		}

		// Authenticate AJAX requests (for quizzes)
		public function ajax_set_user() {

			// this function is from a standalone plugin we gave some customers. Don't do this twice if that plugin is active.
			if ( function_exists( 'appGetUserIdFromToken' ) ) {
				return;
			}

			if ( class_exists( 'Jwt_Auth_Public' ) ) {
				if ( isset( $_REQUEST['app_token'] ) ) {

					$userId = self::get_user_from_token( $_REQUEST['app_token'] );
					// Login the user that we retrieved from token, if exists
					if ( $userId ) {
						wp_set_current_user( $userId );
					}
				}
			}
		}

		public static function get_user_from_token( $token ) {
			// Get the Secret Key
			$secretKey = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : appp_get_setting( 'ap3_jwt_secret_key' );
			if ( $secretKey ) {
				try {
					// Decode the token
					$token = JWT::decode( $token, new Key( $secretKey, apply_filters( 'jwt_auth_algorithm', 'HS256' ) ) );
					if ( $token->iss === get_bloginfo( 'url' ) ) {
						if ( isset( $token->data->user->id ) ) {
							return $token->data->user->id;
						}
					}
				} catch ( Exception $e ) {
					// echo $e->getMessage();
				}
			}

			return false; // No user where found in the given token
		}
	}

	$AppLMS_Functions = new AppLMS_Functions();
	$AppLMS_Functions->instance();

} // end class_exists check
