<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

class Player
{
    public function __construct()
    {
        add_shortcode('mathcourse_video', array($this, 'render'));
    }

    public function render($atts)
    {
        $atts = shortcode_atts(array('url' => '', 'video_id' => 0, 'lesson_id' => 0), $atts, 'mathcourse_video');
        $video_id = absint($atts['video_id']);
        $lesson_id = absint($atts['lesson_id']);
        $url = $atts['url'];
        if (!$video_id && !$url) return '';

        $config = array(
            'videoId' => $video_id,
            'lessonId' => $lesson_id,
            'source' => $url ? esc_url_raw($url) : '',
            'tokenUrl' => $video_id ? rest_url('mathcourse/v1/video/' . $video_id . '/token') : '',
            'manifestUrl' => $video_id ? rest_url('mathcourse/v1/video/' . $video_id . '/manifest') : '',
        );

        ob_start();
        ?>
        <div class="mathcourse-video-wrap" data-mathcourse-player="1">
            <video class="mathcourse-player" controls preload="metadata" playsinline></video>
            <div class="mathcourse-player-message" role="status" aria-live="polite"></div>
        </div>
        <script type="application/json" class="mathcourse-player-config"><?php echo esc_html(wp_json_encode($config)); ?></script>
        <?php
        return ob_get_clean();
    }
}
