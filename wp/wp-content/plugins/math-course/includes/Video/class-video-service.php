<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

class Video_Service
{
    private function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'mathcourse_videos';
    }

    public function save_video($lesson_id, $video_url)
    {
        global $wpdb;

        $lesson_id = absint($lesson_id);
        $video_url = esc_url_raw($video_url);

        if (!$lesson_id || !$video_url) {
            return false;
        }

        return $wpdb->replace(
            $this->table(),
            array(
                'lesson_id' => $lesson_id,
                'video_url' => $video_url,
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );
    }

    /** Return a complete video record by its own database ID. */
    public function get_video_by_id($video_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, lesson_id, video_url, status, created_at
                 FROM {$this->table()}
                 WHERE id = %d AND status = 'active'
                 LIMIT 1",
                absint($video_id)
            )
        );
    }

    /** Return the active video belonging to a Tutor Lesson. */
    public function get_video_by_lesson($lesson_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, lesson_id, video_url, status, created_at
                 FROM {$this->table()}
                 WHERE lesson_id = %d AND status = 'active'
                 LIMIT 1",
                absint($lesson_id)
            )
        );
    }

    /** Backward-compatible helper: historically this accepted a lesson ID. */
    public function get_video($lesson_id)
    {
        $video = $this->get_video_by_lesson($lesson_id);
        return $video ? $video->video_url : null;
    }
}
