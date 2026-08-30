<?php
namespace MathCourse\Progress;
defined('ABSPATH') || exit;
use MathCourse\Access\Access_Service;

class Progress_Service {
    private $access;
    public function __construct(){ $this->access=new Access_Service(); }
    public function get_course_progress($course_id,$user_id=0){$course_id=absint($course_id);$user_id=absint($user_id);if(!$course_id||!$user_id)return $this->empty_progress();return $this->calculate_progress($course_id,$user_id,false);}
    public function get_admin_course_progress($course_id,$user_id){$course_id=absint($course_id);$user_id=absint($user_id);if(!$course_id||!$user_id)return $this->empty_progress();return $this->calculate_progress($course_id,$user_id,true);}
    public function complete_lesson($user_id,$lesson_id,$allow_preview=false){
        $user_id=absint($user_id);$lesson_id=absint($lesson_id);if(!$user_id||!$lesson_id)return false;
        $course_id=$this->get_lesson_course_id($lesson_id);if(!$course_id)return false;
        $can_access=$this->access->can_access_course($user_id,$course_id);
        $is_preview=$this->access->can_preview($course_id,$lesson_id);
        if(!$can_access&&!($allow_preview&&$is_preview))return false;
        $completed=$this->get_completed_lessons($user_id);if(in_array($lesson_id,$completed,true))return true;
        $completed[]=$lesson_id;$completed=array_values(array_unique(array_map('intval',$completed)));$saved=update_user_meta($user_id,'mc_completed_lessons',$completed);if(false===$saved)return false;
        $times=$this->get_completed_times($user_id);$times[$lesson_id]=current_time('timestamp');update_user_meta($user_id,'mc_lesson_completed_time',$times);return true;
    }
    public function uncomplete_lesson($user_id,$lesson_id){$user_id=absint($user_id);$lesson_id=absint($lesson_id);if(!$user_id||!$lesson_id)return false;$completed=$this->get_completed_lessons($user_id);if(!in_array($lesson_id,$completed,true))return true;$completed=array_values(array_diff($completed,array($lesson_id)));$saved=update_user_meta($user_id,'mc_completed_lessons',array_values(array_map('intval',$completed)));if(false===$saved)return false;$times=$this->get_completed_times($user_id);unset($times[$lesson_id]);if(empty($times))delete_user_meta($user_id,'mc_lesson_completed_time');else update_user_meta($user_id,'mc_lesson_completed_time',$times);return true;}
    public function is_completed($user_id,$lesson_id){return in_array(absint($lesson_id),$this->get_completed_lessons($user_id),true);}
    public function get_last_completed_lesson($user_id,$course_id=0){$completed=$this->get_completed_lessons($user_id);if(empty($completed))return 0;if(!$course_id)return $this->find_latest_lesson($completed,$this->get_completed_times($user_id));$ids=array_map('intval',wp_list_pluck($this->get_course_lessons(absint($course_id),false),'ID'));$inside=array();foreach($completed as $id)if(in_array((int)$id,$ids,true))$inside[]=(int)$id;if(empty($inside))return 0;return $this->find_latest_lesson($inside,$this->get_completed_times($user_id));}
    public function get_lesson_completed_time($user_id,$lesson_id){$times=$this->get_completed_times($user_id);$lesson_id=absint($lesson_id);return isset($times[$lesson_id])?absint($times[$lesson_id]):0;}
    private function calculate_progress($course_id,$user_id,$include_unpublished=false){$lessons=$this->get_course_lessons($course_id,$include_unpublished);$completed_lessons=$this->get_completed_lessons($user_id);$completed_times=$this->get_completed_times($user_id);$total=count($lessons);$completed=0;$last_lesson_id=0;$last_time=0;foreach($lessons as $lesson){$id=(int)$lesson->ID;if(in_array($id,$completed_lessons,true)){$completed++;$time=isset($completed_times[$id])?absint($completed_times[$id]):0;if($time>=$last_time){$last_time=$time;$last_lesson_id=$id;}}}return array('completed'=>$completed,'total'=>$total,'percent'=>$total?round(($completed/$total)*100):0,'last_lesson_id'=>$last_lesson_id,'last_time'=>$last_time);}
    private function get_completed_lessons($user_id){$data=get_user_meta(absint($user_id),'mc_completed_lessons',true);return is_array($data)?array_values(array_unique(array_map('intval',$data))):array();}
    private function get_completed_times($user_id){$data=get_user_meta(absint($user_id),'mc_lesson_completed_time',true);if(!is_array($data))return array();$times=array();foreach($data as $id=>$timestamp){$id=absint($id);$timestamp=absint($timestamp);if($id&&$timestamp)$times[$id]=$timestamp;}return $times;}
    private function find_latest_lesson($ids,$times){$latest=0;$latest_time=-1;foreach($ids as $id){$id=absint($id);$time=isset($times[$id])?absint($times[$id]):-1;if($time>=$latest_time){$latest_time=$time;$latest=$id;}}return $latest;}
    private function empty_progress(){return array('completed'=>0,'total'=>0,'percent'=>0,'last_lesson_id'=>0,'last_time'=>0);}
    private function get_course_lessons($course_id,$include_unpublished=false){$course_id=absint($course_id);if(!$course_id||!function_exists('tutor')||empty(tutor()->lesson_post_type))return array();$topics=get_posts(array('post_type'=>'topics','post_parent'=>$course_id,'post_status'=>array('publish','draft','private'),'posts_per_page'=>-1,'orderby'=>array('menu_order'=>'ASC','date'=>'ASC')));$lessons=array();foreach($topics as $topic){$items=get_posts(array('post_type'=>tutor()->lesson_post_type,'post_parent'=>$topic->ID,'post_status'=>$include_unpublished?array('publish','draft','private'):array('publish'),'posts_per_page'=>-1,'orderby'=>array('menu_order'=>'ASC','date'=>'ASC'));foreach($items as $lesson)$lessons[]=$lesson;}return $lessons;}
    private function get_lesson_course_id($lesson_id){$lesson_id=absint($lesson_id);if(!$lesson_id||!function_exists('tutor')||empty(tutor()->lesson_post_type))return 0;$lesson=get_post($lesson_id);if(!$lesson||tutor()->lesson_post_type!==$lesson->post_type)return 0;$topic=get_post($lesson->post_parent);if(!$topic||'topics'!==$topic->post_type)return 0;$course=get_post($topic->post_parent);if(!$course||tutor()->course_post_type!==$course->post_type)return 0;return(int)$course->ID;}
}
