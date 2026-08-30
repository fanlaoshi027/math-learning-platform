<?php
/**
 * Course Progress Component
 */
defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

$course_id = get_the_ID();
$user_id = get_current_user_id();
$progress = array(
    'completed' => 0,
    'total'     => 0,
    'percent'   => 0,
);

$adapter = new Adapter();
if ($adapter->is_available() && $adapter->get_course($course_id)) {
    $progress = $adapter->get_course_progress($course_id, $user_id);
}

$completed_lessons = absint($progress['completed']);
$total_lessons = absint($progress['total']);
$percent = max(0, min(100, absint($progress['percent'])));
?>

<div class="mc-course-progress">
    <div class="mc-progress-title">学习进度</div>
    <div class="mc-progress-number">
        <?php echo esc_html($completed_lessons); ?> / <?php echo esc_html($total_lessons); ?> 课时
    </div>
    <div class="mc-progress-bar">
        <span style="width:<?php echo esc_attr($percent); ?>%"></span>
    </div>
    <div class="mc-progress-percent">
        <?php echo esc_html($percent); ?>%
    </div>
</div>
