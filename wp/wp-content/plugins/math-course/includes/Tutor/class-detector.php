<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * Tutor LMS environment detector.
 * Does not create or modify Tutor data.
 */
class Detector
{
    public function detect()
    {
        $types = get_post_types(array(), 'objects');

        return array(
            'available' => defined('TUTOR_VERSION'),
            'version' => defined('TUTOR_VERSION') ? TUTOR_VERSION : '',
            'course' => $this->find_type($types, array('course', 'courses', 'tutor_course')),
            'lesson' => $this->find_type($types, array('lesson', 'lessons', 'tutor_lesson')),
            'topic' => $this->find_type($types, array('topic', 'topics', 'tutor_topic')),
            'method' => 'wordpress_post_type_registry',
        );
    }

    private function find_type($types, $candidates)
    {
        foreach ($candidates as $candidate) {
            if (isset($types[$candidate])) {
                return $candidate;
            }
        }
        return '';
    }
}
