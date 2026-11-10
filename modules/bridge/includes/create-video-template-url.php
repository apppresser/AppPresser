<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Load the video when the URL matches
 */
function apppresser_bridge_load_video( $template = null ) {
	$video_url = get_query_var( 'appp_video' );
	if ( $video_url && appp_get_setting( 'youtube_post_types' ) ) {
		if ( ! isset( $_GET['url'] ) || ! is_string( $_GET['url'] ) ) {
			wp_die( esc_html__( 'No video URL provided.', 'apppresser' ), '', array( 'response' => 400 ) );
		}

		$video_url = esc_url_raw( wp_unslash( $_GET['url'] ) );
		if ( ! apppresser_bridge_is_youtube_url( $video_url ) ) {
			wp_die( esc_html__( 'A valid YouTube video URL is required.', 'apppresser' ), '', array( 'response' => 400 ) );
		}
		status_header( 200 );
		nocache_headers();

		// Output minimal HTML
		?>
		<!DOCTYPE html>
		<html lang="en">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>Video</title>
			</head>
			<body>
				<iframe src="<?php echo esc_url( $video_url ); ?>" frameborder="0" allowfullscreen style="position: absolute; top:0; left:0; width:100%; height:100%;"></iframe>
			</body>
		</html>
		<?php
		exit;
	}

	return $template; // Return the default template if the variable isn't set
}
add_action( 'template_redirect', 'apppresser_bridge_load_video' );
