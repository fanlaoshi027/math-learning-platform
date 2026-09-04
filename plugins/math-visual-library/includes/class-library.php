<?php
namespace MathVisual;
defined('ABSPATH') || exit;
class Library {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest'));
    }
    public static function register_post_type() {
        register_post_type('mathvisual_asset', array('labels'=>array('name'=>'矢量资源'),'public'=>false,'show_ui'=>false,'supports'=>array('title')));
    }
    public static function register_rest() {
        register_rest_route('mathvisual/v1','/assets',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('edit_posts');},'callback'=>function($r){return self::get_assets(array('subject'=>sanitize_key($r->get_param('subject')),'stage'=>sanitize_key($r->get_param('stage')),'keyword'=>sanitize_text_field($r->get_param('keyword')),'limit'=>min(100,max(1,(int)$r->get_param('limit')?:30))));}));
        register_rest_route('mathvisual/v1','/assets/(?P<key>[A-Za-z0-9_-]+)',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('edit_posts');},'callback'=>function($r){return self::get_by_key(sanitize_key($r['key']));}));
    }
    public static function get_assets($args=array()) {
        $args=wp_parse_args($args,array('subject'=>'','stage'=>'','keyword'=>'','limit'=>30));
        $catalog=require MATHVISUAL_DIR.'includes/catalog.php'; $out=array();
        foreach($catalog as $item){
            if($args['subject'] && $item['subject']!==$args['subject']) continue;
            if($args['stage'] && $item['stage']!==$args['stage']) continue;
            if($args['keyword'] && stripos($item['title'].' '.$item['keywords'],$args['keyword'])===false) continue;
            $out[]=$item; if(count($out)>=$args['limit']) break;
        }
        return $out;
    }
    public static function get_by_key($key) { foreach(self::get_assets(array('limit'=>1000)) as $item) if($item['key']===$key) return $item; return null; }
    public static function register_default_assets() {}
}
