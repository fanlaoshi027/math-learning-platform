<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * Central Lesson access gate.
 *
 * Tutor remains the owner of Course / Topic / Lesson. MathCourse only adds
 * its own preview and course-authorization rules at this boundary.
 */
class Lesson_Access
{
    /**
     * Check whether a lesson may be viewed.
     *
     * Preview lessons are public. Normal lessons require a logged-in user and
     * an active MathCourse course authorization.
     */
    public function check($lesson_id = 0, $user_id = 0)
    {
        $lesson_id = absint($lesson_id ?: get_the_ID());
        $user_id   = absint($user_id ?: get_current_user_id());

        if (!$lesson_id) {
            return false;
        }

        if ($this->is_preview($lesson_id)) {
            return true;
        }

        if (!$user_id) {
            return false;
        }

        $course_id = $this->get_course_id($lesson_id);
        if (!$course_id) {
            return false;
        }

        if (!class_exists('MathCourse\\Access\\Access_Service')) {
            return false;
        }

        $service = new \MathCourse\Access\Access_Service();
        return $service->has_access($user_id, $course_id);
    }

    /**
     * Backwards-compatible explicit course check used by older callers.
     */
    public function can_view($user_id, $course_id)
    {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id || !class_exists('MathCourse\\Access\\Access_Service')) {
            return false;
        }

        $service = new \MathCourse\Access\Access_Service();
        return $service->has_access($user_id, $course_id);
    }

    public function is_preview($lesson_id)
    {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) {
            return false;
        }

        return 'yes' === get_post_meta($lesson_id, '_mathcourse_preview', true);
    }

    /**
     * Resolve the parent course without assuming a Tutor helper method.
     * Tutor's lesson normally belongs to a topic, and the topic belongs to a
     * course. We first use Tutor's utility when available and fall back to
     * walking the WP post hierarchy only when it is sufficient.
     */
    public function get_course_id($lesson_id)
    {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) {
            return 0;
        }

        if (function_exists('tutor_utils')) {
            $utils = tutor_utils();

            if (is_object($utils) && method_exists($utils, 'get_course_id_by_subcontent')) {
                $course_id = absint($utils->get_course_id_by_subcontent($lesson_id));
                if ($course_id) {
                    return $course_id;
                }
            }
        }

        $parent_id = absint(wp_get_post_parent_id($lesson_id));
        if (!$parent_id) {
            return 0;
        }

        $parent = get_post($parent_id);
        if (!$parent) {
            return 0;
        }

        if (function_exists('tutor') && isset(tutor()->course_post_type)) {
            $course_post_type = (string) tutor()->course_post_type;
            if ($course_post_type === $parent->post_type) {
                return $parent_id;
            }
        }

        // A Topic is commonly the immediate parent of a Lesson. If it is not
        // itself the course, walk one more level rather than inventing data.
        $course_id = absint(wp_get_post_parent_id($parent_id));
        if (!$course_id) {
            return 0;
        }

        if (function_exists('tutor') && isset(tutor()->course_post_type)) {
            $course_post_type = (string) tutor()->course_post_type;
            $course = get_post($course_id);
            return ($course && $course_post_type === $course->post_type) ? $course_id : 0;
        }

        return $course_id;
    }
}
