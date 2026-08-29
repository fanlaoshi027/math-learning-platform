<?php
/**
 * Template Name: Course Center
 *
 * Theme layer only.
 * Course data and permissions are handled by MathCourse plugin.
 */
defined('ABSPATH') || exit;
get_header();
?>

<main class="mc-page mc-course-center">
    <section class="mc-container">
        <header class="mc-page-header">
            <h1>课程中心</h1>
            <p>系统化学习初中数学课程</p>
        </header>

        <div class="mc-course-grid">
            <?php
            if (shortcode_exists('math_course_center')) {
                echo do_shortcode('[math_course_center]');
            } else {
                echo '<p>课程中心插件未启用</p>';
            }
            ?>
        </div>
    </section>
</main>

<?php get_footer();
