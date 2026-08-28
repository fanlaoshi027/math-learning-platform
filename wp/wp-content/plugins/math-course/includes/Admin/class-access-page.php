<?php

namespace MathCourse\Admin;

use MathCourse\Access\Access_Service;

defined('ABSPATH') || exit;

class Access_Page
{
    public function render()
    {
        if (!current_user_can('manage_options')) return;

        $message = '';
        if (isset($_POST['mathcourse_access_save'])) {
            check_admin_referer('mathcourse_access_action', 'mathcourse_access_nonce');
            $user_id = absint($_POST['user_id'] ?? 0);
            $course_id = absint($_POST['course_id'] ?? 0);
            $message = ($user_id && $course_id && (new Access_Service())->grant($user_id, $course_id))
                ? '课程授权成功' : '授权失败，请检查用户和课程ID。';
        }

        if (isset($_POST['mathcourse_access_revoke'])) {
            check_admin_referer('mathcourse_access_action', 'mathcourse_access_nonce');
            $message = (new Access_Service())->revoke(absint($_POST['user_id'] ?? 0), absint($_POST['course_id'] ?? 0))
                ? '授权已撤销' : '撤销失败。';
        }

        $records = (new Access_Service())->get_all();
        ?>
        <div class="wrap">
            <h1>课程授权</h1>
            <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('mathcourse_access_action', 'mathcourse_access_nonce'); ?>
                <table class="form-table">
                    <tr><th>学生ID</th><td><input type="number" name="user_id" min="1" required></td></tr>
                    <tr><th>课程ID</th><td><input type="number" name="course_id" min="1" required></td></tr>
                </table>
                <?php submit_button('开通课程', 'primary', 'mathcourse_access_save'); ?>
            </form>

            <h2>授权记录</h2>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>学生ID</th><th>课程ID</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead>
                <tbody>
                <?php if (!$records): ?>
                    <tr><td colspan="6">暂无授权记录</td></tr>
                <?php else: foreach ($records as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->id); ?></td>
                        <td><?php echo esc_html($row->user_id); ?></td>
                        <td><?php echo esc_html($row->course_id); ?></td>
                        <td><?php echo esc_html($row->status); ?></td>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td>
                            <?php if ($row->status === 'active'): ?>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('mathcourse_access_action', 'mathcourse_access_nonce'); ?>
                                <input type="hidden" name="user_id" value="<?php echo esc_attr($row->user_id); ?>">
                                <input type="hidden" name="course_id" value="<?php echo esc_attr($row->course_id); ?>">
                                <?php submit_button('撤销', 'delete small', 'mathcourse_access_revoke', false); ?>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
