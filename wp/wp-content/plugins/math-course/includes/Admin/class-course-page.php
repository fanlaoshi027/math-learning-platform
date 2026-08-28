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
            <p>MathCourse 负责业务管理与扩展；课程主体继续使用 Tutor LMS 原生 Course。</p>

            <?php if (!$adapter->is_available()): ?>
                <div class="notice notice-warning"><p>未检测到 Tutor LMS，请先安装并启用 Tutor LMS。</p></div>
            <?php elseif (!$post_type): ?>
                <div class="notice notice-warning"><p>已检测到 Tutor LMS，但暂未取得 Course Post Type。</p></div>
            <?php else: ?>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $post_type)); ?>">新建课程</a>
                </p>

                <table class="widefat striped">
                    <thead>
                        <tr><th>ID</th><th>课程名称</th><th>状态</th><th>更新时间</th><th>内容</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$courses): ?>
                        <tr><td colspan="6">暂无课程。请使用 Tutor LMS 原生课程编辑器创建第一门课程。</td></tr>
                    <?php else: foreach ($courses as $course):
                        $tree = $adapter->get_course_tree($course->ID);
                        $topic_count = count($tree['topics']);
                        $lesson_count = 0;
                        foreach ($tree['topics'] as $topic_data) {
                            $lesson_count += count($topic_data['lessons']);
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html($course->ID); ?></td>
                            <td><strong><?php echo esc_html(get_the_title($course)); ?></strong></td>
                            <td><?php echo esc_html($course->post_status); ?></td>
                            <td><?php echo esc_html($course->post_modified); ?></td>
                            <td><?php echo esc_html($topic_count . ' 个 Topic / ' . $lesson_count . ' 个 Lesson'); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($course->ID)); ?>">打开 Tutor 编辑器</a></td>
                        </tr>
                        <tr>
                            <td colspan="6" style="padding:0 20px 20px 40px;">
                                <?php if (!$topic_count): ?>
                                    <p style="margin:12px 0 0;">暂无 Topic。</p>
                                <?php else: ?>
                                    <details>
                                        <summary style="cursor:pointer;padding:10px 0;"><strong>查看 Course → Topic → Lesson</strong></summary>
                                        <ul style="margin-top:8px;">
                                        <?php foreach ($tree['topics'] as $topic_data):
                                            $topic = $topic_data['topic'];
                                        ?>
                                            <li style="margin:8px 0;">
                                                <strong><?php echo esc_html(get_the_title($topic)); ?></strong>
                                                <span class="description">（<?php echo esc_html(count($topic_data['lessons'])); ?> 个 Lesson）</span>
                                                <?php if ($topic_data['lessons']): ?>
                                                    <ol style="margin:6px 0 0 24px;">
                                                    <?php foreach ($topic_data['lessons'] as $lesson):
                                                        $page_number = get_post_meta($lesson->ID, 'page_number', true);
                                                        $label = get_the_title($lesson);
                                                        if ($page_number !== '') {
                                                            $label .= ' · 页码 ' . absint($page_number);
                                                        }
                                                    ?>
                                                        <li>
                                                            <?php echo esc_html($label); ?>
                                                            <span class="description">（ID <?php echo esc_html($lesson->ID); ?>）</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                    </ol>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
