<?php
/**
 * Template Name: 课程中心 (Course Center)
 * The MathCourse plugin owns course data, filters and learning routes.
 * This template is intentionally a thin visual shell so the reference UI
 * can be reproduced without changing course business logic.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>

<main class="mc-course-page">
    <div class="mc-course-page__container">
        <?php
        if ( shortcode_exists( 'mathcourse_course_center' ) ) {
            echo do_shortcode( '[mathcourse_course_center]' );
        } elseif ( shortcode_exists( 'mathcourse_course_directory' ) ) {
            echo do_shortcode( '[mathcourse_course_directory]' );
        } else {
            echo '<div class="mathcourse-directory__empty"><strong>课程中心正在加载。</strong><span>请确认 MathCourse 插件已启用。</span></div>';
        }
        ?>
    </div>
</main>

<?php get_footer(); ?>
