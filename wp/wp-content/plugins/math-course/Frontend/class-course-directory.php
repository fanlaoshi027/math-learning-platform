<?php
namespace MathCourse\Frontend;

defined('ABSPATH') || exit;

use MathCourse\Course\Course_Service;
use MathCourse\Tutor\Adapter;

class Course_Directory {
    private $service;
    private $tutor;

    public function __construct() {
        $this->service = new Course_Service();
        $this->tutor = new Adapter();
        add_shortcode('mathcourse_course_directory', array($this, 'render'));
        add_shortcode('mathcourse_course_center', array($this, 'render'));
        add_shortcode('mathcourse_learning_center', array($this, 'render_learning_center'));
    }

    public function render($atts = array()) {
        $atts = shortcode_atts(array('course_id' => 0, 'show_filters' => 1), $atts, 'mathcourse_course_directory');
        $course_id = absint($atts['course_id']);
        if (!$course_id && isset($_GET['course_id'])) $course_id = absint($_GET['course_id']);
        if ($course_id) {
            wp_safe_redirect($this->learning_player_url($course_id));
            exit;
        }
        return $this->render_course_center((bool) absint($atts['show_filters']));
    }

    private function course_center_url() {
        $page = get_page_by_path('course-center');
        return $page ? get_permalink($page) : home_url('/course-center/');
    }

    private function learning_player_url($course_id, $lesson_id = 0) {
        $page = get_page_by_path('learning');
        if (!$page) $page = get_page_by_path('xueyuan-denglu');
        $base = $page ? get_permalink($page) : home_url('/learning/');
        $args = array('course_id' => absint($course_id));
        if ($lesson_id) $args['lesson_id'] = absint($lesson_id);
        return add_query_arg($args, $base);
    }

    private function course_detail_url($course_id) {
        return $this->learning_player_url($course_id);
    }

    private function render_course_cover($data) {
        $cover = !empty($data['cover']) ? $data['cover'] : '';
        if ($cover) {
            return '<img src="' . esc_url($cover) . '" alt="' . esc_attr($data['title']) . '" loading="lazy">';
        }
        $style = !empty($data['cover_style']) ? sanitize_html_class($data['cover_style']) : 'solid';
        $color = !empty($data['cover_color']) ? sanitize_hex_color($data['cover_color']) : '#1769e0';
        if (!$color) $color = '#1769e0';
        $text = !empty($data['cover_text']) ? $data['cover_text'] : $data['title'];
        $classes = 'mathcourse-center__cover-placeholder mathcourse-center__cover-placeholder--' . $style;
        return '<span class="' . esc_attr($classes) . '" style="--mc-cover-color:' . esc_attr($color) . '"><span>' . esc_html($text) . '</span><b>∑</b></span>';
    }

    private function render_course_state($data) {
        $progress = isset($data['progress']) && is_array($data['progress']) ? $data['progress'] : array('completed' => 0, 'total' => 0, 'percent' => 0);
        $completed = max(0, absint($progress['completed'] ?? 0));
        $total = max(0, absint($progress['total'] ?? 0));
        $percent = max(0, min(100, absint($progress['percent'] ?? 0)));
        $has_access = !empty($data['access']);
        if (!$has_access) {
            return '<span class="mathcourse-center__state mathcourse-center__state--preview">试看</span>';
        }
        if ($total > 0 && $completed >= $total) {
            return '<span class="mathcourse-center__state mathcourse-center__state--complete">已完成</span>';
        }
        if ($completed > 0 || $percent > 0) {
            return '<span class="mathcourse-center__state mathcourse-center__state--learning">学习中</span>';
        }
        return '<span class="mathcourse-center__state">未开始</span>';
    }

    private function subject_key($data) {
        $title = (string) ($data['title'] ?? '');
        if (preg_match('/函数|一次函数|二次函数|反比例|图像|坐标/', $title)) return 'function';
        if (preg_match('/几何|三角|全等|相似|角|平行|四边形|圆/', $title)) return 'geometry';
        return 'algebra';
    }

    private function subject_meta($key) {
        $map = array(
            'algebra' => array('label' => '代数', 'color' => 'green', 'icon' => 'x²', 'formula' => 'x + 3 = 7'),
            'geometry' => array('label' => '几何', 'color' => 'blue', 'icon' => '△', 'formula' => '△ABC'),
            'function' => array('label' => '函数', 'color' => 'purple', 'icon' => '⌁', 'formula' => 'y = f(x)'),
        );
        return $map[$key] ?? $map['algebra'];
    }

