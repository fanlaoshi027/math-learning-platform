<?php
namespace MathCourse\Progress;
defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

class Progress_Ajax {
    public function __construct() {
        // Only lesson completion is persisted on the server.
        // Exact playback position is intentionally kept in the student's browser/device.
        add_action('wp_ajax_mathcourse_complete_lesson', array($this, 'complete'));
    }

    private function valid_lesson($lesson_id, $course_id, $user_id) {
        $adapter = new Adapter();
        $lesson = $adapter->get_lesson($lesson_id);
        if (!$lesson || 'publish' !== $lesson->post_status) return false;

        // Never trust the course_id supplied by the browser. Resolve the
        // actual parent course from Tutor's Lesson -> Topic -> Course chain.
        $actual_course_id = $adapter->get_lesson_course_id($lesson_id);
        if (!$actual_course_id || $actual_course_id !== absint($course_id)) return false;

        $course = $adapter->get_course($actual_course_id);
        if (!$course || 'publish' !== $course->post_status) return false;

        $access = new Access_Service();
        return $access->can_access_course($user_id, $actual_course_id) || $access->can_preview($actual_course_id, $lesson_id);
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
        update_user_meta($user_id,'mc_last_completed_course',$course_id);
        wp_send_json_success(array('lesson_id'=>$lesson_id,'course_id'=>$course_id,'completed'=>true,'progress'=>$progress->get_course_progress($course_id,$user_id)));
    }
}
