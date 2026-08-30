<?php
namespace MathCourse\Frontend;

defined('ABSPATH') || exit;

use MathCourse\Course\Course_Service;

class Course_Directory {
    private $service;

    public function __construct() {
        $this->service = new Course_Service();
        add_shortcode('mathcourse_course_directory', array($this, 'render'));
        add_shortcode('mathcourse_course_center', array($this, 'render'));
        add_shortcode('mathcourse_learning_center', array($this, 'render_learning_center'));
    }

    public function render($atts = array()) {
        $atts = shortcode_atts(array('course_id' => 0), $atts, 'mathcourse_course_directory');
        $course_id = absint($atts['course_id']);

        // When the shortcode is used on the Course Center page, a course_id
        // query parameter opens our custom course-detail view. This avoids
        // sending visitors into Tutor LMS' native single-course template.
        if (!$course_id && isset($_GET['course_id'])) {
            $course_id = absint($_GET['course_id']);
        }

        return $course_id ? $this->render_single_course($course_id) : $this->render_course_center();
    }

    /** Build the canonical MathCourse course-detail URL. */
    private function course_detail_url($course_id) {
        $page = get_page_by_path('course-center');
        $base = $page ? get_permalink($page) : home_url('/course-center/');
        return add_query_arg('course_id', absint($course_id), $base);
    }

