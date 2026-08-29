<?php
namespace MathCourse\Frontend;

defined('ABSPATH') || exit;

class Course_Directory {
    public function __construct() {
        add_shortcode('mathcourse_course_directory', array($this, 'render'));
    }

    public function render($atts = array()) {
        if (!function_exists('tutor')) return '<p>课程系统暂不可用。</p>';
        $atts = shortcode_atts(array('course_id' => 0), $atts, 'mathcourse_course_directory');
        $course_id = absint($atts['course_id']);
        if (!$course_id && is_singular(tutor()->course_post_type)) $course_id = get_the_ID();
        if (!$course_id) return '<p>未指定课程。</p>';
        $course = get_post($course_id);
        if (!$course || tutor()->course_post_type !== $course->post_type) return '<p>课程不存在。</p>';

        $topics = get_posts(array(
            'post_type' => 'topics', 'post_parent' => $course_id,
            'post_status' => 'publish', 'posts_per_page' => -1,
            'orderby' => array('menu_order' => 'ASC', 'ID' => 'ASC'),
        ));
        ob_start(); ?>
        <div class="mathcourse-directory" data-course-id="<?php echo esc_attr($course_id); ?>">
            <div class="mathcourse-directory__header">
                <h1><?php echo esc_html(get_the_title($course_id)); ?></h1>
            </div>
            <?php if (!$topics) : ?><p class="mathcourse-directory__empty">本课程暂时还没有课程内容。</p><?php endif; ?>
            <?php foreach ($topics as $index => $topic) :
                $lessons = get_posts(array(
                    'post_type' => 'lesson', 'post_parent' => $topic->ID,
                    'post_status' => 'publish', 'posts_per_page' => -1,
                    'orderby' => array('menu_order' => 'ASC', 'ID' => 'ASC'),
                )); ?>
                <section class="mathcourse-topic">
                    <h2><span><?php echo esc_html($index + 1); ?></span><?php echo esc_html(get_the_title($topic)); ?></h2>
                    <div class="mathcourse-lessons">
                    <?php foreach ($lessons as $lesson) :
                        $preview = get_post_meta($lesson->ID, '_mathcourse_preview', true) === 'yes';
                        $done = false;
                        if (is_user_logged_in() && function_exists('tutor_utils')) {
                            $done = (bool) tutor_utils()->is_completed_lesson($lesson->ID, get_current_user_id());
                        }
                        $url = get_permalink($lesson->ID);
                        ?>
                        <a class="mathcourse-lesson <?php echo $done ? 'is-complete' : ''; ?>" href="<?php echo esc_url($url); ?>">
                            <span class="mathcourse-lesson__state"><?php echo $done ? '✓' : '○'; ?></span>
                            <span class="mathcourse-lesson__title"><?php echo esc_html(get_the_title($lesson)); ?></span>
                            <?php if ($preview) : ?><span class="mathcourse-lesson__preview">试看</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }
}
