<?php
defined( 'ABSPATH' ) || exit;
get_header();
$course_center_url = home_url( '/course-center/' );
$learning_url = home_url( '/learning-center/' );
?>
<main class="mc-home">
<section class="mc-home-hero"><div class="mc-container mc-home-hero__inner"><div class="mc-home-hero__copy"><div class="mc-kicker"><span></span>樊老师数学 · 初中数学在线学习</div><h1>把数学学明白，<br><strong>一步一步来。</strong></h1><p>专题课帮助你突破重点难点，教辅配套课陪你把每一道题真正弄懂。</p><div class="mc-home-hero__actions"><a class="mc-btn mc-btn--primary" href="<?php echo esc_url( $course_center_url ); ?>">浏览全部课程 <span>→</span></a><?php if ( is_user_logged_in() ) : ?><a class="mc-btn mc-btn--ghost" href="<?php echo esc_url( $learning_url ); ?>">继续我的学习</a><?php endif; ?></div></div><div class="mc-home-hero__visual" aria-hidden="true"><div class="mc-math-card mc-math-card--main"><span class="mc-math-card__formula">x² + bx + c = 0</span><span class="mc-math-card__line"></span><span class="mc-math-card__small">理解 · 方法 · 练习</span></div><div class="mc-math-card mc-math-card--float">△ ABC<br><b>∠A = ∠B</b></div><div class="mc-math-orbit mc-math-orbit--one"></div><div class="mc-math-orbit mc-math-orbit--two"></div></div></div></section>
<section class="mc-home-courses"><div class="mc-container"><div class="mc-section-heading mc-section-heading--courses"><div><h2>课程库</h2><p>选择课程，直接开始学习。</p></div><a href="<?php echo esc_url( $course_center_url ); ?>">查看全部 <span>→</span></a></div><?php if ( shortcode_exists( 'mathcourse_course_directory' ) ) { echo do_shortcode( '[mathcourse_course_directory]' ); } elseif ( shortcode_exists( 'mathcourse_course_center' ) ) { echo do_shortcode( '[mathcourse_course_center]' ); } else { echo '<p class="mathcourse-directory__empty">课程中心正在加载。</p>'; } ?></div></section>
<section class="mc-home-note"><div class="mc-container"><div class="mc-home-note__inner"><div><h2>专心学数学，其他的交给网站。</h2><p>清晰的课程目录、视频学习和学习进度，让每一次学习都有记录。</p></div><a class="mc-btn mc-btn--dark" href="<?php echo esc_url( $course_center_url ); ?>">开始学习 <span>→</span></a></div></div></section>
</main>
<?php get_footer(); ?>