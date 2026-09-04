<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

class Menu {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_admin_assets($hook) {
        if (false === strpos((string) $hook, 'mathcourse')) return;
        wp_enqueue_style('mathcourse-admin-ui', MATHCOURSE_URL . 'assets/admin-ui.css', array(), MATHCOURSE_VERSION);
        if (isset($_GET['page']) && 'mathcourse' === sanitize_key(wp_unslash($_GET['page']))) {
            wp_enqueue_style('mathcourse-admin-dashboard', MATHCOURSE_URL . 'assets/admin-dashboard.css', array('mathcourse-admin-ui'), MATHCOURSE_VERSION);
        }
        if (isset($_GET['page']) && 'mathcourse-course-edit' === sanitize_key(wp_unslash($_GET['page']))) {
            wp_enqueue_media();
            wp_enqueue_style('mathcourse-admin-course-editor', MATHCOURSE_URL . 'assets/admin-course-editor.css', array('mathcourse-admin-ui'), MATHCOURSE_VERSION);
            wp_enqueue_style('mathcourse-admin-course-editor-polish', MATHCOURSE_URL . 'assets/admin-course-editor-polish.css', array('mathcourse-admin-course-editor'), MATHCOURSE_VERSION);
            wp_enqueue_style('mathcourse-admin-video-upload', MATHCOURSE_URL . 'assets/admin-video-upload.css', array('mathcourse-admin-course-editor-polish'), MATHCOURSE_VERSION);
        }
    }

    public function register_menu() {
        add_menu_page('MathCourse', '数学课程', 'manage_options', 'mathcourse', array($this, 'dashboard'), 'dashicons-welcome-learn-more', 30);
        add_submenu_page('mathcourse', '课程管理', '课程管理', 'manage_options', 'mathcourse-courses', array($this, 'courses_page'));
        add_submenu_page('mathcourse', '批量创建课时', '批量创建课时', 'manage_options', 'mathcourse-batch', array($this, 'batch_page'));
        add_submenu_page('mathcourse', '课程授权', '学员授权', 'manage_options', 'mathcourse-access', 'manage_options', 'mathcourse-access', array($this, 'access_page'));
        add_submenu_page('mathcourse', '课程激活码', '激活码管理', 'manage_options', 'mathcourse-activation', array($this, 'activation_page'));
        add_submenu_page('mathcourse', '学习进度', '学习进度', 'manage_options', 'mathcourse-progress', array($this, 'progress_page'));
        add_submenu_page('mathcourse', '设置', '系统设置', 'manage_options', 'mathcourse-settings', array($this, 'settings_page'));
        add_submenu_page(null, '编辑课程', '编辑课程', 'manage_options', 'mathcourse-course-edit', array($this, 'course_edit_page'));
    }

