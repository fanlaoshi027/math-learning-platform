<?php
namespace MathCourse\Learning;

defined('ABSPATH') || exit;

use MathCourse\Progress\Progress_Service;

/**
 * 课时状态展示层。
 * 完成状态统一读取 MathCourse Progress Service，避免与 Tutor LMS 使用不同数据源。
 */
class Lesson_Status {
    public static function get($lesson_id, $user_id = 0) {
        $lesson_id = absint($lesson_id);
        $user_id   = absint($user_id);

        if ($lesson_id && $user_id) {
            $progress = new Progress_Service();
            if ($progress->is_completed($user_id, $lesson_id)) {
                return array(
                    'type'  => 'completed',
                    'label' => '已完成',
                    'icon'  => '✅',
                    'allow' => true,
                );
            }
        }

        $trial = get_post_meta($lesson_id, '_mathcourse_video_trial', true);
        if ($trial) {
            return array(
                'type'  => 'trial',
                'label' => '免费试听',
                'icon'  => '🟢',
                'allow' => true,
            );
        }

        return array(
            'type'  => 'normal',
            'label' => '开始学习',
            'icon'  => '▶',
            'allow' => true,
        );
    }
}
