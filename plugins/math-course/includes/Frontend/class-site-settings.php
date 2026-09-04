<?php
namespace MathCourse\Frontend;

defined('ABSPATH') || exit;

class Site_Settings {
    const OPTION = 'mathcourse_site_settings';
    public static function defaults() { return array(
        'site_name'=>'樊老师数学','site_slogan'=>'专注初中数学系统学习','hero_kicker'=>'两大核心分类：初中系统课 + 教辅配套课','hero_title'=>'把初中数学，学成一套体系','hero_description'=>'按数学知识体系组织课程，从基础到综合应用，循序渐进，构建扎实的数学基本功。','why_title'=>'为什么选择樊老师数学课堂','why_subtitle'=>'专注初中数学知识体系研发，让每一个知识点清晰可见','course_center_title'=>'课程中心','course_center_description'=>'按知识体系和学习阶段选择课程，找到适合自己的学习路径。','learning_contact_text'=>'需要开通更多初中数学模块或中考冲刺专题？可随时微信联系樊老师进行课程续费或加订。','wechat_id'=>'','wechat_image'=>'','footer_slogan'=>'专注初中数学系统学习'
    ); }
    public static function get($key,$default='') { $settings=wp_parse_args((array)get_option(self::OPTION,array()),self::defaults()); return isset($settings[$key])?$settings[$key]:$default; }
    public function __construct() { add_action('admin_init',array($this,'register')); add_action('admin_menu',array($this,'menu'),30); add_action('admin_enqueue_scripts',array($this,'assets')); }
    public function register() { register_setting('mathcourse_site_settings',self::OPTION,array('type'=>'array','sanitize_callback'=>array($this,'sanitize'),'default'=>self::defaults())); }
    public function sanitize($input) { $input=is_array($input)?$input:array(); $out=array(); foreach(self::defaults() as $key=>$default) { $value=isset($input[$key])?$input[$key]:$default; $out[$key]='wechat_image'===$key?esc_url_raw($value):sanitize_textarea_field($value); } return $out; }
    public function menu() { if(current_user_can('manage_options')) add_submenu_page('mathcourse','前端网站设置','前端网站设置','manage_options','mathcourse-site-settings',array($this,'page')); }
    public function assets($hook) { if(isset($_GET['page']) && 'mathcourse-site-settings'===sanitize_key(wp_unslash($_GET['page']))) wp_enqueue_media(); }
    public function page() { if(class_exists('MathCourse\\Admin\\Site_Settings_Page')) (new \MathCourse\Admin\Site_Settings_Page())->render(); }
}
