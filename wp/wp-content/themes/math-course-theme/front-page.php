<?php
/**
 * Math Course Theme - Front Page
 */

defined('ABSPATH') || exit;
get_header();
?>

<main class="mc-home">
    <section class="mc-hero">
        <div class="mc-container">
            <p class="mc-eyebrow">樊老师课堂</p>
            <h1>樊老师数学</h1>
            <p>专注初中数学系统学习</p>
        </div>
    </section>

    <section class="mc-home-courses">
        <div class="mc-container">
            <?php
            if (shortcode_exists('mathcourse_course_directory')) {
                echo do_shortcode('[mathcourse_course_directory]');
            } elseif (shortcode_exists('mathcourse_course_center')) {
                echo do_shortcode('[mathcourse_course_center]');
            } else {
                echo '<p class="mathcourse-directory__empty">课程中心正在加载。</p>';
            }
            ?>
        </div>
    </section>
</main>

<?php get_footer();
