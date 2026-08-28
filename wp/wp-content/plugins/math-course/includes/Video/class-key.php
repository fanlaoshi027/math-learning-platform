<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

class Key
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
        register_rest_route('mathcourse/v1', '/video/(?P<video_id>\d+)/key', array(
            'methods' => 'GET',
            'callback' => array($this, 'serve'),
            'permission_callback' => '__return_true',
            'args' => array('video_id' => array('required' => true), 'token' => array('required' => true)),
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
        $path = $this->storage->key_path($video);
        if (!$path) {
            return new \WP_Error('mathcourse_key_not_configured', '该视频尚未配置加密密钥。', array('status' => 404));
        }

        $key = file_get_contents($path);
        if ($key === false || strlen($key) !== 16) {
            return new \WP_Error('mathcourse_invalid_key', '视频加密密钥配置无效。', array('status' => 500));
        }

        return new \WP_REST_Response($key, 200, array(
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ));
    }
}
