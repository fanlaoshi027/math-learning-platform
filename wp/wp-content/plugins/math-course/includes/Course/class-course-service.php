<?php
namespace MathCourse\Course;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;
use MathCourse\Access\Access_Service;

class Course_Service {
    private $tutor;
    private $access;

    public function __construct() {
        $this->tutor  = new Adapter();
        $this->access = new Access_Service();
    }

    /**
     * 获取课程基础信息
     * Tutor LMS 仍然作为课程数据源
     */
    public function get_course($course_id) {
        $course = $this->tutor->get_course($course_id);

        if (!$course) {
            return null;
        }

        return array(
            'id'    => (int) $course->ID,
            'title' => get_the_title($course),
            'type'  => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover' => get_post_meta($course->ID, '_mathcourse_cover', true),
        );
    }

    /**
     * 获取课程目录
     */
    public function get_course_directory($course_id, $user_id = 0) {
        $course = $this->tutor->get_course($course_id);

        if (!$course) {
            return null;
        }

        $user_id = absint($user_id);
        $course_access = $user_id ? $this->access->has_access($user_id, $course->ID) : false;

        $topics = array();
        $total = 0;
        $completed = 0;

        foreach ($this->tutor->get_topics($course->ID) as $topic) {
            $lessons = array();

            foreach ($this->tutor->get_lessons($topic->ID) as $lesson) {
                $completed_lesson = $user_id
                    ? $this->tutor->is_lesson_completed($lesson->ID, $user_id)
                    : false;

                $preview = $this->tutor->is_preview_lesson($lesson->ID);
                $accessible = $course_access || $preview;

                $total++;

                if ($completed_lesson) {
                    $completed++;
                }

                $lessons[] = array(
                    'id'          => (int) $lesson->ID,
                    'title'       => get_the_title($lesson),
                    'page_number' => $this->tutor->get_lesson_page_number($lesson->ID),
                    'video_id'    => $this->tutor->get_lesson_video_id($lesson->ID),
                    'url'         => $accessible ? get_permalink($lesson) : '',
                    'completed'   => $completed_lesson,
                    'preview'     => $preview,
                    'accessible'  => $accessible,
                );
            }

            $topics[] = array(
                'id'      => (int) $topic->ID,
                'title'   => get_the_title($topic),
                'lessons' => $lessons,
            );
        }

        return array(
            'id'    => (int) $course->ID,
            'title' => get_the_title($course),
            'type'  => get_post_meta($course->ID, '_mathcourse_type', true),
            'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            'cover' => get_post_meta($course->ID, '_mathcourse_cover', true),
            'topics'=> $topics,
            'access'=> $course_access,
            'progress'=> array(
                'completed' => $completed,
                'total'     => $total,
                'percent'   => $total ? round(($completed / $total) * 100) : 0,
            ),
        );
    }
}
