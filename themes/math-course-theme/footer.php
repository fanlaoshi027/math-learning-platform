<?php
defined( 'ABSPATH' ) || exit;
$site_name = class_exists('MathCourse\Frontend\Site_Settings') ? MathCourse\Frontend\Site_Settings::get('site_name') : '樊老师数学';
$footer_slogan = class_exists('MathCourse\Frontend\Site_Settings') ? MathCourse\Frontend\Site_Settings::get('footer_slogan') : '专注初中数学系统学习';
?>
<footer class="mc-site-footer">
    <div class="mc-container mc-site-footer__top">
        <a class="mc-footer-brand" href="<?php echo esc_url(home_url('/')); ?>"><span>↗</span><strong><?php echo esc_html($site_name); ?></strong><small>好方法 · 学得更轻松</small></a>
        <nav class="mc-footer-nav" aria-label="页脚导航">
            <a href="<?php echo esc_url(home_url('/')); ?>">首页</a>
            <a href="<?php echo esc_url(home_url('/course-center/')); ?>">课程中心</a>
            <a href="<?php echo esc_url(home_url('/learning-center/')); ?>">学习中心</a>
            <a href="<?php echo esc_url(home_url('/')); ?>#about">关于我们</a>
        </nav>
        <div class="mc-footer-slogan">用数学，点亮更大的可能！</div>
    </div>
    <div class="mc-container mc-site-footer__bottom">© <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html($site_name); ?> · <?php echo esc_html($footer_slogan); ?></div>
</footer>
<?php wp_footer(); ?></body></html>