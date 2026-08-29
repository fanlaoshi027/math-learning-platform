<?php
namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * MathCourse 与 Tutor LMS 的统一数据适配层。
 * 不修改 Tutor LMS 源码。
 */
class Adapter {

    public function is_available() {
        return function_exists('tutor');
    }

    public function get_course($course_id) {
        $course = get_post(absint($course_id));
        if (!$course || !$this->is_available() || tutor()->course_post_type !== $course->post_type) {
            return null;
        }
        return $course;
    }

    public function get_topics($course_id) {
        return get_posts(array(
            'post_type'      => 'topics',
            'post_parent'    => absint($course_id),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'ID' => 'ASC'),
        ));
    }

    public function get_lessons($topic_id) {
        $lesson_post_type = $this->is_available() ? tutor()->lesson_post_type : 'lesson';

        return get_posts(array(
            'post_type'      => $lesson_post_type,
            'post_parent'    => absint($topic_id),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'ID' => 'ASC'),
        ));
    }

    public function get_course_lesson_count($course_id) {
        $count = 0;
        foreach ($this->get_topics($course_id) as $topic) {
            $count += count($this->get_lessons($topic->ID));
        }
        return $count;
    }

    public function get_lesson_course_id($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) {
            return 0;
        }

        $lesson = get_post($lesson_id);
        if (!$lesson || !$this->is_available() || tutor()->lesson_post_type !== $lesson->post_type) {
            return 0;
        }

        $topic = get_post($lesson->post_parent);
        if (!$topic || 'topics' !== $topic->post_type) {
            return 0;
        }

        $course = $this->get_course($topic->post_parent);
        return $course ? (int) $course->ID : 0;
    }

    public function get_lesson_page_number($lesson_id) {
        $lesson_id = absint($lesson_id);
        $value = get_post_meta($lesson_id, '_mathcourse_page_number', true);

        // 兼容当前 MathCourse 后台早期版本写入的 _mathcourse_page。
        if ('' === (string) $value) {
            $value = get_post_meta($lesson_id, '_mathcourse_page', true);
        }

        return sanitize_text_field($value);
    }

    public function get_lesson_video_id($lesson_id) {
        $lesson_id = absint($lesson_id);
        $value = get_post_meta($lesson_id, '_mathcourse_video_id', true);

        // 兼容当前 MathCourse 后台早期版本写入的 _mathcourse_video。
        if ('' === (string) $value) {
            $value = get_post_meta($lesson_id, '_mathcourse_video', true);
        }

        return sanitize_text_field($value);
    }

    public function is_preview_lesson($lesson_id) {
        return 'yes' === get_post_meta(absint($lesson_id), '_mathcourse_preview', true);
    }

    public function get_course_progress($course_id, $user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        $total = 0;
        $completed = 0;

        foreach ($this->get_topics($course_id) as $topic) {
            foreach ($this->get_lessons($topic->ID) as $lesson) {
                $total++;
                if ($this->is_lesson_completed($lesson->ID, $user_id)) {
                    $completed++;
                }
            }
        }

        return array(
            'completed' => $completed,
            'total'     => $total,
            'percent'   => $total ? round(($completed / $total) * 100) : 0,
        );
    }

    public function is_lesson_completed($lesson_id, $user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        if (!$user_id || !function_exists('tutor_utils')) {
            return false;
        }
        return (bool) tutor_utils()->is_completed_lesson(absint($lesson_id), $user_id);
    }
}
