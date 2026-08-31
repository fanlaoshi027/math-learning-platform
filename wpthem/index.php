<?php
/**
 * 樊老师数学课堂 - 默认首页/文章列表通用后备模板 (index.php)
 * 
 * WordPress 核心硬性要求：任何 WP 主题都必须包含 index.php 与 style.css。
 * 遵循《前端主题开发规则 V1.0》与《Math Learning Platform 接口规范 V1.0》。
 * 
 * @package MathCourse_Theme
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

get_header();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
  
  <!-- 页面标题 -->
  <div class="mb-8 border-b border-slate-200 pb-5">
    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
      <?php 
      if (is_home() && !is_front_page()) {
          single_post_title();
      } elseif (is_archive()) {
          the_archive_title();
      } elseif (is_search()) {
          printf(__('搜索结果: %s', 'mathcourse'), '<span>' . get_search_query() . '</span>');
      } else {
          _e('文章与资讯', 'mathcourse');
      }
      ?>
    </h1>
    <p class="text-sm text-slate-500 mt-1">
      <?php _e('初中数学学习方法、中考题型解构与考点解析', 'mathcourse'); ?>
    </p>
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
              <span class="text-[11px] text-slate-400 font-medium">
                <?php echo get_comments_number(); ?> 评论
              </span>
            </div>
          </div>

        </article>
      <?php endwhile; ?>
    </div>

    <!-- 分页导航 -->
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
      <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4 text-2xl">
        📝
      </div>
      <h3 class="text-base font-bold text-slate-800 mb-1">暂无文章内容</h3>
      <p class="text-xs text-slate-500 mb-6">当前分类或列表中暂无发布的资讯与文章。</p>
      <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors">
        <span>前往课程中心</span> &rarr;
      </a>
    </div>
  <?php endif; ?>

</div>

<?php
get_footer();
