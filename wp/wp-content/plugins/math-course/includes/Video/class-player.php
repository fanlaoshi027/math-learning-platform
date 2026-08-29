<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

class Player
{

    public function __construct()
    {
        add_shortcode(
            'mathcourse_video',
            array($this,'render')
        );

        add_action(
            'wp_enqueue_scripts',
            array($this,'assets')
        );
    }

    public function assets()
    {
        wp_enqueue_style(
            'video-js',
            'https://vjs.zencdn.net/8.10.0/video-js.css',
            array(),
            '8.10.0'
        );

        wp_enqueue_script(
            'video-js',
            'https://vjs.zencdn.net/8.10.0/video.min.js',
            array(),
            '8.10.0',
            true
        );

        wp_enqueue_script(
            'mathcourse-player',
            MATHCOURSE_URL . 'assets/js/player.js',
            array('video-js'),
            MATHCOURSE_VERSION,
            true
        );

        wp_localize_script(
            'mathcourse-player',
            'mathcoursePlayer',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mathcourse_progress_nonce')
            )
        );
    }

    public function render($atts)
    {
        $atts = shortcode_atts(
            array(
                'url' => '',
                'lesson_id' => 0,
                'course_id' => 0
            ),
            $atts
        );

        if (empty($atts['url'])) {
            return '';
        }

        $lesson_id = absint($atts['lesson_id']);
        $course_id = absint($atts['course_id']);

        ob_start();
        ?>

        <video
            id="mathcourse-player-<?php echo esc_attr($lesson_id); ?>"
            class="video-js vjs-big-play-centered"
            controls
            preload="metadata"
            playsinline
            data-lesson-id="<?php echo esc_attr($lesson_id); ?>"
            data-course-id="<?php echo esc_attr($course_id); ?>">

            <source
                src="<?php echo esc_url($atts['url']); ?>"
                type="application/x-mpegURL">

        </video>

        <?php
        return ob_get_clean();
    }

}
