<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

class Hooks {
    public function __construct() {
        add_filter('the_content', array($this, 'protect_content'));
        add_action('save_post', array($this, 'sync_lesson_preview_meta'), 99, 3);
    }

    public function protect_content($content) {
        if (!is_singular()) {
            return $content;
        }

        global $post;
        if (!$post) {
            return $content;
        }

        $adapter = new Adapter();

        if ($adapter->is_preview_lesson($post->ID)) {
            return $content;
        }

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $course_id = $adapter->get_lesson_course_id($post->ID);

            if ($course_id && class_exists('MathCourse\\Access\\Access_Service')) {
                $service = new \MathCourse\Access\Access_Service();
                if ($service->has_access($user_id, $course_id)) {
                    return $content;
                }
            }
        }

        return '<div class="mathcourse-lock">本课程需要授权后学习，请联系老师开通。</div>';
    }

    /**
     * 保持 MathCourse 早期字段与 Tutor LMS 4.0.4 原生 _is_preview 一致。
     */
    public function sync_lesson_preview_meta($post_id, $post, $update) {
        unset($update);

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$post) {
            return;
        }

        $adapter = new Adapter();
        if (!$adapter->is_lesson_post_type($post->post_type)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $mathcourse_value = get_post_meta($post_id, '_mathcourse_preview', true);
        $native_value = get_post_meta($post_id, '_is_preview', true);

        if ('yes' === $mathcourse_value || 'no' === $mathcourse_value) {
            if ($native_value !== $mathcourse_value) {
                update_post_meta($post_id, '_is_preview', $mathcourse_value);
            }
            return;
        }

        if ('yes' === $native_value || 'no' === $native_value) {
            update_post_meta($post_id, '_mathcourse_preview', $native_value);
        }
    }
}
