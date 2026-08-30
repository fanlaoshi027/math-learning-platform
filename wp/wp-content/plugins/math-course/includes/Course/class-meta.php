<?php

namespace MathCourse\Course;

use MathCourse\Tutor\Adapter;

defined('ABSPATH') || exit;

class Meta {

    /** @var Adapter */
    private $tutor;

    public function __construct() {
        $this->tutor = new Adapter();
        add_action('add_meta_boxes', array($this,'add'));
        add_action('save_post', array($this,'save'),10,2);
    }

    public function add(){
        if (!$this->tutor->is_available()) return;

        $course_post_type = $this->tutor->get_course_post_type();
        if (!$course_post_type) return;

        add_meta_box(
            'mathcourse_course_settings',
            'MathCourse 课程信息',
            array($this,'box'),
            $course_post_type,
            'side',
            'high'
        );

        add_meta_box(
            'mathcourse_cover',
            '课程封面',
            array($this,'cover_box'),
            $course_post_type,
            'side'
        );
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
            <?php
            foreach(array(
                '7'=>'七年级','8'=>'八年级','9'=>'九年级',
                '10'=>'高一','11'=>'高二','12'=>'高三'
            ) as $k=>$v){
                echo '<option value="'.esc_attr($k).'" '.selected($grade,$k,false).'>'.esc_html($v).'</option>';
            }
            ?>
        </select>
        <?php
    }

    public function cover_box($post){
        $value=get_post_meta($post->ID,'_mathcourse_cover',true);
        ?>
        <input type="url" name="mathcourse_cover" value="<?php echo esc_attr($value); ?>" style="width:100%">
        <?php
    }

    public function save($post_id,$post){
        if (!is_object($post) || !$this->tutor->is_course_post_type($post->post_type)) return;
        if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($post_id)) return;
        if(!current_user_can('edit_post',$post_id)) return;

        if(empty($_POST['mathcourse_course_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_course_meta_nonce'])),'mathcourse_course_meta')) return;

        if(isset($_POST['mathcourse_type'])){
            $type=sanitize_key(wp_unslash($_POST['mathcourse_type']));
            if(in_array($type,array('topic','supplementary'),true)){
                update_post_meta($post_id,'_mathcourse_type',$type);
            }
        }

        if(isset($_POST['mathcourse_grade'])){
            $grade=sanitize_key(wp_unslash($_POST['mathcourse_grade']));
            if(in_array($grade,array('','7','8','9','10','11','12'),true)){
                update_post_meta($post_id,'_mathcourse_grade',$grade);
            }
        }

        if(isset($_POST['mathcourse_cover'])){
            update_post_meta($post_id,'_mathcourse_cover',esc_url_raw(wp_unslash($_POST['mathcourse_cover'])));
        }
    }
}
