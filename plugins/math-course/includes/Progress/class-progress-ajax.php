<?php
namespace MathCourse\Progress;

defined('ABSPATH') || exit;

/**
 * AJAX bridge for lesson completion.
 * Validation and persistence stay in Progress_Service so AJAX does not create
 * a second permission/completion implementation.
 */
class Progress_Ajax {
    public function __construct() {
        // Only lesson completion is persisted on the server.
        // Exact playback position remains local to the student's browser/device.
        add_action('wp_ajax_mathcourse_complete_lesson', array($this, 'complete'));
    }

    public function complete() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'login required'), 403);
        }

        check_ajax_referer('mathcourse_progress_nonce', 'nonce');

        $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
        $user_id   = get_current_user_id();

        if (!$lesson_id) {
            wp_send_json_error(array('message' => 'invalid lesson'), 400);
        }

        $progress = new Progress_Service();
        if (!$progress->complete_lesson($user_id, $lesson_id, true)) {
            wp_send_json_error(array('message' => 'invalid lesson or access'), 403);
        }

        $course_id = $progress->get_lesson_course_id($lesson_id);
        if (!$course_id) {
            wp_send_json_error(array('message' => 'invalid lesson'), 400);
        }

        // Keep server-side learning data minimal: only lesson completion is persisted.
        // Exact playback time, seek position, and heartbeat data stay in the browser.
        wp_send_json_success(array(
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
            'completed' => true,
            'progress'  => $progress->get_course_progress($course_id, $user_id),
        ));
    }
}
