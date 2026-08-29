<?php

namespace MathCourse\Video;

use MathCourse\Access\Access_Service;

defined('ABSPATH') || exit;

class Player
{

    private $access;

    public function __construct()
    {
        $this->access = new Access_Service();

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

        if ($lesson_id && !$this->can_play($lesson_id)) {
            return '<div class="mathcourse-video-lock">🔒 本课暂未开放，请联系老师获取学习权限。</div>';
        }

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


    private function can_play($lesson_id)
    {
        $course_id = tutor_utils()->get_course_id_by_content($lesson_id);

        if (!$course_id) {
            return false;
        }

        $user_id = get_current_user_id();

        // 试看课无需授权
        if (get_post_meta($lesson_id, '_mathcourse_video_trial', true)) {
            return true;
        }

        // 已授权课程
        if ($user_id && $this->access->has_access($user_id, $course_id)) {
            return true;
        }

        return false;
    }

}
