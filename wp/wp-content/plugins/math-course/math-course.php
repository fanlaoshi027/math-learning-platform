<?php
/*
Plugin Name: MathCourse
Description: 数学课程管理系统
Version: 1.0.1
Author:
Text Domain: mathcourse
*/

defined('ABSPATH') || exit;

define('MATHCOURSE_VERSION', '1.0.1');
define('MATHCOURSE_PATH', plugin_dir_path(__FILE__));
define('MATHCOURSE_URL', plugin_dir_url(__FILE__));

require_once MATHCOURSE_PATH . 'includes/class-autoloader.php';
\MathCourse\Autoloader::register();

register_activation_hook(__FILE__, function () {
    if (class_exists('\\MathCourse\\Database\\Install')) {
        \MathCourse\Database\Install::activate();
    }
});

add_action('plugins_loaded', function () {
    if (class_exists('\\MathCourse\\DB_Upgrader')) {
        (new \MathCourse\DB_Upgrader())->maybe_upgrade();
    }

    if (class_exists('\\MathCourse\\Plugin')) {
        (new \MathCourse\Plugin())->run();
    }
});
