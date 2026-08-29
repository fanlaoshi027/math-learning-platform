<?php
/**
 * Course Progress Component
 */
defined('ABSPATH') || exit;

$course_id = get_the_ID();
$user_id = get_current_user_id();

$total_lessons = 0;
$completed_lessons = 0;

if (function_exists('tutor_utils')) {
    $contents = tutor_utils()->get_course_contents_by_course($course_id);
    if ($contents) {
        $total_lessons = count($contents);
        foreach ($contents as $lesson) {
            if ($user_id && tutor_utils()->is_completed_lesson($lesson->ID, $user_id)) {
                $completed_lessons++;
            }
        }
    }
}

$percent = $total_lessons ? round(($completed_lessons / $total_lessons) * 100) : 0;
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
