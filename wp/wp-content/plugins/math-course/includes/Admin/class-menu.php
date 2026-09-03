<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

use MathCourse\Tutor\Adapter;

class Menu {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    public function register_menu() {
        add_menu_page( 'MathCourse', '数学课程', 'manage_options', 'mathcourse', array( $this, 'dashboard' ), 'dashicons-welcome-learn-more', 30 );
        add_submenu_page( 'mathcourse', '课程管理', '课程管理', 'manage_options', 'mathcourse-courses', array( $this, 'courses_page' ) );
        add_submenu_page( 'mathcourse', '批量创建课时', '批量创建课时', 'manage_options', 'mathcourse-batch', array( $this, 'batch_page' ) );
        add_submenu_page( 'mathcourse', '课程授权', '学员授权', 'manage_options', 'mathcourse-access', array( $this, 'access_page' ) );
        add_submenu_page( 'mathcourse', '学习进度', '学习进度', 'manage_options', 'mathcourse-progress', array( $this, 'progress_page' ) );
        add_submenu_page( 'mathcourse', '设置', '系统设置', 'manage_options', 'mathcourse-settings', array( $this, 'settings_page' ) );
        add_submenu_page( null, '编辑课程', '编辑课程', 'manage_options', 'mathcourse-course-edit', array( $this, 'course_edit_page' ) );
    }

    public function dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $tutor = new Adapter();
        $courses = $tutor->get_courses( true, -1 );
        $published_courses_list = $tutor->get_courses( false, -1 );
        $course_count = count( $courses );
        $published_courses = count( $published_courses_list );
        $lesson_count = 0;
        foreach ( $courses as $course ) $lesson_count += $tutor->get_course_lesson_count( $course->ID );
        $user_count = count( get_users( array( 'fields' => 'ids', 'number' => 9999 ) ) );
        ?>
        <div class="wrap mathcourse-admin-wrap">
            <div class="mathcourse-admin-header"><h1>樊老师数学后台</h1><p>课程、课时、学员授权与学习进度的统一管理工作台。</p></div>
            <div class="mathcourse-stat-grid">
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">课程总数</div><div class="mathcourse-stat-num"><?php echo esc_html( $course_count ); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">已发布课程</div><div class="mathcourse-stat-num"><?php echo esc_html( $published_courses ); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">课时总数</div><div class="mathcourse-stat-num"><?php echo esc_html( $lesson_count ); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">学员账号</div><div class="mathcourse-stat-num"><?php echo esc_html( $user_count ); ?></div></div>
            </div>
            <div class="mathcourse-card">
                <div class="mathcourse-card-title"><h2>专题课程管理</h2><a class="button mathcourse-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=mathcourse-courses' ) ); ?>">进入课程管理</a></div>
                <?php if ( $tutor->is_available() ) : ?>
                    <table class="mathcourse-table"><thead><tr><th>课程名称</th><th>课时</th><th>状态</th><th>操作</th></tr></thead><tbody>
                    <?php $courses = $tutor->get_courses( true, 8 ); foreach ( $courses as $course ) : $edit_url = add_query_arg( array( 'page' => 'mathcourse-course-edit', 'course_id' => $course->ID ), admin_url( 'admin.php' ) ); $status_class = 'publish' === $course->post_status ? 'is-published' : 'is-draft'; $status_label = 'publish' === $course->post_status ? '已发布' : ( 'private' === $course->post_status ? '私密' : '草稿' ); ?>
                        <tr><td><strong><?php echo esc_html( $course->post_title ); ?></strong></td><td><?php echo esc_html( $tutor->get_course_lesson_count( $course->ID ) ); ?></td><td><span class="mathcourse-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td><td><a href="<?php echo esc_url( $edit_url ); ?>">编辑课程</a></td></tr>
                    <?php endforeach; ?>
                    <?php if ( empty( $courses ) ) : ?><tr><td colspan="4">暂无课程，请先在 Tutor LMS 创建课程。</td></tr><?php endif; ?></tbody></table>
                <?php else : ?><p>Tutor LMS 尚未启用。启用 Tutor LMS 4.0.4 后，MathCourse 才能接管课程数据。</p><?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function courses_page() { if ( class_exists( 'MathCourse\\Admin\\Course_Page' ) ) ( new Course_Page() )->render(); }
    public function batch_page() { if ( class_exists( 'MathCourse\\Admin\\Batch_Manager_V2' ) ) ( new Batch_Manager_V2() )->render(); }
    public function course_edit_page() { if ( class_exists( 'MathCourse\\Admin\\Course_Editor' ) ) ( new Course_Editor() )->render(); }
    public function access_page() { if ( class_exists( 'MathCourse\\Admin\\Access_Page' ) ) ( new Access_Page() )->render(); }
    public function progress_page() { if ( class_exists( 'MathCourse\\Admin\\Progress_Page' ) ) ( new Progress_Page() )->render(); }
    public function settings_page() { if ( class_exists( 'MathCourse\\Admin\\Settings' ) ) ( new Settings() )->render(); }
}
