<?php
namespace MathCourse\Frontend;
defined('ABSPATH') || exit;

class Course_Directory_Assets {
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue'));
    }

    public function enqueue() {
        if (!is_singular()) return;
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'mathcourse_course_directory') && !has_shortcode($post->post_content, 'mathcourse_course_center') && !has_shortcode($post->post_content, 'mathcourse_learning_center')) return;

        wp_enqueue_style('mathcourse-course-directory', MATHCOURSE_URL . 'assets/frontend-course-directory.css', array(), MATHCOURSE_VERSION);
        wp_enqueue_style('mathcourse-course-directory-ui-polish', MATHCOURSE_URL . 'assets/course-directory-ui-polish.css', array('mathcourse-course-directory'), MATHCOURSE_VERSION);
        wp_enqueue_script('mathcourse-course-directory', MATHCOURSE_URL . 'assets/course-directory.js', array(), MATHCOURSE_VERSION, true);
    }
}
