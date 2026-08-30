<?php
namespace MathCourse\Learning;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

class Lesson_Status {

    public static function get($lesson_id, $user_id = 0) {

        if ($user_id) {
            $adapter = new Adapter();
            if ($adapter->is_lesson_completed($lesson_id, $user_id)) {
                return array(
                    'type' => 'completed',
                    'label' => '已完成',
                    'icon'  => '✅',
                    'allow' => true,
                );
            }
        }

        $trial = get_post_meta($lesson_id, '_mathcourse_video_trial', true);

        if ($trial) {
            return array(
                'type' => 'trial',
                'label' => '免费试听',
                'icon'  => '🟢',
                'allow' => true,
            );
        }

        return array(
            'type' => 'normal',
            'label' => '开始学习',
            'icon'  => '▶',
            'allow' => true,
        );
    }
}
