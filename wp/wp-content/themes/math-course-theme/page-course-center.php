<?php
/** Template Name: Course Center */
defined('ABSPATH') || exit;
get_header();
$course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
?>
<main class="mc-page mc-course-center">
    <section class="mc-container">
        <?php if ($course_id && shortcode_exists('mathcourse_course_player')) : ?>
            <?php echo do_shortcode('[mathcourse_course_player course_id="' . esc_attr($course_id) . '"]'); ?>
        <?php else : ?>
            <header class="mc-page-header"><h1>课程中心</h1><p>系统化学习初中数学课程</p></header>
            <div class="mc-course-grid">
                <?php if (shortcode_exists('mathcourse_course_directory')) echo do_shortcode('[mathcourse_course_directory]'); else echo '<p>课程中心插件未启用</p>'; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php get_footer();
