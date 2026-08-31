<?php
/**
 * Template Name: 课程中心 (Course Center)
 * 遵循《前端页面开发规范 V1.0》第 5 条：展示全部公开课程，支持筛选，点击直达 /learning/?course_id={ID}，杜绝独立详情页。
 * 
 * @package MathCourse_Theme
 */

get_header();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
  <!-- 页面标题 -->
  <div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">课程中心</h1>
    <p class="text-slate-600 text-xs sm:text-sm mt-1">
      涵盖初中数学全部知识体系与《大培优》经典教辅精讲，点击卡片直接进入学习页面。
    </p>
  </div>

  <!-- 课程中心 Shortcode 容器 -->
  <?php echo do_shortcode('[mathcourse_course_center]'); ?>
</div>

<?php get_footer(); ?>
