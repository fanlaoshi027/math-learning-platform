<?php
/**
 * Plugin Name: MathCourse
 * Description: 樊老师数学网校课程管理与学员授权系统。
 * Version: 1.0.0
 * Author: 樊老师
 */
defined('ABSPATH') || exit;
define('MATHCOURSE_VERSION','1.0.0');
define('MATHCOURSE_FILE',__FILE__);
define('MATHCOURSE_DIR',plugin_dir_path(__FILE__));
define('MATHCOURSE_URL',plugin_dir_url(__FILE__));
$mathcourse_files=array(
 'includes/Tutor/class-adapter.php','includes/Access/class-access-service.php','includes/Access/class-access-schema.php','includes/Access/class-activation-schema.php','includes/Access/class-activation-service.php','includes/Progress/class-progress-service.php','includes/Admin/class-menu.php','includes/Admin/class-activation-page.php','includes/Admin/class-course-actions.php','includes/Admin/class-course-sort.php','includes/Admin/class-batch-manager.php','includes/Admin/class-batch-manager-v2.php','includes/Course/class-meta.php','includes/Course/class-lesson-meta.php','includes/Course/class-course-service.php','includes/Progress/class-progress-ajax.php','includes/Tutor/class-hooks.php','includes/Learning/class-lesson-status.php','includes/Learning/class-course-learning.php','includes/Video/class-player.php','includes/Video/class-video-router.php','includes/Video/class-hls-accelerator.php','includes/Frontend/class-activation.php','includes/Frontend/class-student-auth.php','includes/Frontend/class-course-directory.php','includes/Frontend/class-course-player.php','includes/Frontend/class-course-directory-assets.php','includes/class-plugin.php');
foreach($mathcourse_files as $file){$path=MATHCOURSE_DIR.$file;if(file_exists($path))require_once $path;}
function mathcourse_boot(){static $booted=false;if($booted)return;$booted=true;if(class_exists('MathCourse\\Plugin'))(new MathCourse\Plugin())->run();}
add_action('plugins_loaded','mathcourse_boot',20);
register_activation_hook(__FILE__,function(){if(class_exists('MathCourse\\Access\\Access_Schema'))(new MathCourse\Access\Access_Schema())->install();if(class_exists('MathCourse\\Access\\Activation_Schema'))(new MathCourse\Access\Activation_Schema())->install();if(class_exists('MathCourse\\Frontend\\Activation'))MathCourse\Frontend\Activation::ensure_register_page();if(class_exists('MathCourse\\Frontend\\Student_Auth'))MathCourse\Frontend\Student_Auth::ensure_login_page();flush_rewrite_rules();});
