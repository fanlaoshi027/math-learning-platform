<?php
/*
Plugin Name: MathCourse
Plugin URI:
Description: 数学课程管理系统
Version: 1.0.7
Author:
Author URI:
Text Domain: mathcourse
*/
defined('ABSPATH') || exit;
define('MATHCOURSE_VERSION', '1.0.7');
define('MATHCOURSE_PATH', plugin_dir_path(__FILE__));
define('MATHCOURSE_URL', plugin_dir_url(__FILE__));
require_once MATHCOURSE_PATH . 'includes/class-autoloader.php';
\MathCourse\Autoloader::register();
register_activation_hook(__FILE__, function () {
    if (class_exists('\\MathCourse\\Database\\Install')) \MathCourse\Database\Install::activate();
    if (class_exists('\\MathCourse\\Access\\Access_Schema')) \MathCourse\Access\Access_Schema::install();
    if (class_exists('\\MathCourse\\Access\\Activation_Schema')) \MathCourse\Access\Activation_Schema::install();
    if (class_exists('\\MathCourse\\Video\\Video_Router')) { (new \MathCourse\Video\Video_Router(false))->register_route(); flush_rewrite_rules(false); }
    update_option('mathcourse_rewrite_version', MATHCOURSE_VERSION, false);
});
register_deactivation_hook(__FILE__, function () { flush_rewrite_rules(false); });
add_action('plugins_loaded', function () {
    if (class_exists('\\MathCourse\\Access\\Access_Schema') && get_option('mathcourse_access_db_version','') !== \MathCourse\Access\Access_Schema::VERSION) \MathCourse\Access\Access_Schema::install();
    if (class_exists('\\MathCourse\\Access\\Activation_Schema') && get_option('mathcourse_activation_db_version','') !== \MathCourse\Access\Activation_Schema::VERSION) \MathCourse\Access\Activation_Schema::install();
    if (get_option('mathcourse_rewrite_version','') !== MATHCOURSE_VERSION) add_action('init', function () { flush_rewrite_rules(false); update_option('mathcourse_rewrite_version',MATHCOURSE_VERSION,false); },99);
    if (class_exists('\\MathCourse\\Plugin')) (new \MathCourse\Plugin())->run();
});
