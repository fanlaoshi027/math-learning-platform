<?php
/**
 * Course Progress Component
 */
defined('ABSPATH') || exit;

$course_id = get_the_ID();
$user_id = get_current_user_id();
$completed_lessons = 0;
$total_lessons = 0;

// MathCourse is the single source for the custom frontend progress display.
// Tutor LMS completion is not queried here because the custom player writes to
// mathcourse_learning and also maintains legacy compatibility data.
if ($user_id && class_exists('MathCourse\\Progress\\Progress_Service')) {
    $progress_service = new MathCourse\\Progress\\Progress_Service();
    $progress = $progress_service->get_course_progress($course_id, $user_id);
    $completed_lessons = isset($progress['completed']) ? (int) $progress['completed'] : 0;
    $total_lessons = isset($progress['total']) ? (int) $progress['total'] : 0;
} elseif (function_exists('tutor_utils')) {
    // Fallback for sites where the MathCourse plugin has not loaded yet.
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

<div class="mc-course-progress" data-course-id="<?php echo esc_attr($course_id); ?>">
    <div class="mc-progress-title">学习进度</div>
    <div class="mc-progress-number" data-progress-number>
        <?php echo esc_html($completed_lessons); ?> / <?php echo esc_html($total_lessons); ?> 课时
    </div>
    <div class="mc-progress-bar">
        <span data-progress-bar style="width:<?php echo esc_attr($percent); ?>%"></span>
    </div>
    <div class="mc-progress-percent" data-progress-percent>
        <?php echo esc_html($percent); ?>%
    </div>
</div>
