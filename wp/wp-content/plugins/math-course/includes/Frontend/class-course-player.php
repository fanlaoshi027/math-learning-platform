<?php
namespace MathCourse\Frontend;
defined('ABSPATH') || exit;
use MathCourse\Course\Course_Service;

class Course_Player {
    private $service;
    public function __construct() { $this->service = new Course_Service(); add_shortcode('mathcourse_course_player', array($this, 'render')); }
    private function assets() {
        wp_enqueue_style('mathcourse-course-player', MATHCOURSE_URL . 'assets/course-player.css', array(), MATHCOURSE_VERSION);
        wp_enqueue_style('mathcourse-course-outline-accordion', MATHCOURSE_URL . 'assets/css/course-outline-accordion.css', array('mathcourse-course-player'), MATHCOURSE_VERSION);
        wp_enqueue_style('mathcourse-lock-modal-ui', MATHCOURSE_URL . 'assets/lock-modal-ui.css', array('mathcourse-course-player'), MATHCOURSE_VERSION);
        wp_enqueue_style('mathcourse-learning-layout-v3', MATHCOURSE_URL . 'assets/css/learning-layout-v3.css', array('mathcourse-course-outline-accordion'), MATHCOURSE_VERSION);
        wp_enqueue_script('mathcourse-course-outline-accordion', MATHCOURSE_URL . 'assets/js/course-outline-accordion.js', array(), MATHCOURSE_VERSION, true);
        wp_enqueue_script('mathcourse-course-directory-lock-modal', MATHCOURSE_URL . 'assets/course-directory.js', array(), MATHCOURSE_VERSION, true);
    }
    public function render($atts=array()) {
        $atts=shortcode_atts(array('course_id'=>0),$atts,'mathcourse_course_player');
        $course_id=absint($atts['course_id']); if(!$course_id && isset($_GET['course_id'])) $course_id=absint($_GET['course_id']);
        if(!$course_id) return '<p>课程不存在。</p>';
        $data=$this->service->get_course_directory($course_id,get_current_user_id()); if(!$data) return '<p>课程不存在或课程系统暂不可用。</p>';
        $selected=isset($_GET['lesson_id'])?absint($_GET['lesson_id']):0; $current=$this->find_lesson($data,$selected); if(!$current) $current=$this->first_accessible($data);
        $progress=isset($data['progress'])?$data['progress']:array('completed'=>0,'total'=>0,'percent'=>0);
        $page=get_page_by_path('course-center'); $base=$page?get_permalink($page):home_url('/course-center/');
        $navigation=$this->lesson_navigation($data,$current,$course_id); $this->assets();
        ob_start(); ?>
        <div class="mc-course-player" data-course-id="<?php echo esc_attr($course_id); ?>">
            <div class="mc-course-player__workspace">
                <aside class="mc-course-player__sidebar">
                    <div class="mc-course-player__progress-line" aria-label="学习进度">
                        <span>学习进度</span>
                        <strong><?php echo esc_html($progress['percent']); ?>%</strong>
                    </div>
                    <div class="mc-course-player__topics">
                    <?php foreach($data['topics'] as $index=>$topic): $topic_open=$current&&$this->topic_contains_lesson($topic,$current['id']); ?><div class="mc-course-player__topic<?php echo $topic_open?' is-open':''; ?>"><h3 class="mc-course-player__topic-toggle" tabindex="0" role="button" aria-expanded="<?php echo $topic_open?'true':'false'; ?>"><span class="mc-course-player__topic-chevron" aria-hidden="true">›</span><span><?php echo esc_html($index+1); ?>. <?php echo esc_html($topic['title']); ?></span></h3><div class="mc-course-player__lessons"<?php echo $topic_open?'':' hidden'; ?>>
                    <?php foreach($topic['lessons'] as $lesson): $active=$current&&(int)$current['id']===(int)$lesson['id']; $url=add_query_arg(array('course_id'=>$course_id,'lesson_id'=>$lesson['id']),$base); if($lesson['accessible']): ?><a class="mc-course-player__item <?php echo $active?'is-active ':''; echo $lesson['completed']?'is-complete':''; ?>" href="<?php echo esc_url($url); ?>"><span class="mc-course-player__check"><?php echo $lesson['completed']?'✓':($active?'▶':'○'); ?></span><span><?php echo esc_html($lesson['title']); ?></span><?php if($lesson['preview']): ?><small>试看</small><?php endif; ?></a><?php else: ?><div class="mc-course-player__item is-locked" data-mathcourse-lock="1" role="button" tabindex="0"><span class="mc-course-player__check">🔒</span><span><?php echo esc_html($lesson['title']); ?></span><small>需授权</small></div><?php endif; endforeach; ?>
                    </div></div><?php endforeach; ?>
                    </div>
                    <div class="mc-course-player__more"><span>•••</span><strong>更多</strong></div>
                </aside>

                <main class="mc-course-player__main">
                    <?php if($current && $current['accessible']): ?>
                        <div class="mc-course-player__video"><?php if(shortcode_exists('mathcourse_video')) echo do_shortcode('[mathcourse_video lesson_id="'.esc_attr($current['id']).'" course_id="'.esc_attr($course_id).'"]'); ?></div>
                        <div class="mc-course-player__lesson-head"><div><span>当前课时</span><h2><?php echo esc_html($current['title']); ?></h2></div><?php if($current['preview']): ?><em>试看</em><?php endif; ?></div>
                        <div class="mc-course-player__overview"><span class="is-active">▱&nbsp; 概览</span></div>
                        <nav class="mc-course-player__navigation" aria-label="课时导航">
                            <?php if($navigation['previous']): ?><a class="mc-course-player__nav-button" href="<?php echo esc_url($navigation['previous']['url']); ?>"<?php echo empty($navigation['previous']['accessible'])?' data-mathcourse-lock="1"':''; ?>><span>‹</span><small>上一课</small><strong><?php echo esc_html($navigation['previous']['title']); ?></strong></a><?php else: ?><span class="mc-course-player__nav-button is-disabled"><span>‹</span><small>上一课</small><strong>已经是第一课</strong></span><?php endif; ?>
                            <?php if($navigation['next']): ?><a class="mc-course-player__nav-button is-next" href="<?php echo esc_url($navigation['next']['url']); ?>"<?php echo empty($navigation['next']['accessible'])?' data-mathcourse-lock="1"':''; ?>><small>下一课</small><strong><?php echo esc_html($navigation['next']['title']); ?></strong><span>›</span></a><?php else: ?><span class="mc-course-player__nav-button is-disabled is-next"><small>下一课</small><strong>已经是最后一课</strong><span>›</span></span><?php endif; ?>
                        </nav>
                        <div class="mc-course-player__completion" hidden aria-live="polite">
                            <div><strong>✓ 本课已完成</strong><span class="mc-course-player__completion-text">学习进度已更新</span></div>
                            <?php if($navigation['next']): ?><a class="mc-course-player__completion-next" href="<?php echo esc_url($navigation['next']['url']); ?>">下一课：<?php echo esc_html($navigation['next']['title']); ?><span>→</span></a><?php else: ?><a class="mc-course-player__completion-next" href="<?php echo esc_url($base); ?>">返回课程<span>→</span></a><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mc-course-player__empty"><strong>请选择可观看的课时</strong><span>课程列表中，标记“试看”的课时可以直接观看。</span></div>
                    <?php endif; ?>
                </main>
            </div>
        </div><?php return ob_get_clean();
    }
    private function topic_contains_lesson($topic,$lesson_id){ foreach($topic['lessons'] as $lesson) if((int)$lesson['id']===(int)$lesson_id) return true; return false; }
    private function find_lesson($data,$id){ if(!$id)return null; foreach($data['topics'] as $topic)foreach($topic['lessons'] as $lesson)if((int)$lesson['id']===$id&&!empty($lesson['accessible']))return $lesson; return null; }
    private function first_accessible($data){ foreach($data['topics'] as $topic)foreach($topic['lessons'] as $lesson)if(!empty($lesson['accessible']))return $lesson; return null; }
    private function lesson_navigation($data,$current,$course_id){
        $result=array('previous'=>null,'next'=>null); if(!$current)return $result;
        $lessons=array(); foreach($data['topics'] as $topic)foreach($topic['lessons'] as $lesson)$lessons[]=$lesson;
        $current_index=null; foreach($lessons as $index=>$lesson)if((int)$lesson['id']===(int)$current['id']){$current_index=$index;break;}
        if(null===$current_index)return $result;
        $page=get_page_by_path('course-center'); $base=$page?get_permalink($page):home_url('/course-center/'); $course_id=absint($course_id);
        if($current_index>0){$lesson=$lessons[$current_index-1];$result['previous']=array('id'=>$lesson['id'],'title'=>$lesson['title'],'accessible'=>!empty($lesson['accessible']),'url'=>add_query_arg(array('course_id'=>$course_id,'lesson_id'=>$lesson['id']),$base));}
        if($current_index<count($lessons)-1){$lesson=$lessons[$current_index+1];$result['next']=array('id'=>$lesson['id'],'title'=>$lesson['title'],'accessible'=>!empty($lesson['accessible']),'url'=>add_query_arg(array('course_id'=>$course_id,'lesson_id'=>$lesson['id']),$base));}
        return $result;
    }
}