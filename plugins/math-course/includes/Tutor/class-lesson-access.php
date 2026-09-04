<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;

/**
 * Tutor LMS lesson access compatibility layer.
 *
 * Permission decisions remain owned by MathCourse Access_Service so Tutor
 * integration cannot accidentally create a second authorization system.
 */
class Lesson_Access {

    /**
     * Check whether a logged-in user has access to the course.
     */
    public function can_view($user_id, $course_id) {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id) {
            return false;
        }

        if (!class_exists(Access_Service::class)) {
            return false;
        }

        $service = new Access_Service();
        return $service->can_access_course($user_id, $course_id);
    }

    /**
     * Check the final watch permission for a specific lesson.
     * This also allows an explicitly configured preview lesson for guests.
     */
    public function can_watch_lesson($user_id, $course_id, $lesson_id) {
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);
        $user_id   = absint($user_id);

        if (!$course_id || !$lesson_id || !class_exists(Access_Service::class)) {
            return false;
        }

        $service = new Access_Service();
        return $service->can_watch_lesson($user_id, $course_id, $lesson_id);
    }
}
