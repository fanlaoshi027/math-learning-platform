<?php
/**
 * Template Name: 课程中心 (Course Center)
 * Reference UI migrated from wpthem; course data remains plugin-owned.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<main class="mc-course-page min-h-screen bg-slate-50 py-6 sm:py-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <section class="mc-course-hero relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#0b1329] via-[#132b55] to-[#0f3d6e] px-5 py-8 sm:px-10 sm:py-11 mb-7 shadow-xl">
      <div class="relative z-10 max-w-3xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/20 text-blue-100 text-xs mb-4"><span class="w-2 h-2 rounded-full bg-orange-400"></span>初中数学系统课程</div>
        <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">樊老师数学课程中心</h1>
        <p class="mt-3 text-sm sm:text-base text-blue-100 leading-relaxed max-w-2xl">专题突破与教辅配套，按年级选择课程，进入课程后即可查看对应学习内容。</p>
      </div>
      <div class="absolute -right-16 -top-20 w-72 h-72 bg-blue-400/10 rounded-full blur-3xl" aria-hidden="true"></div><div class="absolute right-12 -bottom-20 w-48 h-48 bg-orange-400/10 rounded-full blur-3xl" aria-hidden="true"></div>
    </section>
    <section aria-label="课程列表">
      <?php
      if ( shortcode_exists( 'mathcourse_course_center' ) ) { echo do_shortcode( '[mathcourse_course_center]' ); }
      elseif ( shortcode_exists( 'mathcourse_course_directory' ) ) { echo do_shortcode( '[mathcourse_course_directory]' ); }
      else { echo '<div class="bg-white rounded-3xl border border-slate-200 p-8 text-center text-sm text-slate-500">课程中心正在加载。</div>'; }
      ?>
    </section>
  </div>
</main>
<?php get_footer(); ?>
