<?php
namespace MathCourse\Video;

use MathCourse\Access\Access_Service;

defined('ABSPATH') || exit;

class Video_Router
{
    private $access;

    public function __construct()
    {
        $this->access = new Access_Service();

        add_action('init', array($this, 'register_route'));
        add_action('template_redirect', array($this, 'handle'));
    }

    public function register_route()
    {
        add_rewrite_rule(
            '^math-video/([0-9]+)/?$',
            'index.php?math_video=$matches[1]',
            'top'
        );

        add_rewrite_tag('%math_video%', '([0-9]+)');
    }

    public function handle()
    {
        $lesson_id = absint(get_query_var('math_video'));

        if (!$lesson_id) {
            return;
        }

        $url = get_post_meta($lesson_id, '_mathcourse_video_url', true);

        if (!$url) {
            wp_die('视频不存在');
        }

        // 试看课：允许访问
        $is_trial = get_post_meta($lesson_id, '_mathcourse_video_trial', true);

        $allow = (bool)$is_trial;

        // 已登录用户：检查课程授权
        if (!$allow && is_user_logged_in()) {

            $course_id = 0;

            if (function_exists('tutor_utils')) {
                $course_id = tutor_utils()->get_course_id_by_content($lesson_id);
            }

            if ($course_id) {
                $allow = $this->access->has_access(
                    get_current_user_id(),
                    $course_id
                );
            }
        }

        if (!$allow) {
            status_header(403);
            wp_die('暂无观看权限，请联系老师开通课程。');
        }

        header('Content-Type: application/vnd.apple.mpegurl');
        header('Cache-Control: private, no-store');

        echo esc_url_raw($url);
        exit;
    }
}
