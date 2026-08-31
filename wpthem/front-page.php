<?php
/**
 * 樊老师数学课堂 - 首页模板 (front-page.php)
 * 遵循《前端页面开发规范 V1.0》与《前端 UI 设计规范 V1.0》
 * 
 * @package MathCourse_Theme
 */

get_header();
?>

<!-- 1. Hero 紧凑 Banner (四大体系方框一字排开 - 明亮通透教育主题设计) -->
<section class="bg-gradient-to-b from-blue-50/90 via-sky-50/40 to-white text-slate-900 py-6 sm:py-8 lg:py-9 relative overflow-hidden border-b border-slate-200/80 shadow-xs">
  <div class="absolute inset-0 bg-[radial-gradient(#3b82f615_1px,transparent_1px)] [background-size:20px_20px] pointer-events-none opacity-60"></div>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
      
      <!-- Left Content Area -->
      <div class="lg:col-span-6 space-y-3.5">
        <div>
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-100/90 border border-blue-200 text-blue-700 text-xs font-bold mb-2.5 shadow-2xs">
            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
            <span>两大核心分类：初中系统课 ＋ 教辅配套课</span>
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-[34px] font-extrabold tracking-tight text-slate-900 leading-tight font-sans">
            把初中数学，<span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700">学成一套体系</span>
          </h1>
          <p class="text-slate-600 text-xs sm:text-sm leading-relaxed mt-2 max-w-xl font-normal">
            按数学知识体系组织课程，从基础到综合应用，循序渐进，构建扎实的数学基本功。
          </p>
        </div>

        <!-- Action Buttons (遵循规范 8. 按钮) -->
        <div class="flex flex-wrap items-center gap-3 pt-0.5">
          <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-bold rounded-xl shadow-md shadow-blue-600/20 flex items-center gap-2 transition-all">
            <span>浏览课程中心</span> &rarr;
          </a>
          <a href="<?php echo esc_url(home_url('/learning/?course_id=25')); ?>" class="px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs sm:text-sm font-semibold rounded-xl transition-all flex items-center gap-2 shadow-2xs">
            <span>开始学习之旅</span>
          </a>
        </div>

        <!-- Feature Badges -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-2.5 border-t border-slate-200/80 text-xs text-slate-600 font-medium">
          <div class="flex items-center gap-1.5 bg-white/90 px-3 py-1 rounded-full border border-slate-200/90 shadow-2xs"><span class="text-emerald-600 font-bold">✓</span> 体系完整</div>
          <div class="flex items-center gap-1.5 bg-white/90 px-3 py-1 rounded-full border border-slate-200/90 shadow-2xs"><span class="text-emerald-600 font-bold">✓</span> 内容精讲</div>
          <div class="flex items-center gap-1.5 bg-white/90 px-3 py-1 rounded-full border border-slate-200/90 shadow-2xs"><span class="text-emerald-600 font-bold">✓</span> 方法实用</div>
          <div class="flex items-center gap-1.5 bg-white/90 px-3 py-1 rounded-full border border-slate-200/90 shadow-2xs"><span class="text-emerald-600 font-bold">✓</span> 持续更新</div>
        </div>
      </div>

      <!-- Right: 四大知识体系方框一字排开 (代数、几何、函数、教辅配套) -->
      <div class="lg:col-span-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3">
          
          <a href="<?php echo esc_url(home_url('/courses/?type=topic&system=algebra')); ?>" class="bg-white/95 hover:bg-white border border-blue-200/80 hover:border-blue-400/80 rounded-2xl p-3 sm:p-3.5 flex flex-col items-center justify-center text-center shadow-xs hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-500 to-indigo-600 text-white font-bold font-mono text-base flex items-center justify-center mb-1.5 shadow-md shadow-blue-500/20 group-hover:scale-105 transition-transform">
              x²
            </div>
            <span class="text-slate-900 font-bold text-xs sm:text-sm group-hover:text-blue-600 transition-colors">代数体系</span>
            <span class="text-[10px] text-slate-500 font-medium mt-0.5">方程 · 不等式</span>
          </a>

          <a href="<?php echo esc_url(home_url('/courses/?type=topic&system=geometry')); ?>" class="bg-white/95 hover:bg-white border border-emerald-200/80 hover:border-emerald-400/80 rounded-2xl p-3 sm:p-3.5 flex flex-col items-center justify-center text-center shadow-xs hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center mb-1.5 shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5 stroke-white stroke-2 fill-none" viewBox="0 0 24 24"><path d="M3 20h18L12 4z"/></svg>
            </div>
            <span class="text-slate-900 font-bold text-xs sm:text-sm group-hover:text-emerald-600 transition-colors">几何体系</span>
            <span class="text-[10px] text-slate-500 font-medium mt-0.5">图形 · 证明</span>
          </a>

          <a href="<?php echo esc_url(home_url('/courses/?type=topic&system=function')); ?>" class="bg-white/95 hover:bg-white border border-purple-200/80 hover:border-purple-400/80 rounded-2xl p-3 sm:p-3.5 flex flex-col items-center justify-center text-center shadow-xs hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-500 to-indigo-600 text-white flex items-center justify-center mb-1.5 shadow-md shadow-purple-500/20 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5 stroke-white stroke-2 fill-none" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M19 9l-5 5-4-4-3 3"/></svg>
            </div>
            <span class="text-slate-900 font-bold text-xs sm:text-sm group-hover:text-purple-600 transition-colors">函数体系</span>
            <span class="text-[10px] text-slate-500 font-medium mt-0.5">一次 · 二次</span>
          </a>

          <a href="<?php echo esc_url(home_url('/courses/?type=supplementary')); ?>" class="bg-gradient-to-b from-amber-50/90 to-orange-50/90 hover:from-amber-100 hover:to-orange-100 border border-amber-300/80 rounded-2xl p-3 sm:p-3.5 flex flex-col items-center justify-center text-center shadow-xs hover:shadow-md transition-all group">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center mb-1.5 shadow-md shadow-orange-500/20 group-hover:scale-105 transition-transform">
              <svg class="w-5 h-5 stroke-white stroke-2 fill-none" viewBox="0 0 24 24"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10"/><path d="M6 10h10"/></svg>
            </div>
            <span class="text-slate-900 font-bold text-xs sm:text-sm group-hover:text-amber-700 transition-colors">教辅配套</span>
            <span class="text-[10px] text-amber-800 font-bold mt-0.5">大培优 · 中考在线</span>
          </a>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- 2. 首页核心课程大类展示区 (初中系统课 & 教辅配套课) -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-3 border-b border-slate-200">
    <div>
      <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">精选课程推荐</h2>
      <p class="text-slate-500 text-xs sm:text-sm mt-1">按「初中系统课」构建知识网络，或按「教辅配套课」进行专项培优提分</p>
    </div>
    <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="text-xs sm:text-sm font-semibold text-blue-600 hover:text-blue-700 self-start sm:self-auto">
      进入完整课程中心 &rarr;
    </a>
  </div>

  <!-- 输出公开课程列表 -->
  <?php echo do_shortcode('[mathcourse_course_center]'); ?>
</section>

<?php get_footer(); ?>
