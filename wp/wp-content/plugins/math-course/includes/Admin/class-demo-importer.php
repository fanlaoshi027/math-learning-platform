<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;

/**
 * One-click demo content importer.
 *
 * Creates real Tutor LMS Course / Topic / Lesson posts plus MathCourse
 * metadata. It is intentionally idempotent: re-running updates/reuses the
 * same demo records instead of creating duplicates.
 */
class Demo_Importer {
    const OPTION_KEY = 'mathcourse_demo_import';
    const VERSION = '1.0.0';

    public function __construct() {
        add_action('admin_post_mathcourse_import_demo', array($this, 'handle_import'));
        add_action('admin_post_mathcourse_remove_demo', array($this, 'handle_remove'));
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mathcourse'));
        }

        $state = get_option(self::OPTION_KEY, array());
        $courses = !empty($state['courses']) && is_array($state['courses']) ? $state['courses'] : array();
        $has_demo = !empty($courses);
        ?>
        <div class="wrap mathcourse-admin-wrap">
            <div class="mathcourse-admin-header">
                <h1>一键导入演示数据</h1>
                <p>生成与当前前台参考 UI 对应的真实 Tutor LMS 课程、专题、课时及 MathCourse 业务字段，用于快速验收页面。</p>
            </div>

            <div class="mathcourse-card" style="max-width:980px;">
                <h2>演示数据内容</h2>
                <ul style="line-height:1.9;">
                    <li>3 个专题课程：代数、几何、函数</li>
                    <li>1 个大培优配套课程：大培优·八年级上册</li>
                    <li>自动创建 Topic / Lesson，包含页码、试看、视频演示标记</li>
                    <li>自动创建「首页」「课程中心」「学习中心」「学习页」基础页面</li>
                    <li>自动为当前管理员账号开通全部演示课程，方便直接测试“我的课程”和学习进度</li>
                    <li>重复点击不会无限创建重复课程；可以删除本次演示数据后重新导入</li>
                </ul>

