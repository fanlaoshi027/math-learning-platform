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
    if ($this->is_available() && !empty(tutor()->course_post_type)) {
        return (string) tutor()->course_post_type;
    }

    if (class_exists('\Tutor\Models\CourseModel')) {
        return (string) \Tutor\Models\CourseModel::POST_TYPE;
    }

    return '';
}

        // 兼容 Tutor LMS 4.x：
        // 某些版本未通过 tutor()->course_post_type 暴露课程 Post Type。
        // 从 WordPress 当前注册的 Post Type 中寻找包含 course 的类型。
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
        if (!$post || $post->post_type !== $this->course_post_type()) return null;

        return $post;
    }

    public function get_topics($course_id)
    {
        $course_id = absint($course_id);
        if (!$course_id || !$this->is_available()) return array();

        $query = tutor_utils()->get_topics($course_id);

        return ($query instanceof \WP_Query && !empty($query->posts))
            ? $query->posts
            : array();
    }

    public function get_lessons($topic_id)
    {
        $topic_id = absint($topic_id);
        if (!$topic_id || !$this->is_available()) return array();

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

    /** Create a Tutor course using the WordPress post type exposed by Tutor. */
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
            'post_status'  => in_array(
                $status,
                array('draft', 'publish', 'private'),
                true
            ) ? $status : 'draft',
            'post_author'  => get_current_user_id(),
        )), true);

        if (is_wp_error($course_id)) {
            return $course_id;
        }

        return $this->get_course($course_id);
    }

    /** Create a Tutor topic as a child of a Course. */
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

    /** Create a Tutor lesson using the same post fields used by Tutor's builder. */
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

    /** Batch-create one Topic and a sequential page-numbered set of Lessons. */
    public function create_page_lessons($course_id, $topic_title, $start_page, $end_page)
    {
        $start_page = absint($start_page);
        $end_page = absint($end_page);

        if ($start_page < 1 || $end_page < $start_page) {
            return new \WP_Error(
                'mathcourse_invalid_page_range',
                '页码范围无效。'
            );
        }

        $topic = $this->create_topic($course_id, $topic_title);

        if (is_wp_error($topic)) {
            return $topic;
        }

        $lessons = array();

        for ($page = $start_page; $page <= $end_page; $page++) {
            $lesson = $this->create_lesson(
                $topic->ID,
                '第 ' . $page . ' 页',
                '',
                $page
            );

            if (is_wp_error($lesson)) {
                return new \WP_Error(
                    'mathcourse_batch_lesson_failed',
                    '创建第 ' . $page . ' 页失败。',
                    array(
                        'topic_id'       => $topic->ID,
                        'created_lessons' => count($lessons),
                        'error'          => $lesson->get_error_message()
                    )
                );
            }

            $lessons[] = $lesson;
        }

        return array(
            'course'  => $course_id,
            'topic'   => $topic,
            'lessons' => $lessons
        );
    }

    private function next_topic_order($course_id)
    {
        if (
            function_exists('tutor_utils') &&
            method_exists(tutor_utils(), 'get_next_topic_order_id')
        ) {
            return (int) tutor_utils()->get_next_topic_order_id($course_id);
        }

        $orders = get_posts(array(
            'post_type'      => $this->topic_post_type(),
            'post_parent'    => absint($course_id),
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'orderby'        => 'menu_order',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ));

        return $orders
            ? ((int) get_post_field('menu_order', $orders[0]) + 1)
            : 0;
    }

    private function next_lesson_order($topic_id)
    {
        if (
            function_exists('tutor_utils') &&
            method_exists(tutor_utils(), 'get_next_course_content_order_id')
        ) {
            return (int) tutor_utils()->get_next_course_content_order_id($topic_id);
        }

        $orders = get_posts(array(
            'post_type'      => $this->lesson_post_type(),
            'post_parent'    => absint($topic_id),
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'orderby'        => 'menu_order',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ));

        return $orders
            ? ((int) get_post_field('menu_order', $orders[0]) + 1)
            : 0;
    }
}
