<?php
/** Theme footer. */
defined( 'ABSPATH' ) || exit;
?>
<footer class="mc-site-footer">
    <div class="mc-container mc-site-footer__inner">
        <div class="mc-footer-links">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">关于我们</a>
            <a href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">课程中心</a>
            <a href="<?php echo esc_url( home_url( '/learning-center/' ) ); ?>">学习中心</a>
            <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">隐私政策</a>
        </div>
        <span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> 樊老师数学课堂 · 专注初中数学系统学习</span>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
