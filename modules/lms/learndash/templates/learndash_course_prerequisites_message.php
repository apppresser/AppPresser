<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * Displays the Prerequisites
 *
 * Available Variables:
 * $current_post : (WP_Post Object) Current Post object being display. Equal to global $post in most cases.
 * $prerequisite_post : (WP_Post Object) Post object needed to be taken prior to $current_post
 * $prerequisite_posts_all : (WP_Post Object) Post object needed to be taken prior to $current_post
 * $content_type : (string) Will contain the singlar lowercase common label 'course', 'lesson', 'topic', 'quiz'
 * $course_settings : (array) Settings specific to current course
 * 
 * @since 2.2.1.2
 * 
 * @package LearnDash\Course
 */
?>
<div id="learndash_complete_prerequisites"><?php 
	echo sprintf( 
	 esc_html_x(
			'To take this %1$s, you need to complete the following %2$s first:', 
			'placeholders: (1) will be Course, Lesson or Quiz sigular. (2) Course sigular label', 
			'learndash' 
		), 
		$content_type, 
		LearnDash_Custom_Label::label_to_lower( 'course' ) 
	);
	
	echo '<br>';
		
	$post_links = '';
	if ( !empty( $prerequisite_posts_all ) ) {
		foreach( $prerequisite_posts_all as $pre_post_id => $pre_status ) {
			if ( $pre_status === false ) {
				if ( !empty( $post_links ) ) $post_links .= ', ';
				$post_links .= '<a href="'. get_the_permalink( $pre_post_id ) .'">'. get_the_title( $pre_post_id ) .'</a>';
			}
		}
	}
	if ( !empty( $post_links ) ) echo $post_links;
?></div>
