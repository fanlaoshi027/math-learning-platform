<?php
/*
Plugin Name: MathCourse
Description: 数学课程管理系统
Version: 1.0.0
Author:
Text Domain: mathcourse
*/

defined('ABSPATH') || exit;

define('MATHCOURSE_VERSION', '1.0.0');
define('MATHCOURSE_PATH', plugin_dir_path(__FILE__));
define('MATHCOURSE_URL', plugin_dir_url(__FILE__));

require_once MATHCOURSE_PATH . 'includes/class-autoloader.php';
\MathCourse\Autoloader::register();

register_activation_hook(__FILE__, function () {
    // WordPress 会把激活期间的任何直接输出显示为“意外输出”。
    // 捕获并丢弃激活流程中的非预期输出，同时记录到 PHP error log 便于排查。
    ob_start();

    try {
        if (class_exists('\\MathCourse\\Database\\Install')) {
            \MathCourse\Database\Install::activate();
        }
    } finally {
        $output = ob_get_clean();
        if ($output !== '' && function_exists('error_log')) {
            error_log('MathCourse activation unexpected output: ' . $output);
        }
    }
});

add_action('plugins_loaded', function () {
    if (class_exists('\\MathCourse\\Plugin')) {
        (new \MathCourse\Plugin())->run();
    }
});
