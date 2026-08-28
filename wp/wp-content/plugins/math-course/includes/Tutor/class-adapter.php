<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * Tutor LMS compatibility boundary.
 *
 * Read-only in v0.2. Writes will be added only after the real Tutor
 * Course -> Topic -> Lesson chain has been verified on the target site.
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
        return $this->is_available() && isset(tutor()->course_post_type)
            ? (string) tutor()->course_post_type
            : '';
    }

    public function lesson_post_type()
    {
        return $this->is_available() && isset(tutor()->lesson_post_type)
            ? (string) tutor()->lesson_post_type
            : '';
    }

    public function topic_post_type()
    {
        return 'topics';
    }

    public function get_course($course_id)
    {
        $course_id = absint($course_id);
        if (!$course_id || !$this->is_available()) {
            return null;
        }

        $post = get_post($course_id);
        if (!$post || $post->post_type !== $this->course_post_type()) {
            return null;
        }

        return $post;
    }

    /**
     * Return Tutor topics as WP_Post objects in Tutor's menu order.
     */
    public function get_topics($course_id)
    {
        $course_id = absint($course_id);
        if (!$course_id || !$this->is_available()) {
            return array();
        }

        $query = tutor_utils()->get_topics($course_id);
        return ($query instanceof \WP_Query && !empty($query->posts)) ? $query->posts : array();
    }

    /**
     * Return lessons belonging to one Tutor topic, preserving menu order.
     */
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

    /**
     * Read the complete Tutor course content tree without inventing a new
     * relationship table in MathCourse.
     */
    public function get_course_tree($course_id)
    {
        $course = $this->get_course($course_id);
        if (!$course) {
            return array();
        }

        $tree = array(
            'course' => $course,
            'topics' => array(),
        );

        foreach ($this->get_topics($course_id) as $topic) {
            $tree['topics'][] = array(
                'topic'   => $topic,
                'lessons' => $this->get_lessons($topic->ID),
            );
        }

        return $tree;
    }
}