    private function filter_url($args = array()) {
        $base = remove_query_arg(array('course_id', 'course_type', 'course_grade', 'course_subject', 'course_search'), $this->course_center_url());
        return add_query_arg(array_filter($args, static function($value) { return '' !== (string) $value; }), $base);
    }

    private function render_topic_card($data, $subject_key) {
        $meta = $this->subject_meta($subject_key);
        $detail_url = $this->course_detail_url($data['id']);
        $progress = isset($data['progress']) && is_array($data['progress']) ? $data['progress'] : array();
        $total = max(0, absint($progress['total'] ?? 0));
        $grade = !empty($data['grade']) ? $this->grade_label($data['grade']) : '初中数学';
        $state = $this->render_course_state($data);
        ob_start(); ?>
        <article class="mc-topic-card mc-topic-card--<?php echo esc_attr($meta['color']); ?>">
            <a href="<?php echo esc_url($detail_url); ?>" class="mc-topic-card__link">
                <div class="mc-topic-card__main">
                    <div class="mc-topic-card__title-wrap">
                        <h3><?php echo esc_html($data['title']); ?></h3>
                        <div class="mc-topic-card__meta"><span><?php echo esc_html($total); ?> 讲</span><b><?php echo esc_html($grade); ?></b></div>
                    </div>
                    <div class="mc-topic-card__formula" aria-hidden="true"><?php echo esc_html($meta['formula']); ?></div>
                </div>
                <?php echo $state; ?>
            </a>
        </article>
        <?php return ob_get_clean();
    }

    private function render_supplementary_card($data) {
        $detail_url = $this->course_detail_url($data['id']);
        $progress = isset($data['progress']) && is_array($data['progress']) ? $data['progress'] : array();
        $total = max(0, absint($progress['total'] ?? 0));
        $grade = !empty($data['grade']) ? $this->grade_label($data['grade']) : '初中数学';
        ob_start(); ?>
        <article class="mc-supplementary-card">
            <a href="<?php echo esc_url($detail_url); ?>" class="mc-supplementary-card__cover"><?php echo $this->render_course_cover($data); ?></a>
            <div class="mc-supplementary-card__body">
                <div class="mc-supplementary-card__tag">教辅配套</div>
                <h3><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($data['title']); ?></a></h3>
                <p><?php echo esc_html($grade); ?> · 系统课程配套讲解，进入课程查看全部内容。</p>
                <div class="mc-supplementary-card__bottom"><span><?php echo esc_html($grade); ?> · <?php echo esc_html($total); ?> 讲</span><a href="<?php echo esc_url($detail_url); ?>">▶ 立即听课</a></div>
            </div>
        </article>
        <?php return ob_get_clean();
    }

