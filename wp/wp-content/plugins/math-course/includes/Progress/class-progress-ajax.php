<?php
namespace MathCourse\Progress;
defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

class Progress_Ajax {
    public function __construct() {
        // Only lesson completion is persisted on the server.
        // Exact playback position is intentionally kept in the student's browser/device.
        add_action('wp_ajax_mathcourse_complete_lesson', array($this, 'complete'));
    }

    private function resolve_course_id($lesson_id) {
        $adapter = new Adapter();
        $lesson = $adapter->get_lesson($lesson_id);
        if (!$lesson || 'publish' !== $lesson->post_status) return 0;

        $course_id = $adapter->get_lesson_course_id($lesson_id);
        if (!$course_id) return 0;

        $course = $adapter->get_course($course_id);
        if (!$course || 'publish' !== $course->post_status) return 0;

        return $course_id;
    }

    private function valid_lesson($lesson_id, $user_id) {
        $course_id = $this->resolve_course_id($lesson_id);
        if (!$course_id) return 0;

        $access = new Access_Service();
        if (!$access->can_access_course($user_id, $course_id) && !$access->can_preview($course_id, $lesson_id)) return 0;

        return $course_id;
    }

    public function complete() {
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'login required'),403);
        check_ajax_referer('mathcourse_progress_nonce','nonce');

        $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
        $user_id   = get_current_user_id();
        $course_id = $this->valid_lesson($lesson_id, $user_id);

        if (!$course_id) {
            wp_send_json_error(array('message'=>'invalid lesson or access'),403);
        }

        $progress = new Progress_Service();
        if (!$progress->complete_lesson($user_id, $lesson_id, true)) {
            wp_send_json_error(array('message'=>'unable to save progress'),500);
        }

        // Keep the persisted server-side state minimal: completion only.
        // Do not store exact playback time, seek position, or heartbeat data.
        update_user_meta($user_id, 'mc_last_completed_course', $course_id);

        wp_send_json_success(array(
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
            'completed' => true,
            'progress'  => $progress->get_course_progress($course_id, $user_id),
        ));
    }
}
