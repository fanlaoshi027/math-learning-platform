<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

class Course_Page {
    public function render() {
        if (!current_user_can('manage_options')) return;
        $adapter = new Adapter();
        $courses = $adapter->get_courses(true, 50);
        $total_lessons = 0;
        foreach ($courses as $course) $total_lessons += $adapter->get_course_lesson_count((int)$course->ID);
        ?>
        <div class="wrap mathcourse-admin-wrap">
            <div class="mathcourse-admin-header">
                <h1>课程管理</h1>
                <p>管理课程、专题和课时。课程底层数据由 Tutor LMS 保存，MathCourse 负责课程结构、试看与授权。</p>
            </div>
            <div class="mathcourse-stat-grid">
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">全部课程</div><div class="mathcourse-stat-num"><?php echo esc_html(count($courses)); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">已发布课程</div><div class="mathcourse-stat-num"><?php echo esc_html($this->count_status($courses, 'publish')); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">全部课时</div><div class="mathcourse-stat-num"><?php echo esc_html($total_lessons); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">课程管理</div><div class="mathcourse-stat-num" style="font-size:16px;padding-top:7px;">Course → Topic → Lesson</div></div>
            </div>
            <div class="mathcourse-card">
                <div class="mathcourse-card-title"><h2>专题课程</h2><a class="button mathcourse-primary" href="<?php echo esc_url(admin_url('admin.php?page=create-course')); ?>">＋ 新增课程</a></div>
                <table class="mathcourse-table"><thead><tr><th>课程</th><th>封面</th><th>类型</th><th>课时</th><th>状态</th><th>操作</th></tr></thead><tbody>
                <?php if (!$courses): ?><tr><td colspan="6">暂无课程。点击右上角“新增课程”开始创建。</td></tr><?php endif; ?>
                <?php foreach ($courses as $course): $id=(int)$course->ID; $cover=get_the_post_thumbnail_url($id,'thumbnail'); if(!$cover)$cover=get_post_meta($id,'_mathcourse_cover',true); $type=get_post_meta($id,'_mathcourse_type',true); ?>
                    <tr>
                        <td><strong><?php echo esc_html($course->post_title); ?></strong><div style="color:#6b7280;font-size:12px;margin-top:3px;">ID <?php echo esc_html($id); ?></div></td>
                        <td><?php if($cover): ?><img class="mathcourse-cover" src="<?php echo esc_url($cover); ?>" alt=""><?php else: ?><span class="mathcourse-badge">暂无封面</span><?php endif; ?></td>
                        <td><?php echo esc_html('supplementary'===$type?'教辅配套课':'专题课程'); ?></td>
                        <td><strong><?php echo esc_html($adapter->get_course_lesson_count($id)); ?></strong></td>
                        <td><span class="mathcourse-badge <?php echo 'publish'===$course->post_status?'is-published':'is-draft'; ?>"><?php echo 'publish'===$course->post_status?'已发布':('private'===$course->post_status?'私密':'草稿'); ?></span></td>
                        <td style="white-space:nowrap"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-course-edit&course_id='.$id)); ?>">编辑</a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-batch&course_id='.$id)); ?>">批量课时</a></td>
                    </tr>
                <?php endforeach; ?></tbody></table>
            </div>
        </div>
        <?php
    }
    private function count_status($courses,$status) { $n=0; foreach($courses as $course) if($status===$course->post_status)$n++; return $n; }
}