    private function render_course_center($show_filters = true) {
        if (!$this->tutor->is_available()) return '<p>课程系统暂不可用。</p>';
        $courses = $this->tutor->get_courses(false);
        $type_filter = isset($_GET['course_type']) ? sanitize_key(wp_unslash($_GET['course_type'])) : '';
        $grade_filter = isset($_GET['course_grade']) ? sanitize_key(wp_unslash($_GET['course_grade'])) : '';
        $subject_filter = isset($_GET['course_subject']) ? sanitize_key(wp_unslash($_GET['course_subject'])) : '';
        $search = isset($_GET['course_search']) ? sanitize_text_field(wp_unslash($_GET['course_search'])) : '';
        if (!in_array($type_filter, array('', 'topic', 'supplementary'), true)) $type_filter = '';
        if (!in_array($grade_filter, array('', '7', '8', '9'), true)) $grade_filter = '';
        if (!in_array($subject_filter, array('', 'algebra', 'geometry', 'function'), true)) $subject_filter = '';
        $filter_base = $this->course_center_url();
        $topic_groups = array('algebra' => array(), 'geometry' => array(), 'function' => array());
        $supplementary = array();
        $counts = array('algebra' => 0, 'geometry' => 0, 'function' => 0, 'supplementary' => 0);

        foreach ($courses as $course) {
            $data = $this->service->get_course_directory($course->ID, get_current_user_id());
            if (!$data) continue;
            $data_type = (string) ($data['type'] ?? 'topic');
            $data_grade = (string) ($data['grade'] ?? '');
            $subject = $this->subject_key($data);
            if ('supplementary' === $data_type) {
                $counts['supplementary']++;
            } else {
                $counts[$subject]++;
            }
            if ($type_filter && $type_filter !== $data_type) continue;
            if ($grade_filter && $grade_filter !== $data_grade) continue;
            if ($subject_filter && $subject_filter !== $subject) continue;
            if ($search && false === mb_stripos((string) $data['title'], $search)) continue;
            if ('supplementary' === $data_type) $supplementary[] = $data;
            else $topic_groups[$subject][] = $data;
        }

        ob_start(); ?>
        <div class="mc-course-center">
            <div class="mc-course-center__intro">
                <div>
                    <h1>课程中心 <span>初中数学专题体系</span></h1>
                    <p>按数学知识体系与教材辅导组织课程，点击任意专题直接进入学习页听课。</p>
                </div>
                <form class="mc-course-center__search" method="get" action="<?php echo esc_url($filter_base); ?>">
                    <?php if ($type_filter) : ?><input type="hidden" name="course_type" value="<?php echo esc_attr($type_filter); ?>"><?php endif; ?>
                    <?php if ($grade_filter) : ?><input type="hidden" name="course_grade" value="<?php echo esc_attr($grade_filter); ?>"><?php endif; ?>
                    <?php if ($subject_filter) : ?><input type="hidden" name="course_subject" value="<?php echo esc_attr($subject_filter); ?>"><?php endif; ?>
                    <input type="search" name="course_search" value="<?php echo esc_attr($search); ?>" placeholder="搜索课程或专题" aria-label="搜索课程或专题">
                    <button type="submit" aria-label="搜索">⌕</button>
                </form>
            </div>

            <?php if ($show_filters) : ?>
            <div class="mc-course-center__layout">
                <aside class="mc-course-center__sidebar">
                    <div class="mc-course-center__side-title">知识体系</div>
                    <nav class="mc-course-center__side-nav" aria-label="知识体系筛选">
                        <a class="<?php echo !$subject_filter && !$type_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->filter_url()); ?>"><i>⌁</i><span>全部知识体系</span><b><?php echo esc_html(array_sum(array($counts['algebra'], $counts['geometry'], $counts['function']))); ?></b></a>
                        <?php foreach (array('algebra','geometry','function') as $key) : $meta=$this->subject_meta($key); ?>
                            <a class="<?php echo $subject_filter === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->filter_url(array('course_subject'=>$key))); ?>"><i><?php echo esc_html($meta['icon']); ?></i><span><?php echo esc_html($meta['label']); ?></span><b><?php echo esc_html($counts[$key]); ?></b></a>
                        <?php endforeach; ?>
                        <a class="<?php echo 'supplementary' === $type_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->filter_url(array('course_type'=>'supplementary'))); ?>"><i>▣</i><span>配套课程</span><b><?php echo esc_html($counts['supplementary']); ?></b></a>
                    </nav>
                    <div class="mc-course-center__side-divider"></div>
                    <div class="mc-course-center__side-title">学习阶段</div>
                    <nav class="mc-course-center__grade-nav" aria-label="年级筛选">
                        <?php foreach (array(''=>'全部阶段','7'=>'七年级','8'=>'八年级','9'=>'九年级') as $grade=>$label) : ?>
                            <a class="<?php echo (string)$grade_filter === (string)$grade ? 'is-active' : ''; ?>" href="<?php echo esc_url($this->filter_url(array('course_grade'=>$grade))); ?>"><span><?php echo esc_html($label); ?></span><b>›</b></a>
                        <?php endforeach; ?>
                    </nav>
                </aside>

                <div class="mc-course-center__content">
                    <div class="mc-course-center__filterbar">
                        <span>当前筛选：</span>
                        <a class="is-active" href="<?php echo esc_url($this->filter_url(array('course_type'=>$type_filter,'course_grade'=>$grade_filter,'course_subject'=>$subject_filter))); ?>"><?php echo $subject_filter ? esc_html($this->subject_meta($subject_filter)['label']) : ($type_filter === 'supplementary' ? '配套课程' : '全部体系'); ?></a>
                        <?php if ($grade_filter) : ?><em><?php echo esc_html($this->grade_label($grade_filter)); ?></em><?php endif; ?>
                        <?php if ($search) : ?><em>搜索：<?php echo esc_html($search); ?></em><?php endif; ?>
                    </div>

                    <?php foreach ($topic_groups as $key=>$group) : if (empty($group)) continue; $meta=$this->subject_meta($key); ?>
                        <section class="mc-course-center__group mc-course-center__group--<?php echo esc_attr($meta['color']); ?>">
                            <header class="mc-course-center__group-head"><h2><i></i><?php echo esc_html($meta['label']); ?> · 共 <?php echo esc_html(count($group)); ?> 个专题</h2><a href="<?php echo esc_url($this->filter_url(array('course_subject'=>$key))); ?>">查看全部<?php echo esc_html($meta['label']); ?>专题 →</a></header>
                            <div class="mc-topic-grid">
                                <?php foreach ($group as $data) echo $this->render_topic_card($data, $key); ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <?php if ($supplementary) : ?>
                        <section class="mc-course-center__group mc-course-center__group--supplementary">
                            <header class="mc-course-center__group-head"><h2><i></i>配套课程 ·《大培优》与中考精讲</h2><span>共 <?php echo esc_html(count($supplementary)); ?> 门配套课程</span></header>
                            <div class="mc-supplementary-grid">
                                <?php foreach ($supplementary as $data) echo $this->render_supplementary_card($data); ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if (empty($topic_groups['algebra']) && empty($topic_groups['geometry']) && empty($topic_groups['function']) && empty($supplementary)) : ?>
                        <div class="mathcourse-directory__empty"><strong>没有找到符合条件的课程</strong><span>可以切换课程类型、年级或搜索关键词重新查看。</span></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else : ?>
                <div class="mc-course-center__content mc-course-center__content--full">
                    <div class="mc-topic-grid">
                        <?php foreach ($topic_groups as $key=>$group) foreach ($group as $data) echo $this->render_topic_card($data, $key); ?>
                        <?php foreach ($supplementary as $data) echo $this->render_supplementary_card($data); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    public function render_learning_center() {
        if (!is_user_logged_in()) return '<div class="mathcourse-learning-center__login"><strong>请先登录</strong><span>登录后查看你的课程和学习进度。</span></div>';
        $user_id=get_current_user_id();
        $courses=$this->tutor->get_courses(false);
        $cards=array();
        foreach($courses as $course){$data=$this->service->get_course_directory($course->ID,$user_id);if(!$data||empty($data['access']))continue;$progress=$data['progress']??array('completed'=>0,'total'=>0,'percent'=>0);$continue=$this->find_continue_lesson($data);$cards[]=array('data'=>$data,'progress'=>$progress,'continue'=>$continue);}
        return $this->render_learning_cards($cards);
    }

    private function render_learning_cards($cards) {
        ob_start();
        echo '<div class="mathcourse-learning-center">';
        echo '<div class="mathcourse-learning-center__head"><div><h1>学习中心</h1><p>继续你的课程学习，查看进度并快速进入上次学习位置。</p></div></div>';
        if (!$cards) {
            echo '<div class="mathcourse-directory__empty"><strong>暂时没有已授权课程</strong><span>请联系老师开通课程后再来学习。</span></div>';
        } else {
            echo '<div class="mathcourse-learning-center__grid">';
            foreach ($cards as $item) {
                $data = $item['data'];
                $progress = $item['progress'];
                $continue = $item['continue'];
                $url = $this->course_detail_url($data['id'], !empty($continue['id']) ? $continue['id'] : 0);
                $percent = max(0, min(100, absint($progress['percent'] ?? 0)));
                echo '<article class="mathcourse-learning-center__card">';
                echo '<a href="' . esc_url($url) . '" class="mathcourse-learning-center__cover">' . $this->render_course_cover($data) . '</a>';
                echo '<div class="mathcourse-learning-center__body">';
                echo '<h2><a href="' . esc_url($url) . '">' . esc_html($data['title']) . '</a></h2>';
                echo '<div class="mathcourse-learning-center__progress"><span style="width:' . esc_attr($percent) . '%"></span></div>';
                echo '<div class="mathcourse-learning-center__meta"><span>' . esc_html(absint($progress['completed'] ?? 0)) . ' / ' . esc_html(absint($progress['total'] ?? 0)) . ' 讲</span><b>' . esc_html($percent) . '%</b></div>';
                echo '<a class="mathcourse-learning-center__continue" href="' . esc_url($url) . '">' . ($continue ? '继续学习 →' : '开始学习 →') . '</a>';
                echo '</div></article>';
            }
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    private function grade_label($grade) {
        $map = array('7' => '七年级', '8' => '八年级', '9' => '九年级');
        return $map[(string)$grade] ?? '初中数学';
    }

    private function find_continue_lesson($data) {
        if (!empty($data['continue_lesson']) && is_array($data['continue_lesson'])) return $data['continue_lesson'];
        if (!empty($data['lessons']) && is_array($data['lessons'])) {
            foreach ($data['lessons'] as $lesson) {
                if (empty($lesson['completed'])) return $lesson;
            }
        }
        return !empty($data['lessons'][0]) && is_array($data['lessons'][0]) ? $data['lessons'][0] : array();
    }
}
