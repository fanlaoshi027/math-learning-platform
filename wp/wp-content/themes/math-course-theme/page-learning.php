<?php
/**
 * Math Course Theme - Learning Player Page.
 *
 * Course selection opens the learning player directly. The player chooses
 * the first accessible lesson when lesson_id is not supplied.
 */
defined( 'ABSPATH' ) || exit;

get_header();

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
?>

<main class="mc-learning-page">
	<div class="mc-learning-container">
		<?php
		if ( $course_id && shortcode_exists( 'mathcourse_course_player' ) ) {
			echo do_shortcode(
				'[mathcourse_course_player course_id="' . esc_attr( $course_id ) . '"' .
				( $lesson_id ? ' lesson_id="' . esc_attr( $lesson_id ) . '"' : '' ) .
				']'
			);
		} else {
			?>
			<section class="mc-lesson-card">
				<h1>课程不存在或链接无效</h1>
				<p><a href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">返回课程中心</a></p>
			</section>
			<?php
		}
		?>
	</div>
</main>

<?php get_footer();
