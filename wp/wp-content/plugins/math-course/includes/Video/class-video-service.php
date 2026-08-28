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

    public function save_video($lesson_id, $video_url, $hls_path = '', $aes_key_path = '')
    {
        global $wpdb;

        $lesson_id = absint($lesson_id);
        $video_url = esc_url_raw($video_url);
        $hls_path = $this->sanitize_path($hls_path);
        $aes_key_path = $this->sanitize_path($aes_key_path);

        if (!$lesson_id || (!$video_url && !$hls_path)) {
            return false;
        }

        return $wpdb->replace(
            $this->table(),
            array(
                'lesson_id' => $lesson_id,
                'video_url' => $video_url,
                'hls_path' => $hls_path,
                'aes_key_path' => $aes_key_path,
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }

    public function get_video_by_id($video_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, lesson_id, video_url, hls_path, aes_key_path, status, created_at
                 FROM {$this->table()}
                 WHERE id = %d AND status = 'active'
                 LIMIT 1",
                absint($video_id)
            )
        );
    }

    public function get_video_by_lesson($lesson_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, lesson_id, video_url, hls_path, aes_key_path, status, created_at
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

    private function sanitize_path($path)
    {
        $path = wp_normalize_path((string) $path);
        if ($path === '' || strpos($path, '..') !== false) {
            return '';
        }

        return $path;
    }
}