                <?php if ($has_demo) : ?>
                    <div class="notice notice-success inline"><p>演示数据已经导入。当前记录 <?php echo esc_html(count($courses)); ?> 个课程。</p></div>
                    <p><a class="button button-primary" href="<?php echo esc_url(home_url('/course-center/')); ?>" target="_blank" rel="noopener">打开前台课程中心</a>
                        <a class="button" href="<?php echo esc_url(home_url('/learning-center/')); ?>" target="_blank" rel="noopener">打开我的课程</a></p>
                    <hr>
                    <p><strong>危险操作：</strong>删除由本工具创建的演示课程、Topic、Lesson 以及自动创建的页面，并撤销当前管理员的演示课程授权。</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('确定删除本次导入的全部演示数据吗？');">
                        <input type="hidden" name="action" value="mathcourse_remove_demo">
                        <?php wp_nonce_field('mathcourse_remove_demo'); ?>
                        <button type="submit" class="button">删除演示数据</button>
                    </form>
                <?php else : ?>
                    <div class="notice notice-info inline"><p>当前还没有 MathCourse 演示数据。</p></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="mathcourse_import_demo">
                        <?php wp_nonce_field('mathcourse_import_demo'); ?>
                        <button type="submit" class="button button-primary button-hero">一键导入演示数据</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function handle_import() {
        $this->guard();
        check_admin_referer('mathcourse_import_demo');

        if (!function_exists('tutor')) {
            wp_die('需要先启用 Tutor LMS 4.0.4。');
        }

        $existing = get_option(self::OPTION_KEY, array());
        $course_ids = !empty($existing['courses']) && is_array($existing['courses']) ? $existing['courses'] : array();
        $courses = $this->definitions();
        $created = array();

        foreach ($courses as $definition) {
            $course_id = $this->find_or_create_course($definition, $course_ids);
            if (!$course_id) continue;

            $created[$definition['key']] = $course_id;
            update_post_meta($course_id, '_mathcourse_type', $definition['type']);
            update_post_meta($course_id, '_mathcourse_grade', $definition['grade']);
            update_post_meta($course_id, '_mathcourse_cover', MATHCOURSE_URL . 'assets/demo/covers/' . $definition['cover']);
            update_post_meta($course_id, '_mathcourse_demo', 'yes');
            update_post_meta($course_id, '_tutor_course_price_type', 'paid');

            foreach ($definition['topics'] as $topic_index => $topic_definition) {
                $topic_id = $this->find_or_create_topic($course_id, $topic_definition['title'], $topic_index);
                if (!$topic_id) continue;
                update_post_meta($topic_id, '_mathcourse_demo', 'yes');

                foreach ($topic_definition['lessons'] as $lesson_index => $lesson_definition) {
                    $lesson_id = $this->find_or_create_lesson($topic_id, $lesson_definition, $lesson_index);
                    if (!$lesson_id) continue;
                    update_post_meta($lesson_id, '_mathcourse_demo', 'yes');
                    update_post_meta($lesson_id, '_mathcourse_page_number', isset($lesson_definition['page']) ? $lesson_definition['page'] : '');
                    update_post_meta($lesson_id, '_mathcourse_page', isset($lesson_definition['page']) ? $lesson_definition['page'] : '');
                    update_post_meta($lesson_id, '_mathcourse_video_id', 'demo-' . $definition['key'] . '-' . $lesson_index);
                    update_post_meta($lesson_id, '_mathcourse_hls_url', '');
                    update_post_meta($lesson_id, '_mathcourse_permission_mode', 'authorization');
                    update_post_meta($lesson_id, '_mathcourse_preview', !empty($lesson_definition['trial']) ? 'yes' : 'no');
                    update_post_meta($lesson_id, '_is_preview', !empty($lesson_definition['trial']) ? 'yes' : 'no');
                }
            }
        }

        $this->ensure_pages();

        if (!empty($created)) {
            $access = new Access_Service();
            $user_id = get_current_user_id();
            foreach ($created as $course_id) $access->grant($user_id, $course_id, null);
        }

        update_option(self::OPTION_KEY, array(
            'version' => self::VERSION,
            'courses' => $created,
            'imported_at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        ), false);

        wp_safe_redirect(add_query_arg(array('page' => 'mathcourse-demo', 'imported' => 1), admin_url('admin.php')));
        exit;
    }

    public function handle_remove() {
        $this->guard();
        check_admin_referer('mathcourse_remove_demo');

        $state = get_option(self::OPTION_KEY, array());
        $courses = !empty($state['courses']) && is_array($state['courses']) ? $state['courses'] : array();
        $access = new Access_Service();

        foreach ($courses as $course_id) {
            $course_id = absint($course_id);
            if (!$course_id || 'yes' !== get_post_meta($course_id, '_mathcourse_demo', true)) continue;

            foreach (get_posts(array('post_type' => 'topics', 'post_parent' => $course_id, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids')) as $topic_id) {
                foreach (get_posts(array('post_type' => tutor()->lesson_post_type, 'post_parent' => $topic_id, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids')) as $lesson_id) wp_delete_post($lesson_id, true);
                wp_delete_post($topic_id, true);
            }
            $access->revoke(get_current_user_id(), $course_id);
            wp_delete_post($course_id, true);
        }

        foreach (array('首页', '课程中心', '学习中心', '学习页') as $title) {
            $page = get_page_by_title($title, OBJECT, 'page');
            if ($page && 'yes' === get_post_meta($page->ID, '_mathcourse_demo_page', true)) wp_delete_post($page->ID, true);
        }

        delete_option(self::OPTION_KEY);
        wp_safe_redirect(add_query_arg(array('page' => 'mathcourse-demo', 'removed' => 1), admin_url('admin.php')));
        exit;
    }

    private function definitions() {
        return array(
            array('key' => 'algebra', 'title' => '代数', 'type' => 'topic', 'grade' => '7', 'cover' => 'algebra.svg', 'topics' => array(
                array('title' => '有理数', 'lessons' => array(array('title' => '有理数的概念', 'trial' => true), array('title' => '有理数的运算'), array('title' => '数轴与绝对值'))),
                array('title' => '整式', 'lessons' => array(array('title' => '整式的概念'), array('title' => '整式的加减'), array('title' => '一元一次方程', 'trial' => true))),
                array('title' => '一元一次方程', 'lessons' => array(array('title' => '方程的概念'), array('title' => '解一元一次方程'), array('title' => '实际问题与方程'))),
            )),
            array('key' => 'geometry', 'title' => '几何', 'type' => 'topic', 'grade' => '8', 'cover' => 'geometry.svg', 'topics' => array(
                array('title' => '线段与角', 'lessons' => array(array('title' => '线段与角的基础', 'trial' => true), array('title' => '角的比较与运算'))),
                array('title' => '三角形', 'lessons' => array(array('title' => '三角形的概念'), array('title' => '三角形的边角关系'))),
                array('title' => '全等三角形', 'lessons' => array(array('title' => '全等三角形的概念与表示', 'trial' => true), array('title' => '边边边（SSS）'), array('title' => '边角边（SAS）'), array('title' => '角边角（ASA）'))),
            )),
            array('key' => 'function', 'title' => '函数', 'type' => 'topic', 'grade' => '9', 'cover' => 'function.svg', 'topics' => array(
                array('title' => '一次函数', 'lessons' => array(array('title' => '变量与函数', 'trial' => true), array('title' => '一次函数图象'), array('title' => '一次函数应用'))),
                array('title' => '反比例函数', 'lessons' => array(array('title' => '反比例函数概念'), array('title' => '反比例函数图象与性质'))),
                array('title' => '二次函数', 'lessons' => array(array('title' => '二次函数基础'), array('title' => '二次函数图象与性质'))),
            )),
            array('key' => 'dapeiyou8', 'title' => '大培优·八年级上册', 'type' => 'supplementary', 'grade' => '8', 'cover' => 'dapeiyou.svg', 'topics' => array(
                array('title' => '大培优八上', 'lessons' => array(array('title' => '第1页', 'page' => 1, 'trial' => true), array('title' => '第2页', 'page' => 2), array('title' => '第3页', 'page' => 3), array('title' => '第86页', 'page' => 86), array('title' => '第120页', 'page' => 120))),
            )),
        );
    }

    private function find_or_create_course($definition, $known) {
        foreach ($known as $id) if ($id && get_post_meta($id, '_mathcourse_demo_key', true) === $definition['key']) return absint($id);
        $ids = get_posts(array('post_type' => tutor()->course_post_type, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_mathcourse_demo_key', 'meta_value' => $definition['key']));
        if (!empty($ids)) return absint($ids[0]);
        $course_id = wp_insert_post(array('post_title' => $definition['title'], 'post_type' => tutor()->course_post_type, 'post_status' => 'publish', 'post_author' => get_current_user_id(), 'post_content' => '这是 MathCourse 演示课程，用于验收课程中心、课程详情和学习页 UI。'), true);
        if (is_wp_error($course_id)) return 0;
        update_post_meta($course_id, '_mathcourse_demo_key', $definition['key']);
        return absint($course_id);
    }

    private function find_or_create_topic($course_id, $title, $order) {
        $topics = get_posts(array('post_type' => 'topics', 'post_parent' => absint($course_id), 'post_status' => 'any', 'posts_per_page' => -1));
        foreach ($topics as $topic) if ($topic->post_title === $title && 'yes' === get_post_meta($topic->ID, '_mathcourse_demo', true)) return (int) $topic->ID;
        $topic_id = wp_insert_post(array('post_title' => $title, 'post_type' => 'topics', 'post_status' => 'publish', 'post_author' => get_current_user_id(), 'post_parent' => absint($course_id), 'menu_order' => absint($order)), true);
        return is_wp_error($topic_id) ? 0 : absint($topic_id);
    }

    private function find_or_create_lesson($topic_id, $definition, $order) {
        $lessons = get_posts(array('post_type' => tutor()->lesson_post_type, 'post_parent' => absint($topic_id), 'post_status' => 'any', 'posts_per_page' => -1));
        foreach ($lessons as $lesson) if ($lesson->post_title === $definition['title'] && 'yes' === get_post_meta($lesson->ID, '_mathcourse_demo', true)) return (int) $lesson->ID;
        $lesson_id = wp_insert_post(array('post_title' => $definition['title'], 'post_type' => tutor()->lesson_post_type, 'post_status' => 'publish', 'post_author' => get_current_user_id(), 'post_parent' => absint($topic_id), 'menu_order' => absint($order), 'post_content' => '本节为演示课时内容。正式课程可以在这里填写知识点、例题与讲义说明。'), true);
        return is_wp_error($lesson_id) ? 0 : absint($lesson_id);
    }

    private function ensure_pages() {
        $pages = array(
            '首页' => array('slug' => 'home', 'template' => '', 'content' => ''),
            '课程中心' => array('slug' => 'course-center', 'template' => 'page-course-center.php', 'content' => ''),
            '学习中心' => array('slug' => 'learning-center', 'template' => 'page-learning-center.php', 'content' => ''),
            '学习页' => array('slug' => 'learning', 'template' => 'page-learning.php', 'content' => ''),
        );
        foreach ($pages as $title => $definition) {
            $page = get_page_by_path($definition['slug']);
            $created_here = false;
            if (!$page) {
                $page_id = wp_insert_post(array('post_title' => $title, 'post_name' => $definition['slug'], 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $definition['content']), true);
                if (is_wp_error($page_id)) continue;
                $page = get_post($page_id);
                $created_here = true;
            }
            if ($definition['template']) update_post_meta($page->ID, '_wp_page_template', $definition['template']);
            if ($created_here) update_post_meta($page->ID, '_mathcourse_demo_page', 'yes');
        }
    }

    private function guard() {
        if (!current_user_can('manage_options')) wp_die('没有权限。');
    }
}
