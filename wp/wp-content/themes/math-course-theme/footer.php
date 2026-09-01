<?php
/**
 * Theme footer.
 *
 * @package MathCourseTheme
 */

defined( 'ABSPATH' ) || exit;

$is_learning_page = is_page( 'learning' );
?>
<footer class="mc-site-footer<?php echo $is_learning_page ? ' mc-site-footer--learning' : ''; ?>">
	<div class="mc-container mc-site-footer__inner">
		<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> 樊老师数学</span>
		<span>专注初中数学系统学习</span>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
