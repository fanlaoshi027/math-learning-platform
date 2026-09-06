<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

class Frontend_Settings {
    const LOGO_OPTION = 'mathcourse_site_logo_id';

    public function __construct() {
        add_action('admin_init', array($this, 'register')); 
    }

    public function register() {
        register_setting('mathcourse_frontend_settings', self::LOGO_OPTION, array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ));
    }

    public static function logo_id() {
        return absint(get_option(self::LOGO_OPTION, 0));
    }

    public static function logo_url($size = 'full') {
        $id = self::logo_id();
        if (!$id || !wp_attachment_is_image($id)) return '';
        return (string) wp_get_attachment_image_url($id, $size);
    }

    public function render() {
        if (!current_user_can('manage_options')) wp_die('没有权限访问此页面。');
        $message = '';
        if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['mathcourse_frontend_settings_nonce'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mathcourse_frontend_settings_nonce'])), 'mathcourse_frontend_settings')) {
                $message = '<div class="notice notice-error"><p>页面已过期，请刷新后重试。</p></div>';
            } else {
                $logo_id = absint($_POST['mathcourse_site_logo_id'] ?? 0);
                if ($logo_id && !wp_attachment_is_image($logo_id)) $logo_id = 0;
                update_option(self::LOGO_OPTION, $logo_id);
                $message = '<div class="notice notice-success is-dismissible"><p>网站 Logo 已保存。</p></div>';
            }
        }
        $logo_id = self::logo_id();
        $logo_url = self::logo_url('medium');
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-frontend-settings-page">
            <div class="mathcourse-admin-header">
                <div>
                    <div class="mathcourse-admin-eyebrow">MathCourse · 前端</div>
                    <h1>前端网页设置</h1>
                    <p>管理网站前端统一使用的 Logo 等基础视觉元素。</p>
                </div>
            </div>
            <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <section class="mathcourse-settings-card mathcourse-frontend-logo-card">
                <div class="mathcourse-settings-card-head">
                    <div class="mathcourse-settings-icon"><span class="dashicons dashicons-format-image"></span></div>
                    <div>
                        <h2>网站 Logo</h2>
                        <p>上传后用于网站前端页头等位置。建议使用清晰、背景简洁的 PNG 图片。</p>
                    </div>
                </div>
                <form method="post">
                    <?php wp_nonce_field('mathcourse_frontend_settings', 'mathcourse_frontend_settings_nonce'); ?>
                    <input type="hidden" id="mathcourse_site_logo_id" name="mathcourse_site_logo_id" value="<?php echo esc_attr($logo_id); ?>">
                    <div class="mathcourse-frontend-logo-picker">
                        <div class="mathcourse-frontend-logo-preview" id="mathcourse-site-logo-preview">
                            <?php if ($logo_url) : ?><img src="<?php echo esc_url($logo_url); ?>" alt="网站 Logo 预览"><?php else : ?><span class="mathcourse-logo-placeholder">暂未上传 Logo</span><?php endif; ?>
                        </div>
                        <div class="mathcourse-frontend-logo-actions">
                            <button type="button" class="button button-primary" id="mathcourse-site-logo-choose">选择 / 上传 Logo</button>
                            <button type="button" class="button" id="mathcourse-site-logo-clear" <?php disabled(!$logo_id); ?>>清除 Logo</button>
                            <p class="description">支持 WordPress 媒体库中的图片。推荐透明 PNG，前端会自动按比例缩放。</p>
                        </div>
                    </div>
                    <p><button type="submit" class="button button-primary button-large">保存网站 Logo</button></p>
                </form>
            </section>
        </div>
        <style>
            .mathcourse-frontend-logo-card{max-width:900px;margin-top:20px}
            .mathcourse-frontend-logo-picker{display:flex;gap:24px;align-items:center;padding:24px 0}
            .mathcourse-frontend-logo-preview{width:260px;height:120px;border:1px solid #dfe6ef;border-radius:12px;background:#f7f9fc;display:flex;align-items:center;justify-content:center;overflow:hidden}
            .mathcourse-frontend-logo-preview img{display:block;max-width:90%;max-height:90%;width:auto;height:auto;object-fit:contain}
            .mathcourse-logo-placeholder{color:#8996a8;font-size:14px}
            .mathcourse-frontend-logo-actions{min-width:260px}
            .mathcourse-frontend-logo-actions .button{margin-right:8px}
            @media(max-width:700px){.mathcourse-frontend-logo-picker{display:block}.mathcourse-frontend-logo-preview{width:100%;margin-bottom:16px}.mathcourse-frontend-logo-actions{min-width:0}}
        </style>
        <script>
        jQuery(function($){
            var frame=null, input=$('#mathcourse_site_logo_id'), preview=$('#mathcourse-site-logo-preview'), clear=$('#mathcourse-site-logo-clear');
            $('#mathcourse-site-logo-choose').on('click',function(e){
                e.preventDefault();
                if(frame){frame.open();return;}
                frame=wp.media({title:'选择网站 Logo',button:{text:'使用此 Logo'},library:{type:'image'},multiple:false});
                frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();if(!item||!item.id)return;input.val(item.id);var url=item.sizes&&item.sizes.medium?item.sizes.medium.url:item.url;preview.html('<img src="'+url.replace(/"/g,'&quot;')+'" alt="网站 Logo 预览">');clear.prop('disabled',false);});
                frame.open();
            });
            clear.on('click',function(e){e.preventDefault();input.val('0');preview.html('<span class="mathcourse-logo-placeholder">暂未上传 Logo</span>');clear.prop('disabled',true);});
        });
        </script>
        <?php
    }
}
