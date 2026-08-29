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

        if ($course_id) {
            return $this->render_single_course($course_id);
        }

        return $this->render_course_center();
    }

    private function render_course_center() {
        if (!function_exists('tutor')) {
            return '<p>课程系统暂不可用。</p>';
        }

        $user_id = get_current_user_id();
        $course_post_type = tutor()->course_post_type;
        $courses = get_posts(array(
            'post_type'      => $course_post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'date' => 'DESC'),
        ));

        if (empty($courses)) {
            return '<p class="mathcourse-directory__empty">目前还没有已发布课程。</p>';
        }

        $cards = array();
        foreach ($courses as $course) {
            $data = $this->service->get_course_directory($course->ID, $user_id);
            if (!$data) {
                continue;
            }

            $has_access = !empty($data['access']);
            $progress = isset($data['progress']) ? $data['progress'] : array(
                'completed' => 0,
                'total' => 0,
                'percent' => 0,
                'last_lesson_id' => 0,
                'last_time' => 0,
            );

            $continue_lesson = $this->find_continue_lesson($data);
            $cards[] = array(
                'data' => $data,
                'access' => $has_access,
                'progress' => $progress,
                'continue' => $continue_lesson,
            );
        }

        // 已授权课程优先显示，授权课程内部按课程排序保持稳定。
        usort($cards, function ($a, $b) {
            return (int) $b['access'] <=> (int) $a['access'];
        });

        ob_start(); ?>
        <div class="mathcourse-center">
            <div class="mathcourse-center__grid">
                <?php foreach ($cards as $card) :
                    $data = $card['data'];
                    $progress = $card['progress'];
                    $continue = $card['continue'];
                    $has_access = $card['access'];
                    $cover = !empty($data['cover']) ? $data['cover'] : '';
                    ?>
                    <article class="mathcourse-center__card">
                        <a class="mathcourse-center__cover" href="<?php echo esc_url(get_permalink($data['id'])); ?>">
                            <?php if ($cover) : ?>
                                <img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($data['title']); ?>" loading="lazy">
                            <?php else : ?>
                                <span class="mathcourse-center__cover-placeholder">数学课程</span>
                            <?php endif; ?>
                        </a>
                        <div class="mathcourse-center__body">
                            <div class="mathcourse-center__meta">
                                <?php if (!empty($data['grade'])) : ?><span><?php echo esc_html($this->grade_label($data['grade'])); ?></span><?php endif; ?>
                                <?php if (!empty($data['type'])) : ?><span><?php echo esc_html($data['type'] === 'supplementary' ? '教辅配套课' : '专题课程'); ?></span><?php endif; ?>
                            </div>
                            <h2 class="mathcourse-center__title"><?php echo esc_html($data['title']); ?></h2>

                            <?php if ($has_access) : ?>
                                <div class="mathcourse-center__progress-text">
                                    <span>学习进度</span>
                                    <strong><?php echo esc_html($progress['completed']); ?> / <?php echo esc_html($progress['total']); ?></strong>
                                    <em><?php echo esc_html($progress['percent']); ?>%</em>
                                </div>
                                <div class="mathcourse-center__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($progress['percent']); ?>">
                                    <span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span>
                                </div>
                                <?php if ($continue && !empty($continue['url'])) : ?>
                                    <a class="mathcourse-center__button" href="<?php echo esc_url($continue['url']); ?>">
                                        <?php echo !empty($progress['completed']) ? '继续学习' : '开始学习'; ?>
                                        <span>→</span>
                                    </a>
                                <?php else : ?>
                                    <a class="mathcourse-center__button" href="<?php echo esc_url(get_permalink($data['id'])); ?>">查看课程 <span>→</span></a>
                                <?php endif; ?>
                            <?php else : ?>
                                <div class="mathcourse-center__locked">未授权 · 可查看课程</div>
                                <a class="mathcourse-center__button is-outline" href="<?php echo esc_url(get_permalink($data['id'])); ?>">查看课程 <span>→</span></a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_single_course($course_id) {
        if (!function_exists('tutor')) {
            return '<p>课程系统暂不可用。</p>';
        }

        if (!is_singular() || !is_admin()) {
            $data = $this->service->get_course_directory($course_id, get_current_user_id());
        } else {
            $data = $this->service->get_course_directory($course_id, get_current_user_id());
        }

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
        $continue_lesson = $this->find_continue_lesson($data);

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

    private function find_continue_lesson($data) {
        $has_access = !empty($data['access']);
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

        return $continue_lesson;
    }

    private function grade_label($grade) {
        $labels = array(
            '7' => '七年级',
            '8' => '八年级',
            '9' => '九年级',
            '10' => '高一',
            '11' => '高二',
            '12' => '高三',
        );

        return isset($labels[(string) $grade]) ? $labels[(string) $grade] : (string) $grade;
    }
}
