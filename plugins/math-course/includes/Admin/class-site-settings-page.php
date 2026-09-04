<?php
namespace MathCourse\Admin;

defined('ABSPATH') || exit;

use MathCourse\Frontend\Site_Settings;

class Site_Settings_Page {
    public function render() {
        if (!current_user_can('manage_options')) return;
        $settings = wp_parse_args((array) get_option(Site_Settings::OPTION, array()), Site_Settings::defaults());
        if (isset($_GET['settings-updated'])) echo '<div class="notice notice-success is-dismissible"><p>前端网站设置已保存。</p></div>';
        ?>
        <div class="wrap mathcourse-admin-wrap mathcourse-settings-page">
            <div class="mathcourse-admin-header"><div><div class="mathcourse-admin-eyebrow">MathCourse · 前端</div><h1>前端网站设置</h1><p>统一管理首页、课程中心、学习中心等前台页面显示的品牌文字与联系信息。</p></div></div>
            <form method="post" action="options.php">
                <?php settings_fields('mathcourse_site_settings'); ?>
                <section class="mathcourse-settings-card">
                    <div class="mathcourse-settings-card-head"><div class="mathcourse-settings-icon"><span class="dashicons dashicons-admin-site-alt3"></span></div><div><h2>品牌与联系方式</h2><p>修改后前台对应位置会自动同步。</p></div></div>
                    <table class="form-table" role="presentation">
                        <tr><th><label for="mc-site-name">网站名称</label></th><td><input id="mc-site-name" class="regular-text" name="mathcourse_site_settings[site_name]" value="<?php echo esc_attr($settings['site_name']); ?>"><p class="description">例如：樊老师数学</p></td></tr>
                        <tr><th><label for="mc-site-slogan">网站口号</label></th><td><input id="mc-site-slogan" class="regular-text" name="mathcourse_site_settings[site_slogan]" value="<?php echo esc_attr($settings['site_slogan']); ?>"></td></tr>
                        <tr><th><label for="mc-wechat">微信号</label></th><td><input id="mc-wechat" class="regular-text" name="mathcourse_site_settings[wechat_id]" value="<?php echo esc_attr($settings['wechat_id']); ?>" placeholder="例如：fanlaoshi027"><p class="description">学习中心等前台联系区域使用；留空则不显示微信号。</p></td></tr>
                        <tr><th><label for="mc-wechat-image">微信二维码/微信图像</label></th><td><input id="mc-wechat-image" class="regular-text" name="mathcourse_site_settings[wechat_image]" value="<?php echo esc_attr($settings['wechat_image']); ?>" placeholder="图片地址"><button type="button" class="button" id="mc-wechat-image-button">从媒体库选择</button><div id="mc-wechat-image-preview" style="margin-top:10px;"><?php if ($settings['wechat_image']) : ?><img src="<?php echo esc_url($settings['wechat_image']); ?>" style="max-width:160px;max-height:160px;border-radius:8px;"><?php endif; ?></div></td></tr>
                    </table>
                </section>
                <section class="mathcourse-settings-card">
                    <div class="mathcourse-settings-card-head"><div class="mathcourse-settings-icon"><span class="dashicons dashicons-edit"></span></div><div><h2>首页设置</h2><p>控制首页 Banner 文案和首页首次打开时显示的课程分类。</p></div></div>
                    <table class="form-table" role="presentation">
                        <tr><th><label>首页顶部引导语</label></th><td><input class="regular-text" name="mathcourse_site_settings[hero_kicker]" value="<?php echo esc_attr($settings['hero_kicker']); ?>"></td></tr>
                        <tr><th><label>首页主标题</label></th><td><input class="regular-text" name="mathcourse_site_settings[hero_title]" value="<?php echo esc_attr($settings['hero_title']); ?>"></td></tr>
                        <tr><th><label>首页主标题说明</label></th><td><textarea class="large-text" rows="2" name="mathcourse_site_settings[hero_description]"><?php echo esc_textarea($settings['hero_description']); ?></textarea></td></tr>
                        <tr><th><label>首页默认课程分类</label></th><td><select name="mathcourse_site_settings[home_default_tab]"><option value="topic" <?php selected($settings['home_default_tab'],'topic'); ?>>初中系统课</option><option value="supplementary" <?php selected($settings['home_default_tab'],'supplementary'); ?>>教辅配套课</option></select><p class="description">首页打开时默认展示哪个 Tab。课程内容不足或暂未上传时，可先选择“教辅配套课”。点击另一个 Tab 仍可在首页原地切换，不进入课程中心。</p></td></tr>
                        <tr><th><label>“为什么选择”标题</label></th><td><input class="regular-text" name="mathcourse_site_settings[why_title]" value="<?php echo esc_attr($settings['why_title']); ?>"></td></tr>
                        <tr><th><label>“为什么选择”说明</label></th><td><input class="large-text" name="mathcourse_site_settings[why_subtitle]" value="<?php echo esc_attr($settings['why_subtitle']); ?>"></td></tr>
                    </table>
                </section>
                <section class="mathcourse-settings-card">
                    <div class="mathcourse-settings-card-head"><div class="mathcourse-settings-icon"><span class="dashicons dashicons-welcome-learn-more"></span></div><div><h2>课程中心与学习中心</h2><p>统一修改课程中心说明和学习中心联系提示。</p></div></div>
                    <table class="form-table" role="presentation">
                        <tr><th><label>课程中心标题</label></th><td><input class="regular-text" name="mathcourse_site_settings[course_center_title]" value="<?php echo esc_attr($settings['course_center_title']); ?>"></td></tr>
                        <tr><th><label>课程中心说明</label></th><td><input class="large-text" name="mathcourse_site_settings[course_center_description]" value="<?php echo esc_attr($settings['course_center_description']); ?>"></td></tr>
                        <tr><th><label>学习中心联系提示</label></th><td><textarea class="large-text" rows="2" name="mathcourse_site_settings[learning_contact_text]"><?php echo esc_textarea($settings['learning_contact_text']); ?></textarea></td></tr>
                        <tr><th><label>页脚标语</label></th><td><input class="regular-text" name="mathcourse_site_settings[footer_slogan]" value="<?php echo esc_attr($settings['footer_slogan']); ?>"></td></tr>
                    </table>
                </section>
                <?php submit_button('保存前端设置'); ?>
            </form>
        </div>
        <script>jQuery(function($){$('#mc-wechat-image-button').on('click',function(e){e.preventDefault();var f=wp.media({title:'选择微信图像',button:{text:'使用此图片'},multiple:false});f.on('select',function(){var a=f.state().get('selection').first().toJSON();$('#mc-wechat-image').val(a.url);$('#mc-wechat-image-preview').html('<img src="'+a.url.replace(/"/g,'&quot;')+'" style="max-width:160px;max-height:160px;border-radius:8px;">');});f.open();});});</script>
        <?php
    }
}
