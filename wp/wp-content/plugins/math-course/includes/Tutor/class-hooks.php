<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * WordPress/Tutor integration boundary.
 *
 * Keep Tutor-specific rendering decisions here instead of spreading them
 * through templates and business services.
 */
class Hooks
{
    public function __construct()
    {
        add_filter('the_content', array($this, 'protect_content'), 20);
    }

    public function protect_content($content)
    {
        if (is_admin() || !is_singular()) {
            return $content;
        }

        global $post;
        if (!$post || !$this->is_tutor_lesson($post->ID)) {
            return $content;
        }

        $access = new Lesson_Access();
        if (!$access->check($post->ID)) {
            return $this->locked_content();
        }

        // Keep the Tutor lesson body intact. The video renderer is deliberately
        // an action so the player implementation remains replaceable.
        ob_start();
        do_action('mathcourse_video');
        $video = ob_get_clean();

        if ($video === '') {
            return $content;
        }

        return $video . $content;
    }

    private function is_tutor_lesson($lesson_id)
    {
        if (!function_exists('tutor')) {
            return false;
        }

        $post_type = isset(tutor()->lesson_post_type) ? (string) tutor()->lesson_post_type : '';
        return $post_type !== '' && get_post_type($lesson_id) === $post_type;
    }

    private function locked_content()
    {
        if (!is_user_logged_in()) {
            $message = '请登录后学习本课程。';
        } else {
            $message = '本课程需要授权后学习，请联系老师开通。';
        }

        return '<div class="mathcourse-lock" role="status">' . esc_html($message) . '</div>';
    }
}
