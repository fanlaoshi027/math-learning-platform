<?php
/**
 * 樊老师数学课堂 - 单篇文章模板 (single.php)
 * 
 * 用于渲染单篇博客、资讯或数学解题分享文章。
 * 
 * @package MathCourse_Theme
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

get_header();
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
  <?php while (have_posts()) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-10 shadow-xs'); ?>>
      
      <header class="border-b border-slate-200 pb-6 mb-8">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-blue-600 mb-3">
          <span><?php the_category(', '); ?></span>
          <span class="text-slate-300">•</span>
          <span class="text-slate-400 font-normal"><?php echo get_the_date('Y年m月d日'); ?></span>
          <span class="text-slate-300">•</span>
          <span class="text-slate-400 font-normal">作者：<?php the_author(); ?></span>
        </div>

        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
          <?php the_title(); ?>
        </h1>
      </header>

      <?php if (has_post_thumbnail()) : ?>
        <div class="mb-8 rounded-2xl overflow-hidden shadow-xs border border-slate-100 aspect-video">
          <?php the_post_thumbnail('large', array('class' => 'w-full h-full object-cover')); ?>
        </div>
      <?php endif; ?>

      <div class="prose prose-slate max-w-none text-sm sm:text-base leading-relaxed text-slate-700 space-y-4">
        <?php the_content(); ?>
      </div>

      <footer class="mt-10 pt-6 border-t border-slate-200">
        <?php if (has_tag()) : ?>
          <div class="flex flex-wrap items-center gap-2 mb-6">
            <span class="text-xs text-slate-400">标签：</span>
            <?php the_tags('<span class="inline-block bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-lg">', '</span> <span class="inline-block bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-lg">', '</span>'); ?>
          </div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-semibold text-blue-600">
          <div class="w-full sm:w-1/2 text-left">
            <?php previous_post_link('<span class="text-slate-400 block mb-0.5">上一篇</span> %link'); ?>
          </div>
          <div class="w-full sm:w-1/2 text-right">
            <?php next_post_link('<span class="text-slate-400 block mb-0.5">下一篇</span> %link'); ?>
          </div>
        </div>
      </footer>

    </article>
  <?php endwhile; ?>
</div>

<?php
get_footer();
