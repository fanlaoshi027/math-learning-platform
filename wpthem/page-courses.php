<?php
/**
 * Template Name: 课程中心 (Course Center)
 * UI Migration V2 - Mobile first course showcase
 *
 * @package MathCourse_Theme
 */

get_header();
?>

<main class="min-h-screen bg-slate-50 py-6 sm:py-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <!-- Hero -->
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0b1329] via-[#132b55] to-[#0f3d6e] px-5 py-8 sm:px-10 sm:py-12 mb-8 shadow-xl">
      <div class="relative z-10 max-w-3xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/20 text-blue-100 text-xs mb-4">
          <span class="w-2 h-2 rounded-full bg-orange-400"></span>
          初中数学系统课程
        </div>
        <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
          樊老师数学课程中心
        </h1>
        <p class="mt-3 text-sm sm:text-base text-blue-100 leading-relaxed">
          从基础概念到中考专题，配套视频精讲、章节学习与学习进度管理。
        </p>
      </div>
      <div class="absolute right-0 top-0 w-64 h-64 bg-blue-400/10 rounded-full blur-3xl"></div>
    </section>

    <!-- Course cards are rendered by core plugin -->
    <section class="bg-white rounded-3xl border border-slate-200 shadow-sm p-4 sm:p-6">
      <?php echo do_shortcode('[mathcourse_course_center]'); ?>
    </section>

  </div>
</main>

<?php get_footer(); ?>
