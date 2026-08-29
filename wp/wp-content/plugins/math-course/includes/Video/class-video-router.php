<?php
namespace MathCourse\Video;

defined('ABSPATH') || exit;

class Video_Router
{
    public function __construct()
    {
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

        header('Content-Type: application/vnd.apple.mpegurl');
        header('Cache-Control: private, no-store');

        echo esc_url_raw($url);
        exit;
    }
}
