<?php
namespace MathCourse\Progress;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;

/**
 * Learning progress service.
 *
 * Server stores lesson completion only.
 * Video playback position remains local.
 */
class Progress_Service {

    private $access;

    public function __construct() {
        $this->access = new Access_Service();
    }

    /**
     * 获取课程学习进度
     */
    public function get_course_progress($course_id, $user_id = 0) {

        $course_id = absint($course_id);
        $user_id   = absint($user_id);

        if (!$course_id || !$user_id || !$this->access->has_access($user_id, $course_id)) {
            return array(
                'completed' => 0,
                'total' => 0,
                'percent' => 0,
                'last_lesson_id' => 0
            );
        }

        $lessons = $this->get_course_lessons($course_id);

        $completed_lessons = $this->get_completed_lessons($user_id);

        $total = count($lessons);
        $completed = 0;
        $last_lesson_id = 0;

        foreach ($lessons as $lesson) {

            if (in_array((int)$lesson->ID, $completed_lessons, true)) {
                $completed++;
                $last_lesson_id = (int)$lesson->ID;
            }
        }

        return array(
            'completed' => $completed,
            'total' => $total,
            'percent' => $total ? round(($completed / $total) * 100) : 0,
            'last_lesson_id' => $last_lesson_id
        );
    }


    /**
     * 标记课时完成
     */
    public function complete_lesson($user_id, $lesson_id) {

        $user_id = absint($user_id);
        $lesson_id = absint($lesson_id);

        if (!$user_id || !$lesson_id) {
            return false;
        }

        $completed = $this->get_completed_lessons($user_id);

        if (!in_array($lesson_id, $completed, true)) {
            $completed[] = $lesson_id;
        }

        return update_user_meta(
            $user_id,
            'mc_completed_lessons',
            array_map('intval', $completed)
        );
    }


    /**
     * 取消完成状态
     */
    public function uncomplete_lesson($user_id, $lesson_id) {

        $completed = $this->get_completed_lessons($user_id);

        $completed = array_diff(
            $completed,
            array(absint($lesson_id))
        );

        return update_user_meta(
            $user_id,
            'mc_completed_lessons',
            array_values($completed)
        );
    }


    /**
     * 判断课时是否完成
     */
    public function is_completed($user_id, $lesson_id) {

        return in_array(
            absint($lesson_id),
            $this->get_completed_lessons($user_id),
            true
        );
    }


    private function get_completed_lessons($user_id) {

        $data = get_user_meta(
            absint($user_id),
            'mc_completed_lessons',
            true
        );

        return is_array($data) ? array_map('intval', $data) : array();
    }


    private function get_course_lessons($course_id) {

        return get_posts(array(
            'post_type' => 'mc_lesson',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'course_id',
                    'value' => absint($course_id),
                    'compare' => '='
                )
            ),
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
    }
}
