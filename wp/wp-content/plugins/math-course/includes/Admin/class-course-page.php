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

        $adapter = new Adapter();

        echo '<div class="wrap">';
        echo '<h1>课程管理</h1>';
        echo '<p style="color:#646970;">课程、专题和课时由 MathCourse 管理，底层数据仍使用 Tutor LMS。课时数量按「课程 → 专题 → 课时」真实结构统计。</p>';
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
            echo '<td><a class="button" href="' . esc_url(admin_url('admin.php?page=mathcourse-course-edit&course_id=' . $course_id)) . '">编辑</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
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
