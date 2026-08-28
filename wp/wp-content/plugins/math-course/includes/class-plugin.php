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

        $access_service = null;
        if (class_exists('MathCourse\\Access\\Access_Service')) {
            $access_service = new Access\Access_Service();
        }

        if (class_exists('MathCourse\\Tutor\\Hooks')) {
            new Tutor\Hooks();
        }

        $video_service = null;
        if (class_exists('MathCourse\\Video\\Video_Service')) {
            $video_service = new Video\Video_Service();
        }

        $token = null;
        if (class_exists('MathCourse\\Video\\Token')) {
            $token = new Video\Token();
        }

        if (class_exists('MathCourse\\Video\\Player')) {
            new Video\Player();
        }

        if ($video_service && $access_service && $token) {
            if (class_exists('MathCourse\\Video\\Video_Endpoints')) {
                $endpoints = new Video\Video_Endpoints($token, $video_service, $access_service);
                $endpoints->register();
            }

            if (class_exists('MathCourse\\Video\\Key')) {
                (new Video\Key($token))->register();
            }

            if (class_exists('MathCourse\\Video\\HLS_Endpoints')) {
                (new Video\HLS_Endpoints($token, $video_service))->register();
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
                array('jquery'),
                MATHCOURSE_VERSION,
                true
            );
        });
    }
}
