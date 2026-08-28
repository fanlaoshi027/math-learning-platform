<?php

namespace MathCourse;

defined('ABSPATH') || exit;

class Plugin
{
    public function run()
    {
        $this->load_modules();
        $this->load_assets();
    }

    private function load_modules()
    {
        // 后台菜单
        if (class_exists('MathCourse\\Admin\\Menu')) {
            (new Admin\Menu())->register();
        }

        // 授权服务
        if (class_exists('MathCourse\\Access\\Access_Service')) {
            (new Access\Access_Service());
        }

        // Tutor LMS 对接
        if (class_exists('MathCourse\\Tutor\\Hooks')) {
            (new Tutor\Hooks());
        }

        // 视频播放器
        if (class_exists('MathCourse\\Video\\Player')) {
            (new Video\Player());
        }
    }

    private function load_assets()
    {
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_style(
                'mathcourse',
                MATHCOURSE_URL . 'assets/css/mathcourse.css',
                array(),
                MATHCOURSE_VERSION
            );

            wp_enqueue_script(
                'mathcourse-player',
                MATHCOURSE_URL . 'assets/js/player.js',
                array('jquery'),
                MATHCOURSE_VERSION,
                true
            );
        });
    }
}
