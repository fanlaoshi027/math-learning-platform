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

        echo '<div class="wrap"><h1>学员授权管理</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="mathcourse-access">';
        echo '<input type="text" name="s" value="' . esc_attr(sanitize_text_field(wp_unslash($_GET['s'] ?? ''))) . '" placeholder="搜索用户名/邮箱"> ';
        echo '<button class="button">搜索</button></form><hr>';
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

        echo '<table class="widefat striped"><thead><tr><th>学员</th><th>课程</th><th>状态</th><th>操作</th></tr></thead><tbody>';
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

                echo '<tr><td>' . esc_html($user->display_name) . '<br><small>' . esc_html($user->user_email) . '</small></td>';
                echo '<td>' . esc_html($course->post_title) . '</td>';
                echo '<td>' . esc_html($info['status']) . '</td><td>';
                echo '<a class="button ' . ($info['access'] ? '' : 'button-primary') . '" href="' . esc_url($url) . '">' . ($info['access'] ? '取消授权' : '授权') . '</a>';
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';
    }
}
