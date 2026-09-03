<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Tutor\Adapter;

/**
 * 新版批量课时编辑器。
 * 一个视频对应一个 Lesson，教材页码允许 P6、P6-P8 等任意文本范围，不要求连续。
 */
class Batch_Manager_V2 {
    private $tutor;

    public function __construct() {
        $this->tutor = new Adapter();
        add_action('admin_post_mathcourse_batch_v2_create', array($this, 'create_task'));
        add_action('wp_ajax_mathcourse_batch_v2_step', array($this, 'ajax_step'));
    }

    public function render() {
        if (!current_user_can('manage_options')) wp_die('没有权限访问此页面。');
        if (!$this->tutor->is_available()) {
            echo '<div class="wrap"><div class="mc-batch-v2"><div class="mc-batch-alert">请先启用 Tutor LMS 4.0.4。</div></div></div>';
            return;
        }

        $courses = $this->tutor->get_courses(true, 200);
        $task_id = isset($_GET['task_id']) ? sanitize_key(wp_unslash($_GET['task_id'])) : '';
        $task = $task_id ? get_option('mathcourse_batch_v2_' . $task_id, null) : null;
        if ($task) {
            $this->render_task($task);
            return;
        }
        $this->render_form($courses);
    }

    private function render_form($courses) {
        ?>
        <div class="wrap mc-batch-v2">
            <div class="mc-batch-head">
                <div>
                    <div class="mc-eyebrow">MathCourse · 课程工具</div>
                    <h1>批量添加课时</h1>
                    <p>一次整理多个视频课时。每一行就是一个课时，页码可以是不连续的范围，例如 <strong>P6-P8</strong>。</p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="mc-batch-v2-form">
                <input type="hidden" name="action" value="mathcourse_batch_v2_create">
                <?php wp_nonce_field('mathcourse_batch_v2_create'); ?>

                <section class="mc-panel mc-course-panel">
                    <div class="mc-panel-title"><span class="mc-step">1</span><div><h2>选择课程</h2><p>先确定这些课时要放入哪门课程。</p></div></div>
                    <select name="course_id" required>
                        <option value="">请选择课程</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->ID); ?>"><?php echo esc_html($course->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </section>

                <section class="mc-panel">
                    <div class="mc-panel-title mc-title-between">
                        <div><span class="mc-step">2</span><div><h2>设置专题</h2><p>课时将按顺序加入这个专题。</p></div></div>
                        <input class="mc-topic-input" name="topic_title" value="大培优配套" required aria-label="专题名称">
                    </div>
                </section>

                <section class="mc-panel">
                    <div class="mc-panel-title mc-title-between">
                        <div><span class="mc-step">3</span><div><h2>课时清单</h2><p>不需要连续页码。一个视频对应一行课时。</p></div></div>
                        <button type="button" class="button" id="mc-add-row">＋ 添加课时</button>
                    </div>
                    <div class="mc-desktop-table">
                        <div class="mc-row mc-row-head"><span>#</span><span>课时名称</span><span>教材页码</span><span>视频ID</span><span>HLS 地址</span><span>试看</span><span></span></div>
                        <div id="mc-batch-rows"></div>
                    </div>
                    <div class="mc-mobile-rows" id="mc-batch-mobile-rows"></div>
                    <div class="mc-empty" id="mc-empty">还没有课时，点击右上角「添加课时」开始。</div>
                </section>

