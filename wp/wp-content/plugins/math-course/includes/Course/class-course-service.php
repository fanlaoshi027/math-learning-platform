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
    private $video;

    public function __construct() {
        $this->tutor = new Adapter();
        $this->access = new Access_Service();
        $this->progress = new Progress_Service();
        // URL generation must not register another set of rewrite/template hooks.
        $this->video = new Video_Router(false);
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
     * Single source for lesson playback data.
     * HLS URLs are generated only after the final watch permission passes.
     */
    public function get_lesson_video($lesson_id, $user_id = 0) {
        $lesson_id = absint($lesson_id);
        $user_id = absint($user_id);
        $lesson = $this->tutor->get_lesson($lesson_id);
        if (!$lesson) return null;

        $course_id = $this->tutor->get_lesson_course_id($lesson_id);
        if (!$course_id) return null;

        $accessible = $this->access->can_watch_lesson($user_id, $course_id, $lesson_id);
        $preview = $this->access->can_preview($course_id, $lesson_id);
        $video_id = $this->tutor->get_lesson_video_id($lesson_id);
        $has_hls = (bool) $this->tutor->get_lesson_hls_url($lesson_id);

        return array(
            'id' => $lesson_id,
            'course_id' => $course_id,
            'video_id' => $video_id,
            'hls_url' => ($accessible && $has_hls) ? $this->video->get_protected_url($lesson_id) : '',
            'preview' => $preview,
            'accessible' => $accessible,
        );
    }

    /**
     * Return the canonical front-end learning URL for a lesson.
     * Course detail pages should only describe the course; lesson playback
     * belongs to the dedicated learning page.
     */
    private function lesson_learning_url($course_id, $lesson_id) {
        if (function_exists('mc_get_learning_page_url')) {
            $base = mc_get_learning_page_url();
        } else {
            $page = get_page_by_path('learning', OBJECT, 'page');
            $base = $page ? get_permalink($page) : home_url('/learning/');
        }

        return add_query_arg(
            array(
                'course_id' => absint($course_id),
                'lesson_id' => absint($lesson_id),
            ),
            $base
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
                $lesson_id = (int) $lesson->ID;
                $completed_lesson = ($course_access && $user_id) ? $this->progress->is_completed($user_id, $lesson_id) : false;
                $preview = $this->access->can_preview($course->ID, $lesson_id);
                $accessible = $this->access->can_watch_lesson($user_id, $course->ID, $lesson_id);
                $has_hls = (bool) $this->tutor->get_lesson_hls_url($lesson_id);

                $lessons[] = array(
                    'id' => $lesson_id,
                    'title' => get_the_title($lesson),
                    'page_number' => $this->tutor->get_lesson_page_number($lesson_id),
                    'video_id' => $this->tutor->get_lesson_video_id($lesson_id),
                    'hls_url' => ($accessible && $has_hls) ? $this->video->get_protected_url($lesson_id) : '',
                    'url' => $accessible ? $this->lesson_learning_url($course->ID, $lesson_id) : '',
                    'completed' => $completed_lesson,
                    'preview' => $preview,
                    'accessible' => $accessible,
                );
            }
            $topics[] = array('id' => (int) $topic->ID, 'title' => get_the_title($topic), 'lessons' => $lessons);
        }

        $progress = $user_id
            ? $this->progress->get_course_progress($course->ID, $user_id)
            : array('completed' => 0, 'total' => $this->count_lessons($topics), 'percent' => 0, 'last_lesson_id' => 0);

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
