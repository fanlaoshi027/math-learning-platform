<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <?php wp_head(); ?>
</head>
<body <?php body_class('bg-[#f8fafc] text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between'); ?>>
<?php wp_body_open(); ?>

<?php
$is_logged_in = is_user_logged_in();
$current_user = $is_logged_in ? wp_get_current_user() : null;
?>

<!-- 顶部导航栏 (遵行规范 7. Header: 游客不显示学习中心，登录学员显示) -->
<header class="bg-white/95 text-slate-800 sticky top-0 z-40 shadow-xs border-b border-slate-200/90 backdrop-blur-md" role="banner">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
    
    <!-- 品牌 Logo -->
    <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center gap-2.5 select-none shrink-0" title="<?php bloginfo('name'); ?>">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center shadow-md shadow-blue-600/20 border border-blue-400/30 ring-2 ring-blue-100">
        <svg class="w-5 h-5 text-white stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
        </svg>
      </div>
      <div>
        <div class="text-base sm:text-lg font-extrabold tracking-tight text-slate-900 leading-tight font-sans">
          <?php bloginfo('name'); ?>
        </div>
        <div class="text-[10px] text-blue-600 tracking-wider font-semibold hidden sm:block">
          <?php bloginfo('description'); ?>
        </div>
      </div>
    </a>

    <!-- 主导航菜单 -->
    <nav class="hidden md:flex items-center gap-1.5 lg:gap-2" role="navigation" aria-label="主导航">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="px-3.5 py-1.5 rounded-xl text-xs sm:text-sm font-bold text-blue-600 bg-blue-50/90 border border-blue-200/70 shadow-2xs">
        首页
      </a>
      <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="px-3.5 py-1.5 rounded-xl text-xs sm:text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all">
        课程中心
      </a>
      <?php if ($is_logged_in) : ?>
        <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="px-3.5 py-1.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-gradient-to-r from-amber-500 to-orange-500 shadow-md shadow-orange-500/25 border border-amber-400/40 transition-all flex items-center gap-1.5">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5"/></svg>
          <span>学习中心</span>
        </a>
      <?php endif; ?>
    </nav>

    <!-- 右侧：搜索与登录态 -->
    <div class="flex items-center gap-3">
      <form role="search" method="get" action="<?php echo esc_url(home_url('/courses/')); ?>" class="relative hidden sm:block">
        <input type="text" name="s" placeholder="搜索课程或专题" value="<?php echo get_search_query(); ?>" class="w-36 lg:w-48 bg-slate-100/90 hover:bg-slate-100 focus:bg-white border border-slate-200 rounded-full py-1.5 pl-3.5 pr-8 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500 transition-all" />
        <button type="submit" aria-label="搜索" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-600">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </button>
      </form>

      <?php if ($is_logged_in) : ?>
        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl p-1 pr-2.5 shadow-2xs">
          <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="flex items-center gap-1.5 text-xs text-slate-800 font-bold hover:text-blue-600">
            <div class="w-6 h-6 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center font-bold text-xs text-white shadow-2xs">
              <?php echo esc_html(mb_substr($current_user->display_name, 0, 1)); ?>
            </div>
            <span class="max-w-[70px] truncate"><?php echo esc_html($current_user->display_name); ?></span>
          </a>
          <span class="text-slate-300">|</span>
          <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="text-[11px] text-slate-500 hover:text-rose-600">退出</a>
        </div>
      <?php else : ?>
        <a href="<?php echo esc_url(wp_login_url()); ?>" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 rounded-xl text-xs font-bold text-white shadow-md shadow-blue-600/25 border border-blue-400/30 transition-all shrink-0">
          学员登录
        </a>
      <?php endif; ?>

      <!-- 移动端汉堡菜单按钮 -->
      <button id="mc-mobile-menu-btn" class="md:hidden p-1.5 text-slate-600 hover:text-slate-900 focus:outline-none" aria-label="切换菜单">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
      </button>
    </div>

  </div>

  <!-- 移动端下拉抽屉导航 -->
  <div id="mc-mobile-menu" class="hidden md:hidden border-t border-slate-200 bg-slate-50 px-4 py-3 space-y-2 text-xs">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="block py-1.5 text-blue-600 font-bold">首页</a>
    <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="block py-1.5 text-slate-700 hover:text-blue-600 font-medium">课程中心</a>
    <?php if ($is_logged_in) : ?>
      <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" class="block py-1.5 text-amber-700 font-bold">学习中心 (已激活课程)</a>
    <?php endif; ?>
  </div>
</header>
<main class="flex-grow">
