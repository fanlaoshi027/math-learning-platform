<?php
namespace MathCourse\Admin;
defined('ABSPATH') || exit;
use MathCourse\Tutor\Adapter;
use MathCourse\Access\Activation_Service;
class Activation_Page {
 public function __construct(){add_action('admin_post_mathcourse_generate_codes',array($this,'generate'));add_action('admin_post_mathcourse_revoke_code',array($this,'revoke'));}
 public function render(){
  if(!current_user_can('manage_options'))return;
  $courses=(new Adapter())->get_courses(true,-1);
  $service=new Activation_Service();
  $codes=get_transient('mathcourse_generated_codes_'.get_current_user_id());
  if(false!==$codes)delete_transient('mathcourse_generated_codes_'.get_current_user_id());
  $course_filter=absint($_GET['course_id']??0);
  $status=sanitize_key($_GET['status']??'');
  if(!in_array($status,array('','unused','used','expired','revoked'),true))$status='';
  $page=max(1,absint($_GET['paged']??1));$per_page=30;
  $total=$service->count_codes($course_filter,$status);
  $rows=$service->list_codes(array('course_id'=>$course_filter,'status'=>$status,'limit'=>$per_page,'offset'=>($page-1)*$per_page));
  $stat_total=$service->count_codes($course_filter,'');
  $stat_unused=$service->count_codes($course_filter,'unused');
  $stat_used=$service->count_codes($course_filter,'used');
  $stat_expired=$service->count_codes($course_filter,'expired');
  $stat_revoked=$service->count_codes($course_filter,'revoked');
  ?>
<div class="wrap mathcourse-admin-wrap mathcourse-activation-admin">
 <div class="mathcourse-admin-header"><h1>课程激活码</h1><p>生成与课程绑定的一次性激活码。学员使用激活码注册后，会自动获得对应课程授权。</p></div>
 <div class="mathcourse-activation-stats">
  <div class="mathcourse-activation-stat"><strong><?php echo esc_html($stat_total); ?></strong><span>全部</span></div>
  <div class="mathcourse-activation-stat unused"><strong><?php echo esc_html($stat_unused); ?></strong><span>未使用</span></div>
  <div class="mathcourse-activation-stat used"><strong><?php echo esc_html($stat_used); ?></strong><span>已使用</span></div>
  <div class="mathcourse-activation-stat expired"><strong><?php echo esc_html($stat_expired); ?></strong><span>已过期</span></div>
  <div class="mathcourse-activation-stat revoked"><strong><?php echo esc_html($stat_revoked); ?></strong><span>已撤销</span></div>
 </div>
 <div class="mathcourse-card">
  <h2>生成激活码</h2>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mathcourse-activation-form">
   <?php wp_nonce_field('mathcourse_generate_codes'); ?><input type="hidden" name="action" value="mathcourse_generate_codes">
   <p><label>课程</label><select name="course_id" required><option value="">请选择课程</option><?php foreach($courses as $course): ?><option value="<?php echo esc_attr($course->ID); ?>" <?php selected($course_filter,$course->ID); ?>><?php echo esc_html($course->post_title); ?></option><?php endforeach; ?></select></p>
   <p><label>生成数量</label><input type="number" name="quantity" min="1" max="500" value="10" required></p>
   <p><label>有效期</label><select name="expiry"><option value="0">不限（推荐）</option><option value="365">生成后 365 天</option><option value="90">生成后 90 天</option><option value="30">生成后 30 天</option></select></p>
   <p><button class="button button-primary" type="submit">生成激活码</button></p>
  </form>
 </div>
 <?php if(false!==$codes&&is_array($codes)): ?>
 <div class="mathcourse-card mathcourse-generated-card">
  <div class="mathcourse-card-title"><h2>本次生成的激活码</h2><div><button type="button" class="button" id="mathcourse-copy-codes">复制全部</button> <button type="button" class="button" id="mathcourse-download-codes">保存 TXT</button></div></div>
  <p>激活码只在这里显示一次，请复制保存后再发给学员。数据库不保存明文激活码。</p>
  <textarea class="mathcourse-code-output" id="mathcourse-code-output" rows="12" readonly><?php echo esc_textarea(implode("\n",$codes)); ?></textarea>
 </div>
 <?php endif; ?>
 <div class="mathcourse-card">
  <div class="mathcourse-card-title"><h2><?php echo $course_filter?'当前课程的激活码':'全部课程激活码'; ?></h2><div>当前列表 <?php echo esc_html($total); ?> 条</div></div>
  <form method="get" class="mathcourse-activation-filter"><input type="hidden" name="page" value="mathcourse-activation"><select name="course_id"><option value="0">全部课程</option><?php foreach($courses as $course): ?><option value="<?php echo esc_attr($course->ID); ?>" <?php selected($course_filter,$course->ID); ?>><?php echo esc_html($course->post_title); ?></option><?php endforeach; ?></select><select name="status"><option value="">全部状态</option><option value="unused" <?php selected($status,'unused'); ?>>未使用</option><option value="used" <?php selected($status,'used'); ?>>已使用</option><option value="expired" <?php selected($status,'expired'); ?>>已过期</option><option value="revoked" <?php selected($status,'revoked'); ?>>已撤销</option></select><button class="button" type="submit">筛选</button></form>
  <div class="mathcourse-activation-quick-courses"><span>快速进入课程：</span><?php foreach($courses as $course): ?><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>'mathcourse-activation','course_id'=>$course->ID),admin_url('admin.php'))); ?>"><?php echo esc_html($course->post_title); ?></a><?php endforeach; ?></div>
  <div class="mathcourse-activation-table-wrap"><table class="mathcourse-table"><thead><tr><th>ID</th><th>课程</th><th>状态</th><th>生成时间</th><th>有效期</th><th>使用学员</th><th>使用时间</th><th>操作</th></tr></thead><tbody>
  <?php if(empty($rows)): ?><tr><td colspan="8">暂无符合条件的激活码。</td></tr><?php else: foreach($rows as $row): $course=get_post($row->course_id);$user=$row->used_by?get_user_by('id',$row->used_by):false;$labels=array('unused'=>'未使用','used'=>'已使用','expired'=>'已过期','revoked'=>'已撤销');$label=$labels[$row->status]??$row->status; ?>
  <tr><td><?php echo esc_html($row->id); ?></td><td><?php echo esc_html($course?$course->post_title:'课程已删除'); ?></td><td><span class="mathcourse-badge <?php echo esc_attr($row->status); ?>"><?php echo esc_html($label); ?></span></td><td><?php echo esc_html($row->created_at); ?></td><td><?php echo esc_html($row->expires_at?$row->expires_at:'不限'); ?></td><td><?php echo esc_html($user?$user->user_login:'—'); ?></td><td><?php echo esc_html($row->used_at?$row->used_at:'—'); ?></td><td><?php if('unused'===$row->status): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('确定撤销这个激活码吗？撤销后不能再使用。');"><?php wp_nonce_field('mathcourse_revoke_code_'.$row->id); ?><input type="hidden" name="action" value="mathcourse_revoke_code"><input type="hidden" name="code_id" value="<?php echo esc_attr($row->id); ?>"><button class="button-link-delete" type="submit">撤销</button></form><?php else: ?>—<?php endif; ?></td></tr>
  <?php endforeach; endif; ?></tbody></table></div>
  <?php if($total>$per_page): ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post(paginate_links(array('base'=>add_query_arg(array('page'=>'mathcourse-activation','course_id'=>$course_filter,'status'=>$status,'paged'=>'%#%'),admin_url('admin.php')),'format'=>'','current'=>$page,'total'=>ceil($total/$per_page),'type'=>'plain'))); ?></div></div><?php endif; ?>
 </div>
