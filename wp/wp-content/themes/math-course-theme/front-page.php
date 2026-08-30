<?php
/**
 * Math Course Theme - Front Page.
 *
 * @package MathCourseTheme
 */
defined( 'ABSPATH' ) || exit;
get_header();

$course_center_url = home_url( '/course-center/' );
$topic_url         = add_query_arg( 'course_type', 'topic', $course_center_url );
$supplementary_url = add_query_arg( 'course_type', 'supplementary', $course_center_url );
$learning_url      = home_url( '/learning-center/' );
?>

<main class="mc-home">
	<section class="mc-home-hero">
		<div class="mc-container mc-home-hero__inner">
			<div class="mc-home-hero__copy">
				<div class="mc-kicker"><span></span>樊老师数学 · 初中数学在线学习</div>
				<h1>把数学学明白，<br><strong>一步一步来。</strong></h1>
				<p>专题课帮助你突破重点难点，教辅配套课陪你把每一道题真正弄懂。</p>
				<div class="mc-home-hero__actions">
					<a class="mc-btn mc-btn--primary" href="<?php echo esc_url( $course_center_url ); ?>">浏览全部课程 <span>→</span></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a class="mc-btn mc-btn--ghost" href="<?php echo esc_url( $learning_url ); ?>">继续我的学习</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="mc-home-hero__visual" aria-hidden="true">
				<div class="mc-math-card mc-math-card--main">
					<span class="mc-math-card__label">MATH</span>
					<span class="mc-math-card__formula">x² + bx + c = 0</span>
					<span class="mc-math-card__line"></span>
					<span class="mc-math-card__small">理解 · 方法 · 练习</span>
				</div>
				<div class="mc-math-card mc-math-card--float">△ ABC<br><b>∠A = ∠B</b></div>
				<div class="mc-math-orbit mc-math-orbit--one"></div>
				<div class="mc-math-orbit mc-math-orbit--two"></div>
			</div>
		</div>
	</section>

	<section class="mc-home-types">
		<div class="mc-container">
			<div class="mc-section-heading">
				<div><span class="mc-section-heading__eyebrow">COURSES</span><h2>选择适合你的学习方式</h2></div>
				<p>两类课程，目标清晰，不做多余功能。</p>
			</div>

			<div class="mc-type-grid">
				<a class="mc-type-card mc-type-card--topic" href="<?php echo esc_url( $topic_url ); ?>">
					<div class="mc-type-card__top"><span class="mc-type-card__icon">01</span><span class="mc-type-card__arrow">↗</span></div>
					<div><span class="mc-type-card__tag">专题课</span><h3>针对一个专题，<br>把知识真正学透</h3><p>几何、方程、函数等重点专题，适合系统突破薄弱环节。</p></div>
				</a>
				<a class="mc-type-card mc-type-card--book" href="<?php echo esc_url( $supplementary_url ); ?>">
					<div class="mc-type-card__top"><span class="mc-type-card__icon">02</span><span class="mc-type-card__arrow">↗</span></div>
					<div><span class="mc-type-card__tag">教辅配套</span><h3>跟着教辅逐题学，<br>不会的题有人讲</h3><p>配套教辅视频讲解，按章节和题目循序学习。</p></div>
				</a>
			</div>
		</div>
	</section>

	<section class="mc-home-courses">
		<div class="mc-container">
			<div class="mc-section-heading mc-section-heading--courses">
				<div><span class="mc-section-heading__eyebrow">COURSE LIBRARY</span><h2>课程库</h2></div>
				<a href="<?php echo esc_url( $course_center_url ); ?>">查看全部 <span>→</span></a>
			</div>
			<?php
			if ( shortcode_exists( 'mathcourse_course_directory' ) ) {
				echo do_shortcode( '[mathcourse_course_directory]' );
			} elseif ( shortcode_exists( 'mathcourse_course_center' ) ) {
				echo do_shortcode( '[mathcourse_course_center]' );
		} else {
				echo '<p class="mathcourse-directory__empty">课程中心正在加载。</p>';
			}
			?>
		</div>
	</section>

	<section class="mc-home-note">
		<div class="mc-container">
			<div class="mc-home-note__inner">
				<div><span class="mc-section-heading__eyebrow">LEARNING</span><h2>专心学数学，其他的交给网站。</h2><p>清晰的课程目录、视频学习和学习进度，让每一次学习都有记录。</p></div>
				<a class="mc-btn mc-btn--dark" href="<?php echo esc_url( $course_center_url ); ?>">开始学习 <span>→</span></a>
			</div>
		</div>
	</section>
</main>

<?php get_footer(); ?>