<?php
defined( 'ABSPATH' ) || exit;

get_header();

$course_center_url = home_url( '/course-center/' );
$learning_url      = home_url( '/learning-center/' );
?>

<main class="mc-home mc-home-v2">
	<section class="mc-home-v2__hero">
		<div class="mc-container mc-home-v2__hero-inner">
			<div class="mc-home-v2__copy">
				<div class="mc-home-v2__eyebrow"><span></span>樊老师数学 · 初中数学在线学习</div>
				<h1>把初中数学，<br><strong>学成一套体系</strong></h1>
				<p>按数学知识体系组织课程，从基础到综合应用，循序渐进，帮助你把知识真正学明白。</p>
				<div class="mc-home-v2__actions">
					<a class="mc-btn mc-btn--primary" href="<?php echo esc_url( $course_center_url ); ?>">浏览课程中心 <span>→</span></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a class="mc-btn mc-btn--ghost" href="<?php echo esc_url( $learning_url ); ?>">继续我的学习</a>
					<?php endif; ?>
				</div>
				<div class="mc-home-v2__trust">
					<span><b>✓</b> 体系完整</span>
					<span><b>✓</b> 内容精讲</span>
					<span><b>✓</b> 方法实用</span>
					<span><b>✓</b> 持续更新</span>
				</div>
			</div>

			<div class="mc-home-v2__systems" aria-label="数学知识体系">
				<a class="mc-system-card mc-system-card--blue" href="<?php echo esc_url( home_url( '/course-center/?type=topic&system=algebra' ) ); ?>">
					<span class="mc-system-card__icon">x²</span>
					<strong>代数体系</strong>
					<small>方程 · 不等式</small>
				</a>
				<a class="mc-system-card mc-system-card--green" href="<?php echo esc_url( home_url( '/course-center/?type=topic&system=geometry' ) ); ?>">
					<span class="mc-system-card__icon">△</span>
					<strong>几何体系</strong>
					<small>图形 · 证明</small>
				</a>
				<a class="mc-system-card mc-system-card--purple" href="<?php echo esc_url( home_url( '/course-center/?type=topic&system=function' ) ); ?>">
					<span class="mc-system-card__icon">ƒ</span>
					<strong>函数体系</strong>
					<small>一次 · 二次</small>
				</a>
				<a class="mc-system-card mc-system-card--orange" href="<?php echo esc_url( home_url( '/course-center/?type=supplementary' ) ); ?>">
					<span class="mc-system-card__icon">▤</span>
					<strong>教辅配套</strong>
					<small>大培优 · 中考在线</small>
				</a>
			</div>
		</div>
	</section>

	<section class="mc-home-v2__courses">
		<div class="mc-container">
			<div class="mc-home-v2__section-head">
				<div>
					<h2>精选课程</h2>
					<p>从知识体系到教辅配套，选择课程，直接开始学习。</p>
				</div>
				<a href="<?php echo esc_url( $course_center_url ); ?>">进入完整课程中心 <span>→</span></a>
			</div>
			<div class="mc-home-v2__course-list">
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
		</div>
	</section>

	<section class="mc-home-v2__note">
		<div class="mc-container">
			<div class="mc-home-v2__note-inner">
				<div>
					<h2>专心学数学，其他的交给网站。</h2>
					<p>清晰的课程目录、视频学习和学习进度，让每一次学习都有记录。</p>
				</div>
				<a class="mc-btn mc-btn--dark" href="<?php echo esc_url( $course_center_url ); ?>">开始学习 <span>→</span></a>
			</div>
		</div>
	</section>
</main>

<?php get_footer(); ?>