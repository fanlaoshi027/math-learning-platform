<?php
namespace MathCourse\Access;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

class Access_Service {

    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'mathcourse_access';
    }

    public function has_access($user_id, $course_id) {
        $info = $this->get_access_info($user_id, $course_id);
        return !empty($info['access']);
    }

    /** MathCourse 的课程权限以明确授权为准。 */
    public function is_free_course($course_id) {
        return false;
    }

    public function can_access_course($user_id, $course_id) {
        $user_id = absint($user_id);
        $course_id = absint($course_id);
        if (!$user_id || !$course_id) return false;
        if (user_can($user_id, 'manage_options')) return true;
        return $this->has_access($user_id, $course_id);
    }

    public function get_access_info($user_id, $course_id) {
        $user_id = absint($user_id);
        $course_id = absint($course_id);
        $result = array('access' => false, 'status' => 'none', 'expires_at' => null);
        if (!$user_id || !$course_id) return $result;
        if (user_can($user_id, 'manage_options')) {
            $result['access'] = true;
            $result['status'] = 'admin';
            return $result;
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT status, expires_at FROM {$this->table()} WHERE user_id=%d AND course_id=%d LIMIT 1",
            $user_id,
            $course_id
        ));
        if (!$row) return $result;

        $result['status'] = $row->status;
        $result['expires_at'] = $row->expires_at;
        if ('active' !== $row->status) return $result;
        if (!empty($row->expires_at) && strtotime($row->expires_at) <= current_time('timestamp')) {
            $result['status'] = 'expired';
            return $result;
        }
        $result['access'] = true;
        return $result;
    }

    public function grant($user_id, $course_id, $expires_at = null) {
        $user_id = absint($user_id);
        $course_id = absint($course_id);
        if (!$user_id || !$course_id) return false;
        global $wpdb;
        return false !== $wpdb->replace(
            $this->table(),
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'status' => 'active',
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
            array('user_id' => absint($user_id), 'course_id' => absint($course_id)),
            array('%s'),
            array('%d', '%d')
        );
    }

    public function get_user_courses($user_id) {
        $user_id = absint($user_id);
        if (!$user_id) return array();
        global $wpdb;
        $now = current_time('mysql');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE user_id=%d AND status='active' AND (expires_at IS NULL OR expires_at='' OR expires_at > %s) ORDER BY granted_at DESC",
            $user_id,
            $now
        ));
        return $rows ?: array();
    }

    /** 试看只允许已发布且确实属于该课程的课时。 */
    public function can_preview($course_id, $lesson_id) {
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);
        if (!$course_id || !$lesson_id) return false;

        $adapter = new Adapter();
        if (!$adapter->is_available()) return false;

        $lesson = $adapter->get_lesson($lesson_id);
        $course = $adapter->get_course($course_id);
        if (!$lesson || 'publish' !== $lesson->post_status || !$course || 'publish' !== $course->post_status) return false;
        if ($adapter->get_lesson_course_id($lesson_id) !== $course_id) return false;
        return $adapter->is_preview_lesson($lesson_id);
    }

    /** 最终播放权限：授权学员可观看已发布课时，游客仅可观看明确允许试看且已发布的课时。 */
    public function can_watch_lesson($user_id, $course_id, $lesson_id) {
        $user_id = absint($user_id);
        $course_id = absint($course_id);
        $lesson_id = absint($lesson_id);
        if (!$course_id || !$lesson_id) return false;

        $adapter = new Adapter();
        if (!$adapter->is_available()) return false;

        $lesson = $adapter->get_lesson($lesson_id);
        $course = $adapter->get_course($course_id);
        if (!$lesson || 'publish' !== $lesson->post_status || !$course || 'publish' !== $course->post_status) return false;
        if ($adapter->get_lesson_course_id($lesson_id) !== $course_id) return false;
        if ($user_id && $this->can_access_course($user_id, $course_id)) return true;
        return $this->can_preview($course_id, $lesson_id);
    }
}
