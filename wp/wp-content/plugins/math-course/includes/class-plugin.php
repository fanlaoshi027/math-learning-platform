<?php
namespace MathCourse;
defined('ABSPATH') || exit;

class Plugin {
    public function run() {
        $this->load_modules();
        $this->load_assets();
    }

    private function load_modules() {
        if (class_exists('MathCourse\\Admin\\Menu')) new Admin\Menu();
        if (class_exists('MathCourse\\Admin\\Course_Actions')) new Admin\Course_Actions();
        if (class_exists('MathCourse\\Course\\Meta')) new Course\Meta();
        if (class_exists('MathCourse\\Course\\Course_Service')) new Course\Course_Service();
        if (class_exists('MathCourse\\Access\\Access_Service')) new Access\Access_Service();
        if (class_exists('MathCourse\\Progress\\Progress_Service')) new Progress\Progress_Service();
        if (class_exists('MathCourse\\Progress\\Progress_Ajax')) new Progress\Progress_Ajax();
        if (class_exists('MathCourse\\Tutor\\Hooks')) new Tutor\Hooks();
        if (class_exists('MathCourse\\Tutor\\Adapter')) new Tutor\Adapter();
        if (class_exists('MathCourse\\Admin\\Order_Manager')) new Admin\Order_Manager();
        if (class_exists('MathCourse\\Video\\Player')) new Video\Player();
        if (class_exists('MathCourse\\Video\\Video_Router')) new Video\Video_Router();
        if (class_exists('MathCourse\\Frontend\\Course_Directory')) new Frontend\Course_Directory();
        if (class_exists('MathCourse\\Frontend\\Course_Directory_Assets')) new Frontend\Course_Directory_Assets();
    }

    private function load_assets() {
        add_action('wp_enqueue_scripts', function() {
            wp_enqueue_style('mathcourse', MATHCOURSE_URL . 'assets/css/mathcourse.css', array(), MATHCOURSE_VERSION);
            wp_enqueue_script('mathcourse-player', MATHCOURSE_URL . 'assets/js/player.js', array('jquery'), MATHCOURSE_VERSION, true);
            wp_localize_script('mathcourse-player','MathCourseData',array(
                'ajaxurl'=>admin_url('admin-ajax.php')
            ));
        });
    }
}
