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

    public function get_course($course_id) {
        $course = $this->tutor->get_course($course_id);
        if (!$course) return null;

        return array(
            'id'    => (int) $course->ID,
            'title' => get_the_title($course),
            'type'  => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover' => get_post_meta($course->ID, '_mathcourse_cover', true),
        );
    }

    /**
     * 获取课时视频数据。
     *
     * 播放资源优先使用 MathCourse 的 HLS 地址；如果尚未迁移到 HLS，
     * 则允许播放器回退到 Tutor LMS 原生 _video 数据，兼容已经上传的旧视频。
     */
    public function get_lesson_video($lesson_id, $user_id = 0) {
        $lesson_id = absint($lesson_id);
        $user_id   = absint($user_id);
        $preview   = $this->tutor->is_preview_lesson($lesson_id);
        $course_id = $this->tutor->get_lesson_course_id($lesson_id);
        $course_access = $user_id ? $this->access->can_access_course($user_id, $course_id) : false;
        $accessible = $course_access || $preview;

        $hls_url = $accessible ? get_post_meta($lesson_id, '_mathcourse_hls_url', true) : '';
        $tutor_video = $accessible ? get_post_meta($lesson_id, '_video', true) : '';

        return array(
            'id'           => $lesson_id,
            'course_id'    => $course_id,
            'video_id'     => $this->tutor->get_lesson_video_id($lesson_id),
            'hls_url'      => $hls_url,
            'tutor_video'  => $tutor_video,
            'has_video'    => !empty($hls_url) || !empty($tutor_video),
            'preview'      => $preview,
            'accessible'   => $accessible,
        );
    }

    public function get_course_directory($course_id, $user_id = 0) {
        $course = $this->tutor->get_course($course_id);
        if (!$course) return null;

        $user_id       = absint($user_id);
        $course_access = $user_id ? $this->access->can_access_course($user_id, $course->ID) : false;
        $course_free   = $this->access->is_free_course($course->ID);
        $topics        = array();

        foreach ($this->tutor->get_topics($course->ID) as $topic) {
            $lessons = array();
            foreach ($this->tutor->get_lessons($topic->ID) as $lesson) {
                $completed_lesson = $course_access && $user_id
                    ? $this->progress->is_completed($user_id, $lesson->ID)
                    : false;
                $preview = $this->tutor->is_preview_lesson($lesson->ID);
                $accessible = $course_access || $preview;
                $hls_url = $accessible ? get_post_meta($lesson->ID, '_mathcourse_hls_url', true) : '';
                $tutor_video = $accessible ? get_post_meta($lesson->ID, '_video', true) : '';

                $lessons[] = array(
                    'id'           => (int) $lesson->ID,
                    'title'        => get_the_title($lesson),
                    'page_number'  => $this->tutor->get_lesson_page_number($lesson->ID),
                    'video_id'     => $this->tutor->get_lesson_video_id($lesson->ID),
                    'hls_url'      => $hls_url,
                    'tutor_video'  => $tutor_video,
                    'has_video'    => !empty($hls_url) || !empty($tutor_video),
                    'url'          => $accessible ? get_permalink($lesson) : '',
                    'completed'    => $completed_lesson,
                    'preview'      => $preview,
                    'accessible'   => $accessible,
                );
            }

            $topics[] = array(
                'id'      => (int) $topic->ID,
                'title'   => get_the_title($topic),
                'lessons' => $lessons,
            );
        }

        $progress = $course_access && $user_id
            ? $this->progress->get_course_progress($course->ID, $user_id)
            : array('completed'=>0,'total'=>0,'percent'=>0,'last_lesson_id'=>0,'last_time'=>0);

        return array(
            'id'       => (int) $course->ID,
            'title'    => get_the_title($course),
            'type'     => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade'    => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover'    => get_post_meta($course->ID, '_mathcourse_cover', true),
            'is_free'  => $course_free,
            'topics'   => $topics,
            'access'   => $course_access,
            'progress' => $progress,
        );
    }
}
