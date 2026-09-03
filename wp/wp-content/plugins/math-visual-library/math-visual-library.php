<?php
/**
 * Plugin Name: 中小学理科矢量图库
 * Description: 为 MathCourse 及全站提供小学、初中、高中数学，初高中物理、化学的 SVG 矢量资源与统一调用接口。
 * Version: 0.1.0
 * Author: 樊老师
 */
defined('ABSPATH') || exit;
define('MATHVISUAL_VERSION', '0.1.0');
define('MATHVISUAL_FILE', __FILE__);
define('MATHVISUAL_DIR', plugin_dir_path(__FILE__));
define('MATHVISUAL_URL', plugin_dir_url(__FILE__));
require_once MATHVISUAL_DIR . 'includes/class-library.php';
require_once MATHVISUAL_DIR . 'includes/class-admin.php';
\MathVisual\Library::init();
\MathVisual\Admin::init();
register_activation_hook(__FILE__, function () { \MathVisual\Library::register_default_assets(); });
