<?php

namespace MathCourse\Admin;

use MathCourse\Tutor\Adapter;

defined('ABSPATH') || exit;

class Course_Page
{
    public function render()
    {
        if (!current_user_can('manage_options')) return;

        $adapter = new Adapter();
        $notice = $this->handle_test_builder($adapter);
        $courses = array();
        $post_type = $adapter->course_post_type();

        if ($post_type) {
            $courses = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => 100,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ));
        }
        ?>
        <div class="wrap">
            <h1>课程管理</h1>
            <p>MathCourse 负责业务管理与扩展；课程主体继续使用 Tutor LMS 原生 Course。</p>

            <?php if ($notice): ?>
                <div class="notice <?php echo $notice['type'] === 'success' ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$adapter->is_available()): ?>
                <div class="notice notice-warning"><p>未检测到 Tutor LMS，请先安装并启用 Tutor LMS。</p></div>
            <?php elseif (!$post_type): ?>
                <div class="notice notice-warning"><p>已检测到 Tutor LMS，但暂未取得 Course Post Type。</p></div>
            <?php else: ?>
                <p><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $post_type)); ?>">新建课程</a></p>

                <div style="background:#fff;border:1px solid #ccd0d4;padding:16px 20px;margin:20px 0;max-width:900px;">
                    <h2 style="margin-top:0;">v0.2 课程链路测试</h2>
                    <p>仅用于验证 Tutor LMS 4.0.4 的 Course → Topic → Lesson → 页码关系，不修改 Tutor 插件源码。</p>
                    <form method="post">
                        <?php wp_nonce_field('mathcourse_create_test_course', '_mathcourse_nonce'); ?>
                        <input type="hidden" name="mathcourse_action" value="create_test_course">
                        <label>测试课程名称
                            <input type="text" name="test_course_title" value="大培优·八年级上册" class="regular-text" required>
                        </label>
                        <label style="margin-left:12px;">页码
                            <input type="number" name="start_page" value="1" min="1" style="width:80px;"> ～
                            <input type="number" name="end_page" value="10" min="1" style="width:80px;">
                        </label>
                        <?php submit_button('创建测试课程结构', 'secondary', 'submit', false, array('style' => 'margin-left:12px;')); ?>
                    </form>
                </div>

                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>课程名称</th><th>状态</th><th>更新时间</th><th>内容</th><th>操作</th></tr></thead>
                    <tbody>
                    <?php if (!$courses): ?>
                        <tr><td colspan="6">暂无课程。</td></tr>
                    <?php else: foreach ($courses as $course):
                        $tree = $adapter->get_course_tree($course->ID);
                        $topic_count = count($tree['topics']);
                        $lesson_count = 0;
                        foreach ($tree['topics'] as $topic_data) $lesson_count += count($topic_data['lessons']);
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
                                        <?php foreach ($tree['topics'] as $topic_data): $topic = $topic_data['topic']; ?>
                                            <li style="margin:8px 0;">
                                                <strong><?php echo esc_html(get_the_title($topic)); ?></strong>
                                                <span class="description">（<?php echo esc_html(count($topic_data['lessons'])); ?> 个 Lesson）</span>
                                                <?php if ($topic_data['lessons']): ?>
                                                    <ol style="margin:6px 0 0 24px;">
                                                    <?php foreach ($topic_data['lessons'] as $lesson):
                                                        $page_number = get_post_meta($lesson->ID, 'page_number', true);
                                                        $label = get_the_title($lesson);
                                                        if ($page_number !== '') $label .= ' · 页码 ' . absint($page_number);
                                                    ?>
                                                        <li><?php echo esc_html($label); ?> <span class="description">（ID <?php echo esc_html($lesson->ID); ?>）</span></li>
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

    private function handle_test_builder(Adapter $adapter)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (isset($_POST['mathcourse_action']) ? sanitize_key(wp_unslash($_POST['mathcourse_action'])) : '') !== 'create_test_course') return null;
        if (!current_user_can('manage_options')) return array('type' => 'error', 'message' => '没有权限。');
        if (!isset($_POST['_mathcourse_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mathcourse_nonce'])), 'mathcourse_create_test_course')) {
            return array('type' => 'error', 'message' => '安全校验失败，请刷新页面后重试。');
        }

        $title = isset($_POST['test_course_title']) ? sanitize_text_field(wp_unslash($_POST['test_course_title'])) : '';
        $start = isset($_POST['start_page']) ? absint($_POST['start_page']) : 1;
        $end = isset($_POST['end_page']) ? absint($_POST['end_page']) : 10;
        if ($title === '' || $start < 1 || $end < $start) return array('type' => 'error', 'message' => '测试参数无效。');

        $existing = get_page_by_title($title, OBJECT, $adapter->course_post_type());
        if ($existing) return array('type' => 'error', 'message' => '已存在同名测试课程（ID ' . $existing->ID . '），避免重复创建。');

        $course = $adapter->create_course($title);
        if (is_wp_error($course)) return array('type' => 'error', 'message' => $course->get_error_message());

        $result = $adapter->create_page_lessons($course->ID, '第1章·页码测试', $start, $end);
        if (is_wp_error($result)) {
            return array('type' => 'error', 'message' => '课程已创建（ID ' . $course->ID . '），但课时批量创建失败：' . $result->get_error_message());
        }

        return array('type' => 'success', 'message' => '测试成功：Course ID ' . $course->ID . '，Topic ID ' . $result['topic']->ID . '，Lesson ' . count($result['lessons']) . ' 个。请进入 Tutor Course Builder 核对。');
    }
}
