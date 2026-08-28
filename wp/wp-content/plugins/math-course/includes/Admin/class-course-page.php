<?php

namespace MathCourse\Admin;

use MathCourse\Tutor\Adapter;

defined('ABSPATH') || exit;

class Course_Page
{
    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $adapter = new Adapter();
        $courses = array();
        $post_type = $adapter->course_post_type();

        if ($post_type) {
            $courses = get_posts(array(
                'post_type' => $post_type,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => 100,
                'orderby' => 'date',
                'order' => 'DESC',
            ));
        }
        ?>
        <div class="wrap">
            <h1>课程管理</h1>
            <p>MathCourse 只负责业务管理与扩展；课程主体继续使用 Tutor LMS 原生 Course。</p>

            <?php if (!$adapter->is_available()): ?>
                <div class="notice notice-warning"><p>未检测到 Tutor LMS，请先安装并启用 Tutor LMS。</p></div>
            <?php elseif (!$post_type): ?>
                <div class="notice notice-warning"><p>已检测到 Tutor LMS，但暂未取得 Course Post Type。</p></div>
            <?php else: ?>
                <p><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $post_type)); ?>">新建课程</a></p>
                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>课程名称</th><th>状态</th><th>更新时间</th><th>操作</th></tr></thead>
                    <tbody>
                    <?php if (!$courses): ?>
                        <tr><td colspan="5">暂无课程。请使用 Tutor LMS 原生课程编辑器创建第一门课程。</td></tr>
                    <?php else: foreach ($courses as $course): ?>
                        <tr>
                            <td><?php echo esc_html($course->ID); ?></td>
                            <td><strong><?php echo esc_html(get_the_title($course)); ?></strong></td>
                            <td><?php echo esc_html($course->post_status); ?></td>
                            <td><?php echo esc_html($course->post_modified); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($course->ID)); ?>">打开 Tutor 编辑器</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
