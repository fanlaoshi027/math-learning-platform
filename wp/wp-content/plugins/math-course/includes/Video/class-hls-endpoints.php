<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

/** Protected HLS manifest endpoint. */
class HLS_Endpoints
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
        register_rest_route('mathcourse/v1', '/video/(?P<video_id>\d+)/manifest', array(
            'methods' => 'GET',
            'callback' => array($this, 'manifest'),
            'permission_callback' => '__return_true',
        ));
    }

    public function manifest(\WP_REST_Request $request)
    {
        $video_id = absint($request['video_id']);
        $token = sanitize_text_field(wp_unslash($request->get_param('token')));
        if (!$this->token->verify($token, $video_id, get_current_user_id())) {
            return new \WP_Error('mathcourse_invalid_token', '播放令牌无效或已过期。', array('status' => 403));
        }

        $video = $this->video_service->get_video_by_id($video_id);
        $path = $this->storage->manifest_path($video);
        if (!$video || !$path) {
            return new \WP_Error('mathcourse_hls_not_configured', '该视频尚未配置受保护的 HLS 资源。', array('status' => 404));
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return new \WP_Error('mathcourse_manifest_read_failed', '无法读取视频播放清单。', array('status' => 500));
        }

        $segment_base = rest_url('mathcourse/v1/video/' . $video_id . '/segment');
        $key_url = add_query_arg('token', $token, rest_url('mathcourse/v1/video/' . $video_id . '/key'));

        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as &$line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;

            if (stripos($trimmed, '#EXT-X-KEY:') === 0) {
                $line = preg_replace('/URI="[^"]*"/i', 'URI="' . esc_url_raw($key_url) . '"', $line, 1);
                continue;
            }

            if ($trimmed[0] !== '#') {
                $filename = wp_basename(parse_url($trimmed, PHP_URL_PATH));
                if ($filename === '' || !preg_match('/\.(ts|m4s|mp4|aac|vtt)$/i', $filename)) {
                    return new \WP_Error('mathcourse_invalid_hls_uri', 'HLS 清单包含不支持的资源地址。', array('status' => 503));
                }
                $line = add_query_arg(array('token' => $token, 'file' => $filename), $segment_base);
            }
        }
        unset($line);

        $response = new \WP_REST_Response(implode("\n", $lines), 200);
        $response->header('Content-Type', 'application/vnd.apple.mpegurl');
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
