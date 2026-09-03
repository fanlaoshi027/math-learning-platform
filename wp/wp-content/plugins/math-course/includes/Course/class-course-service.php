<?php
namespace MathCourse\Course;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;
use MathCourse\Access\Access_Service;
use MathCourse\Progress\Progress_Service;
use MathCourse\Video\Video_Router;

class Course_Service {
    private $tutor;
    private $access;
    private $progress;
    private $video;

    public function __construct() {
        $this->tutor = new Adapter();
        $this->access = new Access_Service();
        $this->progress = new Progress_Service();
        $this->video = new Video_Router(false);
    }

    private function get_cover_data($course_id) {
        $course_id = absint($course_id);
        return array(
            'cover'       => (string) get_post_meta($course_id, '_mathcourse_cover', true),
            'cover_style' => (string) get_post_meta($course_id, '_mathcourse_cover_style', true),
            'cover_color' => (string) get_post_meta($course_id, '_mathcourse_cover_color', true),
            'cover_text'  => (string) get_post_meta($course_id, '_mathcourse_cover_text', true),
        );
    }

    public function get_course($course_id) {
        $course = $this->tutor->get_course($course_id);
        if (!$course) return null;
        $cover = $this->get_cover_data($course->ID);
        return array_merge(
            array(
                'id'    => (int) $course->ID,
                'title' => get_the_title($course),
                'type'  => get_post_meta($course->ID, '_mathcourse_type', true),
                'grade' => get_post_meta($course->ID, '_mathcourse_grade', true),
            ),
            $cover
        );
    }

    /**
     * 获取统一的视频地址。
     * _mathcourse_hls_url 是历史字段名，但实际允许保存 HLS 或 MP4；前端统一走受保护网关。
     */
    public function get_lesson_video($lesson_id, $user_id=0) {
        $lesson_id=absint($lesson_id); $user_id=absint($user_id); $lesson=$this->tutor->get_lesson($lesson_id);
        if(!$lesson)return null;
        $course_id=$this->tutor->get_lesson_course_id($lesson_id); if(!$course_id)return null;
        $watch_access=$this->access->can_watch_lesson($user_id,$course_id,$lesson_id);
        $preview=$this->access->can_preview($course_id,$lesson_id); $accessible=$watch_access||$preview;
        $video_id=$this->tutor->get_lesson_video_id($lesson_id);
        $source_url=trim((string)$this->tutor->get_lesson_hls_url($lesson_id));
        $has_source=(bool)$source_url;
        $media_type=$this->video->get_media_type($source_url);
        $protected_url=($accessible&&$has_source)?$this->video->get_protected_url($lesson_id):'';
        return array(
            'id'=>$lesson_id,
            'course_id'=>$course_id,
            'video_id'=>$video_id,
            'hls_url'=>($media_type==='m3u8')?$protected_url:'',
            'media_url'=>$protected_url,
            'media_type'=>$media_type,
            'preview'=>$preview,
            'accessible'=>$accessible
        );
    }

    private function lesson_learning_url($course_id,$lesson_id) {
        if(function_exists('mc_get_learning_page_url'))$base=mc_get_learning_page_url(); else {$page=get_page_by_path('learning',OBJECT,'page');$base=$page?get_permalink($page):home_url('/learning/');}
        return add_query_arg(array('course_id'=>absint($course_id),'lesson_id'=>absint($lesson_id)),$base);
    }

    public function get_course_directory($course_id,$user_id=0) {
        $course=$this->tutor->get_course($course_id); if(!$course)return null;
        $user_id=absint($user_id); $course_access=$user_id?$this->access->can_access_course($user_id,$course->ID):false; $course_free=$this->access->is_free_course($course->ID); $topics=array();
        foreach($this->tutor->get_topics($course->ID,false) as $topic) {
            $lessons=array();
            foreach($this->tutor->get_lessons($topic->ID,false) as $lesson) {
                $lesson_id=(int)$lesson->ID;
                $completed_lesson=$user_id?$this->progress->is_completed($user_id,$lesson_id):false;
                $preview=$this->access->can_preview($course->ID,$lesson_id); $watch_access=$this->access->can_watch_lesson($user_id,$course->ID,$lesson_id); $accessible=$watch_access||$preview;
                $source_url=trim((string)$this->tutor->get_lesson_hls_url($lesson_id));
                $media_type=$this->video->get_media_type($source_url);
                $has_source=(bool)$source_url;
                $protected_url=($accessible&&$has_source)?$this->video->get_protected_url($lesson_id):'';
                $lessons[]=array('id'=>$lesson_id,'title'=>get_the_title($lesson),'page_number'=>$this->tutor->get_lesson_page_number($lesson_id),'video_id'=>$this->tutor->get_lesson_video_id($lesson_id),'hls_url'=>($media_type==='m3u8')?$protected_url:'','media_url'=>$protected_url,'media_type'=>$media_type,'url'=>$accessible?$this->lesson_learning_url($course->ID,$lesson_id):'','completed'=>$completed_lesson,'preview'=>$preview,'accessible'=>$accessible);
            }
            $topics[]=array('id'=>(int)$topic->ID,'title'=>get_the_title($topic),'lessons'=>$lessons);
        }
        $progress=$user_id?$this->progress->get_course_progress($course->ID,$user_id):array('completed'=>0,'total'=>$this->count_lessons($topics),'percent'=>0,'last_lesson_id'=>0);
        $cover=$this->get_cover_data($course->ID);
        return array_merge(
            array('id'=>(int)$course->ID,'title'=>get_the_title($course),'type'=>get_post_meta($course->ID,'_mathcourse_type',true),'grade'=>get_post_meta($course->ID,'_mathcourse_grade',true),'is_free'=>$course_free,'topics'=>$topics,'access'=>$course_access,'progress'=>$progress),
            $cover
        );
    }

    private function count_lessons($topics) { $count=0; foreach($topics as $topic)$count+=count($topic['lessons']); return $count; }
}