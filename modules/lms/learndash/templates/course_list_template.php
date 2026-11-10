<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;

/**
 * This file contains the code that displays the course list.
 * 
 * @since 2.1.0
 * 
 * @package LearnDash\Course
 */

$id = get_the_ID();
$cols = $shortcode_atts['cols'];
?>

<div id="post-<?php echo $id; ?>" class="learndash-course-list <?php if( $cols == '2' ) { echo 'ld-2-col'; }; ?>">

	<a class="push-page" href="<?php echo learndash_get_step_permalink( $id, $course_id ); ?>">
		<div class="card">
		    <div class="item item-text-wrap"><h2><?php the_title(); ?></h2></div>
			
		    <div class="item item-body">

			    <div class="card-media">
				
				  <?php if ( ( isset( $shortcode_atts['show_thumbnail'] ) ) && ( $shortcode_atts['show_thumbnail'] == 'true' ) ) {
						the_post_thumbnail( 'full' ); 
					} ?>
					
				</div>
				
		        <div class="card-content-inner">

		        	<?php the_excerpt();  ?>

				</div>
		    </div>
		</div> 
	</a>
	
</div><!-- #post-## -->