<?php
/**
 * Math Course Theme - Learning Player Page
 */
get_header();
?>
<main class="mc-learning-page">
    <section class="mc-video-layout">
        <div class="mc-video-box">
            <?php
            if ( shortcode_exists('math_learning_player') ) {
                echo do_shortcode('[math_learning_player]');
            } else {
                echo '<p>播放器加载中...</p>';
            }
            ?>
        </div>
    </section>

    <section class="mc-learning-info">
        <h1><?php the_title(); ?></h1>
        <div class="mc-learning-actions">
            <a href="#" class="mc-button">上一课</a>
            <a href="#" class="mc-button">下一课</a>
        </div>
    </section>

    <section class="mc-learning-outline">
        <h2>课程目录</h2>
        <?php
        if ( shortcode_exists('mathcourse_course_learning') ) {
            echo do_shortcode('[mathcourse_course_learning]');
        }
        ?>
    </section>
</main>
<?php
get_footer();
