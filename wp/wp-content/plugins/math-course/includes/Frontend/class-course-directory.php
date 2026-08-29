<?php
namespace MathCourse\Frontend;

defined('ABSPATH') || exit;

use MathCourse\Course\Course_Service;

class Course_Directory {
    private $service;

    public function __construct() {
        $this->service = new Course_Service();
        add_shortcode('mathcourse_course_directory', array($this, 'render'));
    }

    public function render($atts = array()) {
        $atts = shortcode_atts(array('course_id' => 0), $atts, 'mathcourse_course_directory');
        $course_id = absint($atts['course_id']);
        if (!$course_id && function_exists('tutor') && is_singular(tutor()->course_post_type)) {
            $course_id = get_the_ID();
        }
        if (!$course_id) return '<p>未指定课程。</p>';

        $data = $this->service->get_course_directory($course_id, get_current_user_id());
        if (!$data) return '<p>课程不存在或课程系统暂不可用。</p>';

        $progress = isset($data['progress']) ? $data['progress'] : array('completed' => 0, 'total' => 0, 'percent' => 0);
        $is_logged_in = is_user_logged_in();
        $has_access = !empty($data['access']);

        ob_start(); ?>
        <div class="mathcourse-directory" data-course-id="<?php echo esc_attr($data['id']); ?>">
            <div class="mathcourse-directory__header">
                <h1><?php echo esc_html($data['title']); ?></h1>
                <?php if ($has_access) : ?>
                    <div class="mathcourse-directory__progress" aria-label="课程进度">
                        <div class="mathcourse-directory__progress-text">学习进度 <?php echo esc_html($progress['percent']); ?>%（<?php echo esc_html($progress['completed']); ?>/<?php echo esc_html($progress['total']); ?>）</div>
                        <div class="mathcourse-directory__progress-track"><span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span></div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$has_access) : ?>
                <div class="mathcourse-directory__notice">
                    <?php if ($is_logged_in) : ?>
                        <strong>你还没有获得本课程的学习权限</strong>
                        <span>已标记为“试看”的课时仍可直接观看。</span>
                    <?php else : ?>
                        <strong>请登录后学习本课程</strong>
                        <span>已标记为“试看”的课时无需课程授权即可观看。</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($data['topics'])) : ?>
                <p class="mathcourse-directory__empty">本课程暂时还没有课程内容。</p>
            <?php endif; ?>

            <?php foreach ($data['topics'] as $index => $topic) : ?>
                <section class="mathcourse-topic">
                    <h2><span><?php echo esc_html($index + 1); ?></span><?php echo esc_html($topic['title']); ?></h2>
                    <div class="mathcourse-lessons">
                        <?php foreach ($topic['lessons'] as $lesson) : ?>
                            <?php if ($lesson['accessible']) : ?>
                                <a class="mathcourse-lesson <?php echo $lesson['completed'] ? 'is-complete' : ''; ?>" href="<?php echo esc_url($lesson['url']); ?>">
                                    <span class="mathcourse-lesson__state"><?php echo $lesson['completed'] ? '✓' : '○'; ?></span>
                                    <span class="mathcourse-lesson__title"><?php echo esc_html($lesson['title']); ?></span>
                                    <?php if ($lesson['preview']) : ?><span class="mathcourse-lesson__preview">试看</span><?php endif; ?>
                                </a>
                            <?php else : ?>
                                <div class="mathcourse-lesson is-locked" aria-disabled="true">
                                    <span class="mathcourse-lesson__state">🔒</span>
                                    <span class="mathcourse-lesson__title"><?php echo esc_html($lesson['title']); ?></span>
                                    <span class="mathcourse-lesson__locked">需授权</span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }
}
