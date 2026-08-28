<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * Tutor LMS compatibility boundary.
 *
 * Phase v0.2 deliberately starts with capability detection and read-only
 * inspection. We do not guess Tutor LMS internal write APIs here.
 */
class Adapter
{
    public function is_available()
    {
        return function_exists('tutor') && defined('TUTOR_VERSION');
    }

    public function version()
    {
        return defined('TUTOR_VERSION') ? TUTOR_VERSION : '';
    }

    public function course_post_type()
    {
        if (!$this->is_available()) {
            return '';
        }

        $tutor = tutor();
        return isset($tutor->course_post_type) ? (string) $tutor->course_post_type : '';
    }

    public function lesson_post_type()
    {
        if (!$this->is_available()) {
            return '';
        }

        $tutor = tutor();
        return isset($tutor->lesson_post_type) ? (string) $tutor->lesson_post_type : '';
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

    public function get_topics($course_id)
    {
        $course_id = absint($course_id);
        if (!$course_id || !$this->is_available()) {
            return array();
        }

        $url = rest_url('tutor/v1/topics');
        $response = wp_remote_get(add_query_arg('course_id', $course_id, $url), array('timeout' => 10));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : array();
    }

    public function get_lessons($topic_id)
    {
        $topic_id = absint($topic_id);
        if (!$topic_id || !$this->is_available()) {
            return array();
        }

        $url = rest_url('tutor/v1/lessons');
        $response = wp_remote_get(add_query_arg('topic_id', $topic_id, $url), array('timeout' => 10));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : array();
    }
}
