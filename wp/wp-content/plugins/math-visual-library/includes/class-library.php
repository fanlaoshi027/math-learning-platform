<?php
namespace MathVisual;

defined('ABSPATH') || exit;

class Library {
    const TAXONOMY = 'mathvisual_category';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('init', array(__CLASS__, 'register_taxonomy'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest'));
    }

    public static function register_post_type() {
        register_post_type('mathvisual_asset', array(
            'labels' => array('name' => '矢量资源', 'singular_name' => '矢量资源'),
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => array('title'),
            'capability_type' => 'manage_options',
        ));
    }

    public static function register_taxonomy() {
        register_taxonomy(self::TAXONOMY, 'mathvisual_asset', array(
            'public' => false,
            'show_ui' => false,
            'hierarchical' => true,
        ));
    }

    public static function register_rest() {
        register_rest_route('mathvisual/v1', '/assets', array(
            'methods' => 'GET',
            'permission_callback' => function () { return current_user_can('edit_posts'); },
            'callback' => array(__CLASS__, 'rest_assets'),
        ));
        register_rest_route('mathvisual/v1', '/assets/(?P<id>\d+)', array(
            'methods' => 'GET',
            'permission_callback' => function () { return current_user_can('edit_posts'); },
            'callback' => function ($request) { return self::get_asset((int) $request['id']); },
        ));
    }

    public static function rest_assets($request) {
        return self::get_assets(array(
            'subject' => sanitize_key($request->get_param('subject')),
            'stage' => sanitize_key($request->get_param('stage')),
            'keyword' => sanitize_text_field($request->get_param('keyword')),
            'limit' => min(100, max(1, (int) $request->get_param('limit') ?: 30)),
        ));
    }

    public static function get_asset($id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'mathvisual_asset') return null;
        return self::format_asset($post);
    }

    public static function get_assets($args = array()) {
        $args = wp_parse_args($args, array('subject' => '', 'stage' => '', 'keyword' => '', 'limit' => 30));
        $query = new \WP_Query(array(
            'post_type' => 'mathvisual_asset',
            'post_status' => 'publish',
            'posts_per_page' => (int) $args['limit'],
            's' => $args['keyword'],
            'no_found_rows' => true,
        ));
        $items = array();
        foreach ($query->posts as $post) {
            $item = self::format_asset($post);
            if ($args['subject'] && $item['subject'] !== $args['subject']) continue;
            if ($args['stage'] && $item['stage'] !== $args['stage']) continue;
            $items[] = $item;
        }
        return $items;
    }

    public static function format_asset($post) {
        $id = $post->ID;
        return array(
            'id' => $id,
            'title' => get_the_title($id),
            'subject' => (string) get_post_meta($id, '_mathvisual_subject', true),
            'stage' => (string) get_post_meta($id, '_mathvisual_stage', true),
            'grade' => (string) get_post_meta($id, '_mathvisual_grade', true),
            'category' => (string) get_post_meta($id, '_mathvisual_category', true),
            'keywords' => (string) get_post_meta($id, '_mathvisual_keywords', true),
            'svg' => (string) get_post_meta($id, '_mathvisual_svg', true),
        );
    }

    public static function register_default_assets() {
        // First release keeps the resource catalog file-based and extensible.
        // Admin import can later index additional SVG files without changing MathCourse.
    }
}
