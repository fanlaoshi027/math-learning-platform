<?php
namespace MathCourse\Progress;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

class Progress_Service {
    private $access;
    private $tutor;

    public function __construct() {
        $this->access = new Access_Service();
        $this->tutor  = new Adapter();
    }

    public function get_course_progress($course_id, $user_id = 0) {
        $course_id = absint($course_id);
        $user_id   = absint($user_id);
        if (!$course_id || !$user_id) return $this->empty_progress();
        return $this->calculate_progress($course_id, $user_id, false);
    }

    public function get_admin_course_progress($course_id, $user_id) {
        $course_id = absint($course_id);
        $user_id   = absint($user_id);
        if (!$course_id || !$user_id) return $this->empty_progress();
        return $this->calculate_progress($course_id, $user_id, true);
    }

    public function complete_lesson($user_id, $lesson_id, $allow_preview = false) {
        $user_id   = absint($user_id);
        $lesson_id = absint($lesson_id);
        if (!$user_id || !$lesson_id) return false;

        $course_id = $this->tutor->get_lesson_course_id($lesson_id);
        if (!$course_id) return false;

        $can_access = $this->access->can_access_course($user_id, $course_id);
        $is_preview = $this->access->can_preview($course_id, $lesson_id);
        if (!$can_access && !($allow_preview && $is_preview)) return false;

        $completed = $this->get_completed_lessons($user_id);
        if (in_array($lesson_id, $completed, true)) return true;

        $completed[] = $lesson_id;
        $completed = array_values(array_unique(array_map('intval', $completed)));
        return false !== update_user_meta($user_id, 'mc_completed_lessons', $completed);
    }

    public function uncomplete_lesson($user_id, $lesson_id) {
        $user_id   = absint($user_id);
        $lesson_id = absint($lesson_id);
        if (!$user_id || !$lesson_id) return false;

        $completed = $this->get_completed_lessons($user_id);
        if (!in_array($lesson_id, $completed, true)) return true;

        $completed = array_values(array_diff($completed, array($lesson_id)));
        return false !== update_user_meta($user_id, 'mc_completed_lessons', array_values(array_map('intval', $completed)));
    }

    public function is_completed($user_id, $lesson_id) {
        return in_array(absint($lesson_id), $this->get_completed_lessons($user_id), true);
    }

    /**
     * Returns the last completed lesson according to the current Tutor course order.
     */
    public function get_last_completed_lesson($user_id, $course_id = 0) {
        $completed = $this->get_completed_lessons($user_id);
        if (empty($completed)) return 0;

        if (!$course_id) return (int) end($completed);

        $lessons = $this->get_course_lessons(absint($course_id), false);
        $last = 0;
        foreach ($lessons as $lesson) {
            if (in_array((int) $lesson->ID, $completed, true)) $last = (int) $lesson->ID;
        }
        return $last;
    }

    private function calculate_progress($course_id, $user_id, $include_unpublished = false) {
        $lessons = $this->get_course_lessons($course_id, $include_unpublished);
        $completed_lessons = $this->get_completed_lessons($user_id);
        $total = count($lessons);
        $completed = 0;
        $last_lesson_id = 0;

        foreach ($lessons as $lesson) {
            $id = (int) $lesson->ID;
            if (in_array($id, $completed_lessons, true)) {
                $completed++;
                $last_lesson_id = $id;
            }
        }

        return array(
            'completed'       => $completed,
            'total'           => $total,
            'percent'         => $total ? round(($completed / $total) * 100) : 0,
            'last_lesson_id'  => $last_lesson_id,
        );
    }

    private function get_completed_lessons($user_id) {
        $data = get_user_meta(absint($user_id), 'mc_completed_lessons', true);
        return is_array($data) ? array_values(array_unique(array_map('intval', $data))) : array();
    }

    private function empty_progress() {
        return array(
            'completed'      => 0,
            'total'          => 0,
            'percent'        => 0,
            'last_lesson_id' => 0,
        );
    }

    private function get_course_lessons($course_id, $include_unpublished = false) {
        $course_id = absint($course_id);
        if (!$course_id || !$this->tutor->is_available()) return array();

        $lessons = array();
        foreach ($this->tutor->get_topics($course_id, $include_unpublished) as $topic) {
            foreach ($this->tutor->get_lessons($topic->ID, $include_unpublished) as $lesson) {
                $lessons[] = $lesson;
            }
        }
        return $lessons;
    }
}
