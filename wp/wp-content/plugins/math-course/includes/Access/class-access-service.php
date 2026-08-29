<?php
namespace MathCourse\Access;

defined('ABSPATH') || exit;

class Access_Service {

    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'mathcourse_access';
    }

    /**
     * 明确的 MathCourse 课程授权，不包含免费课程。
     */
    public function has_access($user_id, $course_id) {
        $info = $this->get_access_info($user_id, $course_id);
        return !empty($info['access']);
    }

    /**
     * Tutor LMS 课程是否为免费课程。
     * Tutor LMS 4.x 使用 _tutor_course_price_type，free/paid/subscription 为价格类型。
     */
    public function is_free_course($course_id) {
        $course_id = absint($course_id);

        if (!$course_id) {
            return false;
        }

        return 'free' === get_post_meta($course_id, '_tutor_course_price_type', true);
    }

    /**
     * 学员是否可以进入课程完整内容。
     *
     * 规则：
     * 1. 有 MathCourse 授权：可以观看；
     * 2. 免费课程：登录后可以观看；
     * 3. 未授权的付费课程：不能观看完整内容。
     */
    public function can_access_course($user_id, $course_id) {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id) {
            return false;
        }

        return $this->has_access($user_id, $course_id) || $this->is_free_course($course_id);
    }

    public function get_access_info($user_id, $course_id) {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        $result = array(
            'access'     => false,
            'status'     => 'none',
            'expires_at' => null,
        );

        if (!$user_id || !$course_id) {
            return $result;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status, expires_at
                 FROM {$this->table()}
                 WHERE user_id=%d
                 AND course_id=%d
                 LIMIT 1",
                $user_id,
                $course_id
            )
        );

        if (!$row) {
            return $result;
        }

        $result['status'] = $row->status;
        $result['expires_at'] = $row->expires_at;

        if ($row->status !== 'active') {
            return $result;
        }

        if (!empty($row->expires_at) && strtotime($row->expires_at) <= current_time('timestamp')) {
            $result['status'] = 'expired';
            return $result;
        }

        $result['access'] = true;
        return $result;
    }

    public function grant($user_id, $course_id, $expires_at = null) {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);

        if (!$user_id || !$course_id) {
            return false;
        }

        global $wpdb;

        return false !== $wpdb->replace(
            $this->table(),
            array(
                'user_id'    => $user_id,
                'course_id'  => $course_id,
                'status'     => 'active',
                'granted_at' => current_time('mysql'),
                'expires_at' => $expires_at ? sanitize_text_field($expires_at) : null,
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }

    public function revoke($user_id, $course_id) {
        global $wpdb;

        return false !== $wpdb->update(
            $this->table(),
            array('status' => 'revoked'),
            array(
                'user_id'   => absint($user_id),
                'course_id' => absint($course_id),
            ),
            array('%s'),
            array('%d', '%d')
        );
    }

    public function get_user_courses($user_id) {
        $user_id = absint($user_id);

        if (!$user_id) {
            return array();
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table()}
                 WHERE user_id=%d
                 AND status='active'
                 ORDER BY granted_at DESC",
                $user_id
            )
        );

        return $rows ?: array();
    }

    public function can_preview($course_id, $lesson_id) {
        $lesson_id = absint($lesson_id);

        if (!$lesson_id) {
            return false;
        }

        return 'yes' === get_post_meta($lesson_id, '_mathcourse_preview', true);
    }

    /**
     * 播放器统一权限入口。
     * 登录用户的未授权付费课程即使课时标记试看，也不开放试看；游客仍可观看指定试看课时。
     */
    public function can_watch_lesson($user_id, $course_id, $lesson_id) {
        $user_id   = absint($user_id);
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);

        if ($this->can_access_course($user_id, $course_id)) {
            return true;
        }

        if (!$user_id && $this->can_preview($course_id, $lesson_id)) {
            return true;
        }

        return false;
    }
}
