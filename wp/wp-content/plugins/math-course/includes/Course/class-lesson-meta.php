<?php

namespace MathCourse\Course;

defined('ABSPATH') || exit;

class Lesson_Meta {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add'));
        add_action('save_post', array($this, 'save'), 10, 2);
    }

    public function add() {
        if (!function_exists('tutor')) return;

        add_meta_box(
            'mathcourse_lesson_settings',
            'MathCourse 课时信息',
            array($this, 'render'),
            'lesson',
            'side',
            'high'
        );
    }

    public function render($post) {
        wp_nonce_field('mathcourse_lesson_meta', 'mathcourse_lesson_nonce');

        $page = get_post_meta($post->ID, '_mathcourse_page_number', true);
        $video = get_post_meta($post->ID, '_mathcourse_video_id', true);
        $hls = get_post_meta($post->ID, '_mathcourse_hls_url', true);
        $preview = get_post_meta($post->ID, '_mathcourse_preview', true);
        ?>
        <p><label>教材页码</label>
        <input style="width:100%" name="mathcourse_page_number" value="<?php echo esc_attr($page); ?>"></p>

        <p><label>视频ID</label>
        <input style="width:100%" name="mathcourse_video_id" value="<?php echo esc_attr($video); ?>"></p>

        <p><label>HLS地址（仅服务器端保存）</label>
        <input style="width:100%" type="password" autocomplete="off" name="mathcourse_hls_url" value="<?php echo esc_attr($hls); ?>" placeholder="留空则保持原地址不变">
        <small>不会直接输出到前台播放器；播放器只使用受保护的短时地址。</small></p>

        <p><label><input type="checkbox" name="mathcourse_preview" value="yes" <?php checked($preview, 'yes'); ?>> 免费试看</label></p>
        <?php
    }

    public function save($post_id, $post) {
        if (!$post || 'lesson' !== $post->post_type) return;
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (empty($_POST['mathcourse_lesson_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_lesson_nonce'])), 'mathcourse_lesson_meta')) return;

        update_post_meta($post_id, '_mathcourse_page_number', sanitize_text_field(wp_unslash($_POST['mathcourse_page_number'] ?? '')));
        update_post_meta($post_id, '_mathcourse_video_id', sanitize_text_field(wp_unslash($_POST['mathcourse_video_id'] ?? '')));

        // The HLS source is a server-side secret. An empty submitted field means
        // "keep existing value", which prevents accidental deletion when the
        // password-style field is intentionally left blank.
        if (isset($_POST['mathcourse_hls_url']) && '' !== trim((string) wp_unslash($_POST['mathcourse_hls_url']))) {
            update_post_meta($post_id, '_mathcourse_hls_url', esc_url_raw(wp_unslash($_POST['mathcourse_hls_url'])));
        }

        update_post_meta($post_id, '_mathcourse_preview', isset($_POST['mathcourse_preview']) ? 'yes' : 'no');
    }
}
