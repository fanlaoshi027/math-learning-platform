<?php
/**
 * Theme footer.
 *
 * @package MathCourseTheme
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="mc-site-footer" style="background:var(--mc-bg);border-top:0;">
	<div class="mc-container mc-site-footer__inner">
		<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> 樊老师数学</span>
		<span>专注初中数学系统学习</span>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
