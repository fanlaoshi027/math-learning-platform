<?php
namespace MathCourse\Learning;

defined('ABSPATH') || exit;

use MathCourse\Progress\Progress_Service;

/**
 * @deprecated Completion is now handled by Progress_Service.
 * Kept as a compatibility wrapper for older integrations.
 */
class Complete_Service {
    public function save($user_id, $course_id, $lesson_id) {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);

        if (!$user_id || !$course_id || !$lesson_id) return false;

        $service = new Progress_Service();
        $actual_course_id = $service->get_lesson_course_id($lesson_id);
        if (!$actual_course_id || $actual_course_id !== $course_id) return false;

        return $service->complete_lesson($user_id, $lesson_id, false);
    }

    public function is_complete($user_id, $lesson_id) {
        $user_id   = absint($user_id);
        $lesson_id = absint($lesson_id);
        if (!$user_id || !$lesson_id) return false;

        return (new Progress_Service())->is_completed($user_id, $lesson_id);
    }
}
