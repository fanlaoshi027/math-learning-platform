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
			<header class="mc-course-center__hero">
				<div>
					<span class="mc-course-center__eyebrow">COURSE LIBRARY</span>
					<h1>课程中心</h1>
					<p>选择适合你的课程，按自己的节奏一步一步学习。</p>
				</div>
				<div class="mc-course-center__mark" aria-hidden="true">∑</div>
			</header>

			<nav class="mc-course-center__types" aria-label="课程分类">
				<?php
				$topic_url = add_query_arg( array( 'course_type' => 'topic' ), remove_query_arg( array( 'course_id', 'course_grade' ), $center_url ) );
				$book_url  = add_query_arg( array( 'course_type' => 'supplementary' ), remove_query_arg( array( 'course_id', 'course_grade' ), $center_url ) );
				?>
				<a class="is-topic <?php echo 'topic' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $topic_url ); ?>" aria-current="<?php echo 'topic' === $type ? 'page' : 'false'; ?>">
					<span>01</span><strong>专题课</strong><small>重点专题 · 系统突破</small><b>→</b>
				</a>
				<a class="is-book <?php echo 'supplementary' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $book_url ); ?>" aria-current="<?php echo 'supplementary' === $type ? 'page' : 'false'; ?>">
					<span>02</span><strong>教辅配套</strong><small>跟着教辅 · 逐题讲解</small><b>→</b>
				</a>
			</nav>

			<section class="mc-course-center__library">
				<div class="mc-course-center__section-title">
					<div>
						<span><?php echo esc_html( 'topic' === $type ? '专题课程' : ( 'supplementary' === $type ? '教辅配套' : '全部课程' ) ); ?></span>
						<small><?php echo $grade ? esc_html( $grade . ' 年级 · ' ) : ''; ?>按类型和年级选择</small>
					</div>
				</div>
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
