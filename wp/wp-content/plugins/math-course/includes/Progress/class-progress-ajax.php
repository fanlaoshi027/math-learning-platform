<?php
namespace MathCourse\Progress;

defined('ABSPATH') || exit;

/**
 * Lesson completion storage.
 * Playback position is handled by browser localStorage only.
 * Server only stores completed lesson state.
 */
class Progress_Ajax {

    public function __construct() {
        add_action('wp_ajax_mathcourse_complete_lesson', array($this, 'complete'));
    }

    public function complete() {

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'login required'), 403);
        }

        check_ajax_referer(
            'mathcourse_progress_nonce',
            'nonce'
        );

        $lesson_id = isset($_POST['lesson_id'])
            ? absint($_POST['lesson_id'])
            : 0;

        $course_id = isset($_POST['course_id'])
            ? absint($_POST['course_id'])
            : 0;

        if (!$lesson_id) {
            wp_send_json_error(array('message' => 'invalid lesson'));
        }

        $user_id = get_current_user_id();

        $completed = get_user_meta(
            $user_id,
            'mc_completed_lessons',
            true
        );

        if (!is_array($completed)) {
            $completed = array();
        }

        if (!in_array($lesson_id, $completed, true)) {
            $completed[] = $lesson_id;
        }

        update_user_meta(
            $user_id,
            'mc_completed_lessons',
            $completed
        );

        if ($course_id) {
            update_user_meta(
                $user_id,
                'mc_last_completed_course',
                $course_id
            );
        }

        wp_send_json_success(array(
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
            'completed' => true
        ));
    }
}
