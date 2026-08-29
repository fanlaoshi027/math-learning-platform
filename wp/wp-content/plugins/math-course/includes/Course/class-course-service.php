<?php
namespace MathCourse\Course;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;
use MathCourse\Access\Access_Service;

class Course_Service {
    private $tutor;
    private $access;

    public function __construct() {
        $this->tutor = new Adapter();
        $this->access = new Access_Service();
    }

    /**
     * Return the normalized course structure consumed by MathCourse frontend.
     * Tutor LMS remains the source of truth for Course/Topic/Lesson data.
     */
    public function get_course_directory($course_id, $user_id = 0) {
        $course = $this->tutor->get_course($course_id);
        if (!$course) return null;

        $user_id = absint($user_id);
        $course_access = $user_id ? $this->access->has_access($user_id, $course->ID) : false;
        $topics = array();
        $total = 0;
        $completed = 0;

        foreach ($this->tutor->get_topics($course->ID) as $topic) {
            $lessons = array();

            foreach ($this->tutor->get_lessons($topic->ID) as $lesson) {
                $completed_lesson = $user_id ? $this->tutor->is_lesson_completed($lesson->ID, $user_id) : false;
                $preview = get_post_meta($lesson->ID, '_mathcourse_preview', true) === 'yes';
                $accessible = $course_access || $preview;

                $total++;
                if ($completed_lesson) $completed++;

                $lessons[] = array(
                    'id' => (int) $lesson->ID,
                    'title' => get_the_title($lesson),
                    'url' => get_permalink($lesson),
                    'completed' => $completed_lesson,
                    'preview' => $preview,
                    'accessible' => $accessible,
                );
            }

            $topics[] = array(
                'id' => (int) $topic->ID,
                'title' => get_the_title($topic),
                'lessons' => $lessons,
            );
        }

        return array(
            'id' => (int) $course->ID,
            'title' => get_the_title($course),
            'topics' => $topics,
            'access' => $course_access,
            'progress' => array(
                'completed' => $completed,
                'total' => $total,
                'percent' => $total ? round(($completed / $total) * 100) : 0,
            ),
        );
    }
}
