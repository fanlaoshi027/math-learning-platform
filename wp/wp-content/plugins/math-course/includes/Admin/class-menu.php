<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;

class Menu
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
    }

    public function register_menu()
    {
        add_menu_page('MathCourse', 'MathCourse', 'manage_options', 'mathcourse', array($this, 'dashboard'), 'dashicons-welcome-learn-more', 30);
        add_submenu_page('mathcourse', '课程管理', '课程管理', 'manage_options', 'mathcourse-courses', array($this, 'course_page'));
        add_submenu_page('mathcourse', '课程系统', '课程系统', 'manage_options', 'mathcourse-tutor', array($this, 'tutor_page'));
        add_submenu_page('mathcourse', '课程授权', '课程授权', 'manage_options', 'mathcourse-access', array($this, 'access_page'));
        add_submenu_page('mathcourse', '设置', '设置', 'manage_options', 'mathcourse-settings', array($this, 'settings_page'));
    }

    public function dashboard()
    {
        if (class_exists('MathCourse\\Admin\\Dashboard')) {
            (new Dashboard())->render();
            return;
        }
        echo '<div class="wrap"><h1>MathCourse</h1><p>数学课程管理系统</p></div>';
    }

    public function course_page()
    {
        if (class_exists('MathCourse\\Admin\\Course_Page')) {
            (new Course_Page())->render();
        }
    }

    public function tutor_page()
    {
        if (class_exists('MathCourse\\Admin\\Tutor_Page')) {
            (new Tutor_Page())->render();
        }
    }

    public function access_page()
    {
        if (class_exists('MathCourse\\Admin\\Access_Page')) {
            (new Access_Page())->render();
        }
    }

    public function settings_page()
    {
        if (class_exists('MathCourse\\Admin\\Settings')) {
            (new Settings())->render();
        }
    }
}
