<?php
namespace MathVisual;

defined('ABSPATH') || exit;

class Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function menu() {
        add_menu_page('理科矢量图库', '理科矢量图库', 'manage_options', 'mathvisual-library', array(__CLASS__, 'page'), 'dashicons-images-alt2', 58);
    }

    public static function assets($hook) {
        if ($hook !== 'toplevel_page_mathvisual-library') return;
        wp_enqueue_style('mathvisual-admin', MATHVISUAL_URL . 'assets/admin.css', array(), MATHVISUAL_VERSION);
    }

    public static function page() {
        $assets = Library::get_assets(array('limit' => 100));
        ?>
        <div class="wrap mathvisual-wrap">
            <div class="mathvisual-header">
                <div><h1>中小学理科矢量图库</h1><p>数学 · 物理 · 化学　|　小学 · 初中 · 高中</p></div>
                <span class="mathvisual-count">已建立 <?php echo esc_html(count($assets)); ?> 个资源</span>
            </div>
            <div class="mathvisual-toolbar">
                <input type="search" id="mathvisual-search" placeholder="搜索知识点、图形名称……">
                <select id="mathvisual-subject"><option value="">全部学科</option><option value="math">数学</option><option value="physics">物理</option><option value="chemistry">化学</option></select>
                <select id="mathvisual-stage"><option value="">全部学段</option><option value="primary">小学</option><option value="junior">初中</option><option value="senior">高中</option></select>
            </div>
            <div class="mathvisual-grid">
                <?php foreach ($assets as $asset) : ?>
                    <article class="mathvisual-card" data-title="<?php echo esc_attr($asset['title'] . ' ' . $asset['keywords']); ?>" data-subject="<?php echo esc_attr($asset['subject']); ?>" data-stage="<?php echo esc_attr($asset['stage']); ?>">
                        <div class="mathvisual-preview"><?php echo $asset['svg']; // SVG comes from controlled library resources. ?></div>
                        <div class="mathvisual-info"><strong><?php echo esc_html($asset['title']); ?></strong><small><?php echo esc_html($asset['grade'] . ' · ' . $asset['category']); ?></small></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="mathvisual-empty" hidden>没有找到匹配的矢量资源。</div>
        </div>
        <script>
        (function(){const s=document.getElementById('mathvisual-search'),sub=document.getElementById('mathvisual-subject'),stage=document.getElementById('mathvisual-stage');function f(){const q=s.value.toLowerCase(),a=sub.value,b=stage.value;let n=0;document.querySelectorAll('.mathvisual-card').forEach(c=>{const ok=(!q||c.dataset.title.toLowerCase().includes(q))&&(!a||c.dataset.subject===a)&&(!b||c.dataset.stage===b);c.hidden=!ok;if(ok)n++});document.querySelector('.mathvisual-empty').hidden=n!==0} [s,sub,stage].forEach(e=>e.addEventListener('input',f));})();
        </script>
        <?php
    }
}
