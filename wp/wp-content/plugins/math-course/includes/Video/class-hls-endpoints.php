<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

/**
 * Protected HLS endpoint scaffold.
 *
 * It deliberately validates the signed token before any media path is exposed.
 * Physical file/segment streaming is added only after the storage contract is
 * finalized, so a missing configuration fails closed.
 */
class HLS_Endpoints
{
    private $token;
    private $video_service;

    public function __construct(Token $token, $video_service)
    {
        $this->token = $token;
        $this->video_service = $video_service;
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
            'args' => array(
                'video_id' => array('required' => true),
                'token' => array('required' => true),
            ),
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
        if (!$video) {
            return new \WP_Error('mathcourse_video_not_found', '视频不存在。', array('status' => 404));
        }

        // The current database stores a legacy video_url. Do not expose it
        // until the protected HLS storage contract is in place.
        return new \WP_Error('mathcourse_hls_not_configured', '该视频尚未配置受保护的 HLS 资源。', array('status' => 404));
    }
}
