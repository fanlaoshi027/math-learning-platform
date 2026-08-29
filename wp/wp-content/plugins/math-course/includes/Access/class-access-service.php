<?php
namespace MathCourse\Access;
defined('ABSPATH') || exit;

class Access_Service {
    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'mathcourse_access';
    }

    public function has_access($user_id, $course_id) {
        $user_id = absint($user_id);
        $course_id = absint($course_id);
        if (!$user_id || !$course_id) return false;

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT status, expires_at FROM {$this->table()} WHERE user_id=%d AND course_id=%d LIMIT 1",
            $user_id,
            $course_id
        ));

        if (!$row || $row->status !== 'active') return false;
        if (!empty($row->expires_at) && strtotime($row->expires_at) <= current_time('timestamp')) return false;
        return true;
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
}
