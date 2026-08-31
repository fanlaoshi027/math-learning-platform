<?php
/**
 * 樊老师数学课堂 - 通用单页面模板 (page.php)
 * 
 * 用于渲染关于我们、用户协议、隐私政策等标准 WordPress 单页面。
 * 
 * @package MathCourse_Theme
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

get_header();
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
  <?php while (have_posts()) : the_post(); ?>
    <article id="page-<?php the_ID(); ?>" <?php post_class('bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-10 shadow-xs'); ?>>
      
      <header class="border-b border-slate-200 pb-5 mb-6">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          <?php the_title(); ?>
        </h1>
        <div class="text-xs text-slate-400 mt-2">
          <span>最后更新: <?php echo get_the_modified_date('Y-m-d'); ?></span>
        </div>
      </header>

      <div class="prose prose-slate max-w-none text-sm sm:text-base leading-relaxed text-slate-700 space-y-4">
        <?php the_content(); ?>
      </div>

    </article>
  <?php endwhile; ?>
</div>

<?php
get_footer();
