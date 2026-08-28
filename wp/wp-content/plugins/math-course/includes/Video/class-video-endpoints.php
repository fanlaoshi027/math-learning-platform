<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

/**
 * Protected REST endpoints used by the MathCourse player.
 *
 * This layer intentionally does not expose the physical media URL. It only
 * issues a short-lived token after the current user has passed the existing
 * Lesson access check.
 */
class Video_Endpoints
{
    private $token;
    private $video_service;
    private $lesson_access;

    public function __construct(Token $token, $video_service, $lesson_access)
    {
        $this->token = $token;
        $this->video_service = $video_service;
        $this->lesson_access = $lesson_access;
    }

    public function register()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route('mathcourse/v1', '/video/(?P<video_id>\d+)/token', array(
            'methods' => 'GET',
            'callback' => array($this, 'issue_token'),
            'permission_callback' => array($this, 'permission_check'),
            'args' => array(
                'video_id' => array(
                    'required' => true,
                    'validate_callback' => static function ($value) {
                        return absint($value) > 0;
                    },
                ),
                'lesson_id' => array(
                    'required' => false,
                    'validate_callback' => static function ($value) {
                        return absint($value) > 0;
                    },
                ),
            ),
        ));
    }

    public function permission_check(\WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new \WP_Error('mathcourse_login_required', '请先登录。', array('status' => 401));
        }

        $video_id = absint($request['video_id']);
        $lesson_id = absint($request->get_param('lesson_id'));

        $video = $this->video_service->get_video($video_id);
        if (!$video) {
            return new \WP_Error('mathcourse_video_not_found', '视频不存在。', array('status' => 404));
        }

        if ($lesson_id && !$this->lesson_access->can_view($lesson_id)) {
            return new \WP_Error('mathcourse_lesson_forbidden', '无权访问该课程内容。', array('status' => 403));
        }

        return true;
    }

    public function issue_token(\WP_REST_Request $request)
    {
        $video_id = absint($request['video_id']);
        $user_id = get_current_user_id();
        $token = $this->token->create($video_id, $user_id);

        if (!$token) {
            return new \WP_Error('mathcourse_token_failed', '无法生成播放令牌。', array('status' => 500));
        }

        return new \WP_REST_Response(array(
            'token' => $token,
            'expires_in' => 1800,
        ), 200);
    }
}
