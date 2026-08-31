<?php
/**
 * 樊老师数学课堂 - 主题核心功能文件
 * 遵循《前端主题开发规则 V1.0》与《Math Learning Platform 接口规范 V1.0》
 * 严格秉承“插件负责业务，主题负责表现”架构原则，不直接查询数据库，统一调用 Course_Service。
 * 
 * @package MathCourse_Theme
 * @version 1.0.0
 */

defined('ABSPATH') || exit; // 禁止直接脚本访问

/**
 * 1. 主题基础支持与初始化
 */
function mathcourse_theme_setup() {
    // 启用文档标题管理
    add_theme_support('title-tag');

    // 启用特色图片
    add_theme_support('post-thumbnails');
    add_image_size('course-cover', 600, 360, true);

    // 启用 HTML5 语义化支持
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ));

    // 注册导航菜单
    register_nav_menus(array(
        'primary-menu' => __('顶部主导航', 'mathcourse'),
        'footer-menu'  => __('页脚链接', 'mathcourse'),
    ));
}
add_action('after_setup_theme', 'mathcourse_theme_setup');

/**
 * 2. 引入前端资源与轻量交互脚本
 */
function mathcourse_enqueue_scripts() {
    // 引入 Tailwind CSS 样式引擎
    wp_enqueue_script('tailwindcss-cdn', 'https://cdn.tailwindcss.com', array(), null, false);

    // 引入主题基础样式
    wp_enqueue_style('mathcourse-style', get_stylesheet_uri(), array(), '1.0.0');

    // 章节手风琴折叠与轻量 UI 辅助脚本 (遵守 JS 职责纯粹规则)
    $custom_js = "
    document.addEventListener('DOMContentLoaded', function() {
        // 章节折叠展开交互
        const accordionHeaders = document.querySelectorAll('.mc-chapter-toggle');
        accordionHeaders.forEach(function(header) {
            header.addEventListener('click', function() {
                const chapterBox = this.closest('.mc-chapter-item');
                const lessonList = chapterBox ? chapterBox.querySelector('.mc-lesson-list') : null;
                const arrowIcon = this.querySelector('.mc-arrow-icon');
                if (lessonList) {
                    lessonList.classList.toggle('hidden');
                    if (arrowIcon) {
                        arrowIcon.classList.toggle('rotate-180');
                    }
                }
            });
        });

        // 移动端导航抽屉开关
        const mobileMenuBtn = document.getElementById('mc-mobile-menu-btn');
        const mobileMenu = document.getElementById('mc-mobile-menu');
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
        }
    });
    ";
    wp_add_inline_script('tailwindcss-cdn', $custom_js);
}
add_action('wp_enqueue_scripts', 'mathcourse_enqueue_scripts');

/**
 * 3. 辅助函数：安全调用 Course_Service 接口
 * 按照规范 2. 核心服务：MathCourse\Course\Course_Service
 */
function mathcourse_get_service() {
    if (class_exists('\MathCourse\Course\Course_Service')) {
        return new \MathCourse\Course\Course_Service();
    }
    return null;
}

/**
 * 4. 辅助函数：年级与类型枚举名称映射
 * 遵循规范：7/8/9 转换为中文名称，type: topic / supplementary
 */
function mathcourse_format_grade($grade_code) {
    switch (strval($grade_code)) {
        case '7':
            return '七年级';
        case '8':
            return '八年级';
        case '9':
            return '九年级';
        case 'zhongkao':
            return '中考冲刺';
        default:
            return '初中数学';
    }
}

function mathcourse_format_type($type_code) {
    return $type_code === 'supplementary' ? '教辅配套课' : '初中系统课';
}

/**
 * 5. 辅助函数：空封面默认数学特色占位渲染
 * 遵循规范 13. 空封面占位方案：色块 + 数学符号，杜绝破图
 */
function mathcourse_render_cover_fallback($title, $type = 'topic', $grade = '8') {
    $is_supp = ($type === 'supplementary');
    $gradient = $is_supp 
        ? 'from-amber-600 via-orange-600 to-amber-700' 
        : 'from-blue-700 via-indigo-800 to-slate-900';
    $math_symbol = $is_supp ? '培优' : 'x²';

    ob_start();
    ?>
    <div class="w-full aspect-[16/10] rounded-t-xl bg-gradient-to-br <?php echo esc_attr($gradient); ?> flex flex-col justify-between p-4 text-white relative overflow-hidden select-none">
        <div class="flex justify-between items-start z-10">
            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-white/20 backdrop-blur-xs border border-white/20">
                <?php echo esc_html(mathcourse_format_grade($grade)); ?>
            </span>
            <span class="text-xs font-mono font-bold bg-black/25 px-2 py-0.5 rounded">
                <?php echo esc_html(mathcourse_format_type($type)); ?>
            </span>
        </div>
        <div class="z-10 mt-auto">
            <h4 class="text-base sm:text-lg font-bold line-clamp-2 text-white leading-snug drop-shadow-xs">
                <?php echo esc_html($title); ?>
            </h4>
        </div>
        <!-- 背景数学半透明大公式水印 -->
        <div class="absolute -right-2 -bottom-4 text-6xl font-black font-mono text-white/10 pointer-events-none">
            <?php echo esc_html($math_symbol); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 6. 注册标准 Shortcode (遵循规范 10. Shortcode)
 * [mathcourse_course_center]
 * [mathcourse_course_directory]
 * [mathcourse_learning_center]
 */
function mathcourse_shortcode_course_center($atts) {
    ob_start();
    get_template_part('template-parts/content', 'course-center');
    return ob_get_clean();
}
add_shortcode('mathcourse_course_center', 'mathcourse_shortcode_course_center');

function mathcourse_shortcode_course_directory($atts) {
    $atts = shortcode_atts(array(
        'course_id' => 0,
    ), $atts, 'mathcourse_course_directory');

    ob_start();
    set_query_var('mc_course_id', intval($atts['course_id']));
    get_template_part('template-parts/content', 'course-directory');
    return ob_get_clean();
}
add_shortcode('mathcourse_course_directory', 'mathcourse_shortcode_course_directory');

function mathcourse_shortcode_learning_center($atts) {
    ob_start();
    get_template_part('template-parts/content', 'learning-center');
    return ob_get_clean();
}
add_shortcode('mathcourse_learning_center', 'mathcourse_shortcode_learning_center');
