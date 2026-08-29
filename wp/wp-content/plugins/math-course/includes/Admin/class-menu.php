<?php

namespace MathCourse\Admin;

defined( 'ABSPATH' ) || exit;

class Menu {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    public function register_menu() {

        add_menu_page(
            'MathCourse',
            '数学课程',
            'manage_options',
            'mathcourse',
            array( $this, 'dashboard' ),
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'mathcourse',
            '课程管理',
            '课程管理',
            'manage_options',
            'mathcourse-courses',
            array( $this, 'courses_page' )
        );

        add_submenu_page(
            'mathcourse',
            '课程授权',
            '学员授权',
            'manage_options',
            'mathcourse-access',
            array( $this, 'access_page' )
        );

        add_submenu_page(
            'mathcourse',
            '学习进度',
            '学习进度',
            'manage_options',
            'mathcourse-progress',
            array( $this, 'progress_page' )
        );

        add_submenu_page(
            'mathcourse',
            '设置',
            '系统设置',
            'manage_options',
            'mathcourse-settings',
            array( $this, 'settings_page' )
        );

        add_submenu_page(
            null,
            '编辑课程',
            '编辑课程',
            'manage_options',
            'mathcourse-course-edit',
            array( $this, 'course_edit_page' )
        );
    }

    public function dashboard() {
        echo '<div class="wrap"><h1>MathCourse</h1><p>数学课程管理后台</p></div>';
    }

    public function courses_page() {
        if ( class_exists( 'MathCourse\\Admin\\Course_Page' ) ) {
            ( new Course_Page() )->render();
        }
    }

    public function course_edit_page() {
        if ( class_exists( 'MathCourse\\Admin\\Course_Editor' ) ) {
            ( new Course_Editor() )->render();
        }
    }

    public function access_page() {
        if ( class_exists( 'MathCourse\\Admin\\Access_Page' ) ) {
            ( new Access_Page() )->render();
        }
    }

    public function progress_page() {
        echo '<div class="wrap"><h1>学习进度</h1><p>查看学员课程完成情况</p></div>';
    }

    public function settings_page() {
        if ( class_exists( 'MathCourse\\Admin\\Settings' ) ) {
            ( new Settings() )->render();
        }
    }
}
