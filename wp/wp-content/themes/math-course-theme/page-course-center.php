<?php
/** Template Name: Course Center */
defined( 'ABSPATH' ) || exit;

get_header();
$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$center_url = get_permalink();
?>

<main class="mc-page mc-course-center">
	<div class="mc-container">
		<?php if ( $course_id && shortcode_exists( 'mathcourse_course_player' ) ) : ?>
			<div class="mc-course-center__back"><a href="<?php echo esc_url( $center_url ); ?>">← 返回课程中心</a></div>
			<?php echo do_shortcode( '[mathcourse_course_player course_id="' . esc_attr( $course_id ) . '"]' ); ?>
		<?php else : ?>
			<header class="mc-course-center__hero">
				<div>
					<span class="mc-course-center__eyebrow">COURSE LIBRARY</span>
					<h1>课程中心</h1>
					<p>选择适合你的课程，按自己的节奏一步一步学习。</p>
				</div>
				<div class="mc-course-center__mark" aria-hidden="true">∑</div>
			</header>

			<nav class="mc-course-center__types" aria-label="课程分类">
				<a class="is-topic" href="<?php echo esc_url( add_query_arg( 'course_type', 'topic', $center_url ) ); ?>"><span>01</span><strong>专题课</strong><small>重点专题 · 系统突破</small><b>→</b></a>
				<a class="is-book" href="<?php echo esc_url( add_query_arg( 'course_type', 'supplementary', $center_url ) ); ?>"><span>02</span><strong>教辅配套</strong><small>跟着教辅 · 逐题讲解</small><b>→</b></a>
			</nav>

			<section class="mc-course-center__library">
				<div class="mc-course-center__section-title"><div><span>全部课程</span><small>按类型和年级选择</small></div></div>
				<?php
				if ( shortcode_exists( 'mathcourse_course_directory' ) ) {
					echo do_shortcode( '[mathcourse_course_directory]' );
				} else {
					echo '<div class="mc-course-center__empty">课程中心插件未启用。</div>';
				}
				?>
			</section>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>