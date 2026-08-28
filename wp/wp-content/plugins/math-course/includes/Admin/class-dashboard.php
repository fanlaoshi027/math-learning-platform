<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;

class Dashboard
{
    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $access_table = $wpdb->prefix . 'mathcourse_access';
        $access_count = 0;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $access_table)) === $access_table) {
            $access_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$access_table}");
        }

        $course_count = 0;
        if (post_type_exists('courses')) {
            $counts = wp_count_posts('courses');
            $course_count = isset($counts->publish) ? (int) $counts->publish : 0;
        }

        $student_count = (int) count_users()['total_users'];
        ?>
        <div class="wrap mathcourse-dashboard">
            <h1>MathCourse</h1>
            <p>数学课程业务控制台</p>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:16px;max-width:900px;margin-top:20px;">
                <div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;">
                    <strong style="font-size:28px;display:block;"><?php echo esc_html($course_count); ?></strong>
                    <span>已发布课程</span>
                </div>
                <div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;">
                    <strong style="font-size:28px;display:block;"><?php echo esc_html($student_count); ?></strong>
                    <span>网站用户</span>
                </div>
                <div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;">
                    <strong style="font-size:28px;display:block;"><?php echo esc_html($access_count); ?></strong>
                    <span>课程授权记录</span>
                </div>
            </div>
        </div>
        <?php
    }
}
