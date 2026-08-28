<?php

namespace MathCourse\Admin;

use MathCourse\Tutor\Adapter;

defined('ABSPATH') || exit;

class Tutor_Page
{
    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $adapter = new Adapter();
        $available = $adapter->is_available();
        $course_type = $adapter->course_post_type();
        $lesson_type = $adapter->lesson_post_type();
        ?>
        <div class="wrap">
            <h1>课程系统</h1>
            <p>MathCourse 与 Tutor LMS 的连接状态。</p>
            <table class="widefat striped" style="max-width:800px">
                <tbody>
                    <tr><td><strong>Tutor LMS</strong></td><td><?php echo $available ? '正常' : '未检测到'; ?></td></tr>
                    <tr><td><strong>Tutor 版本</strong></td><td><?php echo esc_html($adapter->version() ?: '—'); ?></td></tr>
                    <tr><td><strong>Course Post Type</strong></td><td><?php echo esc_html($course_type ?: '—'); ?></td></tr>
                    <tr><td><strong>Lesson Post Type</strong></td><td><?php echo esc_html($lesson_type ?: '—'); ?></td></tr>
                </tbody>
            </table>
            <p style="margin-top:16px;">当前阶段只读取 Tutor LMS 数据，不自动修改现有课程。</p>
        </div>
        <?php
    }
}
