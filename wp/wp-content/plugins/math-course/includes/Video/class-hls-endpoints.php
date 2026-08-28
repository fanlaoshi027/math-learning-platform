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
        $payload = $this->token->verify($token, $video_id, get_current_user_id());

        if (!$payload) {
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

        $key_url = add_query_arg(
            'token', $token,
            rest_url('mathcourse/v1/video/' . $video_id . '/key')
        );
        $content = preg_replace('/URI="[^"]*"/i', 'URI="' . esc_url_raw($key_url) . '"', $content, 1);

        // Do not expose a public segment URL. Until the segment proxy exists,
        // reject manifests that contain direct media-segment references.
        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && $trimmed[0] !== '#' && strpos($trimmed, 'mathcourse/v1/video/') === false) {
                return new \WP_Error('mathcourse_hls_segments_not_protected', '该 HLS 清单包含尚未受保护的视频分片。', array('status' => 503));
            }
        }

        $response = new \WP_REST_Response($content, 200);
        $response->header('Content-Type', 'application/vnd.apple.mpegurl');
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
