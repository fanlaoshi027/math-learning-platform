<?php
/**
 * 樊老师数学课堂 - 分类/标签/归档列表模板 (archive.php)
 * 
 * @package MathCourse_Theme
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

get_header();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
  
  <div class="mb-8 border-b border-slate-200 pb-5">
    <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-bold mb-2">
      <span>分类归档</span>
    </div>
    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
      <?php the_archive_title(); ?>
    </h1>
    <?php if (get_the_archive_description()) : ?>
      <div class="text-sm text-slate-500 mt-2 max-w-2xl">
        <?php the_archive_description(); ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (have_posts()) : ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all flex flex-col overflow-hidden group'); ?>>
          
          <?php if (has_post_thumbnail()) : ?>
            <a href="<?php the_permalink(); ?>" class="aspect-video overflow-hidden bg-slate-100 block">
              <?php the_post_thumbnail('medium_large', array('class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-300')); ?>
            </a>
          <?php else : ?>
            <div class="aspect-video bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center border-b border-slate-100">
              <div class="w-12 h-12 rounded-xl bg-blue-600/10 flex items-center justify-center text-blue-600 font-mono font-bold text-lg">
                ∑
              </div>
            </div>
          <?php endif; ?>

          <div class="p-5 flex-1 flex flex-col justify-between">
            <div>
              <div class="flex items-center gap-2 text-xs text-slate-400 mb-2">
                <span><?php echo get_the_date('Y-m-d'); ?></span>
                <span>•</span>
                <span><?php the_category(', '); ?></span>
              </div>

              <h2 class="text-base sm:text-lg font-bold text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-2 leading-snug">
                <a href="<?php the_permalink(); ?>">
                  <?php the_title(); ?>
                </a>
              </h2>

              <p class="text-xs sm:text-sm text-slate-500 mt-2 line-clamp-3 leading-relaxed">
                <?php echo wp_trim_words(get_the_excerpt(), 45, '...'); ?>
              </p>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between">
              <a href="<?php the_permalink(); ?>" class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1">
                <span>阅读全文</span> &rarr;
              </a>
            </div>
          </div>

        </article>
      <?php endwhile; ?>
    </div>

    <div class="mt-10 flex justify-center">
      <?php
      the_posts_pagination(array(
          'mid_size'  => 2,
          'prev_text' => __('&larr; 上一页', 'mathcourse'),
          'next_text' => __('下一页 &rarr;', 'mathcourse'),
          'class'     => 'flex items-center gap-2 text-sm font-semibold'
      ));
      ?>
    </div>

  <?php else : ?>
    <div class="bg-white rounded-2xl border border-slate-200/90 p-12 text-center max-w-lg mx-auto shadow-xs">
      <h3 class="text-base font-bold text-slate-800 mb-1">暂无文章</h3>
      <p class="text-xs text-slate-500 mb-6">该分类下暂无内容。</p>
      <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
        <span>前往课程中心</span> &rarr;
      </a>
    </div>
  <?php endif; ?>

</div>

<?php
get_footer();
