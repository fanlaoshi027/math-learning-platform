<?php
/**
 * Template Name: 学习播放页 (Learning Page)
 *
 * 学习页只负责提供页面入口，课程目录、授权判断、课时选择、播放器
 * 与学习进度统一交给 MathCourse 的 Course_Player 业务组件处理。
 *
 * 入口格式：/learning/?course_id={COURSE_ID}&lesson_id={LESSON_ID}
 *
 * @package MathCourse_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
?>
<section class="mc-learning-page" id="main-content">
	<?php
	if ( shortcode_exists( 'mathcourse_course_player' ) ) {
		echo do_shortcode(
			sprintf(
				'[mathcourse_course_player course_id="%d"]',
				$course_id
			)
		);
	} else {
		?>
		<section class="mc-learning-error" role="alert">
			<h1>课程播放器暂不可用</h1>
			<p>课程播放组件尚未加载，请稍后再试。</p>
		</section>
		<?php
	}
	?>
</section>
<?php
get_footer();
