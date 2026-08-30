<?php
namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

use MathCourse\Progress\Progress_Service;

/**
 * Backward-compatible AJAX endpoint for legacy player requests.
 * Completion rules remain centralized in Progress_Service.
 */
class Complete {
    public function __construct() {
        add_action('wp_ajax_mathcourse_complete', array($this, 'complete'));
    }

    public function complete() {
        check_ajax_referer('mathcourse_complete', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('not_login', 401);
        }

        $user_id   = get_current_user_id();
        $lesson_id = isset($_POST['lesson_id']) ? absint(wp_unslash($_POST['lesson_id'])) : 0;

        if (!$lesson_id) {
            wp_send_json_error('invalid_lesson', 400);
        }

        $service = new Progress_Service();
        if (!$service->complete_lesson($user_id, $lesson_id, false)) {
            wp_send_json_error('completion_denied', 403);
        }

        wp_send_json_success('completed');
    }
}
