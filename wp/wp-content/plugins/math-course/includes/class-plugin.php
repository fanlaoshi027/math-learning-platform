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
        if (class_exists('MathCourse\\Admin\\Menu')) {
            new Admin\Menu();
        }

        $access_service = class_exists('MathCourse\\Access\\Access_Service')
            ? new Access\Access_Service()
            : null;

        if (class_exists('MathCourse\\Tutor\\Hooks')) {
            new Tutor\Hooks();
        }

        $video_service = class_exists('MathCourse\\Video\\Video_Service')
            ? new Video\Video_Service()
            : null;

        $token = class_exists('MathCourse\\Video\\Token')
            ? new Video\Token()
            : null;

        if (class_exists('MathCourse\\Video\\Player')) {
            new Video\Player();
        }

        if ($video_service && $access_service && $token) {
            if (class_exists('MathCourse\\Video\\Video_Endpoints')) {
                (new Video\Video_Endpoints($token, $video_service, $access_service))->register();
            }

            $storage = class_exists('MathCourse\\Video\\HLS_Storage')
                ? new Video\HLS_Storage()
                : null;

            if ($storage && class_exists('MathCourse\\Video\\Key')) {
                (new Video\Key($token, $video_service, $storage))->register();
            }

            if ($storage && class_exists('MathCourse\\Video\\HLS_Endpoints')) {
                (new Video\HLS_Endpoints($token, $video_service, $storage))->register();
            }
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
                array(),
                MATHCOURSE_VERSION,
                true
            );
        });
    }
}
