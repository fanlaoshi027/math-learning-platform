<?php
/*
Plugin Name: MathCourse
Plugin URI:
Description: 数学课程管理系统
Version: 1.0.2
Author:
Author URI:
Text Domain: mathcourse
*/

defined('ABSPATH') || exit;

define('MATHCOURSE_VERSION', '1.0.2');
define('MATHCOURSE_PATH', plugin_dir_path(__FILE__));
define('MATHCOURSE_URL', plugin_dir_url(__FILE__));

require_once MATHCOURSE_PATH . 'includes/class-autoloader.php';
\MathCourse\Autoloader::register();

/**
 * 插件激活
 *
 * 不修改 WordPress / Tutor LMS 源码。
 * 所有扩展通过 MathCourse 自身模块完成。
 */
register_activation_hook(__FILE__, function () {
    if (class_exists('\\MathCourse\\Database\\Install')) {
        \MathCourse\Database\Install::activate();
    }

    if (class_exists('\\MathCourse\\Access\\Access_Schema')) {
        \MathCourse\Access\Access_Schema::install();
    }

    // Protected HLS 使用 WordPress rewrite route；激活时必须刷新一次规则。
    if (class_exists('\\MathCourse\\Video\\Video_Router')) {
        (new \MathCourse\Video\Video_Router())->register_route();
        flush_rewrite_rules(false);
    }
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules(false);
});

add_action('plugins_loaded', function () {

    // 保证数据库结构存在
    if (class_exists('\\MathCourse\\Access\\Access_Schema')) {
        $version = get_option('mathcourse_access_db_version', '');

        if ($version !== \MathCourse\Access\Access_Schema::VERSION) {
            \MathCourse\Access\Access_Schema::install();
        }
    }

    // 主插件启动
    if (class_exists('\\MathCourse\\Plugin')) {
        (new \MathCourse\Plugin())->run();
    }

});