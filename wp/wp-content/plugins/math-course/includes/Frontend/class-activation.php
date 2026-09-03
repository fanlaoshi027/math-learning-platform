<?php
namespace MathCourse\Frontend;
defined('ABSPATH') || exit;
use MathCourse\Access\Activation_Service;
class Activation {
 public function __construct(){add_shortcode('math_student_register',array($this,'register_shortcode'));add_filter('option_users_can_register',array($this,'disable_open_registration'));add_filter('registration_errors',array($this,'block_open_registration'),10,3);add_filter('register_url',array($this,'replace_register_url'),10,1);add_filter('login_url',array($this,'replace_login_url'),10,3);}
 public function disable_open_registration($value){return false;}
 public function block_open_registration($errors,$sanitized_user_login,$user_email){$errors->add('mathcourse_activation_required','请使用课程激活注册页面，并输入老师提供的课程激活码。');return $errors;}
 public function replace_register_url($url){$page=$this->get_register_page();return $page?$page:$url;}
 public function replace_login_url($login_url,$redirect='',$force_reauth=false){return $login_url;}
 private function get_register_page(){
  $pages=get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>1,'suppress_filters'=>false,'meta_query'=>array(array('key'=>'_mathcourse_activation_register','value'=>'yes'))));
  if($pages)return get_permalink($pages[0]->ID);
  return '';
 }
 public static function ensure_register_page(){
  $existing=get_posts(array('post_type'=>'page','post_status'=>'any','posts_per_page'=>1,'meta_key'=>'_mathcourse_activation_register','meta_value'=>'yes'));
  if($existing)return (int)$existing[0]->ID;
  $page_id=wp_insert_post(array('post_title'=>'学员注册','post_name'=>'student-register','post_content'=>'[math_student_register]','post_status'=>'publish','post_type'=>'page'),true);
  if(is_wp_error($page_id))return 0;
  update_post_meta($page_id,'_mathcourse_activation_register','yes');
  return (int)$page_id;
 }
 private function rate_key(){ $ip=sanitize_text_field($_SERVER['REMOTE_ADDR']??'unknown'); return 'mathcourse_activation_rate_'.hash_hmac('sha256',$ip,wp_salt('auth')); }
 private function rate_limited(){ $key=$this->rate_key();$data=get_transient($key);return is_array($data)&&!empty($data['blocked']); }
 private function record_failure(){ $key=$this->rate_key();$data=get_transient($key);if(!is_array($data))$data=array('count'=>0,'blocked'=>false);$data['count']=(int)$data['count']+1;if($data['count']>=10)$data['blocked']=true;set_transient($key,$data,10*MINUTE_IN_SECONDS); }
 public function register_shortcode(){
  if(is_user_logged_in())return '<div class="mathcourse-activation-card"><h2>你已经登录</h2><p>当前账号无需重复注册。</p></div>';
  $error='';$success='';
  if('POST'===strtoupper($_SERVER['REQUEST_METHOD']??'')&&isset($_POST['mathcourse_register_nonce'])){
   if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_register_nonce'])),'mathcourse_register'))$error='页面已过期，请刷新后重试。';
   elseif($this->rate_limited())$error='尝试次数过多，请 10 分钟后再试。';
   else{
    $code=sanitize_text_field(wp_unslash($_POST['activation_code']??''));$username=sanitize_user(wp_unslash($_POST['username']??''));$password=(string)wp_unslash($_POST['password']??'');$confirm=(string)wp_unslash($_POST['password_confirm']??'');
    if(!$code||!$username||!$password)$error='请完整填写激活码、账号和密码。';elseif(strlen($password)<8)$error='密码至少需要 8 位。';elseif($password!==$confirm)$error='两次输入的密码不一致。';elseif(username_exists($username))$error='该账号已存在，请换一个账号。';else{
     global $wpdb;$table=$wpdb->prefix.'mathcourse_activation_codes';$hash=hash_hmac('sha256',strtoupper(preg_replace('/[^A-Z0-9]/i','',$code)),wp_salt('auth'));$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code_hash=%s LIMIT 1",$hash));
     if(!$row)$error='激活码无效。';elseif('unused'!==$row->status)$error='激活码已使用或已失效。';elseif(!empty($row->expires_at)&&strtotime($row->expires_at)<=current_time('timestamp'))$error='激活码已过期。';
     if($error)$this->record_failure();
     if(!$error){$user_id=wp_insert_user(array('user_login'=>$username,'user_pass'=>$password,'role'=>'subscriber'));if(is_wp_error($user_id))$error=$user_id->get_error_message();else{$result=(new Activation_Service())->redeem($code,$user_id);if(is_wp_error($result)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user_id);$error=$result->get_error_message();$this->record_failure();}else{wp_set_auth_cookie($user_id,true);$success='注册成功，课程已自动激活。';}}}
    }
   }
  }
  ob_start();?><div class="mathcourse-activation-card"><h2>课程激活注册</h2><p class="mathcourse-activation-tip">请输入老师提供的课程激活码。注册成功后，对应课程会自动加入你的账号。</p><?php if($error):?><div class="mathcourse-activation-error"><?php echo esc_html($error);?></div><?php endif;?><?php if($success):?><div class="mathcourse-activation-success"><?php echo esc_html($success);?></div><p><a class="button" href="<?php echo esc_url(home_url('/'));?>">进入课程</a></p><?php else:?><form method="post"><?php wp_nonce_field('mathcourse_register','mathcourse_register_nonce');?><p><label>课程激活码</label><input name="activation_code" autocomplete="one-time-code" placeholder="例如 8S7K-4P2M-X9QD" required></p><p><label>登录账号</label><input name="username" autocomplete="username" required></p><p><label>设置密码</label><input type="password" name="password" autocomplete="new-password" minlength="8" required></p><p><label>确认密码</label><input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required></p><button type="submit">注册并激活课程</button></form><?php endif;?></div><?php return ob_get_clean();
 }
}
