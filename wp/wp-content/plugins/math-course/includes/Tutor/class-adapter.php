<?php
namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * MathCourse 与 Tutor LMS 的统一数据适配层。
 * 不修改 Tutor LMS 源码。
 */
class Adapter {
    public function is_available() { return function_exists('tutor'); }
    public function get_course_post_type() { if (!$this->is_available()) return 'courses'; $post_type = !empty(tutor()->course_post_type) ? tutor()->course_post_type : 'courses'; return sanitize_key($post_type); }
    public function get_lesson_post_type() { if (!$this->is_available()) return 'lesson'; $post_type = !empty(tutor()->lesson_post_type) ? tutor()->lesson_post_type : 'lesson'; return sanitize_key($post_type); }
    public function get_topic_post_type() { return 'topics'; }
    public function is_course_post_type($post_type) { return $this->get_course_post_type() === $post_type; }
    public function is_lesson_post_type($post_type) { return $this->get_lesson_post_type() === $post_type; }
    public function get_courses($include_unpublished = true, $limit = -1) { $post_type=$this->get_course_post_type(); $status=$include_unpublished?array('publish','draft','private','pending','future'):array('publish'); $limit=(int)$limit; if($limit===0)return array(); return get_posts(array('post_type'=>$post_type,'post_status'=>$status,'posts_per_page'=>$limit,'orderby'=>'date','order'=>'DESC','no_found_rows'=>true)); }
    public function get_course($course_id) { $course=absint($course_id)?get_post(absint($course_id)):null; return ($course&&$this->is_course_post_type($course->post_type))?$course:null; }
    public function get_topic($topic_id) { $topic=absint($topic_id)?get_post(absint($topic_id)):null; return ($topic&&$this->get_topic_post_type()===$topic->post_type)?$topic:null; }
    public function get_topics($course_id,$include_unpublished=true) { $status=$include_unpublished?array('publish','draft','private','pending','future'):array('publish'); return get_posts(array('post_type'=>$this->get_topic_post_type(),'post_parent'=>absint($course_id),'post_status'=>$status,'posts_per_page'=>-1,'orderby'=>'menu_order','order'=>'ASC')); }
    public function get_lessons($topic_id,$include_unpublished=true) { $status=$include_unpublished?array('publish','draft','private','pending','future'):array('publish'); return get_posts(array('post_type'=>$this->get_lesson_post_type(),'post_parent'=>absint($topic_id),'post_status'=>$status,'posts_per_page'=>-1,'orderby'=>'menu_order','order'=>'ASC')); }
    public function get_course_lesson_count($course_id) { $count=0; foreach($this->get_topics($course_id,false) as $topic)$count+=count($this->get_lessons($topic->ID,false)); return $count; }
    public function get_course_lessons($course_id,$include_unpublished=false) { $lessons=array(); foreach($this->get_topics($course_id,$include_unpublished) as $topic)foreach($this->get_lessons($topic->ID,$include_unpublished) as $lesson)$lessons[]=$lesson; return $lessons; }
    public function get_lesson($lesson_id) { $lesson=absint($lesson_id)?get_post(absint($lesson_id)):null; return ($lesson&&$this->is_lesson_post_type($lesson->post_type))?$lesson:null; }
    public function get_lesson_course_id($lesson_id) { $lesson=$this->get_lesson($lesson_id); if(!$lesson)return 0; $topic=$this->get_topic($lesson->post_parent); if(!$topic)return 0; $course=$this->get_course($topic->post_parent); return $course?(int)$course->ID:0; }
    public function get_lesson_page_number($lesson_id) { $value=get_post_meta(absint($lesson_id),'_mathcourse_page_number',true); if($value==='')$value=get_post_meta(absint($lesson_id),'_mathcourse_page',true); return sanitize_text_field($value); }
    public function get_lesson_video_id($lesson_id) { $value=get_post_meta(absint($lesson_id),'_mathcourse_video_id',true); if($value==='')$value=get_post_meta(absint($lesson_id),'_mathcourse_video',true); return sanitize_text_field($value); }
    public function get_lesson_hls_url($lesson_id) { $value=trim((string)get_post_meta(absint($lesson_id),'_mathcourse_hls_url',true)); if($value==='')return ''; if(preg_match('#^https?://#i',$value))return esc_url_raw($value); if(strpos($value,'//')===0)return esc_url_raw((is_ssl()?'https:':'http:').$value); return esc_url_raw(home_url('/'.ltrim($value,'/'))); }
    public function is_preview_lesson($lesson_id) { $native=get_post_meta(absint($lesson_id),'_is_preview',true); if($native==='yes'||$native==='1')return true; return get_post_meta(absint($lesson_id),'_mathcourse_preview',true)==='yes'; }
    public function set_lesson_preview($lesson_id,$enabled) { $value=$enabled?'yes':'no'; update_post_meta(absint($lesson_id),'_is_preview',$value); update_post_meta(absint($lesson_id),'_mathcourse_preview',$value); return true; }
    public function render_lesson_video($lesson_id) { $lesson=$this->get_lesson($lesson_id); if(!$lesson||!function_exists('tutor_lesson_video')||!function_exists('tutor_utils'))return ''; if(get_post_meta($lesson->ID,'_video',true)==='')return ''; global $post; $previous_post=$post; $post=$lesson; setup_postdata($lesson); try{$html=tutor_lesson_video(false);}finally{wp_reset_postdata();$post=$previous_post;} return is_string($html)?$html:''; }
    public function get_course_progress($course_id,$user_id=0) { $user_id=$user_id?absint($user_id):get_current_user_id(); $total=0;$completed=0; foreach($this->get_course_lessons($course_id,false) as $lesson){$total++;if($this->is_lesson_completed($lesson->ID,$user_id))$completed++;} return array('completed'=>$completed,'total'=>$total,'percent'=>$total?round(($completed/$total)*100):0); }

    public function is_lesson_completed($lesson_id,$user_id=0) {
        $user_id=$user_id?absint($user_id):get_current_user_id();
        $lesson_id=absint($lesson_id);
        if(!$user_id||!$lesson_id)return false;
        $data=get_user_meta($user_id,'mc_completed_lessons',true);
        if(is_array($data)&&in_array($lesson_id,array_map('intval',$data),true))return true;
        return function_exists('tutor_utils')?(bool)tutor_utils()->is_completed_lesson($lesson_id,$user_id):false;
    }
}