<?php
namespace MathCourse\Course;

use MathCourse\Tutor\Adapter;

defined('ABSPATH') || exit;

class Meta {
    private $tutor;

    public function __construct() {
        $this->tutor = new Adapter();
        add_action('add_meta_boxes', array($this,'add'));
        add_action('save_post', array($this,'save'),10,2);
        add_action('admin_enqueue_scripts', array($this,'admin_assets'));
    }

    public function add(){
        if (!$this->tutor->is_available()) return;
        $course_post_type = $this->tutor->get_course_post_type();
        if (!$course_post_type) return;
        add_meta_box('mathcourse_course_settings','MathCourse 课程信息',array($this,'box'),$course_post_type,'side','high');
        add_meta_box('mathcourse_cover','课程封面',array($this,'cover_box'),$course_post_type,'side');
    }

    public function admin_assets($hook){
        if (!in_array($hook,array('post.php','post-new.php'),true)) return;
        $screen = get_current_screen();
        if (!$screen || !$this->tutor->is_course_post_type($screen->post_type)) return;
        wp_enqueue_media();
        wp_enqueue_script('mathcourse-course-cover', MATHCOURSE_URL.'assets/js/admin-course-cover.js', array('jquery'), MATHCOURSE_VERSION, true);
    }

    public function box($post){
        wp_nonce_field('mathcourse_course_meta','mathcourse_course_meta_nonce');
        $type=get_post_meta($post->ID,'_mathcourse_type',true);
        $grade=get_post_meta($post->ID,'_mathcourse_grade',true);
        ?>
        <p><strong>课程类型</strong></p>
        <select name="mathcourse_type" style="width:100%">
            <option value="topic" <?php selected($type,'topic'); ?>>专题课程</option>
            <option value="supplementary" <?php selected($type,'supplementary'); ?>>大培优配套</option>
        </select>
        <p><strong>年级</strong></p>
        <select name="mathcourse_grade" style="width:100%">
            <option value="">请选择</option>
            <?php foreach(array('7'=>'七年级','8'=>'八年级','9'=>'九年级','10'=>'高一','11'=>'高二','12'=>'高三') as $k=>$v){ echo '<option value="'.esc_attr($k).'" '.selected($grade,$k,false).'>'.esc_html($v).'</option>'; } ?>
        </select>
        <?php
    }

    public function cover_box($post){
        $image_id=(int)get_post_meta($post->ID,'_mathcourse_cover_id',true);
        $image=get_post_meta($post->ID,'_mathcourse_cover',true);
        if (!$image && $image_id) $image=wp_get_attachment_image_url($image_id,'medium');
        $style=get_post_meta($post->ID,'_mathcourse_cover_style',true) ?: 'auto';
        $color=get_post_meta($post->ID,'_mathcourse_cover_color',true) ?: '#12346b';
        $text=get_post_meta($post->ID,'_mathcourse_cover_text',true);
        ?>
        <div class="mc-cover-admin">
            <input type="hidden" name="mathcourse_cover_id" value="<?php echo esc_attr($image_id); ?>">
            <input type="hidden" name="mathcourse_cover" value="<?php echo esc_attr($image); ?>">
            <p><button type="button" class="button" id="mc-select-cover">上传/选择封面</button> <button type="button" class="button" id="mc-remove-cover">移除</button></p>
            <div id="mc-cover-preview" style="margin-bottom:10px;">
                <?php if($image): ?><img src="<?php echo esc_url($image); ?>" style="display:block;width:100%;height:auto;border-radius:10px;"><?php endif; ?>
            </div>
            <p><label><strong>没有图片时</strong></label><br><select name="mathcourse_cover_style" style="width:100%"><option value="auto" <?php selected($style,'auto'); ?>>自动色块</option><option value="color" <?php selected($style,'color'); ?>>纯色块</option></select></p>
            <p><label>色块颜色</label><br><input type="color" name="mathcourse_cover_color" value="<?php echo esc_attr($color); ?>" style="width:100%;height:42px;padding:2px;"></p>
            <p><label>色块文字（可留空）</label><br><input type="text" name="mathcourse_cover_text" value="<?php echo esc_attr($text); ?>" style="width:100%" placeholder="默认使用课程名称"></p>
            <small>有封面图片时优先显示图片；没有图片时自动生成整齐的彩色课程封面。</small>
        </div>
        <?php
    }

    public function save($post_id,$post){
        if (!is_object($post) || !$this->tutor->is_course_post_type($post->post_type)) return;
        if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($post_id)) return;
        if(!current_user_can('edit_post',$post_id)) return;
        if(empty($_POST['mathcourse_course_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_course_meta_nonce'])),'mathcourse_course_meta')) return;
        if(isset($_POST['mathcourse_type'])){ $type=sanitize_key(wp_unslash($_POST['mathcourse_type'])); if(in_array($type,array('topic','supplementary'),true)) update_post_meta($post_id,'_mathcourse_type',$type); }
        if(isset($_POST['mathcourse_grade'])){ $grade=sanitize_key(wp_unslash($_POST['mathcourse_grade'])); if(in_array($grade,array('','7','8','9','10','11','12'),true)) update_post_meta($post_id,'_mathcourse_grade',$grade); }
        if(isset($_POST['mathcourse_cover_id'])) update_post_meta($post_id,'_mathcourse_cover_id',absint($_POST['mathcourse_cover_id']));
        if(isset($_POST['mathcourse_cover'])){ $cover=esc_url_raw(wp_unslash($_POST['mathcourse_cover'])); if($cover) update_post_meta($post_id,'_mathcourse_cover',$cover); else delete_post_meta($post_id,'_mathcourse_cover'); }
        if(isset($_POST['mathcourse_cover_style'])){ $style=sanitize_key(wp_unslash($_POST['mathcourse_cover_style'])); update_post_meta($post_id,'_mathcourse_cover_style',in_array($style,array('auto','color'),true)?$style:'auto'); }
        if(isset($_POST['mathcourse_cover_color'])){ $color=sanitize_hex_color(wp_unslash($_POST['mathcourse_cover_color'])); if($color) update_post_meta($post_id,'_mathcourse_cover_color',$color); }
        if(isset($_POST['mathcourse_cover_text'])) update_post_meta($post_id,'_mathcourse_cover_text',sanitize_text_field(wp_unslash($_POST['mathcourse_cover_text'])));
    }
}