</div>
<script>(function(){var out=document.getElementById('mathcourse-code-output');var copy=document.getElementById('mathcourse-copy-codes');var download=document.getElementById('mathcourse-download-codes');if(copy&&out){copy.addEventListener('click',function(){navigator.clipboard&&navigator.clipboard.writeText(out.value).then(function(){copy.textContent='已复制';setTimeout(function(){copy.textContent='复制全部';},1500);}).catch(function(){out.select();document.execCommand('copy');copy.textContent='已复制';setTimeout(function(){copy.textContent='复制全部';},1500);});});}if(download&&out){download.addEventListener('click',function(){var blob=new Blob([out.value+'\n'],{type:'text/plain;charset=utf-8'});var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='课程激活码.txt';a.click();setTimeout(function(){URL.revokeObjectURL(a.href);},1000);});}})();</script>
<?php }
 public function generate(){if(!current_user_can('manage_options'))wp_die('无权限');check_admin_referer('mathcourse_generate_codes');$course_id=absint($_POST['course_id']??0);if(!$course_id||'publish'!==get_post_status($course_id))wp_die('请选择有效课程。');$quantity=max(1,min(500,absint($_POST['quantity']??10)));$expiry=absint($_POST['expiry']??0);$expires=$expiry>0?gmdate('Y-m-d H:i:s',time()+$expiry*DAY_IN_SECONDS):null;$codes=(new Activation_Service())->generate($course_id,$quantity,$expires);set_transient('mathcourse_generated_codes_'.get_current_user_id(),$codes,10*MINUTE_IN_SECONDS);wp_safe_redirect(admin_url('admin.php?page=mathcourse-activation&course_id='.$course_id));exit;}
 public function revoke(){if(!current_user_can('manage_options'))wp_die('无权限');$id=absint($_POST['code_id']??0);check_admin_referer('mathcourse_revoke_code_'.$id);(new Activation_Service())->revoke($id);wp_safe_redirect(wp_get_referer()?wp_get_referer():admin_url('admin.php?page=mathcourse-activation'));exit;}
}
