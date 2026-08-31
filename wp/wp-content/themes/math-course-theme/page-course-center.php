<?php
/** Template Name: Course Center */
defined( 'ABSPATH' ) || exit;

get_header();
$course_id  = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$center_url = get_permalink();
$type       = isset( $_GET['course_type'] ) ? sanitize_key( wp_unslash( $_GET['course_type'] ) ) : '';
$grade      = isset( $_GET['course_grade'] ) ? sanitize_key( wp_unslash( $_GET['course_grade'] ) ) : '';
?>

<main class="mc-page mc-course-center">
	<div class="mc-container">
		<?php if ( $course_id && shortcode_exists( 'mathcourse_course_directory' ) ) : ?>
			<div class="mc-course-center__back"><a href="<?php echo esc_url( $center_url ); ?>">← 返回课程中心</a></div>
			<?php echo do_shortcode( '[mathcourse_course_directory course_id="' . esc_attr( $course_id ) . '"]' ); ?>
		<?php else : ?>
			<?php
			$base_url   = remove_query_arg( array( 'course_id', 'course_type', 'course_grade' ), $center_url );
			$all_url    = $base_url;
			$topic_url  = add_query_arg( 'course_type', 'topic', $base_url );
			$book_url   = add_query_arg( 'course_type', 'supplementary', $base_url );
			$g7_url     = add_query_arg( 'course_grade', '7', $base_url );
			$g8_url     = add_query_arg( 'course_grade', '8', $base_url );
			$g9_url     = add_query_arg( 'course_grade', '9', $base_url );
			?>
			<section class="mc-course-library">
				<aside class="mc-course-library__sidebar" aria-label="课程筛选">
					<div class="mc-course-library__side-title">课程中心<small>初中数学课程体系</small></div>
					<div class="mc-course-library__group">
						<strong>知识体系</strong>
						<a class="<?php echo '' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $all_url ); ?>"><span>▣</span>全部课程<b>›</b></a>
						<a class="<?php echo 'topic' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $topic_url ); ?>"><span>△</span>专题课程<b>›</b></a>
						<a class="<?php echo 'supplementary' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $book_url ); ?>"><span>▤</span>教辅配套<b>›</b></a>
					</div>
					<div class="mc-course-library__group">
						<strong>学习阶段</strong>
						<a class="<?php echo '7' === $grade ? 'is-active' : ''; ?>" href="<?php echo esc_url( $g7_url ); ?>"><span>○</span>七年级<b>›</b></a>
						<a class="<?php echo '8' === $grade ? 'is-active' : ''; ?>" href="<?php echo esc_url( $g8_url ); ?>"><span>○</span>八年级<b>›</b></a>
						<a class="<?php echo '9' === $grade ? 'is-active' : ''; ?>" href="<?php echo esc_url( $g9_url ); ?>"><span>○</span>九年级<b>›</b></a>
					</div>
				</aside>

				<section class="mc-course-library__main">
					<header class="mc-course-library__heading">
						<div>
							<h1><?php echo esc_html( 'topic' === $type ? '专题课程' : ( 'supplementary' === $type ? '教辅配套' : '全部课程' ) ); ?></h1>
							<p><?php echo $grade ? esc_html( $grade . '年级 · 精选课程' ) : '按知识体系与学习阶段选择适合你的课程'; ?></p>
						</div>
						<span class="mc-course-library__count">初中数学</span>
					</header>
					<?php
					if ( shortcode_exists( 'mathcourse_course_directory' ) ) {
						echo do_shortcode( '[mathcourse_course_directory]' );
					} else {
						echo '<div class="mc-course-center__empty">课程中心插件未启用。</div>';
					}
					?>
					<div class="mc-course-library__tip"><span>✓</span>课程内容持续更新，已授权课程可直接进入学习。</div>
				</section>
			</section>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
