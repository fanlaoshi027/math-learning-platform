</main>
<!-- 页脚版权与备案信息：与全站浅灰背景保持连续，不再形成突兀白色断层 -->
<footer class="bg-[#f8fafc] text-slate-500 text-xs border-t border-slate-200/80 py-8 mt-auto" role="contentinfo">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-slate-600 font-medium">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-blue-600 transition-colors">平台首页</a>
      <span class="text-slate-300">|</span>
      <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="hover:text-blue-600 transition-colors">课程中心</a>
      <span class="text-slate-300">|</span>
      <a href="<?php echo esc_url(home_url('/privacy/')); ?>" class="hover:text-blue-600 transition-colors">隐私政策</a>
      <span class="text-slate-300">|</span>
      <a href="<?php echo esc_url(home_url('/terms/')); ?>" class="hover:text-blue-600 transition-colors">用户协议</a>
    </div>
    <div class="text-slate-400 text-center md:text-right">
      <span>© <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All Rights Reserved.</span>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
