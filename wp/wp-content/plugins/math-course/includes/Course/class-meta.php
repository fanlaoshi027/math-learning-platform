<?php

namespace MathCourse\Course;

defined('ABSPATH') || exit;

/**
 * Course-level MathCourse metadata.
 *
 * v1.0 keeps this intentionally small: the Tutor course remains the source
 * of truth and MathCourse only stores its own extension metadata.
 */
class Meta
{
    private const COVER_META_KEY = '_mathcourse_cover';
    private const NONCE_ACTION   = 'mathcourse_save_course_meta';
    private const NONCE_NAME     = 'mathcourse_course_meta_nonce';

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add'));
        add_action('save_post', array($this, 'save'), 10, 2);
    }

    public function add()
    {
        $post_type = 'course';

        if (function_exists('tutor') && isset(tutor()->course_post_type)) {
            $post_type = (string) tutor()->course_post_type;
        }

        if ($post_type === '') {
            return;
        }

        add_meta_box(
            'mathcourse_cover',
            '课程封面',
            array($this, 'box'),
            $post_type,
            'side',
            'default'
        );
    }

    public function box($post)
    {
        $value = get_post_meta($post->ID, self::COVER_META_KEY, true);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <label for="mathcourse_cover" class="screen-reader-text">课程封面 URL</label>
        <input
            id="mathcourse_cover"
            type="url"
            name="mathcourse_cover"
            value="<?php echo esc_attr($value); ?>"
            placeholder="https://..."
            style="width:100%"
        />
        <p class="description">填写课程封面图片 URL。</p>
        <?php
    }

    public function save($post_id, $post)
    {
        if (!$post instanceof \WP_Post) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!isset($_POST[self::NONCE_NAME])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['mathcourse_cover'])) {
            return;
        }

        $value = esc_url_raw(wp_unslash($_POST['mathcourse_cover']));

        if ($value === '') {
            delete_post_meta($post_id, self::COVER_META_KEY);
            return;
        }

        update_post_meta($post_id, self::COVER_META_KEY, $value);
    }
}
