<?php
namespace MathCourse\Learning;

defined('ABSPATH') || exit;

use MathCourse\Course\Course_Service;
use MathCourse\Progress\Progress_Service;

class Course_Learning {

    public function __construct() {
        add_shortcode('mathcourse_course_learning', array($this, 'render'));
    }

    public function render($atts = array()) {
        if (!is_user_logged_in()) {
            return '<p>请登录后学习</p>';
        }

        $atts = shortcode_atts(array(
            'course_id' => 0,
        ), $atts, 'mathcourse_course_learning');

        $course_id = absint($atts['course_id']);
        if (!$course_id) {
            return '<p>课程信息不存在。</p>';
        }

        $user_id = get_current_user_id();
        $course_service = new Course_Service();
        $directory = $course_service->get_course_directory($course_id, $user_id);

        if (!$directory) {
            return '<p>课程不存在。</p>';
        }

        if (empty($directory['access'])) {
            return '<p>你还没有获得该课程的学习权限。</p>';
        }

        $progress = isset($directory['progress']) ? $directory['progress'] : array(
            'completed' => 0,
            'total' => 0,
            'percent' => 0,
        );

        $progress_service = new Progress_Service();
        $learning_page = get_permalink(get_page_by_path('学习课程'));
        if (!$learning_page) {
            $learning_page = home_url('/学习课程/');
        }

        ob_start();
        ?>
        <div class="mc-course-learning">
            <div class="mc-course-header-card">
                <h1><?php echo esc_html($directory['title']); ?></h1>
                <div class="mc-course-progress">
                    已完成 <?php echo intval($progress['completed']); ?> /
                    <?php echo intval($progress['total']); ?> 课时
                    （<?php echo intval($progress['percent']); ?>%）
                </div>
            </div>

            <div class="mc-course-outline">
                <h2>课程目录</h2>

                <?php foreach ($directory['topics'] as $topic) : ?>
                    <section class="mc-topic">
                        <h3><?php echo esc_html($topic['title']); ?></h3>

                        <?php if (empty($topic['lessons'])) : ?>
                            <div class="mc-lesson-item mc-lesson-normal">暂无课时</div>
                        <?php else : ?>
                            <?php foreach ($topic['lessons'] as $lesson) : ?>
                                <?php
                                $completed = !empty($lesson['completed']);
                                $lesson_url = add_query_arg(
                                    array('lesson_id' => absint($lesson['id'])),
                                    $learning_page
                                );
                                ?>
                                <div class="mc-lesson-item <?php echo $completed ? 'mc-lesson-completed' : 'mc-lesson-normal'; ?>">
                                    <span class="mc-lesson-state" aria-hidden="true"><?php echo $completed ? '✓' : '○'; ?></span>
                                    <?php if (!empty($lesson['accessible'])) : ?>
                                        <a href="<?php echo esc_url($lesson_url); ?>">
                                            <?php echo esc_html($lesson['title']); ?>
                                        </a>
                                    <?php else : ?>
                                        <span><?php echo esc_html($lesson['title']); ?></span>
                                        <span class="mc-lesson-locked">未解锁</span>
                                    <?php endif; ?>
                                    <?php if (!empty($lesson['preview'])) : ?>
                                        <span class="mc-lesson-preview">试看</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}