    public function dashboard() {
        if (!current_user_can('manage_options')) return;
        $tutor = new Adapter(); $access = new Access_Service(); $message = ''; $message_type = 'success';
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['mathcourse_dashboard_action'])) {
            $action = sanitize_key(wp_unslash($_POST['mathcourse_dashboard_action']));
            if ('grant_access' === $action && isset($_POST['mathcourse_dashboard_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_dashboard_nonce'])), 'mathcourse_dashboard_grant')) {
                $phone = sanitize_text_field(wp_unslash($_POST['student_phone'] ?? '')); $course_id = absint($_POST['course_id'] ?? 0);
                $user = $phone !== '' ? get_user_by('login', $phone) : false;
                if (!$user && $phone !== '') { $users = get_users(array('number' => 1, 'search' => '*' . $phone . '*', 'search_columns' => array('user_login', 'user_email'))); $user = !empty($users) ? $users[0] : false; }
                $course = $course_id ? $tutor->get_course($course_id) : false;
                if (!$user) { $message = '未找到该学员账号，请先在“学员授权管理”中创建账号。'; $message_type = 'error'; }
                elseif (!$course || 'publish' !== $course->post_status) { $message = '请选择有效的已发布课程。'; $message_type = 'error'; }
                elseif ($access->grant($user->ID, $course_id)) $message = '已为“' . $user->display_name . '”开通课程授权。';
                else { $message = '课程授权保存失败，请稍后重试。'; $message_type = 'error'; }
            }
        }
        $all_courses = $tutor->get_courses(true, -1); $published_courses = $tutor->get_courses(false, -1); $course_count = count($all_courses); $published_count = count($published_courses); $lesson_count = 0;
        foreach ($all_courses as $course) $lesson_count += $tutor->get_course_lesson_count($course->ID);
        $user_count = count(get_users(array('fields' => 'ids', 'number' => 9999))); $recent_courses = array_slice($all_courses, 0, 6);
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-dashboard-page">
            <div class="mathcourse-dashboard-hero"><div><div class="mathcourse-eyebrow">MathCourse · 工作台</div><h1>樊老师数学课程中心</h1><p>课程、课时、学员授权与学习数据，都在这里统一管理。</p></div><div class="mathcourse-dashboard-hero-actions"><a class="button mathcourse-primary" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-courses&action=new')); ?>">＋ 新增课程</a><a class="button mathcourse-dashboard-secondary" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-activation')); ?>">激活码管理</a></div></div>
            <?php if ($message !== '') : ?><div class="notice mathcourse-dashboard-notice <?php echo 'error' === $message_type ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
            <div class="mathcourse-dashboard-stats">
                <div class="mathcourse-dashboard-stat is-blue"><span class="dashicons dashicons-groups"></span><div><small>学员账号</small><strong><?php echo esc_html(number_format_i18n($user_count)); ?></strong><em>当前系统账号</em></div></div>
                <div class="mathcourse-dashboard-stat is-green"><span class="dashicons dashicons-welcome-learn-more"></span><div><small>上架课程</small><strong><?php echo esc_html(number_format_i18n($published_count)); ?></strong><em>共 <?php echo esc_html(number_format_i18n($course_count)); ?> 门课程</em></div></div>
                <div class="mathcourse-dashboard-stat is-orange"><span class="dashicons dashicons-video-alt3"></span><div><small>课程课时</small><strong><?php echo esc_html(number_format_i18n($lesson_count)); ?></strong><em>已接入 Tutor LMS</em></div></div>
                <div class="mathcourse-dashboard-stat is-purple"><span class="dashicons dashicons-shield"></span><div><small>管理模块</small><strong>6</strong><em>课程、批量、授权、激活码、进度、设置</em></div></div>
            </div>
            <div class="mathcourse-dashboard-main-grid">
                <section class="mathcourse-dashboard-panel mathcourse-dashboard-quick"><div class="mathcourse-dashboard-panel-head"><div><h2>⚡ 一键录入学员课程权限</h2><p>已有学员账号时，可直接从这里开通课程。</p></div><a href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-access')); ?>">学员管理 →</a></div><form method="post"><input type="hidden" name="mathcourse_dashboard_action" value="grant_access"><?php wp_nonce_field('mathcourse_dashboard_grant', 'mathcourse_dashboard_nonce'); ?><label>学员手机号 / 账号</label><input type="text" name="student_phone" inputmode="tel" placeholder="输入学员手机号或账号"><label>授权课程</label><select name="course_id"><option value="">请选择课程</option><?php foreach ($published_courses as $course) : ?><option value="<?php echo esc_attr($course->ID); ?>"><?php echo esc_html($course->post_title); ?></option><?php endforeach; ?></select><button type="submit" class="button mathcourse-primary mathcourse-dashboard-full-button">立即开通授权</button></form></section>
                <section class="mathcourse-dashboard-panel mathcourse-dashboard-links"><div class="mathcourse-dashboard-panel-head"><div><h2>常用管理</h2><p>把最常用的操作放在手边。</p></div></div><div class="mathcourse-dashboard-link-grid"><a href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-courses')); ?>"><span>📚</span><div><strong>课程与课时</strong><small>课程结构、视频、试看</small></div><b>→</b></a><a href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-access')); ?>"><span>👥</span><div><strong>学员授权</strong><small>课程权限与账号状态</small></div><b>→</b></a><a href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-activation')); ?>"><span>🎫</span><div><strong>激活码</strong><small>生成、分发与激活</small></div><b>→</b></a><a href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-progress')); ?>"><span>📈</span><div><strong>学习进度</strong><small>查看学员学习情况</small></div><b>→</b></a></div></section>
            </div>
            <section class="mathcourse-dashboard-panel mathcourse-dashboard-courses"><div class="mathcourse-dashboard-panel-head"><div><h2>最近课程</h2><p>当前 MathCourse 中最近维护的课程。</p></div><a href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-courses')); ?>">查看全部课程 →</a></div>
            <?php if ($tutor->is_available() && !empty($recent_courses)) : ?><div class="mathcourse-dashboard-course-grid"><?php foreach ($recent_courses as $course) : $edit_url=add_query_arg(array('page'=>'mathcourse-course-edit','course_id'=>$course->ID),admin_url('admin.php'));$lesson_total=$tutor->get_course_lesson_count($course->ID);$status_class='publish'===$course->post_status?'is-published':('private'===$course->post_status?'is-private':'is-draft');$status_label='publish'===$course->post_status?'已发布':('private'===$course->post_status?'私密':'草稿');$grade=get_post_meta($course->ID,'_mathcourse_grade',true); ?><a class="mathcourse-dashboard-course" href="<?php echo esc_url($edit_url); ?>"><div class="mathcourse-dashboard-course-mark"><span><?php echo esc_html(function_exists('mb_substr')?mb_substr($course->post_title,0,1):substr($course->post_title,0,1)); ?></span><i class="<?php echo esc_attr($status_class); ?>"></i></div><div class="mathcourse-dashboard-course-body"><strong><?php echo esc_html($course->post_title); ?></strong><div><span><?php echo esc_html($grade ?: '课程'); ?></span><span><?php echo esc_html($lesson_total); ?> 课时</span></div></div><span class="mathcourse-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></a><?php endforeach; ?></div>
            <?php elseif ($tutor->is_available()) : ?><div class="mathcourse-empty-state"><div class="mathcourse-empty-icon">📚</div><strong>还没有课程</strong><p>创建第一门课程后，这里会自动显示课程概况。</p><a class="button mathcourse-primary" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-courses&action=new')); ?>">新增课程</a></div>
            <?php else : ?><div class="mathcourse-empty-state"><strong>Tutor LMS 尚未启用</strong><p>启用 Tutor LMS 4.0.4 后，MathCourse 才能接管课程数据。</p></div><?php endif; ?></section>
        </div>
        <?php
    }
    public function courses_page(){if(class_exists('MathCourse\\Admin\\Course_Page'))(new Course_Page())->render();}
    public function batch_page(){if(class_exists('MathCourse\\Admin\\Batch_Manager_V2'))(new Batch_Manager_V2())->render();}
    public function activation_page(){if(class_exists('MathCourse\\Admin\\Activation_Page'))(new Activation_Page())->render();}
    public function course_edit_page(){if(class_exists('MathCourse\\Admin\\Course_Editor'))(new Course_Editor())->render();}
    public function access_page(){if(class_exists('MathCourse\\Admin\\Access_Page'))(new Access_Page())->render();}
    public function progress_page(){if(class_exists('MathCourse\\Admin\\Progress_Page'))(new Progress_Page())->render();}
    public function settings_page(){if(class_exists('MathCourse\\Admin\\Settings'))(new Settings())->render();}
}