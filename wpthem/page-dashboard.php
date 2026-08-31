<?php
/**
 * Template Name: 学习中心 (Student Learning Center)
 * 遵循《Math Learning Platform 接口规范 V1.0》第 12 条 &《前端页面开发规范 V1.0》第 6 条
 * 仅面向已登录学员，只展示 access === true 的已授权课程与真实进度。
 * 
 * @package MathCourse_Theme
 */

// 未登录拦截重定向至登录页
if (!is_user_logged_in()) {
    auth_redirect();
    exit;
}

get_header();
$current_user = wp_get_current_user();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
  
  <!-- 学员概况欢迎 Banner -->
  <div class="bg-gradient-to-r from-[#0f1f3d] to-[#1e3a8a] text-white rounded-2xl p-6 sm:p-8 shadow-lg mb-8 border border-blue-900/40">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-full bg-blue-600 border-2 border-blue-300/40 flex items-center justify-center font-bold text-xl text-white shadow-md">
          <?php echo esc_html(mb_substr($current_user->display_name, 0, 1)); ?>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl sm:text-2xl font-extrabold text-white">
              <?php echo esc_html($current_user->display_name); ?> 的学习中心
            </h1>
            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
              已激活学员
            </span>
          </div>
          <p class="text-xs sm:text-sm text-blue-200 mt-1">
            专注初中数学知识体系构建，坚持每天一讲，稳步冲刺中考满分！
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- 输出学习中心已授权课程列表 (Shortcode) -->
  <div class="space-y-6">
    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
      <h2 class="text-lg sm:text-xl font-bold text-slate-900 flex items-center gap-2">
        <span>我的已开通课程与进度</span>
      </h2>
      <span class="text-xs text-slate-500">仅展示 access === true 的已授权课程</span>
    </div>

    <?php echo do_shortcode('[mathcourse_learning_center]'); ?>
  </div>

</div>

<?php get_footer(); ?>
