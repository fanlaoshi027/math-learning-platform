<?php
/**
 * Math Course Theme - Front Page.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<main class="mc-home">
	<section class="mc-hero">
		<div class="mc-container">
			<div class="mc-hero__copy">
				<p class="mc-eyebrow">樊老师数学 · 系统化学习</p>
				<h1>初中数学专题课程<br>与教辅配套视频</h1>
				<p>系统学习 · 配套提升 · 高效提分</p>
				<div class="mc-hero__actions">
					<a class="mc-hero__button" href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">专题课程</a>
					<a class="mc-hero__button mc-hero__button--secondary" href="<?php echo esc_url( add_query_arg( 'course_type', 'supplementary', home_url( '/course-center/' ) ) ); ?>">教辅配套</a>
				</div>
			</div>
			<div class="mc-hero-art" aria-hidden="true">
				<div class="mc-hero-art__play">▶</div>
			</div>
		</div>
	</section>

	<section class="mc-home-courses">
		<div class="mc-container">
			<div class="mc-home-section-title">
				<div>
					<h2>热门课程</h2>
				</div>
				<span>选择课程，开始系统学习</span>
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
</main>

<?php get_footer(); ?>
