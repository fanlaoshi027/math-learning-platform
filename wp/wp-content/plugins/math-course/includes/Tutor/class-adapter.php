<?php
namespace MathCourse\Tutor;
defined('ABSPATH') || exit;

/**
 * Thin adapter around Tutor LMS data used by MathCourse frontend services.
 * No Tutor LMS core files are modified.
 */
class Adapter {
    public function is_available() { return function_exists('tutor'); }

    public function get_course($course_id) {
        $course = get_post(absint($course_id));
        if (!$course || !$this->is_available() || tutor()->course_post_type !== $course->post_type) return null;
        return $course;
    }

    public function get_topics($course_id) {
        return get_posts(array(
            'post_type' => 'topics', 'post_parent' => absint($course_id),
            'post_status' => 'publish', 'posts_per_page' => -1,
            'orderby' => array('menu_order' => 'ASC', 'ID' => 'ASC'),
        ));
    }

    public function get_lessons($topic_id) {
        return get_posts(array(
            'post_type' => 'lesson', 'post_parent' => absint($topic_id),
            'post_status' => 'publish', 'posts_per_page' => -1,
            'orderby' => array('menu_order' => 'ASC', 'ID' => 'ASC'),
        ));
    }

    public function is_lesson_completed($lesson_id, $user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        if (!$user_id || !function_exists('tutor_utils')) return false;
        return (bool) tutor_utils()->is_completed_lesson(absint($lesson_id), $user_id);
    }
}
