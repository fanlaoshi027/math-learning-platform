<?php
namespace MathCourse\Course;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;
use MathCourse\Access\Access_Service;
use MathCourse\Progress\Progress_Service;
use MathCourse\Video\Video_Router;

class Course_Service {
    private $tutor;
    private $access;
    private $progress;

    public function __construct() {
        $this->tutor = new Adapter();
        $this->access = new Access_Service();
        $this->progress = new Progress_Service();
    }

    public function get_course($course_id) {
        $course = $this->tutor->get_course($course_id);
        if (!$course) return null;

        return array(
            'id' => (int) $course->ID,
            'title' => get_the_title($course),
            'type' => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover' => get_post_meta($course->ID, '_mathcourse_cover', true),
        );
    }

    /**
     * Return player data. Authorized HLS playback uses the protected gateway;
     * the original storage URL is never exposed to the browser.
     */
    public function get_lesson_video($lesson_id, $user_id = 0) {
        $lesson_id = absint($lesson_id);
        $user_id = absint($user_id);
        $lesson = $this->tutor->get_lesson($lesson_id);
        if (!$lesson) return null;

        $preview = $this->tutor->is_preview_lesson($lesson_id);
        $course_id = $this->tutor->get_lesson_course_id($lesson_id);
        $course_access = $user_id ? $this->access->can_access_course($user_id, $course_id) : false;
        $accessible = $course_access || $preview;
        $has_hls = (bool) $this->tutor->get_lesson_hls_url($lesson_id);

        return array(
            'id' => $lesson_id,
            'course_id' => $course_id,
            'video_id' => $this->tutor->get_lesson_video_id($lesson_id),
            'hls_url' => ($accessible && $has_hls) ? (new Video_Router())->get_protected_url($lesson_id) : '',
            'preview' => $preview,
            'accessible' => $accessible,
        );
    }

    public function get_course_directory($course_id, $user_id = 0) {
        $course = $this->tutor->get_course($course_id);
        if (!$course) return null;

        $user_id = absint($user_id);
        $course_access = $user_id ? $this->access->can_access_course($user_id, $course->ID) : false;
        $course_free = $this->access->is_free_course($course->ID);
        $topics = array();

        foreach ($this->tutor->get_topics($course->ID, false) as $topic) {
            $lessons = array();
            foreach ($this->tutor->get_lessons($topic->ID, false) as $lesson) {
                $completed_lesson = ($course_access && $user_id) ? $this->progress->is_completed($user_id, $lesson->ID) : false;
                $preview = $this->tutor->is_preview_lesson($lesson->ID);
                $accessible = $course_access || $preview;
                $has_hls = (bool) $this->tutor->get_lesson_hls_url($lesson->ID);

                $lessons[] = array(
                    'id' => (int) $lesson->ID,
                    'title' => get_the_title($lesson),
                    'page_number' => $this->tutor->get_lesson_page_number($lesson->ID),
                    'video_id' => $this->tutor->get_lesson_video_id($lesson->ID),
                    // Only a short-lived gateway URL is exposed for accessible HLS.
                    'hls_url' => ($accessible && $has_hls) ? (new Video_Router())->get_protected_url($lesson->ID) : '',
                    'url' => $accessible ? get_permalink($lesson) : '',
                    'completed' => $completed_lesson,
                    'preview' => $preview,
                    'accessible' => $accessible,
                );
            }
            $topics[] = array('id' => (int) $topic->ID, 'title' => get_the_title($topic), 'lessons' => $lessons);
        }

        $progress = $user_id
            ? $this->progress->get_course_progress($course->ID, $user_id)
            : array('completed' => 0, 'total' => $this->count_lessons($topics), 'percent' => 0, 'last_lesson_id' => 0, 'last_time' => 0);

        return array(
            'id' => (int) $course->ID,
            'title' => get_the_title($course),
            'type' => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover' => get_post_meta($course->ID, '_mathcourse_cover', true),
            'is_free' => $course_free,
            'topics' => $topics,
            'access' => $course_access,
            'progress' => $progress,
        );
    }

    private function count_lessons($topics) {
        $count = 0;
        foreach ($topics as $topic) $count += count($topic['lessons']);
        return $count;
    }
}
