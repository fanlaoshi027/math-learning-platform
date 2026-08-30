<?php
namespace MathCourse\Learning;

defined('ABSPATH') || exit;

use MathCourse\Course\Course_Service;

class Course_Learning {

    public function __construct() {
        add_shortcode('mathcourse_course_learning', array($this, 'render'));
    }

    public function render($atts = array()) {
        if (!is_user_logged_in()) return '<p>请登录后学习</p>';
        $atts = shortcode_atts(array('course_id' => 0), $atts, 'mathcourse_course_learning');
        $course_id = absint($atts['course_id']);
        if (!$course_id && isset($_GET['course_id'])) $course_id = absint($_GET['course_id']);
        if (!$course_id) return '<p>课程信息不存在。</p>';

        $user_id = get_current_user_id();
        $directory = (new Course_Service())->get_course_directory($course_id, $user_id);
        if (!$directory) return '<p>课程不存在。</p>';
        if (empty($directory['access'])) return '<p>你还没有获得该课程的学习权限。</p>';

        $progress = isset($directory['progress']) ? $directory['progress'] : array('completed' => 0, 'total' => 0, 'percent' => 0);
        $learning_page = get_permalink(get_page_by_path('学习课程')) ?: home_url('/学习课程/');
        $current_lesson_id = isset($_GET['lesson_id']) ? absint($_GET['lesson_id']) : 0;
        $continue_lesson = $this->find_continue_lesson($directory);
        $navigation = $this->get_lesson_navigation($directory, $current_lesson_id, $learning_page);

        ob_start();
        ?>
        <div class="mc-course-learning" data-course-id="<?php echo esc_attr($course_id); ?>" data-current-lesson="<?php echo esc_attr($current_lesson_id); ?>">
            <div class="mc-course-header-card">
                <h1><?php echo esc_html($directory['title']); ?></h1>
                <div class="mc-course-progress">已完成 <?php echo intval($progress['completed']); ?> / <?php echo intval($progress['total']); ?> 课时（<?php echo intval($progress['percent']); ?>%）</div>
            </div>

            <?php if ($continue_lesson && !empty($continue_lesson['url'])) : ?>
                <div class="mc-learning-continue">
                    <span><?php echo !empty($progress['completed']) ? '继续学习' : '开始学习'; ?></span>
                    <a href="<?php echo esc_url($continue_lesson['url']); ?>"><?php echo esc_html($continue_lesson['title']); ?> →</a>
                </div>
            <?php endif; ?>

            <?php if ($current_lesson_id && ($navigation['previous'] || $navigation['next'])) : ?>
                <nav class="mc-learning-nav" aria-label="课时导航">
                    <?php if ($navigation['previous']) : ?>
                        <a href="<?php echo esc_url($navigation['previous']['url']); ?>">← <?php echo esc_html($navigation['previous']['title']); ?></a>
                    <?php else : ?>
                        <span aria-hidden="true"></span>
                    <?php endif; ?>
                    <?php if ($navigation['next']) : ?>
                        <a class="is-primary" href="<?php echo esc_url($navigation['next']['url']); ?>">下一讲：<?php echo esc_html($navigation['next']['title']); ?> →</a>
                    <?php else : ?>
                        <a href="<?php echo esc_url($learning_page); ?>">返回课程 →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

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
                                $lesson_id = absint($lesson['id']);
                                $completed = !empty($lesson['completed']);
                                $accessible = !empty($lesson['accessible']);
                                $preview = !empty($lesson['preview']);
                                $is_current = $current_lesson_id === $lesson_id;
                                $classes = 'mc-lesson-item';
                                $classes .= $completed ? ' mc-lesson-completed is-completed' : ' mc-lesson-normal';
                                if (!$accessible) $classes .= ' is-locked';
                                if ($is_current) $classes .= ' is-current';
                                ?>
                                <div class="<?php echo esc_attr($classes); ?>" data-lesson-id="<?php echo esc_attr($lesson_id); ?>">
                                    <span class="mc-lesson-state" aria-hidden="true"><?php echo $is_current ? '▶' : ($completed ? '✓' : ($accessible ? '○' : '🔒')); ?></span>
                                    <?php if ($accessible) : ?>
                                        <a href="<?php echo esc_url(add_query_arg('lesson_id', $lesson_id, $learning_page)); ?>"><?php echo esc_html($lesson['title']); ?></a>
                                    <?php else : ?>
                                        <span><?php echo esc_html($lesson['title']); ?></span>
                                        <span class="mc-lesson-locked">未解锁</span>
                                    <?php endif; ?>
                                    <?php if ($preview) : ?><span class="mc-lesson-preview">试看</span><?php endif; ?>
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

    private function find_continue_lesson($data) {
        if (empty($data['topics'])) return null;
        $last_completed_index = -1;
        $last_completed = null;
        $index = 0;
        foreach ($data['topics'] as $topic) {
            foreach ($topic['lessons'] as $lesson) {
                if (!empty($lesson['completed'])) {
                    $last_completed_index = $index;
                    $last_completed = $lesson;
                }
                $index++;
            }
        }
        $index = 0;
        foreach ($data['topics'] as $topic) {
            foreach ($topic['lessons'] as $lesson) {
                if ($index > $last_completed_index && !empty($lesson['accessible']) && empty($lesson['completed'])) return $lesson;
                $index++;
            }
        }
        return $last_completed;
    }

    private function get_lesson_navigation($data, $current_lesson_id, $learning_page) {
        $lessons = array();
        foreach ($data['topics'] as $topic) {
            foreach ($topic['lessons'] as $lesson) {
                if (!empty($lesson['accessible'])) $lessons[] = $lesson;
            }
        }
        $current_index = -1;
        foreach ($lessons as $index => $lesson) {
            if (absint($lesson['id']) === absint($current_lesson_id)) {
                $current_index = $index;
                break;
            }
        }
        if ($current_index < 0) return array('previous' => null, 'next' => null);

        $make_link = static function ($lesson) use ($learning_page) {
            return array(
                'title' => isset($lesson['title']) ? $lesson['title'] : '',
                'url' => add_query_arg('lesson_id', absint($lesson['id']), $learning_page),
            );
        };
        return array(
            'previous' => $current_index > 0 ? $make_link($lessons[$current_index - 1]) : null,
            'next' => isset($lessons[$current_index + 1]) ? $make_link($lessons[$current_index + 1]) : null,
        );
    }
}
