<?php
namespace MathCourse\Progress;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;
use MathCourse\Access\Access_Service;

/**
 * Unified learning-progress service.
 * Tutor LMS remains the source of truth for lesson completion.
 */
class Progress_Service {
    private $tutor;
    private $access;

    public function __construct() {
        $this->tutor = new Adapter();
        $this->access = new Access_Service();
    }

    public function get_course_progress($course_id, $user_id = 0) {
        $course_id = absint($course_id);
        $user_id = absint($user_id);
        if (!$course_id || !$user_id || !$this->access->has_access($user_id, $course_id)) {
            return array('completed' => 0, 'total' => 0, 'percent' => 0, 'last_lesson_id' => 0);
        }

        $total = 0;
        $completed = 0;
        $last_lesson_id = 0;
        foreach ($this->tutor->get_topics($course_id) as $topic) {
            foreach ($this->tutor->get_lessons($topic->ID) as $lesson) {
                $total++;
                if ($this->tutor->is_lesson_completed($lesson->ID, $user_id)) {
                    $completed++;
                    $last_lesson_id = (int) $lesson->ID;
                }
            }
        }

        return array(
            'completed' => $completed,
            'total' => $total,
            'percent' => $total ? round(($completed / $total) * 100) : 0,
            'last_lesson_id' => $last_lesson_id,
        );
    }
}
