<?php
namespace MathCourse\Frontend;
defined('ABSPATH') || exit;

class Student_Auth {
    private function login_url(){
        $pages = get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>1,'meta_key'=>'_mathcourse_student_login','meta_value'=>'yes'));
        if($pages) return get_permalink($pages[0]->ID);
        $page = get_page_by_path('student-login', OBJECT, 'page');
        return $page ? get_permalink($page->ID) : home_url('/student-login/');
    }

    public static function ensure_login_page(){
        // Prefer the canonical slug first. An older installation may contain a
        // stale page carrying the login marker while /student-login/ points to
        // another page. The canonical URL must always own the login screen.
        $page = get_page_by_path('student-login', OBJECT, 'page');
        if(!$page){
            $pages = get_posts(array('post_type'=>'page','post_status'=>'any','posts_per_page'=>1,'meta_key'=>'_mathcourse_student_login','meta_value'=>'yes'));
            $page = $pages ? $pages[0] : null;
        }
        if($page){
            $id = (int)$page->ID;
            $changes = array('ID' => $id);
            $needs_update = false;
            if($page->post_title !== '学员登录'){
                $changes['post_title'] = '学员登录';
                $needs_update = true;
            }
            if($page->post_name !== 'student-login'){
                $changes['post_name'] = 'student-login';
                $needs_update = true;
            }
            if(trim((string)$page->post_content) !== '[math_student_login]'){
                $changes['post_content'] = '[math_student_login]';
                $needs_update = true;
            }
            if($page->post_status !== 'publish'){
                $changes['post_status'] = 'publish';
                $needs_update = true;
            }
            if($needs_update) wp_update_post($changes);
            // Remove any stale custom page-template assignment left by an old
            // learning-page setup. The login page is not a learning/player page.
            if(get_post_meta($id,'_wp_page_template',true) !== 'default') update_post_meta($id,'_wp_page_template','default');
            if(get_post_meta($id,'_mathcourse_student_login',true) !== 'yes') update_post_meta($id,'_mathcourse_student_login','yes');
            return $id;
        }
        $id = wp_insert_post(array(
            'post_title' => '学员登录',
            'post_name' => 'student-login',
            'post_content' => '[math_student_login]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ), true);
        if(is_wp_error($id)) return 0;
        update_post_meta($id,'_mathcourse_student_login','yes');
        update_post_meta($id,'_wp_page_template','default');
        return (int)$id;
    }

    public function __construct(){
        add_shortcode('math_student_login',array($this,'render'));
        add_action('init',array(__CLASS__,'ensure_login_page'),1);
        // A legacy page can retain the learning template even after its
        // database template setting is repaired. Force only /student-login/
        // through the theme's normal content template at render time.
        add_filter('template_include',array($this,'force_login_template'),9999);
        add_filter('login_redirect',array($this,'login_redirect'),10,3);
        add_action('admin_init',array($this,'block_student_admin'));
        add_action('wp_login',array($this,'enforce_frontend_login'),10,2);
    }

    public function force_login_template($template){
        if(!is_page('student-login')) return $template;
        $fallback = locate_template('index.php');
        return $fallback ? $fallback : $template;
    }

    public function login_redirect($redirect_to,$requested,$user){
        if($user instanceof \WP_User && !in_array('administrator',(array)$user->roles,true)) return home_url('/course-center/');
        return $redirect_to;
    }

    public function block_student_admin(){
        if(!is_user_logged_in() || current_user_can('manage_options')) return;
        wp_safe_redirect($this->login_url());
        exit;
    }

    public function enforce_frontend_login($username,$user){
        if($user instanceof \WP_User && !in_array('administrator',(array)$user->roles,true)) set_transient('mathcourse_front_login_'.get_current_user_id(),'1',30);
    }

    public function render(){
        if(is_user_logged_in()){
            return '<main class="mc-student-login-page"><section class="mc-student-login-card mc-student-login-card--logged"><div class="mc-student-login-brand"><span class="mc-student-login-book" aria-hidden="true"></span><strong>樊老师数学</strong></div><h1>你已经登录</h1><p>进入课程中心，继续学习已激活的课程。</p><a class="mc-student-login-primary" href="'.esc_url(home_url('/course-center/')).'">进入课程中心</a></section></main>';
        }
        $error = '';
        if('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['mathcourse_login_nonce'])){
            if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_login_nonce'])),'mathcourse_login')){
                $error = '页面已过期，请刷新后重试。';
            }else{
                $login = sanitize_text_field(wp_unslash($_POST['login'] ?? ''));
                $password = (string)wp_unslash($_POST['password'] ?? '');
                $user = wp_signon(array('user_login'=>$login,'user_password'=>$password,'remember'=>!empty($_POST['remember'])), is_ssl());
                if(is_wp_error($user)){
                    $error = '账号或密码错误，请重新输入。';
                }elseif(in_array('administrator',(array)$user->roles,true)){
                    wp_safe_redirect(admin_url());
                }else{
                    wp_safe_redirect(home_url('/course-center/'));
                }
                exit;
            }
        }
        ob_start();
        ?>
        <main class="mc-student-login-page">
            <section class="mc-student-login-card" aria-labelledby="mc-student-login-title">
                <div class="mc-student-login-brand">
                    <span class="mc-student-login-book" aria-hidden="true"><i></i></span>
                    <div><strong>樊老师数学</strong><small>好方法 · 学得更轻松</small></div>
                </div>
                <div class="mc-student-login-heading">
                    <h1 id="mc-student-login-title">学员登录</h1>
                    <p>登录后即可进入已激活的课程</p>
                </div>
                <?php if($error): ?><div class="mc-student-login-error" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
                <form method="post" class="mc-student-login-form">
                    <?php wp_nonce_field('mathcourse_login','mathcourse_login_nonce'); ?>
                    <label for="mc-login-account">账号</label>
                    <div class="mc-student-login-input"><span aria-hidden="true">●</span><input id="mc-login-account" name="login" autocomplete="username" placeholder="请输入学员账号" required></div>
                    <label for="mc-login-password">密码</label>
                    <div class="mc-student-login-input"><span aria-hidden="true">●</span><input id="mc-login-password" type="password" name="password" autocomplete="current-password" placeholder="请输入登录密码" required></div>
                    <label class="mc-student-login-remember"><input type="checkbox" name="remember" value="1"> <span>记住登录状态</span></label>
                    <button type="submit" class="mc-student-login-primary">登录</button>
                </form>
                <div class="mc-student-login-contact">
                    <strong>还没有账号？</strong>
                    <span>请微信联系樊老师获取学员账号。</span>
                    <b>微信：fanlaoshi027</b>
                </div>
            </section>
            <p class="mc-student-login-footnote">课程账号仅供本人学习使用，请妥善保管账号和密码。</p>
        </main>
        <?php
        return ob_get_clean();
    }

    private function register_url(){
        $pages = get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>1,'meta_key'=>'_mathcourse_activation_register','meta_value'=>'yes'));
        return $pages ? get_permalink($pages[0]->ID) : home_url('/student-register/');
    }
}
