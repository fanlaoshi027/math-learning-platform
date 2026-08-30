<?php
namespace MathCourse\Admin;
defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

/**
 * 可恢复的 Tutor LMS 课程批量课时创建任务。
 * 不修改 Tutor LMS 源码；使用 Tutor 注册的 Course/Topic/Lesson 数据结构。
 */
class Batch_Manager {
    const OPTION_PREFIX = 'mathcourse_batch_task_';
    const BATCH_SIZE = 10;

    public function __construct() {
        add_action('admin_post_mathcourse_batch_create', array($this, 'create_task'));
        add_action('admin_post_mathcourse_batch_cancel', array($this, 'cancel_task'));
        add_action('wp_ajax_mathcourse_batch_step', array($this, 'ajax_step'));
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mathcourse'));
        }
        if (!function_exists('tutor')) {
            echo '<div class="wrap"><h1>批量创建页码课时</h1><div class="notice notice-error"><p>需要先启用 Tutor LMS 4.0.4。</p></div></div>';
            return;
        }

        $task_id = isset($_GET['task_id']) ? sanitize_key(wp_unslash($_GET['task_id'])) : '';
        $task = $task_id ? $this->get_task($task_id) : null;
        $courses = get_posts(array(
            'post_type' => tutor()->course_post_type,
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => 100,
            'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'),
        ));
        ?>
        <div class="wrap">
            <h1>批量创建页码课时</h1>
            <p style="max-width:850px;color:#646970;">用于“大培优”类课程。任务分批创建真正的 Tutor LMS Lesson，不会一次请求创建数百个课时；中途失败后可以继续或重试。</p>
            <?php if ($task) : ?>
                <?php $this->render_task($task); ?>
            <?php else : ?>
                <div style="max-width:760px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="mathcourse_batch_create">
                        <?php wp_nonce_field('mathcourse_batch_create'); ?>
                        <p><label for="course_id"><strong>课程</strong></label><br>
                            <select id="course_id" name="course_id" style="min-width:420px;" required><option value="">请选择课程</option>
                            <?php foreach ($courses as $course) : ?><option value="<?php echo esc_attr($course->ID); ?>"><?php echo esc_html($course->post_title); ?>（ID <?php echo esc_html($course->ID); ?>）</option><?php endforeach; ?></select>
                        </p>
                        <p><label for="topic_title"><strong>专题名称</strong></label><br><input id="topic_title" name="topic_title" type="text" class="regular-text" value="大培优配套" required></p>
                        <div style="display:flex;gap:16px;"><p><label for="start_page"><strong>起始页</strong></label><br><input id="start_page" name="start_page" type="number" min="1" max="99999" value="1" required></p>
                        <p><label for="end_page"><strong>结束页</strong></label><br><input id="end_page" name="end_page" type="number" min="1" max="99999" value="428" required></p></div>
                        <p style="color:#646970;">已存在的相同页码会跳过，不会重复创建。建议先用 1～10 页进行真实环境验收，再创建完整范围。</p>
                        <p><button type="submit" class="button button-primary button-large">创建批量任务</button></p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function create_task() {
        $this->guard();
        check_admin_referer('mathcourse_batch_create');
        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $start = isset($_POST['start_page']) ? absint($_POST['start_page']) : 0;
        $end = isset($_POST['end_page']) ? absint($_POST['end_page']) : 0;
        $topic_title = isset($_POST['topic_title']) ? sanitize_text_field(wp_unslash($_POST['topic_title'])) : '';

        if (!$course_id || !$start || !$end || $start > $end || !$topic_title) wp_die('批量任务参数无效。');
        if (!function_exists('tutor') || tutor()->course_post_type !== get_post_type($course_id)) wp_die('课程不存在或不是 Tutor LMS Course。');
        if (!current_user_can('edit_post', $course_id)) wp_die('没有权限编辑这个课程。');

        $task_id = wp_generate_uuid4();
        $task = array(
            'id' => $task_id, 'course_id' => $course_id, 'topic_id' => 0, 'topic_title' => $topic_title,
            'start' => $start, 'end' => $end, 'current' => $start, 'total' => $end - $start + 1,
            'created' => current_time('mysql'), 'updated' => current_time('mysql'), 'created_count' => 0,
            'skipped_count' => 0, 'failed_count' => 0, 'errors' => array(), 'status' => 'pending',
        );
        $this->save_task($task);
        wp_safe_redirect(add_query_arg(array('page' => 'mathcourse-batch', 'task_id' => $task_id), admin_url('admin.php')));
        exit;
    }

    public function cancel_task() {
        $this->guard();
        $task_id = isset($_POST['task_id']) ? sanitize_key(wp_unslash($_POST['task_id'])) : '';
        if (!$task_id) wp_die('任务不存在。');
        check_admin_referer('mathcourse_batch_cancel_' . $task_id);
        $task = $this->get_task($task_id);
        if (!$task) wp_die('任务不存在。');
        $task['status'] = 'cancelled';
        $task['updated'] = current_time('mysql');
        $this->save_task($task);
        wp_safe_redirect(add_query_arg(array('page' => 'mathcourse-batch', 'task_id' => $task_id), admin_url('admin.php')));
        exit;
    }

    public function ajax_step() {
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => '没有权限。'), 403);
        check_ajax_referer('mathcourse_batch_step', 'nonce');
        $task_id = isset($_POST['task_id']) ? sanitize_key(wp_unslash($_POST['task_id'])) : '';
        $task = $task_id ? $this->get_task($task_id) : null;
        if (!$task) wp_send_json_error(array('message' => '任务不存在。'), 404);
        if (in_array($task['status'], array('completed', 'cancelled'), true)) wp_send_json_success($this->task_response($task));

        $task['status'] = 'running';
        $adapter = new Adapter();
        if (!$adapter->is_available() || tutor()->course_post_type !== get_post_type($task['course_id'])) {
            $task['status'] = 'paused';
            $this->add_error($task, $task['current'], 'Tutor LMS Course 不可用。');
            $this->save_task($task);
            wp_send_json_success($this->task_response($task));
        }

        $topic_id = $this->ensure_topic($task);
        if (!$topic_id) {
            $task['status'] = 'paused';
            $this->add_error($task, $task['current'], '无法创建或找到 Topic。');
            $this->save_task($task);
            wp_send_json_success($this->task_response($task));
        }
        $task['topic_id'] = $topic_id;
        $lesson_post_type = tutor()->lesson_post_type;
        $existing = $this->existing_page_map($topic_id, $lesson_post_type);
        $processed = 0;

        while ($task['current'] <= $task['end'] && $processed < self::BATCH_SIZE) {
            $page = (int) $task['current'];
            if (isset($existing[(string) $page])) {
                $task['skipped_count']++;
                $task['current']++;
                $processed++;
                continue;
            }

            $lesson_id = wp_insert_post(array(
                'post_title' => '第' . $page . '页', 'post_type' => $lesson_post_type, 'post_status' => 'publish',
                'post_author' => get_current_user_id(), 'post_parent' => $topic_id, 'menu_order' => $page, 'post_content' => '',
            ), true);
            if (is_wp_error($lesson_id)) {
                $task['failed_count']++;
                $task['status'] = 'paused';
                $this->add_error($task, $page, $lesson_id->get_error_message());
                break;
            }

            $lesson_id = absint($lesson_id);
            update_post_meta($lesson_id, '_mathcourse_page_number', $page);
            update_post_meta($lesson_id, '_mathcourse_page', $page);
            update_post_meta($lesson_id, '_mathcourse_preview', 'no');
            update_post_meta($lesson_id, '_is_preview', 'no');
            update_post_meta($lesson_id, '_mathcourse_video_id', '');
            update_post_meta($lesson_id, '_mathcourse_permission_mode', 'authorization');
            $task['created_count']++;
            $task['current']++;
            $processed++;
            $existing[(string) $page] = $lesson_id;
        }

        if ($task['current'] > $task['end'] && 'paused' !== $task['status']) $task['status'] = 'completed';
        $task['updated'] = current_time('mysql');
        $this->save_task($task);
        wp_send_json_success($this->task_response($task));
    }

    private function ensure_topic(&$task) {
        $topic_id = absint($task['topic_id']);
        if ($topic_id && 'topics' === get_post_type($topic_id) && (int) get_post_field('post_parent', $topic_id) === (int) $task['course_id']) return $topic_id;

        $topics = get_posts(array('post_type' => 'topics', 'post_parent' => absint($task['course_id']), 'post_status' => array('publish', 'draft', 'private'), 'posts_per_page' => -1, 'orderby' => array('menu_order' => 'ASC', 'ID' => 'ASC')));
        foreach ($topics as $topic) if ($topic->post_title === $task['topic_title']) return (int) $topic->ID;

        $topic_id = wp_insert_post(array(
            'post_title' => $task['topic_title'], 'post_type' => 'topics', 'post_status' => 'publish',
            'post_author' => get_current_user_id(), 'post_parent' => absint($task['course_id']), 'menu_order' => 0,
        ), true);
        return is_wp_error($topic_id) ? 0 : absint($topic_id);
    }

    private function existing_page_map($topic_id, $lesson_post_type) {
        $map = array();
        $lessons = get_posts(array('post_type' => $lesson_post_type, 'post_parent' => absint($topic_id), 'post_status' => array('publish', 'draft', 'private'), 'posts_per_page' => -1));
        foreach ($lessons as $lesson) {
            $page = get_post_meta($lesson->ID, '_mathcourse_page_number', true);
            if ('' === (string) $page) $page = get_post_meta($lesson->ID, '_mathcourse_page', true);
            if ('' !== (string) $page) $map[(string) absint($page)] = (int) $lesson->ID;
        }
        return $map;
    }

    private function add_error(&$task, $page, $message) {
        if (!isset($task['errors']) || !is_array($task['errors'])) $task['errors'] = array();
        $task['errors'][] = array('page' => absint($page), 'message' => sanitize_text_field($message), 'time' => current_time('mysql'));
        if (count($task['errors']) > 20) $task['errors'] = array_slice($task['errors'], -20);
    }

    private function task_response($task) {
        $done = max(0, (int) $task['current'] - (int) $task['start']);
        if ('completed' === $task['status']) $done = (int) $task['total'];
        return array(
            'id' => $task['id'], 'status' => $task['status'], 'current' => (int) $task['current'],
            'start' => (int) $task['start'], 'end' => (int) $task['end'], 'total' => (int) $task['total'],
            'done' => min((int) $task['total'], $done), 'created_count' => (int) $task['created_count'],
            'skipped_count' => (int) $task['skipped_count'], 'failed_count' => (int) $task['failed_count'],
            'errors' => array_slice((array) $task['errors'], -5),
        );
    }

    private function render_task($task) {
        $data = $this->task_response($task);
        $percent = $data['total'] ? round(($data['done'] / $data['total']) * 100) : 0;
        $nonce = wp_create_nonce('mathcourse_batch_step');
        $step_url = admin_url('admin-ajax.php');
        ?>
        <div style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;">
            <h2 style="margin-top:0;"><?php echo esc_html(get_the_title($task['course_id'])); ?></h2>
            <p>专题：<strong><?php echo esc_html($task['topic_title']); ?></strong>　页码：<strong><?php echo esc_html($task['start']); ?>～<?php echo esc_html($task['end']); ?></strong></p>
            <div style="height:16px;background:#f0f0f1;border-radius:999px;overflow:hidden;"><div id="mc-batch-bar" style="height:100%;width:<?php echo esc_attr($percent); ?>%;background:#2271b1;transition:width .25s;"></div></div>
            <p><strong id="mc-batch-percent"><?php echo esc_html($percent); ?>%</strong>　<span id="mc-batch-text"><?php echo esc_html($data['done']); ?> / <?php echo esc_html($data['total']); ?></span></p>
            <div id="mc-batch-stats" style="display:flex;gap:24px;flex-wrap:wrap;color:#50575e;"><span>新建：<strong><?php echo esc_html($data['created_count']); ?></strong></span><span>跳过：<strong><?php echo esc_html($data['skipped_count']); ?></strong></span><span>失败：<strong><?php echo esc_html($data['failed_count']); ?></strong></span><span>状态：<strong id="mc-batch-state"><?php echo esc_html($data['status']); ?></strong></span></div>
            <div id="mc-batch-errors" style="margin-top:16px;"></div>
            <p style="margin-top:24px;display:flex;gap:10px;"><button id="mc-batch-start" class="button button-primary">开始/继续</button>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;"><input type="hidden" name="action" value="mathcourse_batch_cancel"><input type="hidden" name="task_id" value="<?php echo esc_attr($task['id']); ?>"><?php wp_nonce_field('mathcourse_batch_cancel_' . $task['id']); ?><button class="button" type="submit" onclick="return confirm('确定取消这个任务吗？已创建的课时不会删除。');">取消任务</button></form>
            </p>
        </div>
        <script>
        (function () {
            const button = document.getElementById('mc-batch-start');
            const bar = document.getElementById('mc-batch-bar');
            const percent = document.getElementById('mc-batch-percent');
            const text = document.getElementById('mc-batch-text');
            const state = document.getElementById('mc-batch-state');
            const stats = document.getElementById('mc-batch-stats');
            const errors = document.getElementById('mc-batch-errors');
            let running = false;
            function render(data) {
                const p = data.total ? Math.round((data.done / data.total) * 100) : 0;
                bar.style.width = p + '%'; percent.textContent = p + '%';
                text.textContent = data.done + ' / ' + data.total + '（当前第 ' + (data.current <= data.end ? data.current : data.end) + ' 页）';
                state.textContent = data.status;
                stats.innerHTML = '<span>新建：<strong>' + data.created_count + '</strong></span><span>跳过：<strong>' + data.skipped_count + '</strong></span><span>失败：<strong>' + data.failed_count + '</strong></span><span>状态：<strong id="mc-batch-state">' + data.status + '</strong></span>';
                if (data.errors && data.errors.length) errors.innerHTML = '<div class="notice notice-error inline"><p>' + data.errors.map(function (item) { return '第' + item.page + '页：' + item.message; }).join('<br>') + '</p></div>';
            }
            function step() {
                if (running) return;
                running = true; button.disabled = true;
                const body = new URLSearchParams(); body.set('action', 'mathcourse_batch_step'); body.set('nonce', <?php echo wp_json_encode($nonce); ?>); body.set('task_id', <?php echo wp_json_encode($task['id']); ?>);
                fetch(<?php echo wp_json_encode($step_url); ?>, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:body.toString()}).then(function(r){return r.json();}).then(function(result){
                    running = false; button.disabled = false;
                    if (!result.success) { errors.innerHTML = '<div class="notice notice-error inline"><p>' + (result.data && result.data.message ? result.data.message : '任务执行失败') + '</p></div>'; return; }
                    render(result.data);
                    if (result.data.status === 'running' || result.data.status === 'pending') window.setTimeout(step, 120);
                }).catch(function(){running=false;button.disabled=false;errors.innerHTML='<div class="notice notice-error inline"><p>网络请求失败，可以点击“开始/继续”再次尝试。</p></div>';});
            }
            button.addEventListener('click', step);
            if (<?php echo wp_json_encode(in_array($task['status'], array('pending', 'running'), true)); ?>) window.setTimeout(step, 300);
        }());
        </script>
        <?php
    }

    private function get_task($task_id) {
        $task = get_option(self::OPTION_PREFIX . sanitize_key($task_id), null);
        return is_array($task) ? $task : null;
    }

    private function save_task($task) {
        update_option(self::OPTION_PREFIX . $task['id'], $task, false);
    }

    private function guard() {
        if (!is_admin() || !current_user_can('manage_options')) wp_die('没有权限。');
    }
}
