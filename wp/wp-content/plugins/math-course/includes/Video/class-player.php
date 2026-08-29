<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

class Player
{

    public function __construct()
    {
        add_shortcode(
            'mathcourse_video',
            array(
                $this,
                'render'
            )
        );
    }


    public function render($atts)
    {
        $atts = shortcode_atts(
            array(
                'url' => '',
                'lesson_id' => ''
            ),
            $atts
        );

        if (empty($atts['url'])) {
            return '';
        }

        $lesson_id = absint($atts['lesson_id']);

        ob_start();
        ?>

        <video
            class="mathcourse-player"
            <?php if ($lesson_id): ?>
            data-lesson-id="<?php echo esc_attr($lesson_id); ?>"
            <?php endif; ?>
            controls
            preload="metadata">

            <source
                src="<?php echo esc_url($atts['url']); ?>"
                type="application/x-mpegURL">

        </video>

        <?php
        return ob_get_clean();
    }

}
