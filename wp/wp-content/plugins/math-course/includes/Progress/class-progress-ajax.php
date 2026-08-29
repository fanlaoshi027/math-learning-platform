<?php
namespace MathCourse\Progress;

defined('ABSPATH') || exit;

/**
 * Save lesson completion only.
 * Playback position stays in browser localStorage.
 */
class Progress_Ajax {

    public function __construct() {
        add_action('wp_ajax_mathcourse_complete_lesson', array($this, 'complete'));
    }

    public function complete() {

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message'=>'login required'), 403);
        }

        $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;

        if (!$lesson_id) {
            wp_send_json_error(array('message'=>'invalid lesson'));
        }

        if (function_exists('tutor_utils')) {
            tutor_utils()->mark_lesson_complete($lesson_id);
        }

        wp_send_json_success(array(
            'lesson_id'=>$lesson_id,
            'completed'=>true
        ));
    }
}
