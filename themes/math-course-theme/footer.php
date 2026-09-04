<?php
defined( 'ABSPATH' ) || exit;
$site_name=class_exists('MathCourse\\Frontend\\Site_Settings')?MathCourse\Frontend\Site_Settings::get('site_name'):'樊老师数学';
$footer_slogan=class_exists('MathCourse\\Frontend\\Site_Settings')?MathCourse\Frontend\Site_Settings::get('footer_slogan'):'专注初中数学系统学习';
?>
<footer class="mc-site-footer"><div class="mc-container mc-site-footer__inner"><span>© <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html($site_name); ?></span><span><?php echo esc_html($footer_slogan); ?></span></div></footer>
<?php wp_footer(); ?></body></html>
