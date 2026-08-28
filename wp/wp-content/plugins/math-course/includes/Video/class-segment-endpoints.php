<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

/** Protected HLS media-segment endpoint. */
class Segment_Endpoints
{
    private $token;
    private $video_service;
    private $storage;

    public function __construct(Token $token, $video_service, HLS_Storage $storage)
    {
        $this->token = $token;
        $this->video_service = $video_service;
        $this->storage = $storage;
    }

    public function register()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route('mathcourse/v1', '/video/(?P<video_id>\d+)/segment', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve'),
            'permission_callback' => '__return_true',
            'args' => array(
                'video_id' => array('required' => true),
                'token' => array('required' => true),
                'file' => array('required' => true),
            ),
        ));
    }

    public function serve(\WP_REST_Request $request)
    {
        $video_id = absint($request['video_id']);
        $token = sanitize_text_field(wp_unslash($request->get_param('token')));
        if (!$this->token->verify($token, $video_id, get_current_user_id())) {
            return new \WP_Error('mathcourse_invalid_token', '播放令牌无效或已过期。', array('status' => 403));
        }

        $video = $this->video_service->get_video_by_id($video_id);
        if (!$video || empty($video->hls_path)) {
            return new \WP_Error('mathcourse_hls_not_configured', '该视频尚未配置 HLS。', array('status' => 404));
        }

        $file = wp_basename(sanitize_text_field(wp_unslash($request->get_param('file'))));
        if ($file === '' || !preg_match('/\.(ts|m4s|mp4|aac|vtt)$/i', $file)) {
            return new \WP_Error('mathcourse_invalid_segment', '无效的视频分片。', array('status' => 400));
        }

        $base = dirname($video->hls_path);
        $path = wp_normalize_path($base . '/' . $file);
        $real_base = realpath($base);
        $real_path = realpath($path);
        if (!$real_base || !$real_path || strpos($real_path, wp_normalize_path($real_base) . DIRECTORY_SEPARATOR) !== 0 || !is_readable($real_path)) {
            return new \WP_Error('mathcourse_segment_not_found', '视频分片不存在。', array('status' => 404));
        }

        $mime = 'application/octet-stream';
        $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
        if ($ext === 'ts') $mime = 'video/mp2t';
        elseif ($ext === 'm4s') $mime = 'video/iso.segment';
        elseif ($ext === 'mp4') $mime = 'video/mp4';
        elseif ($ext === 'aac') $mime = 'audio/aac';
        elseif ($ext === 'vtt') $mime = 'text/vtt';

        $size = filesize($real_path);
        $range = isset($_SERVER['HTTP_RANGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE'])) : '';
        $start = 0;
        $end = $size - 1;

        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $match)) {
            if ($match[1] !== '') $start = (int) $match[1];
            if ($match[2] !== '') $end = (int) $match[2];
            if ($match[1] === '') $start = max(0, $size - (int) $match[2]);
            if ($start > $end || $start >= $size) {
                return new \WP_Error('mathcourse_invalid_range', '无效的媒体范围。', array('status' => 416));
            }
            $end = min($end, $size - 1);
        }

        $length = $end - $start + 1;
        $response = new \WP_REST_Response(file_get_contents($real_path, false, null, $start, $length), $range ? 206 : 200);
        $response->header('Content-Type', $mime);
        $response->header('Content-Length', (string) $length);
        $response->header('Accept-Ranges', 'bytes');
        if ($range) $response->header('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $size);
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
