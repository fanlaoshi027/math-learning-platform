<?php
/**
 * Template Name: 学习中心
 *
 * Personalized student learning center. Only authenticated students can
 * access this page; the MathCourse plugin owns authorization and progress.
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( home_url( '/learning-center/' ) ) );
	exit;
}

get_header();
?>

<main class="mc-learning-center-page">
	<div class="mc-learning-center-page__container">
		<?php
		if ( shortcode_exists( 'mathcourse_learning_center' ) ) {
			echo do_shortcode( '[mathcourse_learning_center]' );
		} else {
			echo '<p>学习中心暂不可用，请确认 MathCourse 插件已启用。</p>';
		}
		?>
	</div>
</main>

<?php get_footer();