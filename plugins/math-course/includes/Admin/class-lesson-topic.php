<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

/**
 * 允许在 MathCourse 课程编辑器中把现有课时重新归入本课程的其他专题。
 */
class Lesson_Topic {
    private $tutor;

    public function __construct() {
        $this->tutor = new Adapter();
        add_action('admin_init', array($this, 'handle_move'));
    }

    public function handle_move() {
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? '')) return;
        if (empty($_POST['mathcourse_action']) || 'save_lesson' !== sanitize_key(wp_unslash($_POST['mathcourse_action']))) return;
        if (empty($_POST['lesson_target_topic'])) return;

        $course_id = absint($_GET['course_id'] ?? 0);
        $lesson_id = absint($_POST['editing_lesson_id'] ?? 0);
        $target_topic_id = absint($_POST['lesson_target_topic'] ?? 0);
        if (!$course_id || !$lesson_id || !$target_topic_id) return;
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mathcourse_edit_course_' . $course_id)) return;
        if (!current_user_can('edit_post', $lesson_id)) return;

        $lesson = $this->tutor->get_lesson($lesson_id);
        $target_topic = $this->tutor->get_topic($target_topic_id);
        if (!$lesson || !$target_topic) return;
        if ($course_id !== $this->tutor->get_lesson_course_id($lesson_id)) return;
        if ((int) $target_topic->post_parent !== $course_id) return;
        if ((int) $lesson->post_parent === $target_topic_id) return;

        $order = 0;
        foreach ($this->tutor->get_lessons($target_topic_id, true) as $item) {
            $order = max($order, (int) $item->menu_order + 1);
        }
        wp_update_post(array(
            'ID' => $lesson_id,
            'post_parent' => $target_topic_id,
            'menu_order' => $order,
        ));
    }
}
