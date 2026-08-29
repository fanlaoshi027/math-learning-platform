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

        $progress = isset($data['progress']) ? $data['progress'] : array(
            'completed' => 0,
            'total' => 0,
            'percent' => 0,
            'last_lesson_id' => 0,
            'last_time' => 0,
        );
        $is_logged_in = is_user_logged_in();
        $has_access = !empty($data['access']);

        // 对已授权学员：优先进入第一节未完成课时；全部完成后进入最后完成课时。
        // 未授权访客：进入第一节可试看课时。
        $continue_lesson = null;
        $last_completed_lesson = null;

        foreach ($data['topics'] as $topic) {
            foreach ($topic['lessons'] as $lesson) {
                if ($lesson['completed'] && $has_access) {
                    $last_completed_lesson = $lesson;
                }

                if (!$continue_lesson && $lesson['accessible'] && (!$has_access || !$lesson['completed'])) {
                    $continue_lesson = $lesson;
                }
            }
        }

        if ($has_access && !$continue_lesson && $last_completed_lesson) {
            $continue_lesson = $last_completed_lesson;
        }

        ob_start(); ?>
        <div class="mathcourse-directory" data-course-id="<?php echo esc_attr($data['id']); ?>">
            <div class="mathcourse-directory__header">
                <div class="mathcourse-directory__heading">
                    <h1><?php echo esc_html($data['title']); ?></h1>
                    <?php if ($has_access) : ?>
                        <div class="mathcourse-directory__progress" aria-label="课程进度">
                            <div class="mathcourse-directory__progress-text">
                                学习进度 <?php echo esc_html($progress['percent']); ?>%
                                <span>（<?php echo esc_html($progress['completed']); ?>/<?php echo esc_html($progress['total']); ?>）</span>
                            </div>
                            <div class="mathcourse-directory__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($progress['percent']); ?>">
                                <span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($continue_lesson && !empty($continue_lesson['url'])) : ?>
                    <a class="mathcourse-directory__continue" href="<?php echo esc_url($continue_lesson['url']); ?>">
                        <span><?php echo $has_access && !empty($progress['completed']) ? '继续学习' : '开始学习'; ?></span>
                        <strong><?php echo esc_html($continue_lesson['title']); ?></strong>
                        <span class="mathcourse-directory__continue-arrow">→</span>
                    </a>
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
                <section class="mathcourse-directory__topic">
                    <h2 class="mathcourse-directory__topic-title"><span><?php echo esc_html($index + 1); ?></span><?php echo esc_html($topic['title']); ?></h2>
                    <div class="mathcourse-directory__lessons">
                        <?php foreach ($topic['lessons'] as $lesson) : ?>
                            <?php if ($lesson['accessible']) : ?>
                                <a class="mathcourse-directory__lesson <?php echo $lesson['completed'] ? 'is-complete' : ''; ?>" href="<?php echo esc_url($lesson['url']); ?>">
                                    <span class="mathcourse-directory__status" aria-hidden="true"><?php echo $lesson['completed'] ? '✓' : '○'; ?></span>
                                    <span class="mathcourse-directory__lesson-title"><?php echo esc_html($lesson['title']); ?></span>
                                    <?php if ($lesson['preview']) : ?><span class="mathcourse-directory__preview">试看</span><?php endif; ?>
                                </a>
                            <?php else : ?>
                                <div class="mathcourse-directory__lesson is-locked" aria-disabled="true">
                                    <span class="mathcourse-directory__status" aria-hidden="true">🔒</span>
                                    <span class="mathcourse-directory__lesson-title"><?php echo esc_html($lesson['title']); ?></span>
                                    <span class="mathcourse-directory__locked">需授权</span>
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
