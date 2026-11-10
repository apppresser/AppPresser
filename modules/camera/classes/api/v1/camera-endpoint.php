<?php
// Internal AppPresser module.
defined( 'ABSPATH' ) || exit;


class CameraEndpoint extends WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'appcamera/v1';
    }

    public function registerRoutes() {
        register_rest_route($this->namespace, '/media', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'getImages'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route($this->namespace, '/upload', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'uploadImage'),
            'permission_callback' => array($this, 'permissionsCheck'),
            'args' => array(
                'action' => array(
                    'type' => 'string',
                    'default' => 'this',
                ),
                'appp_post_type' => array(
                    'type' => 'string',
                    'default' => 'post',
                ),
                'appp_cam_post_title' => array(
                    'type' => 'string',
                    'default' => 'false',
                ),
                'appp_cam_post_id' => array(),
                'appp_cam_file' => array(),
            ),
        ));
    }

    public function getImages($request) {
        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_mime_type' => 'image',
        );

        $postArgs = array();
        if (isset($request['post_type'])) {
            $postArgs['post_type'] = $request['post_type'];
            $postArgs['posts_per_page'] = -1;
            $postArgs['fields'] = 'ids';
            if (isset($request['taxonomy']) && isset($request['taxonomy_tags'])) {
                $postArgs['tax_query'] = array(
                    array(
                        'taxonomy' => $request['taxonomy'],
                        'field' => 'slug',
                        'terms' => explode(',', $request['taxonomy_tags']),
                    ),
                );
            }
        }

        if (isset($request['author'])) {
            $postArgs['author'] = $request['author'];
            $args['author'] = $request['author'];
        }

        $postIds = new WP_Query($postArgs);
        if (!empty($postIds->posts)) {
            $args['post_parent__in'] = $postIds->posts;
        }
        $returnArray = [];
        $attachments = get_posts($args);
        foreach ($attachments as $attachment) {
            $attachmentUrl = wp_get_attachment_url($attachment->ID);
            $returnArray[] = [
                'id' => $attachment->ID,
                'attachment_url' => $attachmentUrl,
                'title' => $attachment->post_title,
                'date' => date('F j, Y', strtotime($attachment->post_date)),
            ];
        }

        // Filter the media images array
        return apply_filters('appcamera_media_images_values', $returnArray, $request);
    }

    public function uploadImage($request) {
        $files = $request->get_file_params();

        // Get the file via $_FILES.
        if (empty($files['appp_cam_file'])) {
            return new WP_Error(
                'rest_upload_image_empty_file',
                __('Missing file field.', 'appcamera'),
                array(
                    'status' => 500,
                )
            );
        }

        // Make sure to include the media uploader.
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $uploadedFile = wp_handle_upload($files['appp_cam_file'], ['test_form' => false]);
        if ($uploadedFile) {
            $photoUploadModeration = appp_get_setting('photo_upload_moderation');

            // Generate the image thumbnail
            $wpFiletype = wp_check_filetype(basename($uploadedFile['file']), null);
            $filename = $uploadedFile['file'];
            $wpUploadDir = wp_upload_dir();

            $attachment = array(
                'post_mime_type' => $wpFiletype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                'post_content' => '',
                'post_author' => absint(get_current_user_id()),
                'post_status' => 'inherit',
                'post_type' => 'attachment',
                'guid' => $wpUploadDir['url'] . '/' . basename($filename),
            );

            if ($request['action'] === 'this' && isset($request['appp_cam_post_id'])) {
                // Check if should be attached to the current post
                $postId = $request['appp_cam_post_id'];
            } elseif ($request['action'] === 'new' && isset($request['appp_post_type'])) {
                // Create a new post to attach image to
                $postTitle = $request['appp_cam_post_title'];
                // Set default post_title if blank
                if (!$postTitle) {
                    $postTitle = date('Y-m-d H:i:s', current_time('timestamp', 0));
                    $postTitle = isset($files['appp_cam_file']['name']) ? $postTitle . ' - ' . $files['appp_cam_file']['name'] : $postTitle;
                }

                $args = array(
                    'post_type' => sanitize_text_field($request['appp_post_type']),
                    'post_status' => ($photoUploadModeration) ? 'draft' : 'publish',
                    'post_author' => absint(get_current_user_id()),
                    'post_title' => sanitize_text_field($postTitle),
                );

                if (isset($request['appp_categories'])) {
                    $args['post_category'] = array($request['appp_categories']);
                }

                if (isset($request['appp_taxonomy']) && isset($request['appp_taxonomy_tags'])) {
                    $args['tax_input'] = array($request['appp_taxonomy'] => array($request['appp_taxonomy_tags']));
                }

                $postId = wp_insert_post($args);
            } else {
                $postId = 0;
            }

            if (!$photoUploadModeration) {
                $attachment = array_merge($attachment, array('post_parent' => absint($postId)));
            }

            $attachmentId = wp_insert_attachment($attachment, $uploadedFile['file']);

            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }

            $attachData = wp_generate_attachment_metadata(absint($attachmentId), $uploadedFile['file']);

            // Update the attachment metadata
            wp_update_attachment_metadata(absint($attachmentId), $attachData);
            $updatePost = array('ID' => absint($postId));

            // Photo Moderation
            if ($photoUploadModeration) {
                $photos = get_option('appp_moderation_photos');
                // Random key + attachment_id for approving/denying via email
                $key = wp_generate_password(20, 0, 0) . $attachmentId;
                if (is_array($photos)) {
                    $photos = array_merge($photos, array($key => array('attachment_id' => $attachmentId, 'parent_id' => absint($postId))));
                } else {
                    $photos = array($key => array('attachment_id' => $attachmentId, 'parent_id' => absint($postId)));
                }
                update_option('appp_moderation_photos', $photos);
            }

            // Set post content or featured image
            if ($request['action'] === 'new' && $postId) {
                if (appp_get_setting('upload_to_feat_img')) {
                    set_post_thumbnail(absint($postId), absint($attachmentId));
                } else {
                    $updatePost['post_content'] = (string) wp_get_attachment_image(
                        absint($attachmentId),
                        'photo-blog',
                        false,
                        array('class' => 'app-attachment app-photo-blog')
                    );
                }
                wp_update_post(apply_filters('appp_insert_photo_post', $updatePost));
            }

            // Email new photos to admin
            if (appp_get_setting('photo_upload_email')) {
                $message = wp_get_attachment_image($attachmentId, 'thumbnail');
                // Moderation Approve/Deny
                if (appp_get_setting('photo_upload_moderation')) {
                    $message .= '<p><a href="' . site_url() . '?appp_approve=' . $key . '">' . __('Approve', 'appcamera') . '</a> | ';
                    $message .= '<a href="' . site_url() . '?appp_deny=' . $key . '">' . __('Deny', 'appcamera') . '</a></p>';
                }
                $to = apply_filters('appp_upload_email_to', get_settings('admin_email'));
                $subject = apply_filters('appp_upload_email_subject', __('A new photo was uploaded.', 'appcamera'));
                $message = apply_filters('appp_upload_email_message', $message);
                add_filter('wp_mail_content_type', array($this, 'setContentType'));
                wp_mail($to, $subject, $message);
                remove_filter('wp_mail_content_type', array($this, 'setContentType'));
            }

            // Hook for other stuff
            do_action('appp_after_process_uploads', $postId, $attachmentId);
            @define('APPP_IMPORTING', false);

            // We're done here, let the app know all is well
            $returnArray = [
                'post_id' => $postId,
                'attachment_id' => $attachmentId,
                'attachment_url' => wp_get_attachment_url((int) $attachmentId),
            ];

            return new WP_REST_Response($returnArray, 200);
        }

        return new WP_Error(
            'rest_something_went_wrong',
            __('Whoops, Something went wrong!', 'appcamera'),
            array(
                'status' => 500,
            )
        );
    }

    /**
     * Check if request has permission
     * @return WP_Error|bool
     */
    public function permissionsCheck() {
        // Bail early.
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_authorization_required',
                __('Sorry, you are not allowed to see this resource.', 'appcamera'),
                array(
                    'status' => rest_authorization_required_code(),
                )
            );
        }

        return true;
    }

    /**
     * Set the content type of the email
     */
    public function setContentType() {
        return 'text/html';
    }
}
