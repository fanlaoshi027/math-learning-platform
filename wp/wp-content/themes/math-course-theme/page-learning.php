<?php
/**
 * Math Course Theme - Learning Player Page
 *
 * Theme controls layout only. Course, access, video and progress logic
 * are provided by the MathCourse plugin.
 */

defined('ABSPATH') || exit;
get_header();

$lesson_id = isset($_GET['lesson_id']) ? absint($_GET['lesson_id']) : 0;
$course_id = 0;
$lesson_title = '';
$access_error = '';

if ($lesson_id && function_exists('tutor')) {
    $lesson = get_post($lesson_id);

    if ($lesson && tutor()->lesson_post_type === $lesson->post_type) {
        $topic = get_post($lesson->post_parent);
        if ($topic && 'topics' === $topic->post_type) {
            $course_id = absint($topic->post_parent);
            $lesson_title = get_the_title($lesson_id);
        }
    }
}

if (!$lesson_id || !$course_id) {
    $access_error = '课时不存在或链接无效。';
}
?>

<main class="mc-learning-page">
    <div class="mc-learning-container">

        <?php if ($access_error) : ?>
            <section class="mc-lesson-card">
                <h1><?php echo esc_html($access_error); ?></h1>
                <p><a href="<?php echo esc_url(home_url('/课程中心/')); ?>">返回课程中心</a></p>
            </section>
        <?php else : ?>
            <section class="mc-video-card">
                <div class="mc-video-box">
                    <?php
                    if (shortcode_exists('mathcourse_video')) {
                        echo do_shortcode(
                            '[mathcourse_video lesson_id="' . esc_attr($lesson_id) . '" course_id="' . esc_attr($course_id) . '"]'
                        );
                    } else {
                        echo '<p>播放器加载中...</p>';
                    }
                    ?>
                </div>
            </section>

            <section class="mc-lesson-card">
                <div class="mc-lesson-header">
                    <h1><?php echo esc_html($lesson_title); ?></h1>
                </div>
            </section>

            <section class="mc-outline-card">
                <h2>课程目录</h2>
                <?php
                if (shortcode_exists('mathcourse_course_learning')) {
                    echo do_shortcode(
                        '[mathcourse_course_learning course_id="' . esc_attr($course_id) . '"]'
                    );
                }
                ?>
            </section>
        <?php endif; ?>

    </div>
</main>

<?php get_footer();
