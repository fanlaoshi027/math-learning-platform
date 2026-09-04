<?php

namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

class Course_Page {
    public function render() {
        if (!current_user_can('manage_options')) return;
        $adapter = new Adapter();
        $courses = $adapter->get_courses(true, -1);
        $total_lessons = 0;
        $total_ready_videos = 0;
        foreach ($courses as $course) {
            $course_stats = $this->get_video_stats($adapter, (int) $course->ID);
            $total_lessons += $course_stats['total'];
            $total_ready_videos += $course_stats['ready'];
        }
        $new_course_url = wp_nonce_url(
            admin_url('admin.php?page=mathcourse-courses&action=new'),
            'mathcourse_new_course'
        );
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-course-page">
            <div class="mathcourse-admin-header">
                <div>
                    <h1>课程管理</h1>
                    <p>统一管理课程、专题、课时与课程状态。</p>
                </div>
                <a class="button mathcourse-primary" href="<?php echo esc_url($new_course_url); ?>">＋ 新增课程</a>
            </div>

            <div class="mathcourse-stat-grid">
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">全部课程</div><div class="mathcourse-stat-num"><?php echo esc_html(count($courses)); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">已发布课程</div><div class="mathcourse-stat-num"><?php echo esc_html($this->count_status($courses, 'publish')); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">全部课时</div><div class="mathcourse-stat-num"><?php echo esc_html($total_lessons); ?></div></div>
                <div class="mathcourse-stat-card"><div class="mathcourse-stat-title">视频已就绪</div><div class="mathcourse-stat-num"><?php echo esc_html($total_ready_videos); ?><span class="mathcourse-stat-suffix"> / <?php echo esc_html($total_lessons); ?></span></div></div>
            </div>

            <div class="mathcourse-card mathcourse-course-list-card">
                <div class="mathcourse-card-title">
                    <div>
                        <h2>我的课程</h2>
                        <span class="mathcourse-card-subtitle">共 <?php echo esc_html(count($courses)); ?> 门课程</span>
                    </div>
                    <a class="mathcourse-text-action" href="<?php echo esc_url($new_course_url); ?>">新增课程 <span>→</span></a>
                </div>

                <?php if (!$courses): ?>
                    <div class="mathcourse-empty-state">
                        <div class="mathcourse-empty-icon">＋</div>
                        <strong>还没有课程</strong>
                        <p>创建第一门课程后，它会显示在这里。</p>
                        <a class="button mathcourse-primary" href="<?php echo esc_url($new_course_url); ?>">创建课程</a>
                    </div>
                <?php else: ?>
                    <div class="mathcourse-course-grid">
                    <?php foreach ($courses as $course):
                        $id = (int)$course->ID;
                        $cover = get_the_post_thumbnail_url($id, 'medium');
                        if (!$cover) $cover = get_post_meta($id, '_mathcourse_cover', true);
                        $type = get_post_meta($id, '_mathcourse_type', true);
                        $grade = get_post_meta($id, '_mathcourse_grade', true);
                        $video_stats = $this->get_video_stats($adapter, $id);
                        $lesson_count = $video_stats['total'];
                        $ready_count = $video_stats['ready'];
                        $ready_percent = $lesson_count > 0 ? min(100, (int) round(($ready_count / $lesson_count) * 100)) : 0;
                        $is_publish = 'publish' === $course->post_status;
                        $status_text = $is_publish ? '已发布' : ('private' === $course->post_status ? '私密' : '草稿');
                        $edit_url = admin_url('admin.php?page=mathcourse-course-edit&course_id='.$id);
                        $batch_url = admin_url('admin.php?page=mathcourse-batch&course_id='.$id);
                        $title_mark = function_exists('mb_substr') ? mb_substr($course->post_title, 0, 2) : substr($course->post_title, 0, 2);
                    ?>
                        <article class="mathcourse-course-item">
                            <a class="mathcourse-course-cover" href="<?php echo esc_url($edit_url); ?>" aria-label="编辑 <?php echo esc_attr($course->post_title); ?>">
                                <?php if ($cover): ?>
                                    <img src="<?php echo esc_url($cover); ?>" alt="">
                                <?php else: ?>
                                    <span class="mathcourse-cover-fallback"><b><?php echo esc_html($title_mark); ?></b><small>数学课程</small></span>
                                <?php endif; ?>
                                <span class="mathcourse-course-status <?php echo $is_publish ? 'is-published' : ('private' === $course->post_status ? 'is-private' : 'is-draft'); ?>"><?php echo esc_html($status_text); ?></span>
                            </a>
                            <div class="mathcourse-course-body">
                                <div class="mathcourse-course-heading">
                                    <h3><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($course->post_title); ?></a></h3>
                                    <span class="mathcourse-course-id">ID <?php echo esc_html($id); ?></span>
                                </div>
                                <div class="mathcourse-course-tags">
                                    <span><?php echo esc_html($grade ? $grade . '年级' : '未设置年级'); ?></span>
                                    <span><?php echo esc_html('supplementary' === $type ? '教辅配套课' : '专题课程'); ?></span>
                                </div>
                                <div class="mathcourse-video-readiness">
                                    <div class="mathcourse-video-readiness-head"><span>视频完成度</span><strong><?php echo esc_html($ready_count); ?> / <?php echo esc_html($lesson_count); ?></strong></div>
                                    <div class="mathcourse-video-readiness-track"><i style="width:<?php echo esc_attr($ready_percent); ?>%"></i></div>
                                    <small><?php echo esc_html($lesson_count ? $ready_percent . '% 已就绪' : '暂无线下课时'); ?></small>
                                </div>
                                <div class="mathcourse-course-footer">
                                    <div class="mathcourse-lesson-count"><strong><?php echo esc_html($lesson_count); ?></strong><span>个课时</span></div>
                                    <div class="mathcourse-course-actions">
                                        <a href="<?php echo esc_url($batch_url); ?>">批量课时</a>
                                        <a class="mathcourse-edit-link" href="<?php echo esc_url($edit_url); ?>">编辑课程 →</a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function get_video_stats($adapter, $course_id) {
        $total = 0;
        $ready = 0;
        $topics = $adapter->get_topics($course_id, true);
        foreach ($topics as $topic) {
            $lessons = $adapter->get_lessons($topic->ID, true);
            foreach ($lessons as $lesson) {
                $total++;
                if ('ready' === get_post_meta($lesson->ID, '_mathcourse_video_status', true) && get_post_meta($lesson->ID, '_mathcourse_hls_url', true)) {
                    $ready++;
                }
            }
        }
        return array('total' => $total, 'ready' => $ready);
    }

    private function count_status($courses, $status) {
        $n = 0;
        foreach ($courses as $course) if ($status === $course->post_status) $n++;
        return $n;
    }
}
