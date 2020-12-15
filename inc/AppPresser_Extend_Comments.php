<?php

class AppPresser_Extend_Comments
{

    public function __construct()
    {
        $this->hooks();
    }

    public function hooks()
    {
        if (isset($_REQUEST['children']) && ($_REQUEST['children'] === 'true')) {
            add_action('rest_api_init', array($this, 'add_children_field_to_comment_endpoint'));
        }
    }

    /**
     * Add children field in the comment endpoint
     */
    public function add_children_field_to_comment_endpoint()
    {
        register_rest_field(
            'comment',
            'children',
            array(
                'get_callback'    => array($this, 'get_comment_children'),
                'update_callback' => null,
                'schema'          => null,
            )
        );
    }

    public function get_comment_children($object)
    {
        return $this->getCommentChildren($object['id']);
    }

    private function getCommentChildren($parentId)
    {
        $children = get_comments(array(
            'parent' => $parentId
        ));

        $childrenComment = array();

        foreach ($children as $child) {
            $childrenComment[] = array(
                'id' => (int) $child->comment_ID,
                'post' => (int) $child->comment_post_ID,
                'parent' => (int) $child->comment_parent,
                'author' => (int) $child->user_id,
                'author_name' => $child->comment_author,
                'author_email' => $child->comment_author_email,
                'author_url' => $child->comment_author_url,
                'date' => mysql_to_rfc3339($child->comment_date),
                'date_gmt' => mysql_to_rfc3339($child->comment_date_gmt),
                'content' => array(
                    'rendered' => apply_filters('comment_text', $child->comment_content, $child),
                    'raw' => $child->comment_content
                ),
                'status' => $this->prepare_status_response($child->comment_approved),
                'type' => get_comment_type($child->comment_ID),
                'author_avatar_urls' => rest_get_avatar_urls($child),
                'children' => $this->getCommentChildren((int) $child->comment_ID)
            );
        }

        return $childrenComment;
    }

    private function prepare_status_response($comment_approved)
    {
        switch ($comment_approved) {
            case 'hold':
            case '0':
                $status = 'hold';
                break;

            case 'approve':
            case '1':
                $status = 'approved';
                break;

            case 'spam':
            case 'trash':
            default:
                $status = $comment_approved;
                break;
        }

        return $status;
    }
}

$AppPresser_Extend_Comments = new AppPresser_Extend_Comments();
$AppPresser_Extend_Comments->hooks();
