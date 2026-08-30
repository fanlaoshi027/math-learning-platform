<?php
namespace MathCourse\Progress;
defined('ABSPATH') || exit;
use MathCourse\Access\Access_Service;

class Progress_Ajax {
    public function __construct() {
        add_action('wp_ajax_mathcourse_complete_lesson', array($this, 'complete'));
        add_action('wp_ajax_mathcourse_get_lesson_position', array($this, 'get_position'));
        add_action('wp_ajax_mathcourse_save_lesson_position', array($this, 'save_position'));
    }

    private function valid_lesson($lesson_id, $course_id, $user_id) {
        if (!$lesson_id || !$course_id || !function_exists('tutor')) return false;
        $lesson = get_post($lesson_id);
        if (!$lesson || tutor()->lesson_post_type !== $lesson->post_type || 'publish' !== $lesson->post_status) return false;
        $topic = get_post($lesson->post_parent);
        if (!$topic || 'topics' !== $topic->post_type || (int) $topic->post_parent !== $course_id) return false;
        $course = get_post($course_id);
        if (!$course || tutor()->course_post_type !== $course->post_type || 'publish' !== $course->post_status) return false;
        $access = new Access_Service();
        if (!$access->can_access_course($user_id, $course_id) && !$access->can_preview($course_id, $lesson_id)) return false;
        return true;
    }

    public function complete() {
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'login required'),403);
        check_ajax_referer('mathcourse_progress_nonce','nonce');
        $lesson_id=isset($_POST['lesson_id'])?absint($_POST['lesson_id']):0;
        $course_id=isset($_POST['course_id'])?absint($_POST['course_id']):0;
        $user_id=get_current_user_id();
        if (!$this->valid_lesson($lesson_id,$course_id,$user_id)) wp_send_json_error(array('message'=>'invalid lesson, course, or access'),403);
        $progress=new Progress_Service();
        if (!$progress->complete_lesson($user_id,$lesson_id,true)) wp_send_json_error(array('message'=>'unable to save progress'),500);
        $this->delete_position($user_id,$lesson_id);
        update_user_meta($user_id,'mc_last_completed_course',$course_id);
        wp_send_json_success(array('lesson_id'=>$lesson_id,'course_id'=>$course_id,'completed'=>true,'progress'=>$progress->get_course_progress($course_id,$user_id)));
    }

    public function get_position() {
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'login required'),403);
        check_ajax_referer('mathcourse_progress_nonce','nonce');
        $lesson_id=isset($_POST['lesson_id'])?absint($_POST['lesson_id']):0;
        $course_id=isset($_POST['course_id'])?absint($_POST['course_id']):0;
        $user_id=get_current_user_id();
        if (!$this->valid_lesson($lesson_id,$course_id,$user_id)) wp_send_json_error(array('message'=>'invalid lesson, course, or access'),403);
        $positions=get_user_meta($user_id,'mc_lesson_positions',true);
        $position=is_array($positions)&&isset($positions[$lesson_id])?$positions[$lesson_id]:array();
        $seconds=isset($position['seconds'])?max(0,(int)$position['seconds']):0;
        $updated=isset($position['updated'])?max(0,(int)$position['updated']):0;
        if ((new Progress_Service())->is_completed($user_id,$lesson_id)) $seconds=0;
        wp_send_json_success(array('lesson_id'=>$lesson_id,'course_id'=>$course_id,'seconds'=>$seconds,'updated'=>$updated));
    }

    public function save_position() {
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'login required'),403);
        check_ajax_referer('mathcourse_progress_nonce','nonce');
        $lesson_id=isset($_POST['lesson_id'])?absint($_POST['lesson_id']):0;
        $course_id=isset($_POST['course_id'])?absint($_POST['course_id']):0;
        $seconds=isset($_POST['seconds'])?absint($_POST['seconds']):0;
        $user_id=get_current_user_id();
        if (!$this->valid_lesson($lesson_id,$course_id,$user_id)) wp_send_json_error(array('message'=>'invalid lesson, course, or access'),403);
        $progress=new Progress_Service();
        if ($progress->is_completed($user_id,$lesson_id)) {
            $this->delete_position($user_id,$lesson_id);
            wp_send_json_success(array('saved'=>false,'completed'=>true));
        }
        $positions=get_user_meta($user_id,'mc_lesson_positions',true);
        $positions=is_array($positions)?$positions:array();
        $positions[$lesson_id]=array('seconds'=>min($seconds,86400),'updated'=>current_time('timestamp'));
        if (false===update_user_meta($user_id,'mc_lesson_positions',$positions)) wp_send_json_error(array('message'=>'unable to save position'),500);
        wp_send_json_success(array('saved'=>true,'lesson_id'=>$lesson_id,'seconds'=>$positions[$lesson_id]['seconds']));
    }

    private function delete_position($user_id,$lesson_id) {
        $positions=get_user_meta($user_id,'mc_lesson_positions',true);
        if (!is_array($positions)||!array_key_exists($lesson_id,$positions)) return;
        unset($positions[$lesson_id]);
        if (empty($positions)) delete_user_meta($user_id,'mc_lesson_positions');
        else update_user_meta($user_id,'mc_lesson_positions',$positions);
    }
}
