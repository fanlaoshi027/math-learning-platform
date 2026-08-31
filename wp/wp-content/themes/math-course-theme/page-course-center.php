<?php
/** Template Name: Course Center */
defined( 'ABSPATH' ) || exit;

get_header();
$course_id  = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$center_url = get_permalink();
$type       = isset( $_GET['course_type'] ) ? sanitize_key( wp_unslash( $_GET['course_type'] ) ) : '';
$grade      = isset( $_GET['course_grade'] ) ? sanitize_key( wp_unslash( $_GET['course_grade'] ) ) : '';

if ( $course_id ) {
	$learning_page = get_page_by_path( 'learning' );
	$learning_url  = $learning_page ? get_permalink( $learning_page ) : home_url( '/learning/' );
	wp_safe_redirect( add_query_arg( 'course_id', $course_id, $learning_url ), 302 );
	exit;
}

$grades = array( '7' => '七年级', '8' => '八年级', '9' => '九年级' );
$all_url = remove_query_arg( array( 'course_id', 'course_type', 'course_grade' ), $center_url );
$topic_url = add_query_arg( array( 'course_type' => 'topic' ), remove_query_arg( array( 'course_id', 'course_grade' ), $center_url ) );
$book_url = add_query_arg( array( 'course_type' => 'supplementary' ), remove_query_arg( array( 'course_id', 'course_grade' ), $center_url ) );
?>

<main class="mc-page mc-course-center">
	<div class="mc-container">
		<header class="mc-course-center__hero">
			<div class="mc-course-center__hero-copy">
				<span class="mc-course-center__eyebrow">MATH · LEARNING</span>
				<h1>课程中心</h1>
				<p>按知识体系和学习阶段选择课程，找到适合自己的学习路径。</p>
			</div>
			<div class="mc-course-center__mark" aria-hidden="true">∑</div>
		</header>

		<div class="mc-course-center__workspace">
			<aside class="mc-course-center__sidebar" aria-label="课程筛选">
				<div class="mc-course-center__side-title"><span>知识体系</span></div>
				<nav class="mc-course-center__side-nav">
					<a class="<?php echo '' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $all_url ); ?>"><i>全</i><span>全部课程</span><b>›</b></a>
					<a class="<?php echo 'topic' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $topic_url ); ?>"><i>专</i><span>专题课程</span><b>›</b></a>
					<a class="<?php echo 'supplementary' === $type ? 'is-active' : ''; ?>" href="<?php echo esc_url( $book_url ); ?>"><i>辅</i><span>教辅配套</span><b>›</b></a>
				</nav>

				<div class="mc-course-center__side-divider"></div>
				<div class="mc-course-center__side-title mc-course-center__side-title--grade"><span>学习阶段</span></div>
				<nav class="mc-course-center__grade-nav">
					<?php foreach ( $grades as $grade_value => $grade_label ) :
						$grade_args = array( 'course_grade' => $grade_value );
						if ( $type ) $grade_args['course_type'] = $type;
						$grade_url = add_query_arg( $grade_args, remove_query_arg( array( 'course_id', 'course_type', 'course_grade' ), $center_url ) );
						$is_active = $grade === $grade_value;
					?>
					<a class="<?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $grade_url ); ?>"><span><?php echo esc_html( $grade_label ); ?></span><b><?php echo $is_active ? '✓' : '›'; ?></b></a>
					<?php endforeach; ?>
				</nav>
			</aside>

			<section class="mc-course-center__library">
				<div class="mc-course-center__section-title">
					<div>
						<span><?php echo esc_html( 'topic' === $type ? '专题课程' : ( 'supplementary' === $type ? '教辅配套' : '全部课程' ) ); ?></span>
						<small><?php echo $grade ? esc_html( $grades[ $grade ] . ' · ' ) : ''; ?>选择课程开始学习</small>
					</div>
				</div>
				<div class="mc-course-center__course-list">
				<?php
				if ( shortcode_exists( 'mathcourse_course_directory' ) ) {
					echo do_shortcode( '[mathcourse_course_directory show_filters="0"]' );
				} else {
					echo '<div class="mc-course-center__empty">课程中心插件未启用。</div>';
				}
				?>
				</div>
			</section>
		</div>
	</div>
</main>

<?php get_footer(); ?>