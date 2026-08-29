<?php
/**
 * Template Name: Course Center
 */
defined('ABSPATH') || exit;
get_header();
?>
<main class="mc-page mc-course-center">
    <section class="mc-container">
        <h1>课程中心</h1>
        <div class="mc-course-grid">
            <?php
            $courses = new WP_Query(array(
                'post_type' => 'courses',
                'posts_per_page' => -1,
            ));
            if ($courses->have_posts()):
                while ($courses->have_posts()): $courses->the_post();
            ?>
                <article class="mc-course-card">
                    <a href="<?php the_permalink(); ?>">
                        <?php the_post_thumbnail('medium'); ?>
                        <h2><?php the_title(); ?></h2>
                        <p><?php echo esc_html(wp_trim_words(get_the_content(), 25)); ?></p>
                        <span class="mc-button">进入学习</span>
                    </a>
                </article>
            <?php
                endwhile;
                wp_reset_postdata();
            else:
                echo '<p>暂无课程</p>';
            endif;
            ?>
        </div>
    </section>
</main>
<?php get_footer();
