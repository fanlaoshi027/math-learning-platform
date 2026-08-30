<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

class Course_Page {

    /**
     * 后台课程列表。
     */
    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $message = '';
        $message_type = 'success';

        if ('POST' === $_SERVER['REQUEST_METHOD'] && !empty($_POST['mathcourse_sync_permissions'])) {
            $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
            if ($course_id && check_admin_referer('mathcourse_sync_permissions_' . $course_id)) {
                $result = $this->sync_course_permissions($course_id);
                if (is_wp_error($result)) {
                    $message = $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = sprintf('课程权限已同步：课程已设为公开浏览 + Paid，%d 个课时已设为 Published。', (int) $result['lessons']);
                }
            }
        }

        $adapter = new Adapter();

        echo '<div class="wrap">';
        echo '<h1>课程管理</h1>';
        echo '<p style="color:#646970;">课程、专题和课时由 MathCourse 管理，底层数据仍使用 Tutor LMS。MathCourse 负责最终的授权、试看和播放权限。</p>';
        if ($message) {
            echo '<div class="notice notice-' . esc_attr($message_type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>课程</th><th>封面</th><th>课时</th><th>状态</th><th>操作</th></tr></thead>';
        echo '<tbody>';

        foreach ($this->get_courses() as $course) {
            $course_id = (int) $course->ID;

            echo '<tr>';
            echo '<td><strong>' . esc_html($course->post_title) . '</strong></td>';
            echo '<td>';

            $cover = get_the_post_thumbnail_url($course_id, 'thumbnail');
            if (!$cover) {
                $cover = get_post_meta($course_id, '_mathcourse_cover', true);
            }

            if ($cover) {
                echo '<img src="' . esc_url($cover) . '" width="80" height="45" style="object-fit:cover;border-radius:6px;">';
            } else {
                echo '-';
            }

            echo '</td>';
            echo '<td><strong>' . esc_html($adapter->get_course_lesson_count($course_id)) . '</strong></td>';
            echo '<td>' . esc_html(ucfirst($course->post_status)) . '</td>';
            echo '<td style="white-space:nowrap;">';
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=mathcourse-course-edit&course_id=' . $course_id)) . '">编辑</a> ';
            echo '<form method="post" style="display:inline-block;margin-left:4px;">';
            wp_nonce_field('mathcourse_sync_permissions_' . $course_id);
            echo '<input type="hidden" name="course_id" value="' . esc_attr($course_id) . '">';
            echo '<input type="hidden" name="mathcourse_sync_permissions" value="1">';
            echo '<button type="submit" class="button" onclick="return confirm(\'同步后：课程设为公开浏览 + Paid，现有课时设为 Published。是否继续？\');">同步课程权限</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * 将已有 Tutor LMS 课程接管到 MathCourse 的权限模型。
     * 公开只负责让课程目录可被浏览；Paid 防止 Tutor 将课程当作免费课程。
     * 真正的完整播放权限仍由 MathCourse Access_Service 决定。
     */
    private function sync_course_permissions($course_id) {
        $course_id = absint($course_id);

        if (!$course_id || !function_exists('tutor') || tutor()->course_post_type !== get_post_type($course_id)) {
            return new \WP_Error('invalid_course', '课程不存在或 Tutor LMS 未启用。');
        }

        if (!current_user_can('edit_post', $course_id)) {
            return new \WP_Error('forbidden', '没有权限同步这个课程。');
        }

        // Tutor LMS：允许公开浏览，但明确标记为 Paid。
        update_post_meta($course_id, '_tutor_is_public_course', 'yes');
        update_post_meta($course_id, '_tutor_course_price_type', 'paid');

        // MathCourse 明确记录该课程由自己的授权体系接管。
        update_post_meta($course_id, '_mathcourse_permission_mode', 'authorization');

        $adapter = new Adapter();
        $lessons_count = 0;

        foreach ($adapter->get_topics($course_id) as $topic) {
            $lessons = $adapter->get_lessons($topic->ID);
            foreach ($lessons as $lesson) {
                if (current_user_can('edit_post', $lesson->ID) && 'publish' !== $lesson->post_status) {
                    wp_update_post(array(
                        'ID' => $lesson->ID,
                        'post_status' => 'publish',
                    ));
                }
                update_post_meta($lesson->ID, '_mathcourse_permission_mode', 'authorization');
                if (!metadata_exists('post', $lesson->ID, '_mathcourse_preview')) {
                    update_post_meta($lesson->ID, '_mathcourse_preview', 'no');
                }
                $lessons_count++;
            }
        }

        return array('lessons' => $lessons_count);
    }

    /**
     * 获取 Tutor LMS 课程。
     */
    private function get_courses() {
        if (!function_exists('tutor')) {
            return array();
        }

        return get_posts(array(
            'post_type'      => tutor()->course_post_type,
            'post_status'    => array('publish', 'draft', 'private'),
            'posts_per_page' => 50,
            'orderby'        => array('menu_order' => 'ASC', 'date' => 'DESC'),
        ));
    }

    /**
     * 保留旧方法，避免其他代码调用时产生兼容问题。
     */
    private function lesson_count($course_id) {
        return (new Adapter())->get_course_lesson_count($course_id);
    }
}
