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
        $atts = shortcode_atts(array('course_id' => 0), $atts, 'mathcourse_course_directory');
        $course_id = absint($atts['course_id']);
        if (!$course_id && isset($_GET['course_id'])) $course_id = absint($_GET['course_id']);
        if ($course_id) {
            wp_safe_redirect($this->learning_player_url($course_id));
            exit;
        }
        return $this->render_course_center();
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

    private function render_course_center() {
        if (!$this->tutor->is_available()) return '<p>课程系统暂不可用。</p>';
        $courses = $this->tutor->get_courses(false);
        $type_filter = isset($_GET['course_type']) ? sanitize_key(wp_unslash($_GET['course_type'])) : '';
        $grade_filter = isset($_GET['course_grade']) ? sanitize_key(wp_unslash($_GET['course_grade'])) : '';
        if (!in_array($type_filter, array('', 'topic', 'supplementary'), true)) $type_filter = '';
        if (!in_array($grade_filter, array('', '7', '8', '9'), true)) $grade_filter = '';
        $filter_base = $this->course_center_url();

        ob_start(); ?>
        <div class="mathcourse-center">
            <div class="mc-course-filter" role="navigation" aria-label="课程筛选">
                <a class="<?php echo '' === $type_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url($filter_base); ?>">全部</a>
                <a class="<?php echo 'topic' === $type_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('course_type','topic',remove_query_arg(array('course_id','course_grade'),$filter_base))); ?>">专题课程</a>
                <a class="<?php echo 'supplementary' === $type_filter ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('course_type','supplementary',remove_query_arg(array('course_id','course_grade'),$filter_base))); ?>">教辅配套</a>
            </div>
            <div class="mc-course-grade-filter" aria-label="年级筛选">
                <span>年级</span>
                <?php foreach (array(''=>'全部','7'=>'七年级','8'=>'八年级','9'=>'九年级') as $grade=>$label) : ?>
                    <?php $url_args=array(); if($type_filter)$url_args['course_type']=$type_filter; if($grade)$url_args['course_grade']=$grade; ?>
                    <a class="<?php echo (string)$grade_filter === (string)$grade ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg($url_args,remove_query_arg(array('course_id','course_type','course_grade'),$filter_base))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <div class="mathcourse-center__grid">
                <?php $visible=0; foreach($courses as $course) : $data=$this->service->get_course_directory($course->ID,get_current_user_id()); if(!$data)continue; $data_type=(string)($data['type']??'topic'); $data_grade=(string)($data['grade']??''); if($type_filter&&$type_filter!==$data_type)continue; if($grade_filter&&$grade_filter!==$data_grade)continue; $visible++; $cover=!empty($data['cover'])?$data['cover']:''; $detail_url=$this->course_detail_url($data['id']); ?>
                    <article class="mathcourse-center__card" data-course-type="<?php echo esc_attr($data_type); ?>" data-course-grade="<?php echo esc_attr($data_grade); ?>">
                        <a class="mathcourse-center__cover" href="<?php echo esc_url($detail_url); ?>"><?php if($cover): ?><img src="<?php echo esc_url($cover); ?>" alt="<?php echo esc_attr($data['title']); ?>" loading="lazy"><?php else: ?><span class="mathcourse-center__cover-placeholder">数学课程</span><?php endif; ?></a>
                        <div class="mathcourse-center__body"><div class="mathcourse-center__meta"><?php if(!empty($data['grade'])):?><span><?php echo esc_html($this->grade_label($data['grade'])); ?></span><?php endif;?><?php if(!empty($data['type'])):?><span><?php echo esc_html('supplementary'===$data['type']?'教辅配套':'专题课程'); ?></span><?php endif;?></div><h2 class="mathcourse-center__title"><?php echo esc_html($data['title']); ?></h2><a class="mathcourse-center__button" href="<?php echo esc_url($detail_url); ?>">查看课程 <span>→</span></a></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if(!$visible): ?><div class="mathcourse-directory__empty"><strong>没有找到符合条件的课程</strong><span>可以切换课程类型或年级重新查看。</span></div><?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    public function render_learning_center() {
        if (!is_user_logged_in()) return '<div class="mathcourse-learning-center__login"><strong>请先登录</strong><span>登录后查看你的课程和学习进度。</span></div>';
        $user_id=get_current_user_id();
        $courses=$this->tutor->get_courses(false);
        $cards=array();
        foreach($courses as $course){$data=$this->service->get_course_directory($course->ID,$user_id);if(!$data||empty($data['access']))continue;$progress=$data['progress']??array('completed'=>0,'total'=>0,'percent'=>0);$continue=$this->find_continue_lesson($data);$cards[]=array('data'=>$data,'progress'=>$progress,'continue'=>$continue);}
        ob_start(); ?>
        <div class="mathcourse-learning-center"><div class="mathcourse-learning-center__heading"><h1>我的课程</h1><p>已授权课程与学习进度</p></div>
        <?php if(empty($cards)): ?><div class="mathcourse-learning-center__empty"><strong>还没有已授权课程</strong><span>获得课程授权后，会显示在这里。</span></div><?php else: ?><div class="mathcourse-learning-center__grid"><?php foreach($cards as $card):$data=$card['data'];$progress=$card['progress'];$continue=$card['continue'];?><article class="mathcourse-learning-center__card"><div class="mathcourse-learning-center__cover"><?php if(!empty($data['cover'])):?><img src="<?php echo esc_url($data['cover']); ?>" alt="<?php echo esc_attr($data['title']); ?>" loading="lazy"><?php else:?><span>数学课程</span><?php endif;?></div><div class="mathcourse-learning-center__body"><h2><?php echo esc_html($data['title']); ?></h2><div class="mathcourse-learning-center__progress-row"><span>学习进度</span><strong><?php echo esc_html($progress['completed']); ?> / <?php echo esc_html($progress['total']); ?></strong><em><?php echo esc_html($progress['percent']); ?>%</em></div><div class="mathcourse-learning-center__progress-track"><span style="width:<?php echo esc_attr($progress['percent']); ?>%"></span></div><a class="mathcourse-learning-center__button" href="<?php echo esc_url($continue&&!empty($continue['url'])?$continue['url']:$this->learning_player_url($data['id'])); ?>"><?php echo !empty($progress['completed'])?'继续学习':'开始学习'; ?><span>→</span></a></div></article><?php endforeach;?></div><?php endif;?></div>
        <?php return ob_get_clean();
    }

    private function find_continue_lesson($data) {
        if(empty($data['topics']))return null;
        if(empty($data['access'])){
            foreach($data['topics'] as $topic)foreach($topic['lessons'] as $lesson)if(!empty($lesson['accessible']))return $lesson;
            return null;
        }
        $last_completed_index=-1;$last_completed=null;$index=0;
        foreach($data['topics'] as $topic)foreach($topic['lessons'] as $lesson){if(!empty($lesson['completed'])){$last_completed_index=$index;$last_completed=$lesson;}$index++;}
        if($last_completed_index<0)foreach($data['topics'] as $topic)foreach($topic['lessons'] as $lesson)if(!empty($lesson['accessible']))return $lesson;
        $index=0;foreach($data['topics'] as $topic)foreach($topic['lessons'] as $lesson){if($index>$last_completed_index&&!empty($lesson['accessible'])&&empty($lesson['completed']))return $lesson;$index++;}
        return $last_completed;
    }

    private function grade_label($grade) {
        $labels=array('7'=>'七年级','8'=>'八年级','9'=>'九年级');return $labels[(string)$grade]??$grade;
    }
}