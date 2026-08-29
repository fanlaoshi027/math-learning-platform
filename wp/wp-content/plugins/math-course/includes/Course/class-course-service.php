<?php
namespace MathCourse\Course;
defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

class Course_Service {
    private $tutor;
    public function __construct() { $this->tutor = new Adapter(); }
    public function get_course_directory($course_id, $user_id = 0) {
        $course = $this->tutor->get_course($course_id);
        if (!$course) return null;
        $topics = array();
        foreach ($this->tutor->get_topics($course->ID) as $topic) {
            $lessons = array();
            foreach ($this->tutor->get_lessons($topic->ID) as $lesson) {
                $lessons[] = array(
                    'id' => (int) $lesson->ID,
                    'title' => get_the_title($lesson),
                    'url' => get_permalink($lesson),
                    'completed' => $this->tutor->is_lesson_completed($lesson->ID, $user_id),
                    'preview' => get_post_meta($lesson->ID, '_mathcourse_preview', true) === 'yes',
                );
            }
            $topics[] = array('id' => (int) $topic->ID, 'title' => get_the_title($topic), 'lessons' => $lessons);
        }
        return array('id' => (int) $course->ID, 'title' => get_the_title($course), 'topics' => $topics);
    }
}