                <section class="mc-submit-bar">
                    <div><strong id="mc-row-count">0 个课时</strong><span>　页码只用于记录教材范围，不会自动补齐中间页码。</span></div>
                    <button class="button button-primary button-large" type="submit">创建全部课时</button>
                </section>
            </form>
        </div>
        <style>
        .mc-batch-v2{max-width:1380px;margin:24px 28px 50px 0;color:#172033}.mc-batch-head{background:linear-gradient(135deg,#f8fbff,#fff);border:1px solid #e3e8f0;border-radius:18px;padding:26px 30px;margin-bottom:16px}.mc-eyebrow{font-size:12px;font-weight:700;color:#2563eb;letter-spacing:.08em;text-transform:uppercase}.mc-batch-head h1{font-size:28px;margin:7px 0}.mc-batch-head p,.mc-panel-title p{color:#64748b;margin:0;line-height:1.6}.mc-panel{background:#fff;border:1px solid #e3e8f0;border-radius:16px;padding:22px;margin-bottom:14px;box-shadow:0 5px 20px rgba(15,23,42,.035)}.mc-panel-title{display:flex;align-items:center;gap:12px;margin-bottom:16px}.mc-panel-title>div:last-child h2{font-size:17px;margin:0 0 3px}.mc-step{display:grid;place-items:center;width:30px;height:30px;border-radius:9px;background:#eff6ff;color:#2563eb;font-weight:800;flex:none}.mc-title-between{justify-content:space-between}.mc-title-between>div:first-child{display:flex;gap:12px;align-items:center}.mc-batch-v2 select,.mc-batch-v2 input{min-height:42px;border:1px solid #d7dee9;border-radius:9px;box-shadow:none;padding:8px 11px;background:#fff}.mc-batch-v2 select{width:100%;max-width:680px}.mc-topic-input{width:300px}.mc-batch-v2 input:focus,.mc-batch-v2 select:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1);outline:none}.mc-row{display:grid;grid-template-columns:42px minmax(190px,1.25fr) 120px minmax(130px,1fr) minmax(220px,1.5fr) 55px 70px;gap:8px;align-items:center}.mc-row-head{padding:0 10px 8px;color:#94a3b8;font-size:12px;font-weight:700}.mc-row-item{padding:9px 10px;border:1px solid #e7ebf1;border-radius:11px;margin-bottom:8px;background:#fbfcfe}.mc-row-item input{width:100%;box-sizing:border-box}.mc-index{font-weight:800;color:#64748b;text-align:center}.mc-preview-check{display:flex;justify-content:center}.mc-preview-check input{width:18px;height:18px;min-height:18px}.mc-delete{border:0;background:none;color:#94a3b8;cursor:pointer;font-size:18px}.mc-delete:hover{color:#ef4444}.mc-mobile-rows{display:none}.mc-empty{border:1px dashed #cbd5e1;border-radius:12px;padding:28px;text-align:center;color:#94a3b8}.mc-submit-bar{position:sticky;bottom:14px;z-index:5;background:#fff;border:1px solid #e3e8f0;border-radius:14px;padding:13px 16px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 10px 30px rgba(15,23,42,.1)}.mc-submit-bar span{color:#94a3b8;font-size:13px}.mc-batch-alert{padding:20px;background:#fff4f4;border:1px solid #fecaca;border-radius:12px;color:#991b1b}
        @media(max-width:782px){.mc-batch-v2{margin:12px 10px 30px 0}.mc-batch-head{padding:19px 16px;border-radius:14px}.mc-batch-head h1{font-size:22px}.mc-panel{padding:15px;border-radius:13px}.mc-title-between{display:block}.mc-title-between>div:first-child{margin-bottom:12px}.mc-topic-input{width:100%;box-sizing:border-box}.mc-desktop-table{display:none}.mc-mobile-rows{display:block}.mc-mobile-card{border:1px solid #e3e8f0;border-radius:13px;padding:13px;margin-bottom:10px;background:#fff}.mc-mobile-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.mc-mobile-num{font-weight:800;color:#2563eb}.mc-mobile-card label{display:block;font-size:12px;font-weight:700;color:#64748b;margin:9px 0 5px}.mc-mobile-card input{width:100%;box-sizing:border-box;min-height:44px;font-size:16px}.mc-mobile-card .mc-mobile-inline{display:grid;grid-template-columns:1fr 1fr;gap:9px}.mc-mobile-preview{display:flex;align-items:center;justify-content:space-between;margin-top:11px;padding-top:11px;border-top:1px solid #eef1f5}.mc-mobile-preview input{width:20px;min-height:20px}.mc-panel-title .button{min-height:42px}.mc-submit-bar{bottom:8px;padding:10px;gap:10px}.mc-submit-bar>div{min-width:0}.mc-submit-bar span{display:none}.mc-submit-bar .button{min-height:44px}.mc-empty{padding:20px}.mc-row-count{font-size:13px}}
        </style>
        <script>
        (function(){
            var desktop=document.getElementById('mc-batch-rows'), mobile=document.getElementById('mc-batch-mobile-rows'), empty=document.getElementById('mc-empty'), count=document.getElementById('mc-row-count'), add=document.getElementById('mc-add-row'), form=document.getElementById('mc-batch-v2-form');
            if(!desktop||!mobile)return;
            var index=0;
            function esc(s){return String(s||'').replace(/[&<>\"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c]})}
            function addRow(data){data=data||{};index++;var n=index;
                var d=document.createElement('div');d.className='mc-row mc-row-item';d.dataset.n=n;d.innerHTML='<span class="mc-index">'+n+'</span><input name="items['+n+'][title]" value="'+esc(data.title)+'" placeholder="例如：第5讲 综合练习" required><input name="items['+n+'][page]" value="'+esc(data.page)+'" placeholder="P6-P8"><input name="items['+n+'][video]" value="'+esc(data.video)+'" placeholder="video_005"><input name="items['+n+'][hls]" value="'+esc(data.hls)+'" placeholder="/uploads/.../index.m3u8"><label class="mc-preview-check"><input type="checkbox" name="items['+n+'][preview]" value="yes" '+(data.preview?'checked':'')+'></label><button type="button" class="mc-delete" aria-label="删除">×</button>';
                desktop.appendChild(d);
                var m=document.createElement('div');m.className='mc-mobile-card';m.dataset.n=n;m.innerHTML='<div class="mc-mobile-card-head"><strong class="mc-mobile-num">课时 '+n+'</strong><button type="button" class="mc-delete" aria-label="删除">×</button></div><label>课时名称</label><input name="items['+n+'][title]" value="'+esc(data.title)+'" placeholder="例如：第5讲 综合练习" required><div class="mc-mobile-inline"><div><label>教材页码</label><input name="items['+n+'][page]" value="'+esc(data.page)+'" placeholder="P6-P8"></div><div><label>视频ID</label><input name="items['+n+'][video]" value="'+esc(data.video)+'" placeholder="video_005"></div></div><label>HLS 地址</label><input name="items['+n+'][hls]" value="'+esc(data.hls)+'" placeholder="/uploads/.../index.m3u8"><div class="mc-mobile-preview"><span>免费试看</span><input type="checkbox" name="items['+n+'][preview]" value="yes" '+(data.preview?'checked':'')+'></div>';
                mobile.appendChild(m);
                [d,m].forEach(function(el){el.querySelector('.mc-delete').addEventListener('click',function(){d.remove();m.remove();renumber()})});renumber();
            }
            function renumber(){var ds=desktop.children;Array.prototype.forEach.call(ds,function(el,i){el.querySelector('.mc-index').textContent=i+1});var ms=mobile.children;Array.prototype.forEach.call(ms,function(el,i){el.querySelector('.mc-mobile-num').textContent='课时 '+(i+1)});var total=ds.length;empty.style.display=total?'none':'block';count.textContent=total+' 个课时'}
            add.addEventListener('click',function(){addRow()});
            addRow({title:'',page:'',video:'',hls:'',preview:false});
            form.addEventListener('submit',function(e){if(!desktop.children.length){e.preventDefault();alert('请至少添加一个课时。')}});
        })();
        </script>
        <?php
    }

    public function create_task(){
        if(!current_user_can('manage_options'))wp_die('没有权限。');
        check_admin_referer('mathcourse_batch_v2_create');
        $course_id=isset($_POST['course_id'])?absint($_POST['course_id']):0;
        $topic_title=isset($_POST['topic_title'])?sanitize_text_field(wp_unslash($_POST['topic_title'])):'';
        $raw=isset($_POST['items'])&&is_array($_POST['items'])?wp_unslash($_POST['items']):array();
        if(!$course_id||!$this->tutor->get_course($course_id)||!$topic_title)wp_die('课程或专题参数无效。');
        if(!current_user_can('edit_post',$course_id))wp_die('没有权限编辑此课程。');
        $items=array();
        foreach($raw as $item){
            if(!is_array($item))continue;
            $title=sanitize_text_field($item['title']??'');
            if($title==='')continue;
            $items[]=array('title'=>$title,'page'=>sanitize_text_field($item['page']??''),'video'=>sanitize_text_field($item['video']??''),'hls'=>sanitize_text_field(trim((string)($item['hls']??''))),'preview'=>isset($item['preview'])?'yes':'no');
        }
        if(!$items)wp_die('请至少添加一个有效课时。');
        $task_id=wp_generate_uuid4();
        $task=array('id'=>$task_id,'course_id'=>$course_id,'topic_title'=>$topic_title,'items'=>$items,'current'=>0,'total'=>count($items),'created'=>0,'skipped'=>0,'failed'=>0,'errors'=>array(),'status'=>'pending');
        update_option('mathcourse_batch_v2_'.$task_id,$task,false);
        wp_safe_redirect(add_query_arg(array('page'=>'mathcourse-batch','task_id'=>$task_id),admin_url('admin.php')));exit;
    }

    public function ajax_step(){
        if(!current_user_can('manage_options'))wp_send_json_error(array('message'=>'没有权限。'),403);
        check_ajax_referer('mathcourse_batch_v2_step','nonce');
        $id=isset($_POST['task_id'])?sanitize_key(wp_unslash($_POST['task_id'])):'';
        $task=$id?get_option('mathcourse_batch_v2_'.$id,null):null;
        if(!$task)wp_send_json_error(array('message'=>'任务不存在。'),404);
        if(!current_user_can('edit_post',(int)$task['course_id']))wp_send_json_error(array('message'=>'没有权限。'),403);
        if($task['current']>=$task['total']){$task['status']='completed';update_option('mathcourse_batch_v2_'.$id,$task,false);wp_send_json_success($this->response($task));}
        $topic_id=$this->find_or_create_topic($task);
        if(!$topic_id){$task['status']='paused';$task['errors'][]='无法创建专题。';update_option('mathcourse_batch_v2_'.$id,$task,false);wp_send_json_success($this->response($task));}
        $item=$task['items'][$task['current']];
        $existing=$this->find_existing_lesson($topic_id,$item);
        if($existing){$task['skipped']++;$task['current']++;}
        else{
            $lesson_id=wp_insert_post(array('post_title'=>$item['title'],'post_type'=>$this->tutor->get_lesson_post_type(),'post_status'=>'publish','post_author'=>get_current_user_id(),'post_parent'=>$topic_id,'menu_order'=>$task['current'],'post_content'=>''),true);
            if(is_wp_error($lesson_id)){$task['failed']++;$task['status']='paused';$task['errors'][]='第'.($task['current']+1).'项：'.$lesson_id->get_error_message();}
            else{$lesson_id=absint($lesson_id);$this->save_item_meta($lesson_id,$item);$task['created']++;$task['current']++;}
        }
        if($task['current']>=$task['total']&&$task['status']!=='paused')$task['status']='completed';
        update_option('mathcourse_batch_v2_'.$id,$task,false);wp_send_json_success($this->response($task));
    }

    private function find_or_create_topic($task){
        foreach($this->tutor->get_topics($task['course_id'],true) as $topic)if($topic->post_title===$task['topic_title'])return(int)$topic->ID;
        return $this->tutor->create_topic($task['course_id'],$task['topic_title']);
    }
    private function find_existing_lesson($topic_id,$item){
        foreach($this->tutor->get_lessons($topic_id,true) as $lesson){
            if($lesson->post_title===$item['title'])return(int)$lesson->ID;
            $page=$this->tutor->get_lesson_page_number($lesson->ID);if($item['page']!==''&&$page!==''&&$page===$item['page'])return(int)$lesson->ID;
        }return 0;
    }
    private function save_item_meta($lesson_id,$item){
        update_post_meta($lesson_id,'_mathcourse_page_number',$item['page']);update_post_meta($lesson_id,'_mathcourse_page',$item['page']);
        update_post_meta($lesson_id,'_mathcourse_video_id',$item['video']);
        if($item['hls']!=='')update_post_meta($lesson_id,'_mathcourse_hls_url',$item['hls']);else delete_post_meta($lesson_id,'_mathcourse_hls_url');
        update_post_meta($lesson_id,'_mathcourse_preview',$item['preview']);update_post_meta($lesson_id,'_is_preview',$item['preview']);update_post_meta($lesson_id,'_mathcourse_permission_mode','authorization');
    }
    private function response($task){return array('id'=>$task['id'],'current'=>(int)$task['current'],'total'=>(int)$task['total'],'created'=>(int)$task['created'],'skipped'=>(int)$task['skipped'],'failed'=>(int)$task['failed'],'status'=>$task['status'],'errors'=>array_slice((array)$task['errors'],-5));}
    private function render_task($task){$d=$this->response($task);$p=$d['total']?round($d['current']/$d['total']*100):0;$nonce=wp_create_nonce('mathcourse_batch_v2_step');$url=admin_url('admin-ajax.php');?>
        <div class="wrap mc-batch-v2"><div class="mc-batch-head"><div class="mc-eyebrow">MathCourse · 批量创建</div><h1>正在创建课时</h1><p><?php echo esc_html(get_the_title($task['course_id'])); ?>　·　<?php echo esc_html($task['topic_title']); ?></p></div><section class="mc-panel"><div class="mc-progress"><div id="mc-v2-bar" style="width:<?php echo esc_attr($p); ?>%"></div></div><div class="mc-progress-text"><strong id="mc-v2-percent"><?php echo esc_html($p); ?>%</strong><span id="mc-v2-count"><?php echo esc_html($d['current']); ?> / <?php echo esc_html($d['total']); ?></span></div><div class="mc-v2-stats"><span>新建 <b id="mc-v2-created"><?php echo esc_html($d['created']); ?></b></span><span>跳过 <b id="mc-v2-skipped"><?php echo esc_html($d['skipped']); ?></b></span><span>失败 <b id="mc-v2-failed"><?php echo esc_html($d['failed']); ?></b></span></div><div id="mc-v2-errors"></div><button id="mc-v2-start" class="button button-primary button-large">开始创建</button></section></div>
        <style>.mc-progress{height:12px;background:#edf1f7;border-radius:99px;overflow:hidden}.mc-progress div{height:100%;background:#2563eb;transition:width .25s}.mc-progress-text{display:flex;justify-content:space-between;margin:12px 0 22px}.mc-v2-stats{display:flex;gap:25px;color:#64748b;margin-bottom:22px}.mc-v2-stats b{color:#172033}.mc-v2-stats span{padding:9px 13px;background:#f8fafc;border-radius:9px}.mc-batch-v2 #mc-v2-start{min-height:44px}.mc-v2-error{color:#b91c1c;background:#fff1f2;padding:10px;border-radius:9px;margin-bottom:10px}</style>
        <script>(function(){var btn=document.getElementById('mc-v2-start'),busy=false;function run(){if(busy)return;busy=true;btn.disabled=true;var fd=new FormData();fd.append('action','mathcourse_batch_v2_step');fd.append('task_id','<?php echo esc_js($task['id']); ?>');fd.append('nonce','<?php echo esc_js($nonce); ?>');fetch('<?php echo esc_url_raw($url); ?>',{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json()}).then(function(j){if(!j.success)throw new Error(j.data&&j.data.message?j.data.message:'创建失败');var d=j.data.data||j.data;document.getElementById('mc-v2-bar').style.width=Math.round(d.current/d.total*100)+'%';document.getElementById('mc-v2-percent').textContent=Math.round(d.current/d.total*100)+'%';document.getElementById('mc-v2-count').textContent=d.current+' / '+d.total;document.getElementById('mc-v2-created').textContent=d.created;document.getElementById('mc-v2-skipped').textContent=d.skipped;document.getElementById('mc-v2-failed').textContent=d.failed;if(d.errors&&d.errors.length)document.getElementById('mc-v2-errors').innerHTML=d.errors.map(function(e){return '<div class="mc-v2-error">'+String(e).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]})+'</div>'}).join('');if(d.status==='completed'){btn.textContent='创建完成';busy=false;return}busy=false;btn.disabled=false;run()}).catch(function(e){busy=false;btn.disabled=false;document.getElementById('mc-v2-errors').innerHTML='<div class="mc-v2-error">'+e.message+'</div>'})}btn.addEventListener('click',run);})();</script><?php }
}
