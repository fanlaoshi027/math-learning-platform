<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * Tutor LMS compatibility boundary.
 *
 * Tutor LMS 4.0.4 stores Course -> Topic -> Lesson as WordPress posts.
 * All Tutor-specific assumptions stay inside this adapter.
 */
class Adapter
{
    public function is_available()
    {
        return function_exists('tutor') && function_exists('tutor_utils') && defined('TUTOR_VERSION');
    }

    public function version()
    {
        return defined('TUTOR_VERSION') ? TUTOR_VERSION : '';
    }

    public function course_post_type()
    {
        // 优先读取 Tutor LMS 官方属性
        if ($this->is_available() && !empty(tutor()->course_post_type)) {
            return (string) tutor()->course_post_type;
        }

        // 兼容 Tutor LMS 4.x
        // 部分版本没有暴露 tutor()->course_post_type
        // 从 WordPress 注册类型中自动寻找 Course
        $types = get_post_types();

        foreach ($types as $type) {
            if (stripos($type, 'course') !== false) {
                return $type;
            }
        }

        return '';
    }

    public function lesson_post_type()
    {
        return $this->is_available() && isset(tutor()->lesson_post_type)
            ? (string) tutor()->lesson_post_type
            : '';
    }

    public function topic_post_type()
    {
        return $this->is_available() ? 'topics' : '';
    }

    public function get_course($course_id)
    {
        $course_id = absint($course_id);
        if (!$course_id || !$this->is_available()) return null;

        $post = get_post($course_id);

        if (!$post || $post->post_type !== $this->course_post_type()) {
            return null;
        }

        return $post;
    }

    public function get_topics($course_id)
    {
        $course_id = absint($course_id);

        if (!$course_id || !$this->is_available()) {
            return array();
        }

        $query = tutor_utils()->get_topics($course_id);

        return ($query instanceof \WP_Query && !empty($query->posts))
            ? $query->posts
            : array();
    }

    public function get_lessons($topic_id)
    {
        $topic_id = absint($topic_id);

        if (!$topic_id || !$this->is_available()) {
            return array();
        }

        return get_posts(array(
            'post_type'      => $this->lesson_post_type(),
            'post_parent'    => $topic_id,
            'post_status'    => 'any',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => -1,
        ));
    }

    public function get_course_tree($course_id)
    {
        $course = $this->get_course($course_id);

        if (!$course) return array();

        $tree = array(
            'course' => $course,
            'topics' => array()
        );

        foreach ($this->get_topics($course_id) as $topic) {

            $tree['topics'][] = array(
                'topic'   => $topic,
                'lessons' => $this->get_lessons($topic->ID),
            );

        }

        return $tree;
    }

    public function create_course($title, $content = '', $status = 'draft')
    {
        if (!$this->is_available() || !$this->course_post_type()) {
            return new \WP_Error(
                'mathcourse_tutor_unavailable',
                'Tutor LMS 不可用。'
            );
        }

        $title = sanitize_text_field($title);

        if ($title === '') {
            return new \WP_Error(
                'mathcourse_invalid_course_title',
                '课程名称不能为空。'
            );
        }

        $course_id = wp_insert_post(wp_slash(array(
            'post_type'    => $this->course_post_type(),
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => in_array($status, array('draft','publish','private'), true)
                ? $status
                : 'draft',
            'post_author'  => get_current_user_id(),
        )), true);

        if (is_wp_error($course_id)) {
            return $course_id;
        }

        return $this->get_course($course_id);
    }

    public function create_topic($course_id, $title, $content = '')
    {
        $course = $this->get_course($course_id);

        if (!$course) {
            return new \WP_Error(
                'mathcourse_invalid_course',
                'Tutor 课程不存在。'
            );
        }

        $title = sanitize_text_field($title);

        if ($title === '') {
            return new \WP_Error(
                'mathcourse_invalid_topic_title',
                'Topic 名称不能为空。'
            );
        }

        $topic_id = wp_insert_post(wp_slash(array(
            'post_type'    => $this->topic_post_type(),
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_parent'  => $course_id,
            'post_author'  => get_current_user_id(),
            'menu_order'   => $this->next_topic_order($course_id),
        )), true);

        return is_wp_error($topic_id)
            ? $topic_id
            : get_post($topic_id);
    }

    public function create_lesson($topic_id, $title, $content = '', $page_number = 0)
    {
        $topic_id = absint($topic_id);

        if (!$topic_id || get_post_type($topic_id) !== $this->topic_post_type()) {
            return new \WP_Error(
                'mathcourse_invalid_topic',
                'Tutor Topic 不存在。'
            );
        }

        $title = sanitize_text_field($title);

        if ($title === '') {
            return new \WP_Error(
                'mathcourse_invalid_lesson_title',
                'Lesson 名称不能为空。'
            );
        }

        $lesson_id = wp_insert_post(wp_slash(array(
            'post_type'      => $this->lesson_post_type(),
            'post_title'     => $title,
            'post_name'      => sanitize_title($title),
            'post_content'   => $content,
            'post_status'    => 'publish',
            'comment_status' => 'open',
            'post_author'    => get_current_user_id(),
            'post_parent'    => $topic_id,
            'menu_order'     => $this->next_lesson_order($topic_id),
        )), true);

        if (is_wp_error($lesson_id)) {
            return $lesson_id;
        }

        if ($page_number > 0) {
            update_post_meta(
                $lesson_id,
                'page_number',
                absint($page_number)
            );
        }

        do_action('tutor/lesson/created', $lesson_id);

        return get_post($lesson_id);
    }

    private function next_topic_order($course_id)
    {
        return 0;
    }

    private function next_lesson_order($topic_id)
    {
        return 0;
    }
}
