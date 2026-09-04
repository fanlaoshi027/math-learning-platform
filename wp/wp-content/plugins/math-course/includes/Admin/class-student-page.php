<?php
namespace MathCourse\Admin;
defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;

class Student_Page {
    public function render() {
        if (!current_user_can('manage_options')) return;
        $notice = $this->handle_post();
        $edit_id = isset($_GET['student_id']) ? absint($_GET['student_id']) : 0;
        $edit_user = $edit_id ? get_userdata($edit_id) : false;
        if ($edit_user && !in_array('subscriber', (array) $edit_user->roles, true)) $edit_user = false;
        $keyword = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $args = array('role' => 'subscriber', 'number' => 50, 'orderby' => 'registered', 'order' => 'DESC');
        if ($keyword !== '') {
            $args['search'] = '*' . $keyword . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }
        $students = get_users($args);
        $access = new Access_Service();
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-student-page">
            <div class="mathcourse-admin-header">
                <div><div class="mathcourse-eyebrow">MathCourse · 学员管理</div><h1>学员管理</h1><p>新增、编辑学员账号，并查看当前学员的课程授权数量。</p></div>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-students&action=new')); ?>">＋ 新增学员</a>
            </div>
            <?php if ($notice) : ?><div class="notice <?php echo esc_attr($notice['type']); ?> is-dismissible"><p><?php echo esc_html($notice['message']); ?></p></div><?php endif; ?>
            <?php if ('new' === ($_GET['action'] ?? '') || $edit_user) : ?>
                <?php $this->render_form($edit_user); ?>
            <?php endif; ?>
            <div class="mathcourse-access-toolbar">
                <form method="get"><input type="hidden" name="page" value="mathcourse-students"><input class="mathcourse-search-input" type="text" name="s" value="<?php echo esc_attr($keyword); ?>" placeholder="搜索账号、姓名或邮箱"><button class="button button-primary">搜索学员</button></form>
            </div>
            <div class="mathcourse-access-card">
                <div class="mathcourse-card-title"><div><h2>学员账号</h2><span class="mathcourse-card-subtitle">共 <?php echo esc_html(number_format_i18n(count($students))); ?> 个显示结果</span></div><a href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-access')); ?>">课程授权 →</a></div>
                <div class="mathcourse-access-table-wrap"><table class="mathcourse-access-table"><thead><tr><th>学员</th><th>账号</th><th>注册时间</th><th>已授权课程</th><th>操作</th></tr></thead><tbody>
                <?php foreach ($students as $student) : $courses = $access->get_user_courses($student->ID); ?>
                    <tr><td><div class="mathcourse-user-cell"><span class="mathcourse-user-avatar"><?php echo esc_html(mb_strtoupper(mb_substr($student->display_name ?: $student->user_login, 0, 1))); ?></span><div><strong><?php echo esc_html($student->display_name ?: '未设置姓名'); ?></strong><small><?php echo esc_html($student->user_email ?: '未填写邮箱'); ?></small></div></div></td><td><?php echo esc_html($student->user_login); ?></td><td><?php echo esc_html(mysql2date('Y-m-d', $student->user_registered)); ?></td><td><span class="mathcourse-access-status is-active"><i></i><?php echo esc_html(count($courses)); ?> 门课程</span></td><td><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'mathcourse-students','student_id'=>$student->ID), admin_url('admin.php'))); ?>">编辑学员</a></td></tr>
                <?php endforeach; ?>
                <?php if (!$students) : ?><tr><td colspan="5"><div class="mathcourse-empty-state"><strong>还没有学员</strong><p>可以点击右上角“新增学员”创建账号。</p></div></td></tr><?php endif; ?>
                </tbody></table></div>
            </div>
        </div>
        <?php
    }

    private function render_form($user) {
        $is_edit = $user instanceof \WP_User;
        $action = $is_edit ? 'edit' : 'create';
        ?>
        <div class="mathcourse-access-card mathcourse-student-form-card">
            <div class="mathcourse-card-title"><div><h2><?php echo $is_edit ? '编辑学员' : '新增学员'; ?></h2><span class="mathcourse-card-subtitle"><?php echo $is_edit ? '修改姓名、邮箱或重置登录密码。' : '创建后学员可直接使用账号密码登录前台。'; ?></span></div></div>
            <form method="post" class="mathcourse-student-form">
                <?php wp_nonce_field('mathcourse_student_' . $action, 'mathcourse_student_nonce'); ?>
                <input type="hidden" name="mathcourse_student_action" value="<?php echo esc_attr($action); ?>">
                <?php if ($is_edit) : ?><input type="hidden" name="student_id" value="<?php echo esc_attr($user->ID); ?>"><?php endif; ?>
                <div class="mathcourse-student-form-grid">
                    <p><label>登录账号</label><input type="text" name="user_login" value="<?php echo esc_attr($is_edit ? $user->user_login : ''); ?>" <?php echo $is_edit ? 'readonly' : 'required'; ?>></p>
                    <p><label>学员姓名</label><input type="text" name="display_name" value="<?php echo esc_attr($is_edit ? $user->display_name : ''); ?>" placeholder="例如：张同学"></p>
                    <p><label>邮箱（可选）</label><input type="email" name="user_email" value="<?php echo esc_attr($is_edit ? $user->user_email : ''); ?>"></p>
                    <p><label><?php echo $is_edit ? '新密码（留空不修改）' : '登录密码'; ?></label><input type="password" name="user_pass" minlength="8" <?php echo $is_edit ? '' : 'required'; ?> autocomplete="new-password"></p>
                </div>
                <div class="mathcourse-student-form-actions"><button type="submit" class="button button-primary"><?php echo $is_edit ? '保存学员' : '创建学员'; ?></button><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mathcourse-students')); ?>">取消</a></div>
            </form>
        </div>
        <?php
    }

    private function handle_post() {
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? '') || empty($_POST['mathcourse_student_action'])) return null;
        $action = sanitize_key(wp_unslash($_POST['mathcourse_student_action']));
        if (!in_array($action, array('create','edit'), true)) return null;
        if (empty($_POST['mathcourse_student_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_student_nonce'])), 'mathcourse_student_' . $action)) return array('type'=>'notice-error','message'=>'页面已过期，请刷新后重试。');
        $login = sanitize_user(wp_unslash($_POST['user_login'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));
        $pass = (string) wp_unslash($_POST['user_pass'] ?? '');
        if ('create' === $action) {
            if ($login === '' || $pass === '') return array('type'=>'notice-error','message'=>'请填写登录账号和密码。');
            if (strlen($pass) < 8) return array('type'=>'notice-error','message'=>'密码至少需要 8 位。');
            if (username_exists($login)) return array('type'=>'notice-error','message'=>'该登录账号已经存在。');
            if ($email !== '' && email_exists($email)) return array('type'=>'notice-error','message'=>'该邮箱已经被其他账号使用。');
            $id = wp_insert_user(array('user_login'=>$login,'user_pass'=>$pass,'user_email'=>$email,'display_name'=>$name ?: $login,'role'=>'subscriber'));
            if (is_wp_error($id)) return array('type'=>'notice-error','message'=>$id->get_error_message());
            return array('type'=>'notice-success','message'=>'学员“' . ($name ?: $login) . '”创建成功。');
        }
        $id = absint($_POST['student_id'] ?? 0);
        $user = $id ? get_userdata($id) : false;
        if (!$user || !in_array('subscriber', (array)$user->roles, true)) return array('type'=>'notice-error','message'=>'学员账号不存在或不是学员账号。');
        if ($email !== '' && $email !== $user->user_email) { $owner = email_exists($email); if ($owner && (int)$owner !== $id) return array('type'=>'notice-error','message'=>'该邮箱已经被其他账号使用。'); }
        $data = array('ID'=>$id,'display_name'=>$name ?: $user->user_login,'user_email'=>$email);
        if ($pass !== '') { if (strlen($pass) < 8) return array('type'=>'notice-error','message'=>'新密码至少需要 8 位。'); $data['user_pass']=$pass; }
        $result = wp_update_user($data);
        if (is_wp_error($result)) return array('type'=>'notice-error','message'=>$result->get_error_message());
        return array('type'=>'notice-success','message'=>'学员信息已保存。');
    }
}
