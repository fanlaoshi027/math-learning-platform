<?php
/**
 * Template Name: 课程中心 (Course Center)
 * UI Migration V2 - screenshot-aligned, mobile-first course showcase.
 *
 * @package MathCourse_Theme
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="mc-course-page min-h-screen">
    <div class="mc-course-page__container">
        <section aria-label="课程列表">
            <?php
            if ( shortcode_exists( 'mathcourse_course_center' ) ) {
                echo do_shortcode( '[mathcourse_course_center]' );
            } elseif ( shortcode_exists( 'mathcourse_course_directory' ) ) {
                echo do_shortcode( '[mathcourse_course_directory]' );
            } else {
                echo '<div class="mathcourse-directory__empty"><strong>课程中心正在加载</strong><span>请确认 MathCourse 插件已启用。</span></div>';
            }
            ?>
        </section>
    </div>
</main>

<?php get_footer(); ?>
