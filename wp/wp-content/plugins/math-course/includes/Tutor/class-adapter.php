<?php
namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * MathCourse 与 Tutor LMS 的统一数据适配层。
 * 不修改 Tutor LMS 源码。
 */
class Adapter {

    public function is_available() {
        return function_exists('tutor');
    }

    /** Tutor 课程 Post Type。业务层不得自行猜测。 */
    public function get_course_post_type() {
        return $this->is_available() && !empty(tutor()->course_post_type) ? tutor()->course_post_type : '';
    }

    /** Tutor 课时 Post Type。业务层不得自行猜测。 */
    public function get_lesson_post_type() {
        return $this->is_available() && !empty(tutor()->lesson_post_type) ? tutor()->lesson_post_type : '';
    }

    /** MathCourse/Tutor 课程所属的 Topic Post Type。 */
    public function get_topic_post_type() {
        return 'topics';
    }

    public function get_courses($include_unpublished = true, $limit = -1) {
        if (!$this->is_available()) return array();
        $post_type = $this->get_course_post_type();
        if (!$post_type) return array();
        $status = $include_unpublished ? array('publish', 'draft', 'private') : array('publish');
        $limit = (int) $limit;
        if (0 === $limit) return array();
        return get_posts(array('post_type'=>$post_type,'post_status'=>$status,'posts_per_page'=>$limit,'orderby'=>array('menu_order'=>'ASC','date'=>'DESC')));
    }

    public function get_course($course_id) {
        $course_id = absint($course_id);
        $post_type = $this->get_course_post_type();
        $course = $course_id ? get_post($course_id) : null;
        if (!$course || !$post_type || $post_type !== $course->post_type) return null;
        return $course;
    }

    public function get_topic($topic_id) {
        $topic_id = absint($topic_id);
        if (!$topic_id) return null;
        $topic = get_post($topic_id);
        return ($topic && $this->get_topic_post_type() === $topic->post_type) ? $topic : null;
    }

    public function get_topics($course_id, $include_unpublished = true) {
        $status = $include_unpublished ? array('publish', 'draft', 'private') : array('publish');
        return get_posts(array('post_type'=>$this->get_topic_post_type(),'post_parent'=>absint($course_id),'post_status'=>$status,'posts_per_page'=>-1,'orderby'=>array('menu_order'=>'ASC','ID'=>'ASC')));
    }

    public function get_lessons($topic_id, $include_unpublished = true) {
        $lesson_post_type = $this->get_lesson_post_type();
        if (!$lesson_post_type) return array();
        $status = $include_unpublished ? array('publish', 'draft', 'private') : array('publish');
        return get_posts(array('post_type'=>$lesson_post_type,'post_parent'=>absint($topic_id),'post_status'=>$status,'posts_per_page'=>-1,'orderby'=>array('menu_order'=>'ASC','ID'=>'ASC')));
    }

    public function get_course_lesson_count($course_id) {
        $count = 0;
        foreach ($this->get_topics($course_id, false) as $topic) $count += count($this->get_lessons($topic->ID, false));
        return $count;
    }

    public function get_course_lessons($course_id, $include_unpublished = false) {
        $lessons = array();
        foreach ($this->get_topics($course_id, $include_unpublished) as $topic) foreach ($this->get_lessons($topic->ID, $include_unpublished) as $lesson) $lessons[] = $lesson;
        return $lessons;
    }

    public function get_lesson($lesson_id) {
        $lesson_id = absint($lesson_id);
        $lesson_post_type = $this->get_lesson_post_type();
        if (!$lesson_id || !$lesson_post_type) return null;
        $lesson = get_post($lesson_id);
        return (!$lesson || $lesson_post_type !== $lesson->post_type) ? null : $lesson;
    }

    public function get_lesson_course_id($lesson_id) {
        $lesson = $this->get_lesson($lesson_id);
        if (!$lesson) return 0;
        $topic = $this->get_topic($lesson->post_parent);
        if (!$topic) return 0;
        $course = $this->get_course($topic->post_parent);
        return $course ? (int)$course->ID : 0;
    }

    public function get_lesson_page_number($lesson_id) {
        $lesson_id = absint($lesson_id);
        $value = get_post_meta($lesson_id, '_mathcourse_page_number', true);
        if ('' === (string)$value) $value = get_post_meta($lesson_id, '_mathcourse_page', true);
        return sanitize_text_field($value);
    }

    public function get_lesson_video_id($lesson_id) {
        $lesson_id = absint($lesson_id);
        $value = get_post_meta($lesson_id, '_mathcourse_video_id', true);
        if ('' === (string)$value) $value = get_post_meta($lesson_id, '_mathcourse_video', true);
        return sanitize_text_field($value);
    }

    public function get_lesson_hls_url($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) return '';
        return esc_url_raw(get_post_meta($lesson_id, '_mathcourse_hls_url', true));
    }

    public function is_preview_lesson($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) return false;
        $native = get_post_meta($lesson_id, '_is_preview', true);
        if ('yes' === $native || '1' === (string)$native) return true;
        return 'yes' === get_post_meta($lesson_id, '_mathcourse_preview', true);
    }

    public function set_lesson_preview($lesson_id, $enabled) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) return false;
        $value = $enabled ? 'yes' : 'no';
        update_post_meta($lesson_id, '_is_preview', $value);
        update_post_meta($lesson_id, '_mathcourse_preview', $value);
        return true;
    }

    public function get_course_progress($course_id, $user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        $total = 0; $completed = 0;
        foreach ($this->get_course_lessons($course_id, false) as $lesson) {
            $total++;
            if ($this->is_lesson_completed($lesson->ID, $user_id)) $completed++;
        }
        return array('completed'=>$completed,'total'=>$total,'percent'=>$total ? round(($completed/$total)*100) : 0);
    }

    public function is_lesson_completed($lesson_id, $user_id = 0) {
        $user_id = $user_id ? absint($user_id) : get_current_user_id();
        if (!$user_id || !function_exists('tutor_utils')) return false;
        return (bool)tutor_utils()->is_completed_lesson(absint($lesson_id), $user_id);
    }
}
