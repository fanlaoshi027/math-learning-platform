<?php

namespace MathCourse\Access;

defined('ABSPATH') || exit;

class Access_Service
{
    public function has_access($user_id, $course_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mathcourse_access';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id=%d AND course_id=%d AND status='active'",
            absint($user_id),
            absint($course_id)
        ));
    }

    public function grant($user_id, $course_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mathcourse_access';

        $user_id = absint($user_id);
        $course_id = absint($course_id);
        if (!$user_id || !$course_id) {
            return false;
        }

        return false !== $wpdb->replace(
            $table,
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );
    }

    public function revoke($user_id, $course_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mathcourse_access';

        return false !== $wpdb->update(
            $table,
            array('status' => 'revoked'),
            array('user_id' => absint($user_id), 'course_id' => absint($course_id)),
            array('%s'),
            array('%d', '%d')
        );
    }

    public function get_all($limit = 100)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mathcourse_access';
        $limit = max(1, min(500, absint($limit)));

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}");
    }
}