    /**
     * 公开课程目录：游客和登录用户都看到已发布课程。
     * 这里不是“我的课程”，因此不按个人授权过滤。
     */
    private function render_course_center() {
        if (!function_exists('tutor')) return '<p>课程系统暂不可用。</p>';

        $courses = get_posts(array(
            'post_type' => tutor()->course_post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'),
        ));

        if (empty($courses)) return '<p class="mathcourse-directory__empty">目前还没有已发布课程。</p>';

        ob_start(); ?>
        <div class="mathcourse-center">
            <div class="mathcourse-center__grid">
                <?php foreach ($courses as $course) :
                    $data = $this->service->get_course_directory($course->ID, get_current_user_id());
                    if (!$data) continue;
                    $cover = !empty($data['cover']) ? $data['cover'] : '';
                    $detail_url = $this->course_detail_url($data['id']);
                ?>
                    <article class="mathcourse-center__card">
                        <a class="mathcourse-center__cover" href="<?php echo esc_url($detail_url); ?>">
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
                            <a class="mathcourse-center__button" href="<?php echo esc_url($detail_url); ?>">查看课程 <span>→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    /** 学员学习中心：只显示当前用户已经获得 MathCourse 授权的课程，并显示 Progress。 */
    public function render_learning_center() {
        if (!is_user_logged_in()) {
            return '<div class="mathcourse-learning-center__login"><strong>请先登录</strong><span>登录后查看你的课程和学习进度。</span></div>';
        }

        $user_id = get_current_user_id();
        $courses = get_posts(array(
            'post_type' => function_exists('tutor') ? tutor()->course_post_type : 'courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'),
        ));

        $cards = array();
        foreach ($courses as $course) {
            $data = $this->service->get_course_directory($course->ID, $user_id);
            if (!$data || empty($data['access'])) continue;
            $progress = isset($data['progress']) ? $data['progress'] : array('completed'=>0,'total'=>0,'percent'=>0);
            $continue = $this->find_continue_lesson($data);
            $cards[] = array('data'=>$data, 'progress'=>$progress, 'continue'=>$continue);
        }

        ob_start(); ?>
        <div class="mathcourse-learning-center">
            <div class="mathcourse-learning-center__heading">
                <h1>学习中心</h1>
                <p>我的课程与学习进度</p>
            </div>
            <?php if (empty($cards)) : ?>
                <div class="mathcourse-learning-center__empty"><strong>还没有已授权课程</strong><span>获得课程授权后，会显示在这里。</span></div>
            <?php else : ?>
                <div class="mathcourse-learning-center__grid">
                    <?php foreach ($cards as $card) : $data=$card['data']; $progress=$card['progress']; $continue=$card['continue']; ?>
                        <article class="mathcourse-learning-center__card">
                            <div class="mathcourse-learning-center__cover">
                                <?php if (!empty($data['cover'])) : ?><img src="<?php echo esc_url($data['cover']); ?>" alt="<?php echo esc_attr($data['title']); ?>" loading="lazy"><?php else : ?><span>数学课程</span><?php endif; ?>
                            </div>
                            <div class="mathcourse-learning-center__body">
                                <h2><?php echo esc_html($data['title']); ?></h2>
                                <div class="mathcourse-learning-center__progress-row"><span>学习进度</span><strong><?php echo esc_html($progress['completed']); ?> / <?php echo esc_html($progress['total']); ?></strong><em><?php echo esc_html($progress['percent']); ?>%</em></div>
                                <div class="mathcourse-learning-center__progress-track"><span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span></div>
                                <a class="mathcourse-learning-center__button" href="<?php echo esc_url($continue && !empty($continue['url']) ? $continue['url'] : $this->course_detail_url($data['id'])); ?>"><?php echo !empty($progress['completed']) ? '继续学习' : '开始学习'; ?><span>→</span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    private function render_single_course($course_id) {
        if (!function_exists('tutor')) return '<p>课程系统暂不可用。</p>';
        $data = $this->service->get_course_directory($course_id, get_current_user_id());
        if (!$data) return '<p>课程不存在或课程系统暂不可用。</p>';

        $progress = $data['progress'];
        $has_access = !empty($data['access']);
        $is_logged_in = is_user_logged_in();
        $continue = $this->find_continue_lesson($data);

        ob_start(); ?>
        <div class="mathcourse-directory" data-course-id="<?php echo esc_attr($data['id']); ?>">
            <div class="mathcourse-directory__header">
                <div class="mathcourse-directory__heading">
                    <h1><?php echo esc_html($data['title']); ?></h1>
                    <?php if ($has_access) : ?><div class="mathcourse-directory__progress"><div class="mathcourse-directory__progress-text">学习进度 <?php echo esc_html($progress['percent']); ?>% <span>（<?php echo esc_html($progress['completed']); ?>/<?php echo esc_html($progress['total']); ?>）</span></div><div class="mathcourse-directory__progress-track"><span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span></div></div><?php endif; ?>
                </div>
                <?php if ($continue && !empty($continue['url'])) : ?><a class="mathcourse-directory__continue" href="<?php echo esc_url($continue['url']); ?>"><span><?php echo $has_access && !empty($progress['completed']) ? '继续学习' : '开始学习'; ?></span><strong><?php echo esc_html($continue['title']); ?></strong><span>→</span></a><?php endif; ?>
            </div>

            <?php if (!$has_access) : ?><div class="mathcourse-directory__notice"><strong><?php echo $is_logged_in ? '你还没有获得本课程的学习授权' : '本课程部分内容可免费试看'; ?></strong><span><?php echo $is_logged_in ? '标记“试看”的课时可以直接观看，其余课时需要授权。' : '标记“试看”的课时可以直接观看，其余课时需要登录并获得课程授权。'; ?></span></div><?php endif; ?>

            <?php foreach ($data['topics'] as $index => $topic) : ?>
                <section class="mathcourse-directory__topic">
                    <h2 class="mathcourse-directory__topic-title"><span><?php echo esc_html($index + 1); ?></span><?php echo esc_html($topic['title']); ?></h2>
                    <div class="mathcourse-directory__lessons">
                        <?php foreach ($topic['lessons'] as $lesson) : ?>
                            <?php if ($lesson['accessible']) : ?>
                                <a class="mathcourse-directory__lesson <?php echo $lesson['completed'] ? 'is-complete' : ''; ?>" href="<?php echo esc_url($lesson['url']); ?>"><span class="mathcourse-directory__status"><?php echo $lesson['completed'] ? '✓' : '○'; ?></span><span class="mathcourse-directory__lesson-title"><?php echo esc_html($lesson['title']); ?></span><?php if ($lesson['preview']) : ?><span class="mathcourse-directory__preview">试看</span><?php endif; ?></a>
                            <?php else : ?>
                                <button type="button" class="mathcourse-directory__lesson is-locked" data-mathcourse-lock="1" data-course-title="<?php echo esc_attr($data['title']); ?>"><span class="mathcourse-directory__status">🔒</span><span class="mathcourse-directory__lesson-title"><?php echo esc_html($lesson['title']); ?></span><span class="mathcourse-directory__locked">需授权</span></button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    private function find_continue_lesson($data) {
        if (empty($data['access'])) return null;
        $last = null;
        foreach ($data['topics'] as $topic) {
            foreach ($topic['lessons'] as $lesson) {
                if ($lesson['completed']) $last = $lesson;
                elseif (!$last && $lesson['accessible']) return $lesson;
            }
        }
        return $last;
    }

    private function grade_label($grade) {
        $labels = array('7'=>'七年级','8'=>'八年级','9'=>'九年级','10'=>'高一','11'=>'高二','12'=>'高三');
        return isset($labels[(string)$grade]) ? $labels[(string)$grade] : (string)$grade;
    }
}
