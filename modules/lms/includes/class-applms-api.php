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

if ( ! class_exists( 'AppLMS_API' ) ) {

	/**
	 * AppLMS_API class
	 *
	 * @since       0.2.0
	 */
	class AppLMS_API {

		/**
		 * @var         AppLMS_API $instance The one true AppLMS_API
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
		 * @return      object self::$instance The one true AppLMS_API
		 */
		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance = new AppLMS_API();

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

			// add courses to the API
			add_action( 'init', array( $this, 'learndash_to_api' ), 25 );

			add_action( 'rest_api_init', array( $this, 'api_endpoints_fields' ) );

			add_action( 'learndash_rest_archive_bypass', array( $this, 'allow_questions_to_be_read_from_users_with_read_capability' ), 10, 2 );
		}

		public function api_endpoints_fields() {

			register_rest_route(
				'appp',
				'learndash/courses',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_all_courses' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'appp',
				'learndash/course/(?P<id>\d+)',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_course' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'appp',
				'learndash/course/(?P<id>\d+)/enroll',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'course_enroll' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				)
			);

			register_rest_route(
				'appp',
				'learndash/user/(?P<id>\d+)/progress',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_user_progress' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				)
			);

			register_rest_route(
				'appp',
				'learndash/mark-complete',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'mark_complete' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'appp',
				'learndash/course/(?P<id>\d+)/enroll',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'enroll_user' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				)
			);

			register_rest_route(
				'appp',
				'learndash/quiz/(?P<id>\d+)',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit_quiz' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				)
			);

			register_rest_route(
				'appp',
				'learndash/certificate/(?P<id>\d+)',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_quiz_certificate' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				)
			);

			register_rest_field(
				'sfwd-courses',
				'featured_image_urls',
				array(
					'get_callback'    => array( $this, 'image_sizes' ),
					'update_callback' => null,
					'schema'          => null,
				)
			);

			register_rest_field(
				'sfwd-lessons',
				'quizzes',
				array(
					'get_callback' => array( $this, 'get_quizzes' ),
					'schema'       => null,
				)
			);

			register_rest_field(
				'sfwd-lessons',
				'show_mark_complete',
				array(
					'get_callback' => array( $this, 'show_mark_lesson_complete' ),
					'schema'       => null,
				)
			);

			register_rest_field(
				'sfwd-lessons',
				'lesson_data',
				array(
					'get_callback'    => array( $this, 'get_lesson_data' ),
					'update_callback' => null,
					'schema'          => null,
				)
			);

			register_rest_field(
				'sfwd-topic',
				'topic_data',
				array(
					'get_callback'    => array( $this, 'get_topic_data' ),
					'update_callback' => null,
					'schema'          => null,
				)
			);

			register_rest_field(
				'sfwd-topic',
				'quizzes',
				array(
					'get_callback' => array( $this, 'get_quizzes' ),
					'schema'       => null,
				)
			);

			register_rest_field(
				'sfwd-quiz',
				'questions',
				array(
					'get_callback' => array( $this, 'get_questions' ),
					'schema'       => null,
				)
			);

			register_rest_field(
				'sfwd-question',
				'answer_data',
				array(
					'get_callback' => array( $this, 'get_answer_data' ),
					'schema'       => null,
				)
			);
		}

		/**
		 * Check if request has permission
		 *
		 * @return WP_Error|bool
		 */
		public function permissions_check( $request ) {
			// Bail early.
			if ( ! is_user_logged_in() ) {
				return new WP_Error(
					'rest_authorization_required',
					__( 'Sorry, you are not allowed to do that.', 'applms' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}

			return true;
		}

		function get_user_progress( $request ) {
			$user_id = $request['id'];
			if ( empty( $user_id ) ) {
				return new WP_Error( 'rest_user_invalid_id', esc_html__( 'Invalid user ID.', 'applms' ), array( 'status' => 404 ) );
			}

			return get_user_meta( $user_id, '_sfwd-course_progress', true );
		}

		function show_mark_lesson_complete( $data ) {
			$course_id        = ( isset( $_GET['course'] ) ? $_GET['course'] : null );
			$AppLMS_Functions = new AppLMS_Functions();
			$status           = $AppLMS_Functions->show_mark_lesson_complete( $data['id'], $course_id );
			return $status;
		}

		function get_quizzes( $data ) {
			$quizzes = learndash_get_lesson_quiz_list( $data['id'], $user_id = null, $course_id = null );
			if ( empty( $quizzes ) ) {
				return;
			}
			foreach ( $quizzes as $key => $value ) {
				$quizzes_cleaned[] = $quizzes[ $key ];
			}
			return $quizzes_cleaned;
		}

		function get_questions( $data ) {
			$quiz_questions = learndash_get_quiz_questions( $data['id'] );
			$question_ids   = array();
			if ( ! empty( $quiz_questions ) ) {
				// Extract question IDs.
				$question_ids = array_keys( $quiz_questions );
			}

			return $question_ids;
		}

		function get_answer_data( $data ) {
			$question_controller = new LD_REST_Questions_Controller_V2();
			$question_data       = $question_controller->get_question_data( $data['id'] );
			$answer_data         = $question_data['_answerData'];

			return $answer_data;
		}

		public function mark_complete( $data ) {

			if ( empty( $data['post_id'] ) || empty( $data['user_id'] ) ) {
				return array(
					'success' => false,
					'message' => 'Missing required fields.',
				);
			}

			$post_id   = $data['post_id'];
			$user_id   = $data['user_id'];
			$post_type = $data['post_type'];
			$course_id = $data['course_id'];
			$lesson_id = $data['lesson_id'];

			$res = learndash_process_mark_complete( $user_id, $post_id, false, $course_id );

			if ( $res === false ) {
				return array(
					'success' => $res,
					'message' => 'Unknown error.',
				);
			}

			$AppLMS_Functions = new AppLMS_Functions();
			$completion       = $AppLMS_Functions->is_anything_completed( $post_id, $user_id, $post_type, $lesson_id, $course_id );

			return array(
				'success' => $res,
				'message' => $completion,
			);
		}

		public function enroll_user( $data ) {

			if ( empty( $data['id'] ) || empty( $data['user_id'] ) ) {
				return array(
					'success' => false,
					'message' => 'Missing required fields.',
				);
			}

			$course_id = $data['id'];
			$user_id   = $data['user_id'];

			// add user to course
			return ld_update_course_access( $user_id, $course_id, false );
		}

		function submit_quiz( WP_REST_Request $data ) {

			if ( empty( $data['user_id'] ) || empty( $data['quiz_id'] ) || empty( $data['answers'] ) ) {
				return array(
					'success' => false,
					'message' => 'Missing required fields.',
				);
			}

			$user_id        = intval( $data['user_id'] );
			$quiz_id        = intval( $data['quiz_id'] );
			$answers        = $data['answers'];
			$started_time   = $data['started_time'];
			$completed_time = $data['completed_time'];

			$quiz_data = $this->build_quiz_result( $quiz_id, $user_id, $answers, $started_time, $completed_time );

			if ( ! is_wp_error( $quiz_data ) ) {
				// Save result as shown before.
				$results = get_user_meta( $user_id, '_sfwd-quizzes', true );
				if ( empty( $results ) ) {
					$results = array();
				}
				$results[] = $quiz_data;
				update_user_meta( $user_id, '_sfwd-quizzes', $results );

				do_action(
					'applms_quiz_completed',
					array(
						'quiz'      => $quiz_id,
						'user'      => $user_id,
						'quiz_data' => $quiz_data,
					)
				);

				do_action( 'learndash_quiz_completed', $quiz_data, get_user_by( 'id', $user_id ) );
			}

			return array(
				'success' => true,
			);
		}

		private function build_quiz_result( $quiz_id, $user_id, $answers, $started_time, $completed_time ) {
			$score        = 0;
			$points       = 0;
			$total_points = 0;
			$statistics   = array();

			foreach ( $answers as $answer ) {
				$question_post_id                                   = $answer['id'];
				$question_data                                      = $this->get_data_for_question( $question_post_id );
				$answer_type                                        = $question_data['answer_type'];
				$correct_points                                     = $question_data['correct_points'];
				$user_answer                                        = $this->get_user_answer( $user_id, $question_post_id, $question_data['answer_type'], $answer['answer'] );
				$correct_answer                                     = $this->get_correct_answer( $user_id, $question_post_id, $question_data['answer_type'], $question_data['correct_answer'] );
				$statistics[ $question_post_id ]['correct_count']   = 0;
				$statistics[ $question_post_id ]['incorrect_count'] = 0;
				$statistics[ $question_post_id ]['points']          = 0;
				$statistics[ $question_post_id ]['question_time']   = $answer['question_time'];
				$statistics[ $question_post_id ]['answer_data']     = $this->get_user_answer_data( $user_id, $question_post_id, $question_data['answer_type'], $answer['answer_data'] );
				if ( $this->is_answer_correct( $user_answer, $correct_answer, $answer_type ) ) {
					// If the answer is correct, add the points to the score.
					$points += $correct_points;
					// Increment the score.
					++$score;
					// Increment the statistics correct count.
					++$statistics[ $question_post_id ]['correct_count'];
					// Increment the statistics points count.
					$statistics[ $question_post_id ]['points'] = $correct_points;
				} else {
					// Increment the statistics incorrect count.
					++$statistics[ $question_post_id ]['incorrect_count'];
				}
				// Calucalte the total points for all the answers provided.
				$total_points += $correct_points;
			}

			// Calculate total number of questions.
			$total_questions = count( $answers );

			// Check if the user passed the quiz.
			$percentage         = ( $total_points > 0 ) ? ( $points / $total_points ) * 100 : 0;
			$passing_percentage = $this->get_quiz_passing_percentage( $quiz_id );
			$passed             = $percentage >= $passing_percentage ? 1 : 0;

			// Get the quiz pro ID.
			$quiz_pro_id = learndash_get_setting( $quiz_id, 'quiz_pro' );

			// Get the course ID.
			$course_id = learndash_get_course_id( $quiz_id );

			// Get the lesson ID.
			$lesson_id = learndash_get_lesson_id( $quiz_id );

			// Get the topic ID.
			$topic_id = wp_get_post_parent_id( $quiz_id );

			// Get the has graded status.
			$has_graded = false; // Hardcoded as false for now.

			// Create and get the statistic reference ID.
			$statistic_ref_id = $this->handle_statistic_ref_id( $quiz_pro_id, $user_id, $quiz_id, $course_id, $statistics );

			// Get the plugin version.
			$ld_version = LEARNDASH_VERSION;

			// Get the quiz key.
			$quiz_key = time() . '_' . $quiz_pro_id . '_' . $question_post_id . '_' . $course_id;

			$result = array(
				'quiz'                => $quiz_id,
				'score'               => (float) $score,
				'count'               => $total_questions,
				'question_show_count' => $total_questions,
				'pass'                => $passed,
				'rank'                => '-',
				'time'                => (int) $completed_time,
				'pro_quizid'          => $quiz_pro_id,
				'course'              => $course_id,
				'lesson'              => $lesson_id,
				'topic'               => $topic_id,
				'points'              => (float) $points,
				'total_points'        => (float) $total_points,
				'percentage'          => round( $percentage, 2 ),
				'timespent'           => (float) $completed_time - $started_time,
				'has_graded'          => $has_graded,
				'statistic_ref_id'    => $statistic_ref_id,
				'started'             => (int) $started_time,
				'completed'           => (int) $completed_time,
				'ld_version'          => $ld_version,
				'quiz_key'            => $quiz_key,
			);

			return $result;
		}

		private function get_user_answer( $user_id, $question_post_id, $answer_type, $user_answers ) {
			if ( 'sort_answer' === $answer_type ) {
				$question_controller  = new LD_REST_Questions_Controller_V2();
				$question_data        = $question_controller->get_question_data( $question_post_id );
				$original_answer_data = $question_data['_answerData'];
				// Create a hash for each answer.
				$hashed_original_answer_data = array();
				foreach ( $original_answer_data as $original_position => $original_answer ) {
					$question_id   = get_post_meta( $question_post_id, 'question_pro_id', true );
					$hashed_answer = md5( $user_id . $question_id . $original_position );
					$hashed_original_answer_data[ $original_answer['_answer'] ] = $hashed_answer;
				}
				// Replace the original answer data with the hashed version.
				foreach ( $user_answers as &$user_answer ) {
					$user_answer = $hashed_original_answer_data[ $user_answer ];
				}
			} elseif ( 'matrix_sort_answer' === $answer_type ) {
				$question_controller  = new LD_REST_Questions_Controller_V2();
				$question_data        = $question_controller->get_question_data( $question_post_id );
				$original_answer_data = $question_data['_answerData'];
				// Create a hash for each answer.
				$hashed_original_answer_data = array();
				foreach ( $original_answer_data as $original_position => $original_answer ) {
					$question_id   = get_post_meta( $question_post_id, 'question_pro_id', true );
					$hashed_answer = md5( $user_id . $question_id . $original_position );
					$hashed_original_answer_data[ $original_answer['_sortString'] ] = $hashed_answer;
				}
				// Replace the original answer data with the hashed version.
				$hashed_users_answers = array();
				foreach ( $user_answers as $user_answer ) {
					$hashed_users_answers[] = $hashed_original_answer_data[ $user_answer[0]['_sortString'] ];
				}
				$user_answers = $hashed_users_answers;
			} elseif ( 'cloze_answer' === $answer_type ) {
				// Replace the original answer data with just their values.
				$user_answers_values = array();
				foreach ( $user_answers as $user_answer ) {
					$user_answers_values[] = empty( $user_answer ) ? '' : $user_answer;
				}
				$user_answers = $user_answers_values;
			}

			return $user_answers;
		}

		private function get_correct_answer( $user_id, $question_post_id, $answer_type, $correct_answers ) {
			if ( 'sort_answer' === $answer_type || 'matrix_sort_answer' === $answer_type ) {
				// Replace the original answer data with the hashed version.
				foreach ( $correct_answers as $position => &$answer ) {
					$question_id = get_post_meta( $question_post_id, 'question_pro_id', true );
					$answer      = md5( $user_id . $question_id . $position );
				}
			}

			return $correct_answers;
		}

		private function get_user_answer_data( $user_id, $question_post_id, $answer_type, $user_answer_data ) {
			if ( 'sort_answer' === $answer_type ) {
				$question_controller  = new LD_REST_Questions_Controller_V2();
				$question_data        = $question_controller->get_question_data( $question_post_id );
				$original_answer_data = $question_data['_answerData'];
				// Create a hash for each answer.
				$hashed_original_answer_data = array();
				foreach ( $original_answer_data as $original_position => $original_answer ) {
					$question_id   = get_post_meta( $question_post_id, 'question_pro_id', true );
					$hashed_answer = md5( $user_id . $question_id . $original_position );
					$hashed_original_answer_data[ $original_answer['_answer'] ] = $hashed_answer;
				}
				// Replace the original answer data with the hashed version.
				foreach ( $user_answer_data as &$user_answer ) {
					$user_answer = $hashed_original_answer_data[ $user_answer ];
				}
			} elseif ( 'matrix_sort_answer' === $answer_type ) {
				$question_controller  = new LD_REST_Questions_Controller_V2();
				$question_data        = $question_controller->get_question_data( $question_post_id );
				$original_answer_data = $question_data['_answerData'];
				// Create a hash for each answer.
				$hashed_original_answer_data = array();
				foreach ( $original_answer_data as $original_position => $original_answer ) {
					$question_id   = get_post_meta( $question_post_id, 'question_pro_id', true );
					$hashed_answer = md5( $user_id . $question_id . $original_position );
					$hashed_original_answer_data[ $original_answer['_sortString'] ] = $hashed_answer;
				}
				// Replace the original answer data with the hashed version.
				$hashed_users_answers = array();
				foreach ( $user_answer_data as $index => $user_answer ) {
					if ( $user_answer ) {
						$hashed_users_answers[ $index ] = $hashed_original_answer_data[ $user_answer['_sortString'] ];
					}
				}
				$user_answer_data = $hashed_users_answers;
			}

			return wp_json_encode( $user_answer_data );
		}

		private function is_answer_correct( $user_answer, $correct_answer, $answer_type ) {
			if ( 'single' === $answer_type ) {
				if ( $user_answer === $correct_answer[0] ) {
					return true;
				}
			} elseif ( 'multiple' === $answer_type ) {
				if ( is_array( $user_answer ) && is_array( $correct_answer ) ) {
					sort( $user_answer );
					sort( $correct_answer );
					if ( $user_answer === $correct_answer ) {
						return true;
					}
				}
			} elseif ( 'free_answer' === $answer_type ) {
				// Convert the correct anwser string to an array.
				$answer_elements = array_map(
					function ( $line ) {
						return explode( '|', $line )[0];
					},
					explode( "\n", $correct_answer[0] ) // It needs double quotes "\n" to work properly.
				);
				// Make everything lowercase.
				$lowercase_answer_elements = array_map( 'strtolower', $answer_elements );
				if ( in_array( strtolower( $user_answer ), $lowercase_answer_elements, true ) ) {
					return true;
				}
			} elseif ( 'sort_answer' === $answer_type ) {
				if ( $user_answer === $correct_answer ) {
					return true;
				}
			} elseif ( 'matrix_sort_answer' === $answer_type ) {
				if ( $user_answer === $correct_answer ) {
					return true;
				}
			} elseif ( 'cloze_answer' === $answer_type ) {
				$all_match = true;
				foreach ( $correct_answer[0] as $index => $answer ) {
					if ( ! in_array( strtolower( $user_answer[ $index ] ), $answer, true ) ) {
						$all_match = false;
						break;
					}
				}
				if ( $all_match ) {
					return true;
				}
			}

			return false;
		}

		private function get_data_for_question( $question_post_id ) {
			global $wpdb;

			// Get the question pro ID from the post meta.
			$question_pro_id = get_post_meta( $question_post_id, 'question_pro_id', true );

			// Get the question data from the database.
			$table    = esc_sql( LDLMS_DB::get_table_name( 'quiz_question' ) );
			$question = $wpdb->get_row(
				$wpdb->prepare( "SELECT answer_data, points, answer_type FROM $table WHERE id = %d", $question_pro_id ),
				ARRAY_A
			);
			if ( ! $question ) {
				return null;
			}

			// Get the answer type.
			$answer_type = $question['answer_type'];

			// Get the correct answers.
			$correct_answers = array();
			$answers         = unserialize( $question['answer_data'] );
			foreach ( $answers as $answer ) {
				if ( 'single' === $question['answer_type'] ) {
					// Check for single answer type.
					if ( $answer->isCorrect() ) {
						$correct_answers[] = $answer->getAnswer();
					}
				} elseif ( 'multiple' === $question['answer_type'] ) {
					// Check for multiple answer types.
					if ( $answer->isCorrect() ) {
						$correct_answers[] = $answer->getAnswer();
					}
				} elseif ( 'free_answer' === $question['answer_type'] ) {
					// Check for free answer types.
					$correct_answers[] = $answer->getAnswer();
				} elseif ( 'sort_answer' === $question['answer_type'] ) {
					// Check for sort answer types.
					$correct_answers[] = $answer->getAnswer();
				} elseif ( 'matrix_sort_answer' === $question['answer_type'] ) {
					// Check for matrix sort answer types.
					$correct_answers[] = $answer->getSortString();
				} elseif ( 'cloze_answer' === $question['answer_type'] ) {
					// Check for fill in the blank answer types.
					$question_answer_data = learndash_question_cloze_fetch_data( $answer->getAnswer() );
					$correct_answers[]    = $question_answer_data['correct'];
				}
			}

			// Get the current points.
			$correct_points = $question['points'];

			return array(
				'answer_type'    => $answer_type,
				'correct_answer' => $correct_answers,
				'correct_points' => $correct_points,
			);
		}

		private function get_quiz_passing_percentage( $quiz_id ) {
			$passing_percentage = 0;

			// Retrieve the serialized data from the _sfwd-quiz post meta.
			$quiz_settings = get_post_meta( $quiz_id, '_sfwd-quiz', true );

			// If quiz settings are found and serialized data exists.
			if ( ! empty( $quiz_settings ) ) {
				// Unserialize the data.
				$quiz_data = maybe_unserialize( $quiz_settings );

				// Check if passing percentage exists in the unserialized data.
				if ( isset( $quiz_data['sfwd-quiz_passingpercentage'] ) ) {
					$passing_percentage = (int) $quiz_data['sfwd-quiz_passingpercentage'];
				}
			}

			return $passing_percentage;
		}

		private function handle_statistic_ref_id( $quiz_pro_id, $user_id, $quiz_post_id, $course_post_id, $statistics ) {
			global $wpdb;

			$wpdb->insert(
				LDLMS_DB::get_table_name( 'quiz_statistic_ref' ),
				array(
					'quiz_id'        => $quiz_pro_id,
					'user_id'        => $user_id,
					'create_time'    => time(),
					'is_old'         => 0,
					'form_data'      => null,
					'quiz_post_id'   => $quiz_post_id,
					'course_post_id' => $course_post_id,
				)
			);

			$statistic_ref_id = $wpdb->insert_id;

			$questions = learndash_get_quiz_questions( $quiz_post_id );
			if ( ! empty( $questions ) ) {
				foreach ( $questions as $question_post_id => $question ) {
					$wpdb->insert(
						LDLMS_DB::get_table_name( 'quiz_statistic' ),
						array(
							'statistic_ref_id' => $statistic_ref_id,
							'question_id'      => $question,
							'correct_count'    => $statistics[ $question_post_id ]['correct_count'] ?? 0,
							'incorrect_count'  => $statistics[ $question_post_id ]['incorrect_count'] ?? 0,
							'hint_count'       => 0, // Hardcoded as 0 for now.
							'points'           => $statistics[ $question_post_id ]['points'] ?? 0,
							'question_time'    => $statistics[ $question_post_id ]['question_time'],
							'answer_data'      => $statistics[ $question_post_id ]['answer_data'],
							'question_post_id' => $question_post_id,
						)
					);
				}
			}

			return $statistic_ref_id;
		}

		public function get_all_courses( $request ) {

			$page = ( isset( $request['page'] ) ? $request['page'] : 1 );

			$args = array(
				'post_type'      => 'sfwd-courses',
				'posts_per_page' => 10,
				'paged'          => $page,
			);

			if ( isset( $request['course_category'] ) ) {
				$cats              = explode( ',', $request['course_category'] );
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'ld_course_category',
						'field'    => 'slug',
						'terms'    => $cats,
					),
				);
			}

			if ( isset( $request['category'] ) ) {
				$args['category_name'] = $request['category'];
			}

			$query = new WP_Query( $args );

			$courses = array();

			foreach ( $query->posts as $key => $course ) {
				$courses[] = $this->get_course(
					array(
						'id'       => $course->ID,
						'user_id'  => $request['userId'],
						'sections' => $request['sections'],
					)
				);
			}

			return $courses;
		}

		public function get_course( $request ) {
			$course_id = $request['id'];
			$user_id   = ( isset( $request['user_id'] ) ? $request['user_id'] : 0 );

			$post                   = get_post( $course_id );
			$meta                   = get_post_meta( $course_id, '_sfwd-courses', 1 );
			$course['ID']           = $post->ID;
			$course['post_title']   = $post->post_title;
			$course['slug']         = $post->post_name;
			$course['post_date']    = $post->post_date;
			$course['post_content'] = apply_filters( 'the_content', $post->post_content );
			$course['excerpt']      = $post->post_excerpt;

			$image_id                          = get_post_thumbnail_id( $course_id );
			$course['featured_images']         = $this->get_image_sizes( $image_id );
			$course['featured_image_alt_text'] = $this->get_image_text( $image_id );

			$course['meta'] = $meta;

			$course['user_has_access']        = sfwd_lms_has_access_fn( $course_id, $user_id );
			$course['user_met_prerequisites'] = learndash_is_course_prerequities_completed( $course_id, $user_id );
			$course['quizzes']                = learndash_get_course_quiz_list( $course_id );

			$lesson_query_args = array(
				'pagination'     => 'false',
				'posts_per_page' => -1,
				'nopaging'       => true,
			);
			$lessons           = learndash_get_course_lessons_list( $course_id, $user_id, $lesson_query_args );
			if ( ! empty( $lessons ) ) {

				$AppLMS_Functions = new AppLMS_Functions();

				foreach ( $lessons as $lesson ) {
					$course['lessons'][] = array(
						'ID'               => $lesson['post']->ID,
						'title'            => $lesson['post']->post_title,
						'excerpt'          => $lesson['post']->post_excerpt,
						'status'           => $lesson['status'],
						'type'             => 'lesson',
						'quizzes_complete' => $AppLMS_Functions->show_mark_lesson_complete( $lesson['post']->ID, $course['ID'] ),
					);
				}
			}

			if ( isset( $request['sections'] ) && $request['sections'] === 'true' ) {
				$sections = learndash_course_get_sections( $course_id );
				if ( ! empty( $sections ) ) {
					$course['has_sections'] = true;
					foreach ( $sections as $section ) {
						$section_heading = array(
							'ID'      => $section->ID,
							'title'   => $section->post_title,
							'type'    => $section->type,
							'excerpt' => '',
						);
						array_splice( $course['lessons'], $section->order, 0, array( $section_heading ) );
					}
				}
			}

			if ( $user_id ) {
				$course['progress'] = $this->get_course_progress( $course_id, $user_id );
			}

			return apply_filters( 'applms_prepare_course', $course, $request );
		}

		// enroll in free course
		public function course_enroll( $request ) {
			$course_id = $request['id'];
			$user_id   = $request['user_id'];

			if ( empty( $course_id ) || empty( $user_id ) ) {
				return false;
			}

			ld_update_course_access( $user_id, $course_id );

			// make sure it worked
			if ( sfwd_lms_has_access_fn( $course_id, $user_id ) ) {
				return true;
			}

			return false;
		}

		public function get_course_progress( $course_id, $user_id ) {

			$course_progress = get_user_meta( $user_id, '_sfwd-course_progress', true );

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
			}

			$progress = array(
				'percentage' => @$percentage,
				'completed'  => @$completed,
				'total'      => @$total,
			);

			return $progress;
		}

		public function get_quiz_certificate( $request ) {
			$quiz_id = $request['id'];
			$user_id = ( isset( $request['user_id'] ) ? $request['user_id'] : 0 );
			return learndash_certificate_details( $quiz_id, $user_id );
		}

		public function get_image_sizes( $image_id ) {
			$sizes     = wp_get_attachment_metadata( $image_id );
			$size_data = new stdClass();
			if ( ! empty( $sizes['sizes'] ) ) {
				foreach ( $sizes['sizes'] as $key => $size ) {
					// Use the same method image_downsize() does
					$image_src = wp_get_attachment_image_src( $image_id, $key );
					if ( ! $image_src ) {
						continue;
					}
					$size_data->$key = $image_src[0];
				}
			}

			return $size_data;
		}

		public function get_image_text( $image_id ) {
			$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_text ) ) {
				return $alt_text;
			}

			return get_the_title( $image_id );
		}

		public function learndash_to_api() {
			global $wp_post_types;

			// be sure to set this to the name of your post type!
			$post_type_name = 'sfwd-courses';
			if ( isset( $wp_post_types[ $post_type_name ] ) ) {
				$wp_post_types[ $post_type_name ]->show_in_rest = true;
				// Optionally customize the rest_base or controller class
				$wp_post_types[ $post_type_name ]->rest_base             = $post_type_name;
				$wp_post_types[ $post_type_name ]->rest_controller_class = 'WP_REST_Posts_Controller';
			}
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

		public function get_lesson_data( $post ) {
			$meta = get_post_meta( $post['id'], '_sfwd-lessons', 1 );
			$data = array();
			if ( isset( $meta['sfwd-lessons_lesson_video_url'] ) ) {
				$data['lesson_video_url'] = $meta['sfwd-lessons_lesson_video_url'];
				if ( false !== strpos( $meta['sfwd-lessons_lesson_video_url'], '<iframe' ) ) {
					$data['lesson_video_embed'] = '<div class="ld-video-wrap">' . $meta['sfwd-lessons_lesson_video_url'] . '</div>';
				} else {
					$data['lesson_video_embed'] = '<div class="ld-video-wrap">' . wp_oembed_get( $meta['sfwd-lessons_lesson_video_url'] ) . '</div>';
				}
			}

			if ( isset( $meta['sfwd-lessons_forced_lesson_time_enabled'] ) ) {
				$data['forced_lesson_time'] = $meta['sfwd-lessons_forced_lesson_time'];
			}

			if ( isset( $meta['sfwd-lessons_lesson_materials_enabled'] ) ) {
				$data['lesson_materials'] = $meta['sfwd-lessons_lesson_materials'];
			}

			$data['next_post_id']        = $this->_get_next_id( $post );
			$data['next_post_type']      = get_post_type( $data['next_post_id'] );
			$data['next_post_permalink'] = get_permalink( $data['next_post_id'] );
			$data['prev_post_id']        = $this->_get_prev_id( $post );
			$data['prev_post_type']      = get_post_type( $data['prev_post_id'] );
			$data['prev_post_permalink'] = get_permalink( $data['prev_post_id'] );

			return $data;
		}

		public function get_topic_data( $post ) {
			$data = array();
			$meta = get_post_meta( $post['id'], '_sfwd-topic', 1 );

			if ( isset( $meta['sfwd-topic_lesson_video_url'] ) ) {
				$data['lesson_video_url'] = $meta['sfwd-topic_lesson_video_url'];
				if ( false !== strpos( $meta['sfwd-topic_lesson_video_url'], '<iframe' ) ) {
					$data['lesson_video_embed'] = '<div class="ld-video-wrap">' . $meta['sfwd-topic_lesson_video_url'] . '</div>';
				} else {
					$data['lesson_video_embed'] = '<div class="ld-video-wrap">' . wp_oembed_get( $meta['sfwd-topic_lesson_video_url'] ) . '</div>';
				}
			}

			if ( isset( $meta['sfwd-topic_forced_lesson_time_enabled'] ) ) {
				$data['forced_lesson_time'] = $meta['sfwd-topic_forced_lesson_time'];
			}

			if ( isset( $meta['sfwd-topic_topic_materials_enabled'] ) ) {
				$data['topic_materials'] = $meta['sfwd-topic_topic_materials'];
			}

			$data['next_post_id']        = $this->_get_next_id( $post );
			$data['next_post_type']      = get_post_type( $data['next_post_id'] );
			$data['next_post_permalink'] = get_permalink( $data['next_post_id'] );
			$data['prev_post_id']        = $this->_get_prev_id( $post );
			$data['prev_post_type']      = get_post_type( $data['prev_post_id'] );
			$data['prev_post_permalink'] = get_permalink( $data['prev_post_id'] );

			return $data;
		}

		private function _get_next_id( $post ) {

			$post_class = get_post( $post['id'] );

			return learndash_next_post_link( '', 'id', $post_class );
		}

		private function _get_prev_id( $post ) {

			$post_class = get_post( $post['id'] );

			return learndash_previous_post_link( '', 'id', $post_class );
		}

		public function allow_questions_to_be_read_from_users_with_read_capability( $bypass, $post_type_slug ) {
			if ( 'sfwd-question' === $post_type_slug && current_user_can( 'read' ) ) {
				return true;
			}

			return $bypass;
		}
	}

	$AppLMS_API = new AppLMS_API();
	$AppLMS_API->instance();
} // end class_exists check
