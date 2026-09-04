<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

class Menu {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_admin_assets($hook) {
        if (false === strpos((string) $hook, 'mathcourse')) return;
        wp_enqueue_style('mathcourse-admin-ui', MATHCOURSE_URL . 'assets/admin-ui.css', array(), MATHCOURSE_VERSION);
        if (isset($_GET['page']) && 'mathcourse' === sanitize_key(wp_unslash($_GET['page']))) {
            wp_enqueue_style('mathcourse-admin-dashboard', MATHCOURSE_URL . 'assets/admin-dashboard.css', array('mathcourse-admin-ui'), MATHCOURSE_VERSION);
        }
        if (isset($_GET['page']) && 'mathcourse-courses' === sanitize_key(wp_unslash($_GET['page']))) {
            wp_enqueue_style('mathcourse-admin-course', MATHCOURSE_URL . 'assets/admin-course.css', array('mathcourse-admin-ui'), MATHCOURSE_VERSION);
        }
        if (isset($_GET['page']) && 'mathcourse-course-edit' === sanitize_key(wp_unslash($_GET['page']))) {
            wp_enqueue_media();
            wp_enqueue_style('mathcourse-admin-course-editor', MATHCOURSE_URL . 'assets/admin-course-editor.css', array('mathcourse-admin-ui'), MATHCOURSE_VERSION);
            wp_enqueue_style('mathcourse-admin-course-editor-polish', MATHCOURSE_URL . 'assets/admin-course-editor-polish.css', array('mathcourse-admin-course-editor'), MATHCOURSE_VERSION);
            wp_enqueue_style('mathcourse-admin-video-upload', MATHCOURSE_URL . 'assets/admin-video-upload.css', array('mathcourse-admin-course-editor-polish'), MATHCOURSE_VERSION);
        }
    }

    public function register_menu() {
        add_menu_page('MathCourse', '数学课程', 'manage_options', 'mathcourse', array($this, 'dashboard'), 'dashicons-welcome-learn-more', 30);
        add_submenu_page('mathcourse', '课程管理', '课程管理', 'manage_options', 'mathcourse-courses', array($this, 'courses_page'));
        add_submenu_page('mathcourse', '批量创建课时', '批量创建课时', 'manage_options', 'mathcourse-batch', array($this, 'batch_page'));
        add_submenu_page('mathcourse', '课程授权', '学员授权', 'manage_options', 'mathcourse-access', 'manage_options', 'mathcourse-access', array($this, 'access_page'));
        add_submenu_page('mathcourse', '课程激活码', '激活码管理', 'manage_options', 'mathcourse-activation', array($this, 'activation_page'));
        add_submenu_page('mathcourse', '学习进度', '学习进度', 'manage_options', 'mathcourse-progress', array($this, 'progress_page'));
        add_submenu_page('mathcourse', '设置', '系统设置', 'manage_options', 'mathcourse-settings', array($this, 'settings_page'));
        add_submenu_page(null, '编辑课程', '编辑课程', 'manage_options', 'mathcourse-course-edit', array($this, 'course_edit_page));
    }

    public function dashboard() {
        if (!current_user_can('manage_options')) return;
        $tutor = new Adapter(); $access = new Access_Service(); $message = ''; $message_type = 'success';
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['mathcourse_dashboard_action'])) {
            $action = sanitize_key(wp_unslash($_POST['mathcourse_dashboard_action']));
            if ('grant_access' === $action && isset($_POST['mathcourse_dashboard_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_dashboard_nonce'])), 'mathcourse_dashboard_grant')) {
                $phone = sanitize_text_field(wp_unslash($_POST['student_phone'] ?? '')); $course_id = absint($_POST['course_id'] ?? 0);
                $user = $phone !== '' ? get_user_by('login', $phone) : false;
                if (!$user && $phone !== '') { $users = get_users(array('number' => 1, 'search' => '*' . $phone . '*', 'search_columns' => array('user_login', 'user_email'))); $user = !empty($users) ? $users[0] : false; }
                $course = $course_id ? $tutor->get_course($course_id) : false;
                if (!$user) { $message = '未找到该学员账号，请先在“学员授权管理”中创建账号。'; $message_type = 'error'; }
                elseif (!$course || 'publish' !== $course->post_status) { $message = '请选择有效的已发布课程。'; $message_type = 'error'; }
                elseif ($access->grant($user->ID, $course_id)) $message = '已为“' . $user->display_name . '”开通课程授权。';
                else { $message = '课程授权保存失败，请稍后重试。'; $message_type = 'error'; }
            }
        }
        $all_courses = $tutor->get_courses(true, -1); $published_courses = $tutor->get_courses(false, -1); $course_count = count($all_courses); $published_count = count($published_courses); $lesson_count = 0;
        foreach ($all_courses as $course) $lesson_count += $tutor->get_course_lesson_count($course->ID);
        $user_count = count(get_users(array('fields' => 'ids', 'number' => 9999))); $recent_courses = array_slice($all_courses, 0, 6);
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-dashboard-page">
