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
        $course_id = absint($course_id);
        $course = $course_id ? get_post($course_id) : null;

        if (!$course || !$this->is_available() || tutor()->course_post_type !== $course->post_type) {
            return null;
        }

        return $course;
    }

    public function get_topics($course_id, $include_unpublished = true) {
        $status = $include_unpublished ? array('publish', 'draft', 'private') : array('publish');

        return get_posts(array(
            'post_type'      => 'topics',
            'post_parent'    => absint($course_id),
            'post_status'    => $status,
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'ID' => 'ASC'),
        ));
    }

    public function get_lessons($topic_id, $include_unpublished = true) {
        $lesson_post_type = $this->is_available() ? tutor()->lesson_post_type : 'lesson';
        $status = $include_unpublished ? array('publish', 'draft', 'private') : array('publish');

        return get_posts(array(
            'post_type'      => $lesson_post_type,
            'post_parent'    => absint($topic_id),
            'post_status'    => $status,
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'ID' => 'ASC'),
        ));
    }

    /**
     * 返回课程实际可见的已发布课时总数。
     * 统一走 Adapter，避免业务层重复拼装 Tutor 结构。
     */
    public function get_course_lesson_count($course_id) {
        $count = 0;
        foreach ($this->get_topics($course_id, false) as $topic) {
            $count += count($this->get_lessons($topic->ID, false));
        }
        return $count;
    }

    /**
     * 返回课程的扁平 Lesson 列表，顺序严格按照 Topic → Lesson 的 menu_order。
     */
    public function get_course_lessons($course_id, $include_unpublished = false) {
        $lessons = array();

        foreach ($this->get_topics($course_id, $include_unpublished) as $topic) {
            foreach ($this->get_lessons($topic->ID, $include_unpublished) as $lesson) {
                $lessons[] = $lesson;
            }
        }

        return $lessons;
    }

    public function get_lesson($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id || !$this->is_available()) {
            return null;
        }

        $lesson = get_post($lesson_id);
        if (!$lesson || tutor()->lesson_post_type !== $lesson->post_type) {
            return null;
        }

        return $lesson;
    }

    public function get_lesson_course_id($lesson_id) {
        $lesson = $this->get_lesson($lesson_id);
        if (!$lesson) {
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

        if ('' === (string) $value) {
            $value = get_post_meta($lesson_id, '_mathcourse_page', true);
        }

        return sanitize_text_field($value);
    }

    public function get_lesson_video_id($lesson_id) {
        $lesson_id = absint($lesson_id);
        $value = get_post_meta($lesson_id, '_mathcourse_video_id', true);

        if ('' === (string) $value) {
            $value = get_post_meta($lesson_id, '_mathcourse_video', true);
        }

        return sanitize_text_field($value);
    }

    /**
     * Tutor LMS 4.0.4 Preview 的业务适配。
     */
    public function is_preview_lesson($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) {
            return false;
        }

        $native = get_post_meta($lesson_id, '_is_preview', true);
        if ('yes' === $native || '1' === (string) $native) {
            return true;
        }

        return 'yes' === get_post_meta($lesson_id, '_mathcourse_preview', true);
    }

    public function set_lesson_preview($lesson_id, $enabled) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) {
            return false;
        }

        $value = $enabled ? 'yes' : 'no';
        update_post_meta($lesson_id, '_is_preview', $value);
        update_post_meta($lesson_id, '_mathcourse_preview', $value);

        return true;
    }

    public function get_course_progress($course_id, $user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        $total = 0;
        $completed = 0;

        foreach ($this->get_course_lessons($course_id, false) as $lesson) {
            $total++;
            if ($this->is_lesson_completed($lesson->ID, $user_id)) {
                $completed++;
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
