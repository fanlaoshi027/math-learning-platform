<?php
/**
 * Math Course Theme - Single Course Learning Page
 *
 * The course experience is rendered by MathCourse, not Tutor LMS' native
 * single-course template. Tutor LMS remains the underlying data layer.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="mc-page mc-course-single">
	<section class="mc-container">
		<?php
		if ( shortcode_exists( 'mathcourse_course_directory' ) ) {
			echo do_shortcode( '[mathcourse_course_directory course_id="' . absint( get_the_ID() ) . '"]' );
		} else {
			echo '<p>课程系统未启用。</p>';
		}
		?>
	</section>
</main>
<?php get_footer();
