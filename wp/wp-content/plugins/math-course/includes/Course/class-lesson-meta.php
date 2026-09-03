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
        <p><label>视频地址</label><input style="width:100%" type="text" inputmode="url" autocomplete="off" name="mathcourse_hls_url" value="<?php echo esc_attr($hls); ?>" placeholder="/wp-content/uploads/.../index.m3u8 或 video.mp4"><small>推荐填写相对地址。本站完整 URL 保存时会自动转换为相对地址；外部域名地址不会被偷偷改写。</small></p>
        <p><label><input type="checkbox" name="mathcourse_preview" value="yes" <?php checked($preview,'yes'); ?>> 免费试看</label></p>
        <?php }
    private function normalize_video_url($value){
        $value=trim((string)$value);
        if($value==='')return '';
        $value=esc_url_raw($value);
        if(preg_match('#^https?://#i',$value)){
            $parts=wp_parse_url($value);
            $home=wp_parse_url(home_url('/'));
            if(!$parts||!$home||empty($parts['host']))return '';
            $source_host=strtolower($parts['host']);
            $home_host=strtolower($home['host']??'');
            $source_scheme=strtolower($parts['scheme']??'');
            $home_scheme=strtolower($home['scheme']??'');
            $source_port=(int)($parts['port']??0);
            $home_port=(int)($home['port']??0);
            $source_effective=$source_port?:($source_scheme==='https'?443:80);
            $home_effective=$home_port?:($home_scheme==='https'?443:80);
            if($source_host!==$home_host||$source_effective!==$home_effective)return '';
            $path=!empty($parts['path'])?$parts['path']:'/';
            if(!empty($parts['query']))$path.='?'.$parts['query'];
            return '/'.ltrim($path,'/');
        }
        return '/'.ltrim($value,'/');
    }
    public function save($post_id,$post){ if(!$post||!$this->tutor->is_lesson_post_type($post->post_type))return; if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($post_id))return; if(!current_user_can('edit_post',$post_id))return; if(empty($_POST['mathcourse_lesson_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_lesson_nonce'])),'mathcourse_lesson_meta'))return; update_post_meta($post_id,'_mathcourse_page_number',sanitize_text_field(wp_unslash($_POST['mathcourse_page_number']??''))); update_post_meta($post_id,'_mathcourse_video_id',sanitize_text_field(wp_unslash($_POST['mathcourse_video_id']??''))); if(isset($_POST['mathcourse_hls_url'])){ $raw=trim((string)wp_unslash($_POST['mathcourse_hls_url'])); $hls=$this->normalize_video_url($raw); if($raw!==''&&$hls===''){ add_filter('redirect_post_location',function($location)use($post_id){return add_query_arg('mathcourse_hls_error','external',$location);}); } elseif($hls!=='')update_post_meta($post_id,'_mathcourse_hls_url',sanitize_text_field($hls)); else delete_post_meta($post_id,'_mathcourse_hls_url'); } update_post_meta($post_id,'_mathcourse_preview',isset($_POST['mathcourse_preview'])?'yes':'no'); }
}
