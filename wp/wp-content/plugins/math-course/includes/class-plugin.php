<?php
namespace MathCourse;
defined('ABSPATH') || exit;

class Plugin {
    public function run() { $this->load_modules(); $this->load_assets(); $this->load_admin_assets(); }

    private function load_modules() {
        // Load foundational services before consumers that instantiate them.
        if (class_exists('MathCourse\\Access\\Access_Service')) new Access\Access_Service();
        if (class_exists('MathCourse\\Tutor\\Adapter')) new Tutor\Adapter();
        if (class_exists('MathCourse\\Progress\\Progress_Service')) new Progress\Progress_Service();

        if (class_exists('MathCourse\\Admin\\Menu')) new Admin\Menu();
        if (class_exists('MathCourse\\Admin\\Course_Actions')) new Admin\Course_Actions();
        if (class_exists('MathCourse\\Admin\\Course_Sort')) new Admin\Course_Sort();
        if (class_exists('MathCourse\\Admin\\Batch_Manager')) new Admin\Batch_Manager();
        if (class_exists('MathCourse\\Course\\Meta')) new Course\Meta();
        if (class_exists('MathCourse\\Course\\Lesson_Meta')) new Course\Lesson_Meta();
        if (class_exists('MathCourse\\Course\\Course_Service')) new Course\Course_Service();
        if (class_exists('MathCourse\\Progress\\Progress_Ajax')) new Progress\Progress_Ajax();
        if (class_exists('MathCourse\\Tutor\\Hooks')) new Tutor\Hooks();
        if (class_exists('MathCourse\\Learning\\Lesson_Status')) new Learning\Lesson_Status();
        if (class_exists('MathCourse\\Learning\\Course_Learning')) new Learning\Course_Learning();
        if (class_exists('MathCourse\\Video\\Player')) new Video\Player();
        if (class_exists('MathCourse\\Video\\Video_Router')) new Video\Video_Router();
        if (class_exists('MathCourse\\Frontend\\Course_Directory')) new Frontend\Course_Directory();
        if (class_exists('MathCourse\\Frontend\\Course_Player')) new Frontend\Course_Player();
        if (class_exists('MathCourse\\Frontend\\Course_Directory_Assets')) new Frontend\Course_Directory_Assets();
    }

    private function load_assets() {
        add_action('wp_enqueue_scripts', function() {
            wp_enqueue_style('mathcourse', MATHCOURSE_URL.'assets/css/mathcourse.css', array(), MATHCOURSE_VERSION);
        });
    }

    private function load_admin_assets() {
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_style('mathcourse-admin', MATHCOURSE_URL.'assets/css/admin.css', array(), MATHCOURSE_VERSION);
        });
    }
}