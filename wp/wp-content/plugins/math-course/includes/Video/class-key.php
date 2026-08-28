<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

/**
 * Controlled AES-128 key delivery.
 * The key material is stored outside the public player configuration and is
 * only returned after the video token has been validated.
 */
class Key
{
    private $token;

    public function __construct(Token $token)
    {
        $this->token = $token;
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
            'args' => array(
                'video_id' => array('required' => true),
                'token' => array('required' => true),
            ),
        ));
    }

    public function serve(\WP_REST_Request $request)
    {
        $video_id = absint($request['video_id']);
        $token = sanitize_text_field(wp_unslash($request->get_param('token')));
        $payload = $this->token->verify($token, $video_id, get_current_user_id());

        if (!$payload) {
            return new \WP_Error('mathcourse_invalid_token', '播放令牌无效或已过期。', array('status' => 403));
        }

        // Key storage will be wired to the video record in the next HLS step.
        // Do not return a guessed/default key: fail closed until real key data exists.
        return new \WP_Error('mathcourse_key_not_configured', '该视频尚未配置加密密钥。', array('status' => 404));
    }
}
