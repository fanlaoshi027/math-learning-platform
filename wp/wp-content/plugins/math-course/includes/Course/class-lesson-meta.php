<?php
namespace MathCourse\Course;
use MathCourse\Tutor\Adapter;
defined('ABSPATH') || exit;
class Lesson_Meta {
    private $tutor;
    public function __construct(){ $this->tutor=new Adapter(); add_action('add_meta_boxes',array($this,'add')); add_action('save_post',array($this,'save'),10,2); }
    public function add(){ if(!$this->tutor->is_available())return; $lesson_post_type=$this->tutor->get_lesson_post_type(); if(!$lesson_post_type)return; add_meta_box('mathcourse_lesson_settings','MathCourse 课时信息',array($this,'render'),$lesson_post_type,'side','high'); }
    public function render($post){ wp_nonce_field('mathcourse_lesson_meta','mathcourse_lesson_nonce'); $page=get_post_meta($post->ID,'_mathcourse_page_number',true); $video=get_post_meta($post->ID,'_mathcourse_video_id',true); $hls=get_post_meta($post->ID,'_mathcourse_hls_url',true); $preview=get_post_meta($post->ID,'_mathcourse_preview',true); ?>
        <p><label>教材页码</label><input style="width:100%" name="mathcourse_page_number" value="<?php echo esc_attr($page); ?>"></p>
        <p><label>视频ID</label><input style="width:100%" name="mathcourse_video_id" value="<?php echo esc_attr($video); ?>"></p>
        <p><label>HLS地址</label><input style="width:100%" type="url" autocomplete="off" name="mathcourse_hls_url" value="<?php echo esc_attr($hls); ?>" placeholder="https://.../index.m3u8"><small>服务器端保存；前台播放器使用受保护的短时地址。</small></p>
        <p><label><input type="checkbox" name="mathcourse_preview" value="yes" <?php checked($preview,'yes'); ?>> 免费试看</label></p>
        <?php }
    public function save($post_id,$post){ if(!$post||!$this->tutor->is_lesson_post_type($post->post_type))return; if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($post_id))return; if(!current_user_can('edit_post',$post_id))return; if(empty($_POST['mathcourse_lesson_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_lesson_nonce'])),'mathcourse_lesson_meta'))return; update_post_meta($post_id,'_mathcourse_page_number',sanitize_text_field(wp_unslash($_POST['mathcourse_page_number']??''))); update_post_meta($post_id,'_mathcourse_video_id',sanitize_text_field(wp_unslash($_POST['mathcourse_video_id']??''))); if(isset($_POST['mathcourse_hls_url'])){ $hls=trim((string)wp_unslash($_POST['mathcourse_hls_url'])); if($hls!=='')update_post_meta($post_id,'_mathcourse_hls_url',esc_url_raw($hls)); else delete_post_meta($post_id,'_mathcourse_hls_url'); } update_post_meta($post_id,'_mathcourse_preview',isset($_POST['mathcourse_preview'])?'yes':'no'); }
}
