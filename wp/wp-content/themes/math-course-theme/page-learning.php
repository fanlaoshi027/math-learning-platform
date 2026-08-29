<?php
/**
 * Math Course Theme - Learning Player Page
 */

get_header();

$lesson_id = isset($_GET['lesson_id']) ? absint($_GET['lesson_id']) : get_the_ID();

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

        <div class="mc-lesson-header">

            <h1>
                <?php
                echo esc_html(get_the_title($lesson_id));
                ?>
            </h1>

            <div class="mc-learning-actions">

                <a class="mc-button mc-prev-lesson" href="#">
                    上一课
                </a>

                <a class="mc-button mc-next-lesson" href="#">
                    下一课
                </a>

            </div>

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
