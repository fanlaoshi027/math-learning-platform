<?php
namespace MathCourse\Course;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;
use MathCourse\Access\Access_Service;
use MathCourse\Progress\Progress_Service;

class Course_Service {
    private $tutor;
    private $access;
    private $progress;

    public function __construct() {
        $this->tutor    = new Adapter();
        $this->access   = new Access_Service();
        $this->progress = new Progress_Service();
    }

    /**
     * 获取课程基础信息
     * Tutor LMS 作为课程数据源
     */
    public function get_course($course_id) {
        $course = $this->tutor->get_course($course_id);

        if (!$course) {
            return null;
        }

        return array(
            'id' => (int) $course->ID,
            'title' => get_the_title($course),
            'type' => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover' => get_post_meta($course->ID, '_mathcourse_cover', true),
        );
    }

    /**
     * 获取课时视频数据
     */
    public function get_lesson_video($lesson_id, $user_id = 0) {
        $preview = $this->tutor->is_preview_lesson($lesson_id);
        $course_id = $this->tutor->get_lesson_course_id($lesson_id);

        $accessible = $preview;
        if ($user_id && $course_id) {
            $accessible = $this->access->has_access($user_id, $course_id) || $preview;
        }

        return array(
            'id' => (int) $lesson_id,
            'video_id' => get_post_meta($lesson_id, '_mathcourse_video_id', true),
            'hls_url' => $accessible ? get_post_meta($lesson_id, '_mathcourse_hls_url', true) : '',
            'preview' => $preview,
            'accessible' => $accessible,
        );
    }

    /**
     * 获取课程目录。
     *
     * 课程完成状态统一由 MathCourse Progress_Service 提供，避免前台目录
     * 同时读取 Tutor LMS 原生完成状态和 MathCourse 自己的完成状态。
     */
    public function get_course_directory($course_id, $user_id = 0) {
        $course = $this->tutor->get_course($course_id);

        if (!$course) {
            return null;
        }

        $user_id      = absint($user_id);
        $course_access = $user_id ? $this->access->has_access($user_id, $course->ID) : false;

        $topics = array();

        foreach ($this->tutor->get_topics($course->ID) as $topic) {
            $lessons = array();

            foreach ($this->tutor->get_lessons($topic->ID) as $lesson) {
                $completed_lesson = $user_id ? $this->progress->is_completed($user_id, $lesson->ID) : false;
                $preview          = $this->tutor->is_preview_lesson($lesson->ID);
                $accessible       = $course_access || $preview;

                $lessons[] = array(
                    'id' => (int) $lesson->ID,
                    'title' => get_the_title($lesson),
                    'page_number' => $this->tutor->get_lesson_page_number($lesson->ID),
                    'video_id' => $this->tutor->get_lesson_video_id($lesson->ID),
                    'hls_url' => $accessible ? get_post_meta($lesson->ID, '_mathcourse_hls_url', true) : '',
                    'url' => $accessible ? get_permalink($lesson) : '',
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

        $progress = $course_access && $user_id
            ? $this->progress->get_course_progress($course->ID, $user_id)
            : array(
                'completed' => 0,
                'total' => 0,
                'percent' => 0,
                'last_lesson_id' => 0,
                'last_time' => 0,
            );

        return array(
            'id' => (int) $course->ID,
            'title' => get_the_title($course),
            'type' => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover' => get_post_meta($course->ID, '_mathcourse_cover', true),
            'topics' => $topics,
            'access' => $course_access,
            'progress' => $progress,
        );
    }
}
