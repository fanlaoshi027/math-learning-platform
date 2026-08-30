<?php
/** Template Name: Course Center */
defined( 'ABSPATH' ) || exit;
get_header();
$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
?>
<main class="mc-page mc-course-center">
    <?php if ( $course_id && shortcode_exists( 'mathcourse_course_player' ) ) : ?>
        <section class="mc-container mc-course-center__player-wrap">
            <?php echo do_shortcode( '[mathcourse_course_player course_id="' . esc_attr( $course_id ) . '"]' ); ?>
        </section>
    <?php else : ?>
        <section class="mc-container mc-course-center__intro">
            <div>
                <p class="mc-eyebrow">COURSE CENTER</p>
                <h1>课程中心</h1>
                <p>初中数学知识体系 · 专题课程 · 教辅配套</p>
            </div>
            <div class="mc-course-center__stats"><strong>系统学习</strong><span>从知识点到综合应用</span></div>
        </section>
        <section class="mc-container mc-course-center__body">
            <?php if ( shortcode_exists( 'mathcourse_course_directory' ) ) : ?>
                <?php echo do_shortcode( '[mathcourse_course_directory]' ); ?>
            <?php else : ?>
                <p>课程中心插件未启用</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
