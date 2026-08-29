<?php
namespace MathCourse\Progress;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;

/**
 * Learning progress service.
 *
 * Rules:
 * - Server stores only lesson completed status.
 * - Playback position is stored in browser localStorage.
 * - No playback seconds are uploaded.
 */
class Progress_Service {

    private $access;

    public function __construct() {
        $this->access = new Access_Service();
    }

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

        $lessons = get_posts(array(
            'post_type' => 'mc_lesson',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'course_id',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));

        $total = count($lessons);
        $completed = 0;
        $last_lesson_id = 0;

        $completed_lessons = get_user_meta(
            $user_id,
            'mc_completed_lessons',
            true
        );

        if (!is_array($completed_lessons)) {
            $completed_lessons = array();
        }

        foreach ($lessons as $lesson) {

            if (in_array($lesson->ID, $completed_lessons)) {
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
}
