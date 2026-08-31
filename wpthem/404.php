<?php
/**
 * 樊老师数学课堂 - 404 页面未找到模板 (404.php)
 * 
 * @package MathCourse_Theme
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

get_header();
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">
  <div class="w-20 h-20 rounded-3xl bg-blue-50 text-blue-600 border border-blue-200/80 flex items-center justify-center mx-auto mb-6 text-3xl font-mono font-bold shadow-xs">
    404
  </div>
  
  <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
    抱歉，未找到您访问的页面
  </h1>
  
  <p class="text-sm text-slate-500 max-w-md mx-auto mb-8 leading-relaxed">
    您访问的课程、文章或链接可能已移动或不存在。您可以返回平台首页或在课程中心查找最新课程。
  </p>

  <div class="flex flex-wrap items-center justify-center gap-3">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
      <span>返回首页</span>
    </a>
    <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-xl shadow-2xs transition-colors">
      <span>浏览课程中心</span> &rarr;
    </a>
  </div>
</div>

<?php
get_footer();
