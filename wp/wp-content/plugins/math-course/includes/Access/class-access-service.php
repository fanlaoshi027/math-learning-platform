<?php
namespace MathCourse\Access;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

class Access_Service {
    private static $access_cache = array();

    private function table() { global $wpdb; return $wpdb->prefix . 'mathcourse_access'; }
    public function has_access($user_id, $course_id) { return !empty($this->get_access_info($user_id, $course_id)['access']); }
    public function is_free_course($course_id) { return false; }

    public function can_access_course($user_id, $course_id) {
        $user_id=absint($user_id); $course_id=absint($course_id);
        if(!$user_id||!$course_id)return false;
        if(user_can($user_id,'manage_options'))return true;
        return $this->has_access($user_id,$course_id);
    }

    public function get_access_info($user_id,$course_id) {
        $user_id=absint($user_id); $course_id=absint($course_id);
        $result=array('access'=>false,'status'=>'none','expires_at'=>null);
        if(!$user_id||!$course_id)return $result;
        $cache_key=$user_id.':'.$course_id;
        if(isset(self::$access_cache[$cache_key]))return self::$access_cache[$cache_key];
        if(user_can($user_id,'manage_options')){
            $result['access']=true; $result['status']='admin'; self::$access_cache[$cache_key]=$result; return $result;
        }
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT status, expires_at FROM {$this->table()} WHERE user_id=%d AND course_id=%d LIMIT 1",$user_id,$course_id));
        if(!$row){self::$access_cache[$cache_key]=$result;return $result;}
        $result['status']=$row->status; $result['expires_at']=$row->expires_at;
        if('active'!==$row->status){self::$access_cache[$cache_key]=$result;return $result;}
        if(!empty($row->expires_at)&&strtotime($row->expires_at)<=current_time('timestamp')){$result['status']='expired';self::$access_cache[$cache_key]=$result;return $result;}
        $result['access']=true; self::$access_cache[$cache_key]=$result; return $result;
    }

    public function grant($user_id,$course_id,$expires_at=null) {
        $user_id=absint($user_id);$course_id=absint($course_id);if(!$user_id||!$course_id)return false;global $wpdb;
        $saved=false!==$wpdb->replace($this->table(),array('user_id'=>$user_id,'course_id'=>$course_id,'status'=>'active','granted_at'=>current_time('mysql'),'expires_at'=>$expires_at?sanitize_text_field($expires_at):null),array('%d','%d','%s','%s','%s'));
        if($saved)unset(self::$access_cache[$user_id.':'.$course_id]);return $saved;
    }
    public function revoke($user_id,$course_id) {
        $user_id=absint($user_id);$course_id=absint($course_id);global $wpdb;
        $saved=false!==$wpdb->update($this->table(),array('status'=>'revoked'),array('user_id'=>$user_id,'course_id'=>$course_id),array('%s'),array('%d','%d'));
        if($saved)unset(self::$access_cache[$user_id.':'.$course_id]);return $saved;
    }
    public function get_user_courses($user_id) {
        $user_id=absint($user_id);if(!$user_id)return array();global $wpdb;$now=current_time('mysql');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table()} WHERE user_id=%d AND status='active' AND (expires_at IS NULL OR expires_at='' OR expires_at > %s) ORDER BY granted_at DESC",$user_id,$now));return $rows?:array();
    }

    public function can_preview($course_id,$lesson_id) {
        $course_id=absint($course_id);$lesson_id=absint($lesson_id);if(!$course_id||!$lesson_id)return false;$adapter=new Adapter();if(!$adapter->is_available())return false;
        $lesson=$adapter->get_lesson($lesson_id);$course=$adapter->get_course($course_id);
        if(!$lesson||'publish'!==$lesson->post_status||!$course||'publish'!==$course->post_status)return false;
        if($adapter->get_lesson_course_id($lesson_id)!==$course_id)return false;return $adapter->is_preview_lesson($lesson_id);
    }

    /** 最终播放权限：有效授权学员可观看；没有该课程授权记录的游客可观看明确允许的试看课时。 */
    public function can_watch_lesson($user_id,$course_id,$lesson_id) {
        $user_id=absint($user_id);$course_id=absint($course_id);$lesson_id=absint($lesson_id);if(!$course_id||!$lesson_id)return false;$adapter=new Adapter();if(!$adapter->is_available())return false;
        $lesson=$adapter->get_lesson($lesson_id);$course=$adapter->get_course($course_id);
        if(!$lesson||'publish'!==$lesson->post_status||!$course||'publish'!==$course->post_status)return false;
        if($adapter->get_lesson_course_id($lesson_id)!==$course_id)return false;
        if($user_id){
            $info=$this->get_access_info($user_id,$course_id);
            if('admin'===$info['status']||!empty($info['access']))return true;
            // 已存在但已撤销/过期的授权不能回退为游客试看。
            if('none'!==$info['status'])return false;
        }
        return $this->can_preview($course_id,$lesson_id);
    }
}