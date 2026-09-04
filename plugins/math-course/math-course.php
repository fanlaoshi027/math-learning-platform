<?php
/**
 * Plugin Name: MathCourse
 * Description: 樊老师数学网校课程管理与学员授权系统。
 * Version: 1.0.11
 * Author: 樊老师
 */
defined('ABSPATH') || exit;

define('MATHCOURSE_VERSION','1.0.11');
define('MATHCOURSE_FILE',__FILE__);
define('MATHCOURSE_DIR',plugin_dir_path(__FILE__));
define('MATHCOURSE_URL',plugin_dir_url(__FILE__));

/* MP4 临时上传目录与 WordPress 网站目录分离，避免原始视频落到公开 uploads。 */
if (!defined('MATHCOURSE_VIDEO_UPLOAD_ROOT')) {
    define('MATHCOURSE_VIDEO_UPLOAD_ROOT','/www/wwwroot/fanlaoshishu-media/uploads');
}

/* HLS 成品统一写入独立媒体目录；播放器仍通过 /__mathcourse_hls/ 逻辑路径访问。 */
if (!defined('MATHCOURSE_MEDIA_ROOT')) {
    define('MATHCOURSE_MEDIA_ROOT','/www/wwwroot/fanlaoshishu-media/hls');
}

/* 某些服务器未安装 mbstring；课程目录命名不应因此导致后台上传 500。 */
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null) {
        return strlen((string) $string);
    }
}

$mathcourse_files=array(
 'includes/Tutor/class-adapter.php',
 'includes/Access/class-access-service.php',
 'includes/Access/class-access-schema.php',
 'includes/Access/class-activation-schema.php',
 'includes/Access/class-activation-service.php',
 'includes/Progress/class-progress-service.php',
 'includes/Admin/class-menu.php',
 'includes/Admin/class-activation-page.php',
 'includes/Admin/class-access-page.php',
 'includes/Admin/class-progress-page.php',
 'includes/Admin/class-settings.php',
 'includes/Admin/class-course-actions.php',
 'includes/Admin/class-course-page.php',
 'includes/Admin/class-course-trash-page.php',
 'includes/Admin/class-course-editor.php',
 'includes/Admin/class-course-sort.php',
 'includes/Admin/class-batch-manager-v2.php',
 'includes/Admin/class-demo-importer.php',
 'includes/Admin/class-student-page.php',
 'includes/Course/class-meta.php',
 'includes/Course/class-lesson-meta.php',
 'includes/Course/class-course-service.php',
 'includes/Progress/class-progress-ajax.php',
 'includes/Tutor/class-hooks.php',
 'includes/Learning/class-lesson-status.php',
 'includes/Learning/class-course-learning.php',
 'includes/Video/class-player.php',
 'includes/Video/class-video-router.php',
 'includes/Video/class-hls-accelerator.php',
 'includes/Video/class-local-hls-source.php',
 'includes/Video/class-hls-converter.php',
 'includes/Frontend/class-activation.php',
 'includes/Frontend/class-student-auth.php',
 'includes/Frontend/class-course-directory.php',
 'includes/Frontend/class-course-player.php',
 'includes/Frontend/class-course-directory-assets.php',
 'includes/class-plugin.php'
);

foreach($mathcourse_files as $file){
    $path=MATHCOURSE_DIR.$file;
    if(file_exists($path)) require_once $path;
}

function mathcourse_boot(){
    static $booted=false;
    if($booted) return;
    $booted=true;
    if(class_exists('MathCourse\\Plugin')) (new MathCourse\Plugin())->run();
}
add_action('plugins_loaded','mathcourse_boot',20);

/* 更新插件后自动刷新一次重写规则，确保 /math-video/ 保护播放路由立即生效。 */
add_action('init', function(){
    $rewrite_version = '1.0.11';
    if (get_option('_mathcourse_rewrite_version') !== $rewrite_version) {
        flush_rewrite_rules(false);
        update_option('_mathcourse_rewrite_version', $rewrite_version, false);
    }
}, 99);

register_activation_hook(__FILE__,function(){
    if(class_exists('MathCourse\\Access\\Access_Schema')) (new MathCourse\Access\Access_Schema())->install();
    if(class_exists('MathCourse\\Access\\Activation_Schema')) (new MathCourse\Access\Activation_Schema())->install();
    if(class_exists('MathCourse\\Frontend\\Activation')) MathCourse\Frontend\Activation::ensure_register_page();
    if(class_exists('MathCourse\\Frontend\\Student_Auth')) MathCourse\Frontend\Student_Auth::ensure_login_page();
    flush_rewrite_rules();
});
