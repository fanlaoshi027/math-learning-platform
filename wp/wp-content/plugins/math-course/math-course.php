<?php
/*
Plugin Name: MathCourse
Plugin URI:
Description: 数学课程管理系统
Version: 1.0.0
Author:
Author URI:
Text Domain: mathcourse
*/

defined('ABSPATH') || exit;

define('MATHCOURSE_VERSION', '1.0.0');
define('MATHCOURSE_PATH', plugin_dir_path(__FILE__));
define('MATHCOURSE_URL', plugin_dir_url(__FILE__));

require_once MATHCOURSE_PATH . 'includes/class-autoloader.php';
\MathCourse\Autoloader::register();

register_activation_hook(__FILE__, function () {
    if (class_exists('\\MathCourse\\Database\\Install')) {
        \MathCourse\Database\Install::activate();
    }
    if (class_exists('\\MathCourse\\Access\\Access_Schema')) {
        \MathCourse\Access\Access_Schema::install();
    }
});

add_action('plugins_loaded', function () {
    if (class_exists('\\MathCourse\\Access\\Access_Schema')) {
        $version = get_option('mathcourse_access_db_version', '');
        if ($version !== \MathCourse\Access\Access_Schema::VERSION) {
            \MathCourse\Access\Access_Schema::install();
        }
    }
    if (class_exists('\\MathCourse\\Plugin')) {
        (new \MathCourse\Plugin())->run();
    }
});
