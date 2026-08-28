<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

/**
 * Resolves protected HLS storage paths without exposing filesystem details to
 * the player. Physical files remain outside the public WordPress media URL.
 */
class HLS_Storage
{
    public function manifest_path($video)
    {
        if (!$video || empty($video->hls_path)) {
            return '';
        }

        return $this->safe_path($video->hls_path);
    }

    public function key_path($video)
    {
        if (!$video || empty($video->aes_key_path)) {
            return '';
        }

        return $this->safe_path($video->aes_key_path);
    }

    private function safe_path($path)
    {
        $path = wp_normalize_path((string) $path);
        if ($path === '' || strpos($path, '..') !== false || !file_exists($path) || !is_readable($path)) {
            return '';
        }

        return $path;
    }
}
