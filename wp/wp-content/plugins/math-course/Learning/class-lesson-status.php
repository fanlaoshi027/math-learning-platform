<?php
namespace MathCourse\Learning;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Progress\Progress_Service;

/**
 * 课时状态展示层。
 * 完成状态统一读取 Progress Service，访问状态统一读取 Access Service。
 */
class Lesson_Status {
    public static function get($lesson_id, $user_id = 0) {
        $lesson_id = absint($lesson_id);
        $user_id   = absint($user_id);

        if (!$lesson_id) {
            return array(
                'type'  => 'normal',
                'label' => '开始学习',
                'icon'  => '▶',
                'allow' => false,
            );
        }

        $progress = new Progress_Service();
        $course_id = $progress->get_lesson_course_id($lesson_id);
        if (!$course_id) {
            return array(
                'type'  => 'normal',
                'label' => '开始学习',
                'icon'  => '▶',
                'allow' => false,
            );
        }

        $access = new Access_Service();
        $is_preview = $access->can_preview($course_id, $lesson_id);
        $can_watch = $user_id ? $access->can_watch_lesson($user_id, $course_id, $lesson_id) : false;
        $has_access = $is_preview || $can_watch;

        // 完成状态只描述学习记录；allow 仍必须经过当前实时访问权限。
        // 这样即使用户授权后来被撤销，历史“已完成”也不会变成可绕过权限的入口。
        if ($user_id && $progress->is_completed($user_id, $lesson_id)) {
            return array(
                'type'  => 'completed',
                'label' => '已完成',
                'icon'  => '✅',
                'allow' => $has_access,
            );
        }

        if ($is_preview) {
            return array(
                'type'  => 'trial',
                'label' => '免费试听',
                'icon'  => '🟢',
                'allow' => true,
            );
        }

        if ($can_watch) {
            return array(
                'type'  => 'normal',
                'label' => '开始学习',
                'icon'  => '▶',
                'allow' => true,
            );
        }

        return array(
            'type'  => 'locked',
            'label' => '需要授权',
            'icon'  => '🔒',
            'allow' => false,
        );
    }
}
