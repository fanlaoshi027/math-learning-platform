<?php
namespace MathCourse\Admin;
defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

class Access_Page {
    public function render() {
        if (!current_user_can('manage_options')) return;

        $service = new Access_Service();
        $this->handle_action($service);

        echo '<div class="wrap mathcourse-admin-wrap mathcourse-access-page">';
        echo '<div class="mathcourse-admin-header"><div><div class="mathcourse-eyebrow">MathCourse · 学员管理</div><h1>学员授权管理</h1><p>为学员开通或取消课程访问权限，授权状态与课程内容保持实时同步。</p></div></div>';
        echo '<div class="mathcourse-access-toolbar"><form method="get">';
        echo '<input type="hidden" name="page" value="mathcourse-access">';
        echo '<input class="mathcourse-search-input" type="text" name="s" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['s'] ?? ''))) . '" placeholder="搜索用户名、姓名或邮箱">';
        echo '<button class="button button-primary">搜索学员</button></form></div>';
        $this->render_table($service);
        echo '</div>';
    }

    private function handle_action($service) {
        $action = isset($_GET['action_type']) ? sanitize_key(wp_unslash($_GET['action_type'])) : '';
        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;

        if (!$action || !$user_id || !$course_id) return;
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mathcourse_access_action')) return;
        if (!current_user_can('manage_options')) return;

        $adapter = new Adapter();
        $course = $adapter->get_course($course_id);
        if (!$course || 'publish' !== $course->post_status) return;

        if ('grant' === $action) {
            $service->grant($user_id, $course_id);
        } elseif ('revoke' === $action) {
            $service->revoke($user_id, $course_id);
        }
    }

    private function render_table($service) {
        $keyword = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $args = array('number' => 20, 'orderby' => 'ID', 'order' => 'ASC');
        if ($keyword !== '') {
            $args['search'] = '*' . $keyword . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }
        $users = get_users($args);

        $adapter = new Adapter();
        $courses = $adapter->get_courses(20);

        echo '<div class="mathcourse-access-card">';
        echo '<div class="mathcourse-card-title"><div><h2>课程授权列表</h2><span class="mathcourse-card-subtitle">显示当前学员与课程的授权关系</span></div></div>';
        echo '<div class="mathcourse-access-table-wrap"><table class="mathcourse-access-table"><thead><tr><th>学员</th><th>课程</th><th>状态</th><th>操作</th></tr></thead><tbody>';
        foreach ($users as $user) {
            foreach ($courses as $course) {
                $info = $service->get_access_info($user->ID, $course->ID);
                $nonce = wp_create_nonce('mathcourse_access_action');
                $url = add_query_arg(array(
                    'page' => 'mathcourse-access',
                    'action_type' => $info['access'] ? 'revoke' : 'grant',
                    'user_id' => $user->ID,
                    'course_id' => $course->ID,
                    '_wpnonce' => $nonce,
                ), admin_url('admin.php'));

                $status_class = $info['access'] ? 'is-active' : 'is-inactive';
                echo '<tr><td><div class="mathcourse-user-cell"><span class="mathcourse-user-avatar">' . esc_html(mb_strtoupper(mb_substr($user->display_name, 0, 1))) . '</span><div><strong>' . esc_html($user->display_name) . '</strong><small>' . esc_html($user->user_email) . '</small></div></div></td>';
                echo '<td><strong class="mathcourse-course-name">' . esc_html($course->post_title) . '</strong></td>';
                echo '<td><span class="mathcourse-access-status ' . esc_attr($status_class) . '"><i></i>' . esc_html($info['status']) . '</span></td><td>';
                echo '<a class="button ' . ($info['access'] ? 'mathcourse-revoke-button' : 'button-primary') . '" href="' . esc_url($url) . '">' . ($info['access'] ? '取消授权' : '授权') . '</a>';
                echo '</td></tr>';
            }
        }
        echo '</tbody></table></div></div>';
    }
}
